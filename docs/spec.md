# Spécification détaillée — v1 : Base de profils (CV)

> Document de spécification fonctionnelle. Nouveau projet, indépendant de tout codebase existant.
Version 1 — périmètre validé le 10/06/2026.
> 

---

## 1. Vision & contexte

### 1.1 Vision long terme

Faciliter l'**association de profils à des missions clients** selon les compétences et les besoins du client (matching automatique).

### 1.2 Périmètre de la v1

La v1 se concentre **exclusivement** sur la constitution et l'exploration d'une **base de profils**. Aucune notion de mission, de client ou de matching n'est implémentée à ce stade.

Un **profil** = la représentation structurée d'un CV : une personne avec une identité, des expériences (et leurs compétences), et des formations.

**Objectifs de la v1 :**

1. Importer un CV au format PDF.
2. Extraire automatiquement les données structurées via un LLM.
3. Réviser/corriger puis valider chaque profil (contrôle humain).
4. Visualiser la liste des profils et la filtrer (compétences, formations, secteur, statut, recherche texte).

**Hors périmètre v1** (reporté en v2+) :

- Missions, clients, matching automatique.
- Import en lot (batch).
- Normalisation des compétences / secteurs (ex: « JS » = « JavaScript »).
- Calcul du nombre d'années par compétence.
- Historique complet des modifications / journal d'audit.
- Traduction des contenus.

---

## 2. Modèle de données (conceptuel)

### 2.1 Profil

| Champ | Type | Obligatoire | Notes |
| --- | --- | --- | --- |
| `nom` | texte | **Oui** (pour valider) |  |
| `email` | texte | **Oui** (pour valider) | Clé unique anti-doublon |
| `linkedin` | texte (URL) | Non |  |
| `titre` | texte | Non | Ex : « Dev Senior » |
| `description` | texte | Non | Résumé / accroche |
| `statut` | enum | Oui | `à valider` | `validé` |
| `importé par` | utilisateur | Oui | Renseigné automatiquement |
| `date import` | date | Oui | Renseignée automatiquement |
| `validé par` | utilisateur | Non | Renseigné à la validation |
| `date validation` | date | Non | Renseignée à la validation |
| `pdf_source` | fichier | Oui | PDF original conservé |
| `experiences` | liste | Non | Voir 2.2 |
| `formations` | liste | Non | Voir 2.3 |

> **Note sur « obligatoire »** : les champs `nom` et `email` sont **requis pour la validation humaine**, mais **pas pour l'extraction**. Un profil fraîchement extrait (`à valider`) peut temporairement ne pas les contenir.
> 

### 2.2 Expérience

| Champ | Type | Obligatoire | Notes |
| --- | --- | --- | --- |
| `entreprise` | texte | Non |  |
| `poste` | texte | Non |  |
| `secteur` | texte | Non | Champ libre. Ex : « Fintech », « Santé » |
| `date_debut` | date | Non |  |
| `date_fin` | date | Non | Vide = poste en cours |
| `description` | texte | Non | Description courte |
| `competences` | liste de mots-clés | Non | Voir 2.4 |

### 2.3 Formation

| Champ | Type | Obligatoire | Notes |
| --- | --- | --- | --- |
| `intitule` | texte | Non |  |
| `etablissement` | texte | Non |  |
| `type` | enum | Non | `diplôme` | `certification` |
| `annee` | nombre | Non |  |

### 2.4 Compétences

- Les compétences sont des **mots-clés** (ex : `FastAPI`, `Firebase`, `Stripe`, `CI/CD`).
- Elles sont **rattachées à chaque expérience**, pas directement au profil.
- Exemple : une expérience décrivant « Développement des APIs en FastAPI, intégration de Firebase, Stripe, Sentry… » produit la liste `["FastAPI", "Firebase", "Stripe", "Sentry"]`.
- Le **nombre d'années par compétence** n'est pas calculé en v1, mais le modèle (compétences datées par expérience) permettra ce calcul plus tard, en agrégeant les durées des expériences où chaque compétence apparaît.
- **Aucune normalisation** en v1 : les mots-clés sont stockés tels que produits par le LLM.

---

## 3. Pipeline d'import & d'extraction

### 3.1 Déroulé

1. **Import unitaire** : l'utilisateur dépose **un** fichier PDF.
2. **Déclenchement asynchrone** : l'application web transmet le PDF au service Python et reçoit un identifiant de **job d'import**. L'utilisateur voit une page « Import en cours… ».
3. **Traitement en arrière-plan** (service Python) :
    1. Sauvegarde du PDF sur le stockage local partagé.
    2. **Extraction du texte brut** du PDF de manière **déterministe** (pas d'IA). Le LLM ne reçoit jamais le PDF.
    3. **Extraction structurée par LLM** : le texte brut est envoyé au LLM, qui retourne le profil structuré (identité, expériences + compétences, formations) dans un **format précis**.
    4. Le profil est créé en base avec le statut **`à valider`**.
    5. Le **texte brut intermédiaire est jeté** (reproductible depuis le PDF à tout moment).
4. **Suivi du job** : l'application web interroge l'état du job (`en cours` → `réussi` ou `échoué`) jusqu'à completion.
5. **Résultat** : redirection vers l'**écran de revue** si succès, ou **message d'erreur** si échec technique (aucun profil créé).

> Le **PDF original est conservé** dès le début du traitement (avant l'extraction LLM).

### 3.2 Extraction permissive

- L'extraction **ne échoue jamais** pour un champ manquant. Le LLM remplit ce qu'il peut.
- Les champs requis manquants (`nom`, `email`) sont signalés plus tard, au moment de la validation.

### 3.3 Échec technique d'extraction

- Si l'extraction échoue techniquement (PDF illisible, texte vide, timeout LLM, réponse non exploitable) → **message d'erreur affiché à l'utilisateur**, **aucun profil n'est créé**.
- L'échec n'est **pas** un statut de profil.

### 3.4 Langues

- Extraction **tolérante FR + EN** : le LLM produit les champs structurés quelle que soit la langue d'entrée.
- **Aucune traduction** : un contenu en anglais reste en anglais.

---

## 4. Cycle de vie & statuts

### 4.1 Statuts

| Statut | Description |
| --- | --- |
| `à valider` | État initial après extraction LLM. Données non vérifiées. |
| `validé` | Données revues et confirmées par un humain. |

> Pas de statut `rejeté` en v1.
> 

### 4.2 Transition `à valider` → `validé`

Conditions pour valider :

- `nom` renseigné.
- `email` renseigné.
- **Contrôle de doublon** sur l'email (voir §5) passé.

### 4.3 Revue obligatoire

- L'écran de revue présente **tous les champs en édition** (identité, expériences + compétences, formations).
- Le **PDF original est affiché côte à côte** avec les champs extraits, pour comparer et corriger.
- L'utilisateur corrige librement, puis valide.

### 4.4 Réédition après validation

- Un profil `validé` reste **éditable à tout moment** depuis sa fiche (mêmes champs).

---

## 5. Gestion des doublons

- **L'email est l'identifiant unique** d'un profil. Pas de doublons en base.
- Le **contrôle s'effectue au moment de la validation** (pas à l'import), car l'email peut être absent juste après extraction.
- Si, à la validation, l'email existe déjà sur un autre profil, deux options sont proposées :
    1. **Remplacer l'existant** : le profil existant est écrasé par les données en cours.
    2. **Annuler** : abandon de la validation en cours et **redirection vers l'édition du profil existant**.

---

## 6. Visualisation & filtres

### 6.1 Liste des profils

- **Une seule liste** (pas de séparation entre file d'attente et base validée).
- Le **statut est affiché** sur chaque profil (obligatoire).

### 6.2 Recherche & filtres

| Mécanisme | Portée |
| --- | --- |
| **Recherche texte libre** | nom, titre, description, formations, secteur |
| **Filtre compétence** | multi-sélection parmi les **valeurs existantes** en base ; logique **ET** ; agrégée sur **toutes** les expériences du profil |
| **Filtre statut** | `à valider` | `validé` |

**Sémantique du filtre compétence :**

- Une compétence vit dans une expérience, mais le filtre raisonne **au niveau profil** : « le profil a au moins une expérience taguée X ».
- Multi-compétences = **intersection (ET)** : le profil doit posséder **toutes** les compétences sélectionnées, réparties sur n'importe quelle(s) expérience(s).

**Hors v1 :** filtres par nombre d'années d'expérience, par établissement, par entreprise.

---

## 7. Utilisateurs & accès

- **Multi-utilisateur**, **base de profils partagée** (tous les utilisateurs voient/éditent la même base).
- **Authentification par login email/password.**
- **Pas d'inscription publique en v1** : les comptes sont créés manuellement (seed ou script administrateur).

### 7.1 Traçabilité (minimale)

- On enregistre et affiche sur la fiche profil :
    - `importé par` + date d'import.
    - `validé par` + date de validation.
- Pas d'historique complet des modifications en v1.

---

## 8. Internationalisation

- **Interface en français.**
- Contenus de CV acceptés en **FR et EN**, sans traduction ni normalisation linguistique.
- Remarque connue : un même secteur peut apparaître sous deux libellés (« Santé » / « Healthcare »), pouvant générer deux entrées de filtre distinctes. Accepté en v1.

---

## 9. Parcours utilisateur (récapitulatif)

```
Connexion (email/password)
        │
        ▼
┌─────────────────────────┐
│  Liste des profils      │◄──────────────┐
│  (recherche + filtres)  │               │
└───────────┬─────────────┘               │
            │ « Importer un CV »          │
            ▼                              │
   Dépôt d'un PDF (unitaire)               │
            │                              │
   Job d'import créé (en cours)            │
            │                              │
   Page « Import en cours… » (polling)     │
            │                              │
   [Python] Extraction texte + LLM         │
            │                              │
       échec ? ──► message d'erreur        │
            │ succès                       │
            ▼                              │
   Profil créé « à valider »               │
            │                              │
            ▼                              │
┌─────────────────────────┐               │
│  Écran de revue         │               │
│  (champs éditables +    │               │
│   PDF côte à côte)      │               │
└───────────┬─────────────┘               │
            │ « Valider »                  │
            ▼                              │
   Vérif : nom + email + doublon           │
       │              │                    │
   email existe ?     │ OK                 │
   ┌────┴────┐        ▼                    │
 Remplacer  Annuler  Profil « validé » ────┘
   l'existant  → édition de l'existant
```

---

## 10. Critères d'acceptation (v1)

- Un utilisateur peut se connecter via email/password.
- Un utilisateur peut importer **un** PDF de CV et suivre la progression du job d'import jusqu'à completion.
- Le texte est extrait du PDF de manière déterministe, puis envoyé au LLM (le PDF n'est jamais envoyé au LLM).
- Le LLM produit un profil structuré (identité, expériences + compétences par expérience, formations).
- En cas d'échec d'extraction, un message d'erreur s'affiche et aucun profil n'est créé.
- Le profil créé apparaît avec le statut `à valider`.
- L'écran de revue affiche tous les champs en édition + le PDF original côte à côte.
- La validation est bloquée si `nom` ou `email` manque.
- À la validation, si l'email existe déjà : choix « remplacer » ou « annuler + édition de l'existant ».
- Un profil validé reste éditable.
- La liste affiche tous les profils avec leur statut.
- La recherche texte libre couvre nom, titre, description, formations, secteur.
- Le filtre compétence (multi-sélection, valeurs existantes, logique ET) fonctionne au niveau profil.
- Le filtre statut fonctionne.
- Le PDF original est conservé ; le texte brut n'est pas stocké.
- La fiche profil affiche `importé par` / `validé par` + dates.
- L'interface est en français ; les CV FR et EN sont extraits sans traduction.

---

## 11. Stack technique

> Décisions d'architecture tranchées — détail dans [ADR-0001](../adr/0001-stack-technique-v1.md).

| Composant | Choix v1 |
| --- | --- |
| Web | PHP 8.3, MVC maison, Apache + mod_php |
| Import / IA | Python 3.12, FastAPI, BackgroundTasks |
| BDD | PostgreSQL 16, SQL brut (PDO / psycopg3) |
| Migrations | Fichiers SQL versionnés neutres |
| Import | Async HTTP : `POST /import` → `202 + job_id`, polling BDD côté PHP |
| PDF | Stockage local, volume partagé, Python écrit / PHP lit |
| Extraction texte | PyMuPDF (pas d'OCR v1) |
| Auth | Sessions PHP natives + bcrypt, pas d'inscription publique |
| LLM | **Reporté** — interface abstraite côté Python |

---

## 12. Décisions reportées (v2+)

- Missions, clients, matching automatique.
- Import en lot.
- Calcul du nombre d'années par compétence (agrégation des durées).
- Normalisation des compétences et des secteurs.
- Statut `rejeté` / archivage.
- Historique complet des modifications.
- Fournisseur LLM (choix reporté, voir ADR-0001).
- OCR pour CV scannés.