# Câblage async PHP → Python pour l'import de CV

Date : 2026-07-08
Statut : approuvé

## Contexte

Le flux d'upload PHP (`ImportController::upload()`) ne fait aujourd'hui qu'enregistrer un nom de
fichier en base (`ImportModel::enregistrerImport()`) : il ne sauvegarde pas le PDF et n'appelle
jamais le service Python d'extraction/LLM (`import_cv/`). Le pont entre le formulaire web et le
pipeline d'analyse IA n'existe pas encore.

L'ADR [0001-stack-technique-v1](../../adr/0001-stack-technique-v1.md) et la
[spec fonctionnelle](../../spec.md) documentent déjà le design cible : import **asynchrone**,
PHP reçoit un `job_id` immédiatement, Python traite en tâche de fond, PHP lit l'état du job
directement en base par polling. Ce document détaille comment câbler ce flux en respectant cette
architecture déjà actée.

Écart supplémentaire corrigé au passage : le PHP actuel crée le `profil` et le `job` **avant**
d'appeler Python, alors que la spec (§3.1, §3.3) exige qu'aucun profil ne soit créé en cas d'échec
technique de l'extraction.

## Architecture

```
PHP upload() ──cURL multipart──▶ POST /import (FastAPI)
                                     │ sauvegarde PDF, INSERT import_jobs (en cours)
                                     │ programme une BackgroundTask
                                     ▼
                              202 {job_id}
PHP ◀─────────────────────────────┘
  │ redirige vers /import/status/{job_id}
  ▼
Page "Import en cours…" ──fetch toutes les 2s──▶ GET /import/job/{id}/status (PHP, lit import_jobs en BDD)
  │ dès statut = réussi/échoué
  ▼
redirection /revue/{profile_id}  OU  affichage message d'erreur

[Tâche de fond Python] extraction PyMuPDF → LLM Mistral
  → succès : INSERT profils + experiences + competences + formations, UPDATE job (réussi, profile_id)
  → échec  : UPDATE job (échoué, error_message), aucun profil créé
```

PHP ne touche plus jamais `import_jobs` en écriture pour la création : Python est seul responsable
de la création du job (il a besoin de générer le `job_id` retourné immédiatement) et de son
avancement. PHP se contente de lire l'état du job en base pour le polling (conforme à l'ADR : « pas
d'API de polling côté Python »).

## Composants

### Python — `import_cv/app/routes/import_cv.py`

Remplace l'endpoint synchrone `POST /import-cv` par un endpoint asynchrone `POST /import` :

- **Requête** : `multipart/form-data` avec `file` (PDF) et `importe_par_user_id` (int, fourni par PHP).
- **Validation immédiate** (avant retour de la réponse) : extension `.pdf`, taille ≤ `MAX_FILE_SIZE`
  (10 MB, déjà en place), présence de `importe_par_user_id`.
- **Traitement synchrone avant retour** :
  1. `INSERT INTO import_jobs (statut, importe_par_user_id, nom_fichier) VALUES ('en cours', %s, %s) RETURNING id`
     — `nom_fichier` est le nom original du fichier, tel que reçu (sert à l'affichage dans
     l'historique, `Views/import.php`).
  2. Sauvegarde le PDF sur le volume partagé, sous un nom **distinct** de `nom_fichier` pour éviter
     les collisions : `/data/pdfs/{job_id}_{nom_fichier}`. Ce chemin (pas le nom original) est ce
     qui sera stocké plus tard dans `profils.pdf_source`.
  3. Programme une `BackgroundTasks.add_task(traiter_import, job_id, chemin_pdf)`.
  4. Retourne `202 Accepted` avec `{"job_id": <id>}`.
- **Tâche de fond `traiter_import(job_id, chemin_pdf)`** (nouvelle connexion DB dédiée, la requête
  HTTP est déjà terminée) :
  1. Extrait le texte via PyMuPDF (réutilise la logique actuelle, avec `try/finally`).
  2. Si texte vide/blanc → `UPDATE import_jobs SET statut='échoué', error_message='PDF vide ou texte non extractible.' WHERE id=job_id`, fin.
  3. Appelle `extraire_donnees_cv(texte)` (fonction existante, déjà validée avec la clé Mistral au
     démarrage du module).
  4. Sur succès : `INSERT INTO profils (...) VALUES (..., statut='à valider', importe_par_user_id, pdf_source='{job_id}_{nom_fichier}') RETURNING id` (le nom de fichier stocké sur disque, cf. étape 2 ci-dessus — pas le nom original), puis `experiences`/`competences`/`formations` (logique déjà existante, réutilisée telle quelle), puis `UPDATE import_jobs SET statut='réussi', profile_id=<id> WHERE id=job_id`.
  5. Sur toute exception aux étapes 3-4 : log via `logger.exception(...)`, rollback des inserts
     partiels, `UPDATE import_jobs SET statut='échoué', error_message='Erreur lors de l'analyse du CV.' WHERE id=job_id`.
- Supprime la création automatique de l'utilisateur `system@import` (upsert `ON CONFLICT`) : le
  service ne crée plus d'utilisateur, `importe_par_user_id` est fourni par l'appelant (PHP) et doit
  déjà exister (contrainte FK).
- L'ancien endpoint `POST /import-cv` est retiré (remplacé, pas dupliqué) pour éviter deux chemins
  d'import concurrents.

### PHP — `web/public/`

- **`Controllers/ImportController.php`** :
  - `upload()` réécrit : ne touche plus la BDD directement. Transmet le fichier reçu (`$_FILES['cv_file']`)
    via cURL natif (`multipart/form-data`) vers `POST {IMPORT_SERVICE_URL}/import` avec
    `importe_par_user_id` (constante en dur `1`, comme le code actuel — pas de changement du
    système d'auth, hors scope). Sur `202`, extrait `job_id` de la réponse JSON et redirige vers
    `/import/status/{job_id}`. Sur erreur (cURL échoué, code HTTP ≠ 202), affiche un message
    d'erreur (pas de redirection, pas de job créé côté PHP).
  - Nouvelle méthode `status($jobId)` : rend la vue `Views/import_status.php` en lui passant `$jobId`.
  - Nouvelle méthode `jobStatusJson($jobId)` : appelle `ImportModel::getJobById($jobId)` et renvoie
    le résultat en JSON (`{statut, profile_id, error_message}`), 404 si le job n'existe pas.
- **`Models/ImportModel.php`** :
  - Supprime `enregistrerImport()` (plus utilisée).
  - Ajoute `getJobById($id)` : `SELECT statut, profile_id, error_message FROM import_jobs WHERE id = ?`.
  - `getDerniersImports()` inchangée (continue de fonctionner, les jobs sont maintenant créés côté
    Python mais dans la même table).
- **`Views/import_status.php`** (nouvelle vue) : page minimale avec un indicateur de chargement et
  un script JS vanilla (`fetch` + `setInterval`, cohérent avec le style existant de `import.php`)
  qui interroge `/import/job/{id}/status` toutes les 2 secondes :
  - `statut === 'réussi'` → `window.location = '/revue/' + profile_id`.
  - `statut === 'échoué'` → affiche `error_message`, arrête le polling, propose un lien retour vers `/`.
  - `statut === 'en cours'` → continue le polling.
- **`public/index.php`** : ajoute les routes `/import/status/(\d+)` et `/import/job/(\d+)/status`
  (mêmes conventions `preg_match` que les routes existantes).
- **`docker-compose.yml`** : ajoute `IMPORT_SERVICE_URL: http://import:8000` aux variables
  d'environnement du service `web` (nom de service Docker interne, port interne 8000 — pas le port
  hôte 8001).

## Gestion des erreurs

| Situation | Comportement |
| --- | --- |
| Fichier absent / erreur upload PHP | Message d'erreur immédiat, pas d'appel Python |
| cURL vers Python échoue (service down, timeout de connexion) | Message d'erreur affiché, pas de job créé |
| Python renvoie autre chose que 202 (validation échouée : mauvais type, trop gros) | Message d'erreur affiché avec le détail renvoyé par Python (400/413), pas de job créé |
| PDF illisible / texte vide | Job passe à `échoué` avec message explicite, aucun profil créé |
| Erreur Mistral (timeout, quota, réponse invalide) | Job passe à `échoué` avec message générique, détail loggé côté serveur, aucun profil créé |
| Erreur DB pendant l'écriture du profil | Rollback, job passe à `échoué`, aucun profil partiel |

## Hors scope (ne sera pas traité dans cette itération)

- L'affichage du PDF dans `Views/revue.php` (`<iframe src="/data/pdfs/...">`) semble cassé
  indépendamment : le volume `/data/pdfs` est monté hors de la racine Apache
  (`/var/www/html`) dans `web/Dockerfile`/`docker-compose.yml`, donc ce chemin n'est probablement
  pas servi tel quel. Pré-existant, pas introduit par ce changement — à traiter séparément.
- Authentification réelle : `importe_par_user_id` reste en dur à `1` côté PHP, comme le code actuel.
  Le seed/existence de cet utilisateur en base reste une dépendance manuelle non automatisée.
- Normalisation des noms de fichiers (caractères spéciaux, espaces) au-delà du préfixe `job_id`.

## Tests

- Test manuel de bout en bout : upload d'un vrai PDF de CV via `/import/upload`, vérifier la
  redirection vers `/import/status/{id}`, le polling jusqu'à `réussi`, la création du profil et de
  ses enfants en base, et la redirection finale vers `/revue/{id}`.
- Test manuel du chemin d'échec : upload d'un PDF sans texte extractible (page blanche) → job
  `échoué` avec message explicite, aucun profil créé.
- Vérification que `docker compose up` démarre toujours proprement et que `/health` (Python) et
  `/health.php` (PHP) répondent `ok` après les changements.
