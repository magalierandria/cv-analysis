Status: ready-for-agent

# PRD — Base de profils (CV) v1

Source : `docs/spec.md` (périmètre validé le 10/06/2026).

---

## Problem Statement

Une équipe doit constituer et exploiter une base de profils à partir de CV PDF, mais aujourd'hui ce travail est manuel, lent et peu structuré. Sans représentation normalisée des compétences par expérience, il est difficile de retrouver rapidement les profils pertinents. La v1 doit permettre d'importer un CV, d'en extraire automatiquement les données structurées, de les faire valider par un humain, puis de parcourir et filtrer la base partagée — sans encore aborder missions, clients ou matching.

## Solution

Une application web multi-utilisateur en français qui :

1. Authentifie les utilisateurs par email/mot de passe (comptes créés manuellement).
2. Accepte l'import unitaire d'un PDF de CV.
3. Déclenche un **job d'import asynchrone** : le service Python extrait le texte brut de façon déterministe, puis structure le profil via un LLM (le PDF n'est jamais envoyé au LLM).
4. Affiche une page de suivi pendant le traitement, puis crée un profil au statut `à valider`, conserve le PDF source, et redirige vers un écran de revue côte à côte (PDF + champs éditables).
5. Permet la validation humaine (avec contrôle de doublon sur l'email) et l'édition continue des profils validés.
6. Affiche une liste unique de tous les profils avec recherche texte et filtres (compétences en logique ET, statut).

## User Stories

### Authentification & accès

1. As a utilisateur, I want me connecter avec mon email et mon mot de passe, so that j'accède à la base de profils partagée.
2. As a utilisateur, I want que ma session soit sécurisée, so that seuls les utilisateurs authentifiés peuvent voir et modifier les profils.
3. As a utilisateur, I want voir la même base de profils que mes collègues, so that nous collaborons sur un référentiel unique.
4. As a utilisateur, I want que l'interface soit entièrement en français, so that je puisse travailler dans ma langue habituelle.
5. As a utilisateur, I want que les comptes soient créés par un administrateur (pas d'inscription publique), so that seuls les membres de l'équipe accèdent à l'outil en v1.

### Import de CV

6. As a utilisateur, I want importer un seul fichier PDF à la fois, so that je contrôle chaque profil individuellement en v1.
7. As a utilisateur, I want déposer un PDF via un parcours d'import dédié depuis la liste des profils, so that l'action est visible et accessible.
8. As a utilisateur, I want que le texte soit extrait du PDF de manière déterministe, so that le même PDF produit toujours le même texte brut.
9. As a utilisateur, I want que le PDF original ne soit jamais envoyé au LLM, so that seul le texte brut alimente l'extraction structurée.
10. As a utilisateur, I want que le LLM structure le profil (identité, expériences avec compétences, formations), so that je n'aie pas à saisir manuellement toutes les données.
11. As a utilisateur, I want que l'extraction tolère les CV en français et en anglais, so that la langue du document n'empêche pas l'import.
12. As a utilisateur, I want que le contenu extrait reste dans la langue d'origine, so that aucune traduction automatique ne déforme le CV.
13. As a utilisateur, I want qu'un profil soit créé même si certains champs manquent après extraction, so that je puisse compléter les données en revue.
14. As a utilisateur, I want qu'un profil fraîchement importé ait le statut `à valider`, so that je sache qu'il n'a pas encore été vérifié.
15. As a utilisateur, I want que le PDF source soit conservé avec le profil, so that je puisse toujours comparer avec l'original.
16. As a utilisateur, I want que le texte brut intermédiaire ne soit pas stocké, so that le stockage reste minimal (reproductible depuis le PDF).
17. As a utilisateur, I want voir une page « Import en cours… » pendant le traitement du job d'import, so that je sache que mon CV est en cours d'analyse.
18. As a utilisateur, I want être redirigé vers l'écran de revue après un import réussi, so that je corrige immédiatement les données extraites.
19. As a utilisateur, I want voir un message d'erreur clair si le job d'import échoue techniquement, so that je comprenne pourquoi aucun profil n'a été créé.
20. As a utilisateur, I want qu'aucun profil ne soit créé en cas d'échec technique (PDF illisible, texte vide, timeout LLM, réponse non exploitable), so that la base ne contienne pas de données corrompues.
21. As a utilisateur, I want que `importé par` et `date import` soient renseignés automatiquement, so that la traçabilité minimale soit assurée dès la création.

### Modèle profil — identité

22. As a utilisateur, I want éditer le nom d'un profil, so that je corrige les erreurs d'extraction.
23. As a utilisateur, I want éditer l'email d'un profil, so that je renseigne ou corrige l'identifiant unique.
24. As a utilisateur, I want éditer le LinkedIn, le titre et la description d'un profil, so that l'identité soit complète.
25. As a utilisateur, I want voir le statut du profil (`à valider` ou `validé`), so that je sache où il en est dans le cycle de vie.

### Modèle profil — expériences

26. As a utilisateur, I want ajouter, modifier et supprimer des expériences, so that le parcours professionnel reflète le CV.
27. As a utilisateur, I want renseigner pour chaque expérience l'entreprise, le poste, le secteur, les dates et une description, so that le détail soit fidèle au document source.
28. As a utilisateur, I want qu'une date de fin vide signifie « poste en cours », so that les postes actuels soient correctement représentés.
29. As a utilisateur, I want associer des compétences (mots-clés) à chaque expérience, so that les savoir-faire soient contextualisés.
30. As a utilisateur, I want que les compétences soient stockées telles qu'extraites ou saisies, sans normalisation, so that la v1 reste simple et prévisible.
31. As a utilisateur, I want que le secteur soit un champ libre par expérience, so that je puisse taguer « Fintech », « Santé », etc.

### Modèle profil — formations

32. As a utilisateur, I want ajouter, modifier et supprimer des formations, so that le parcours académique soit complet.
33. As a utilisateur, I want renseigner l'intitulé, l'établissement, le type (`diplôme` ou `certification`) et l'année, so that chaque formation soit structurée.

### Revue & validation

34. As a utilisateur, I want un écran de revue avec tous les champs en édition, so that je corrige librement avant validation.
35. As a utilisateur, I want voir le PDF original côte à côte avec les champs extraits, so that je compare visuellement et corrige efficacement.
36. As a utilisateur, I want que la validation soit bloquée si le nom est manquant, so that aucun profil validé n'ait d'identité incomplète.
37. As a utilisateur, I want que la validation soit bloquée si l'email est manquant, so que l'identifiant unique soit toujours présent à la validation.
38. As a utilisateur, I want valider un profil pour le passer au statut `validé`, so that je confirme que les données sont correctes.
39. As a utilisateur, I want que `validé par` et `date validation` soient renseignés à la validation, so that la traçabilité minimale soit complète.
40. As a utilisateur, I want rééditer un profil déjà validé depuis sa fiche, so that je puisse corriger des erreurs ultérieures.
41. As a utilisateur, I want qu'il n'existe pas de statut `rejeté` en v1, so that le cycle de vie reste binaire (à valider / validé).

### Gestion des doublons

42. As a utilisateur, I want que l'email soit l'identifiant unique d'un profil en base, so that deux personnes ne partagent pas le même enregistrement.
43. As a utilisateur, I want que le contrôle de doublon s'effectue uniquement à la validation, so that l'import ne bloque pas quand l'email est encore absent.
44. As a utilisateur, I want qu'à la validation, si l'email existe déjà sur un autre profil, on me propose de remplacer l'existant, so that je puisse mettre à jour un profil avec un CV plus récent.
45. As a utilisateur, I want qu'à la validation, si l'email existe déjà, on me propose d'annuler et d'éditer le profil existant, so that je ne perde pas involontairement des données.
46. As a utilisateur, I want qu'en choisissant « remplacer », le profil existant soit écrasé par les données en cours, so that la base reflète la version la plus récente.
47. As a utilisateur, I want qu'en choisissant « annuler », je sois redirigé vers l'édition du profil existant, so that je reprenne le travail au bon endroit.

### Liste & exploration

48. As a utilisateur, I want voir une liste unique de tous les profils (à valider et validés mélangés), so that j'aie une vue d'ensemble sans navigation complexe.
49. As a utilisateur, I want voir le statut affiché sur chaque profil dans la liste, so that j'identifie rapidement les profils à traiter.
50. As a utilisateur, I want accéder à la fiche détaillée d'un profil depuis la liste, so that je consulte ou édite un profil.
51. As a utilisateur, I want effectuer une recherche texte libre sur nom, titre, description, formations et secteur, so that je retrouve un profil par mots-clés larges.
52. As a utilisateur, I want filtrer par statut (`à valider` ou `validé`), so that je me concentre sur les profils à traiter ou validés.
53. As a utilisateur, I want filtrer par compétences via multi-sélection parmi les valeurs existantes en base, so that je trouve des profils possédant des savoir-faire précis.
54. As a utilisateur, I want que le filtre compétence utilise une logique ET (intersection), so that seuls les profils possédant toutes les compétences sélectionnées apparaissent.
55. As a utilisateur, I want que le filtre compétence raisonne au niveau profil (agrégation sur toutes les expériences), so that je trouve un profil même si la compétence est sur une expérience différente.
56. As a utilisateur, I want voir sur la fiche profil qui a importé et validé, avec les dates, so that la traçabilité minimale soit visible.

### Non-régression & limites acceptées v1

57. As a utilisateur, I want accepter que « JS » et « JavaScript » apparaissent comme deux compétences distinctes en v1, so that nous n'attendions pas de normalisation prématurée.
58. As a utilisateur, I want accepter que « Santé » et « Healthcare » soient deux secteurs distincts en filtre, so that la v1 reste sans normalisation linguistique.
59. As a utilisateur, I want accepter qu'il n'y ait pas de calcul du nombre d'années par compétence en v1, so that le modèle reste simple tout en permettant ce calcul ultérieurement.
60. As a utilisateur, I want accepter qu'il n'y ait pas d'historique complet des modifications en v1, so que seules les métadonnées import/validation soient tracées.

## Implementation Decisions

### Périmètre & vision

- **v1 = base de profils uniquement** : aucune entité mission, client, ou logique de matching.
- Un **profil** = représentation structurée d'un CV : identité, expériences (avec compétences rattachées), formations.
- **Interface en français** ; contenus CV acceptés FR/EN sans traduction.

### Modèle de données

**Profil**

| Champ | Type | Obligatoire | Notes |
| --- | --- | --- | --- |
| nom | texte | Oui pour valider | Peut être absent après extraction |
| email | texte | Oui pour valider | Clé unique anti-doublon |
| linkedin | URL | Non | |
| titre | texte | Non | |
| description | texte | Non | |
| statut | enum | Oui | `à valider` \| `validé` |
| importé par | utilisateur | Oui | Auto |
| date import | date | Oui | Auto |
| validé par | utilisateur | Non | À la validation |
| date validation | date | Non | À la validation |
| pdf_source | fichier | Oui | PDF original conservé |
| experiences | liste | Non | Voir Expérience |
| formations | liste | Non | Voir Formation |

**Expérience**

| Champ | Type | Notes |
| --- | --- | --- |
| entreprise, poste, secteur | texte | Secteur libre |
| date_debut, date_fin | date | date_fin vide = en cours |
| description | texte | |
| competences | liste de mots-clés | Rattachées à l'expérience, pas au profil |

**Formation**

| Champ | Type | Notes |
| --- | --- | --- |
| intitule, etablissement | texte | |
| type | enum | `diplôme` \| `certification` |
| annee | nombre | |

**Compétences** : mots-clés non normalisés, stockés tels que produits par le LLM ou saisis en revue. Le modèle (compétences datées par expérience) prépare le calcul futur d'années par compétence.

### Architecture technique (version simplifiée)

```
┌─────────────┐         ┌──────────────────┐
│  PHP (WEB)  │◄───────►│       BDD        │
└──────┬──────┘         └────────▲─────────┘
       │                           │
       │ déclenche import          │ écrit profil extrait
       ▼                           │
┌─────────────┐         ┌──────────┴───────┐
│   Python    │◄───────►│       LLM        │
│ (Import/IA) │         └──────────────────┘
└─────────────┘
```

| Composant | Rôle |
| --- | --- |
| **PHP (WEB)** | MVC maison (Apache + mod_php) : UI, auth (sessions + bcrypt), CRUD, revue/validation, liste & filtres, polling jobs, client cURL → FastAPI. PDO + SQL. |
| **Python (Import / IA)** | FastAPI + BackgroundTasks : `POST /import`, PyMuPDF, appel LLM, persistance profil/job. psycopg3 + SQL. |
| **LLM** | Structuration du texte brut en profil (identité, expériences + compétences, formations). Ne reçoit jamais le PDF. |
| **BDD** | PostgreSQL — source de vérité partagée (profils, utilisateurs, jobs d'import, métadonnées, référence PDF). |

**Flux principaux :**

- **Parcours web courant** : PHP ↔ PostgreSQL (consultation, édition, validation, filtres, polling jobs).
- **Import CV (async)** : PHP reçoit le PDF → `POST` FastAPI → `202 + job_id` → page de suivi (polling BDD) → Python traite en BackgroundTask → profil `à valider` ou échec → redirection revue ou erreur.
- **PDF** : volume local partagé `/data/pdfs/` — Python écrit, PHP lit pour la revue.

> Détail complet : [ADR-0001](../../docs/adr/0001-stack-technique-v1.md).

### Stack technique (tranchée)

| Domaine | Choix v1 |
| --- | --- |
| Web | PHP 8.3, MVC maison, Apache + mod_php |
| BDD | PostgreSQL 16, PDO (PHP) / psycopg3 (Python), SQL brut |
| Migrations | Fichiers SQL versionnés neutres (`migrations/`) |
| Import | Python 3.12 + FastAPI, BackgroundTasks, PyMuPDF |
| Communication | HTTP async : cURL natif PHP → `POST /import` |
| Auth | Sessions PHP natives + bcrypt, pas d'inscription publique |
| Monorepo | `web/`, `import/`, `migrations/` |
| LLM | **Reporté** — interface abstraite côté Python |

### Pipeline d'import

1. L'utilisateur dépose un PDF via **PHP (WEB)**.
2. **PHP** transmet le PDF à **FastAPI** (`POST /import`, cURL multipart) → reçoit `202 { job_id }`.
3. **PHP** affiche la page « Import en cours… » et interroge la table `import_jobs` par polling.
4. **FastAPI** (BackgroundTask) : sauvegarde le PDF sur le volume partagé, crée le job `en cours`.
5. **Python** extrait le texte brut via **PyMuPDF** (déterministe, pas d'IA). Le LLM ne reçoit jamais le PDF.
6. **Python** envoie le texte brut au **LLM** → profil structuré (schéma JSON à définir).
7. **Python** crée le profil `à valider` en **BDD**, met à jour le job `réussi` (+ `profile_id`). Le texte brut est **jeté**.
8. **PHP** détecte `réussi` → redirection revue ; `échoué` → message d'erreur (aucun profil créé).

**Extraction permissive** : ne jamais échouer pour un champ manquant ; signaler nom/email manquants à la validation uniquement.

**Échec technique** : message d'erreur utilisateur, **aucun profil créé** — l'échec n'est pas un statut de profil.

### Cycle de vie

- Statuts : `à valider` (initial) → `validé` (après revue humaine). Pas de `rejeté`.
- Validation requiert : nom + email + contrôle doublon email passé.
- Profils validés restent éditables.

### Gestion des doublons

- Email = identifiant unique en base.
- Contrôle à la validation uniquement.
- Si doublon : choix **Remplacer l'existant** (écrasement) ou **Annuler** (redirection édition du profil existant).

### Visualisation & filtres

- Liste unique, statut visible par profil.
- Recherche texte : nom, titre, description, formations, secteur.
- Filtre compétence : multi-sélection, valeurs existantes en base, logique **ET** au niveau profil (union des compétences de toutes les expériences).
- Filtre statut : `à valider` \| `validé`.

### Utilisateurs & sécurité

- Multi-utilisateur, base partagée (tous voient/éditent la même base).
- Authentification email/mot de passe (sessions PHP natives + bcrypt).
- **Pas d'inscription publique** : comptes créés manuellement (seed SQL ou script CLI).
- Traçabilité minimale : importé par/date, validé par/date — pas d'audit trail complet.

### Job d'import — cycle de vie

| Statut | Description |
| --- | --- |
| `en cours` | Job créé, traitement Python en cours |
| `réussi` | Profil créé (`profile_id` renseigné) |
| `échoué` | Erreur technique, aucun profil créé (`error_message` renseigné) |

### Modules à construire (greenfield)

**PHP (`web/`)**

- MVC maison : front controller `public/index.php`, routing, contrôleurs, vues, repositories PDO.
- Authentification (sessions natives, bcrypt).
- UI française : connexion, liste + filtres, dépôt PDF, page suivi import (polling), revue côte à côte, fiche profil.
- CRUD profils, validation, doublons, recherche & filtres.
- `ImportServiceClient` (cURL natif) → `POST /import` vers FastAPI.
- `ImportJobRepository` — lecture polling directe en BDD.
- Affichage PDF depuis volume partagé `/data/pdfs/`.

**Python (`import/`)**

- FastAPI : `POST /import` (multipart PDF + `imported_by_user_id`) → `202 { job_id }`.
- BackgroundTasks : sauvegarde PDF, extraction PyMuPDF, appel LLM (interface abstraite), écriture profil + mise à jour job.
- psycopg3 + SQL brut pour persistance.
- `GET /health` pour healthcheck docker.

**BDD & infra**

- PostgreSQL : profils, expériences, compétences, formations, utilisateurs, `import_jobs`.
- Migrations SQL neutres dans `migrations/`, service `migrate` docker-compose.
- Volume partagé `data/pdfs/` monté sur `web` et `import`.

### Décisions techniques encore ouvertes

- **Fournisseur LLM** (interface abstraite en place, implémentation reportée).
- Schéma JSON précis de la réponse LLM.
- Format exact des codes/messages d'erreur inter-services.

## Testing Decisions

### Principe

Les tests vérifient le **comportement observable** via les interfaces publiques (API HTTP, parcours utilisateur), pas les détails d'implémentation. Un bon test survit aux refactors internes tant que le comportement métier est inchangé.

### Seams proposés (du plus haut au plus bas)

| Seam | Ce qu'on teste | Injection / isolation |
| --- | --- | --- |
| **1. Parcours E2E (PHP)** | Connexion → import async (polling) → revue → validation → liste/filtres | FastAPI fixture ; LLM mocké |
| **2. PHP + BDD (intégration)** | Auth, CRUD, validation, doublons, filtres, polling `import_jobs` | BDD éphémère ; client cURL mocké |
| **3. Python + BDD (intégration)** | BackgroundTask : PDF → PyMuPDF → LLM → profil + job `réussi`/`échoué` | LLM et PyMuPDF injectables |
| **4. Contrat PHP ↔ Python** | `POST /import` → `202 + job_id` ; erreurs HTTP sans profil créé | Tests contractuels |
| **5. Règles métier profil** | Validation, doublons, filtre compétences ET | Via PHP + BDD |

**Seams privilégiés :** PHP+BDD (2) pour le métier web et le polling ; Python+BDD (3) pour le pipeline ; contrat **(4)** pour la frontière `POST /import`. Le polling se teste côté PHP sans appeler Python.

### Comportements prioritaires à tester

1. Import async réussi → job `réussi`, profil `à valider`, PDF conservé, redirection revue.
2. Job `échoué` → message d'erreur, aucun profil créé.
3. Page de suivi polling : transitions `en cours` → `réussi` ou `échoué`.
4. Validation bloquée sans nom ou email.
5. Doublon email à validation → remplacer vs annuler.
6. Filtre compétences multi-sélection logique ET au niveau profil.
7. Recherche texte sur les champs spécifiés.
8. Profil validé reste éditable.
9. Traçabilité import/validation affichée.

### Prior art

Projet greenfield — pas de tests existants. Établir les conventions de test dès le premier tracer bullet (auth + import minimal).

## Out of Scope

- Missions, clients, matching automatique.
- Import en lot (batch).
- Normalisation des compétences et secteurs.
- Calcul du nombre d'années par compétence.
- Statut `rejeté` / archivage.
- Historique complet des modifications / journal d'audit.
- Traduction des contenus CV.
- Filtres par nombre d'années d'expérience, établissement ou entreprise.
- Fournisseur LLM (reporté, voir ADR-0001).
- OCR pour CV scannés.

## Further Notes

- **ADR-0001** : `docs/adr/0001-stack-technique-v1.md` — référence architecture et stack.
- Schéma JSON réponse LLM à définir quand le fournisseur sera tranché.
- Import **async** : PHP poll la BDD directement (pas d'API de statut côté Python).
- Le texte brut n'est pas stocké ; les tests vérifient sa non-persistance.
- Vision long terme : association profils ↔ missions clients par compétences.
- Documents sources : `docs/spec.md`, `CONTEXT.md`.
