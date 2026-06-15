Status: ready-for-agent

# IMPORT-02 — POST /import asynchrone

## Parent

`.scratch/base-profils-cv-v1/PRD.md`

## What to build

Endpoint `POST /import` (multipart PDF + `imported_by_user_id`) → `202 { job_id }`. Création job `en cours` en BDD. BackgroundTask avec traitement mocké (profil minimal `à valider`). Mise à jour job `réussi` + `profile_id` ou `échoué` + `error_message`.

Testable entièrement via HTTP client — **aucun PHP requis**.

## Acceptance criteria

- [ ] `POST /import` retourne 202 et `job_id`
- [ ] Job transite `en cours` → `réussi` avec profil créé en BDD
- [ ] `importé par` + `date import` renseignés
- [ ] Échec simulé → job `échoué`, aucun profil
- [ ] Tests API bout en bout sans PHP

## Blocked by

- `import-01-fastapi-skeleton`

## User stories

13, 14, 21
