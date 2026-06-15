Status: ready-for-agent

# IMPORT-07 — Stockage PDF volume partagé

## Parent

`.scratch/base-profils-cv-v1/PRD.md`

## What to build

Python sauvegarde le PDF sur `/data/pdfs/{profile_id}.pdf` au début du job. Chemin référencé en BDD. Testable via import API + vérification fichier sur disque.

## Acceptance criteria

- [ ] PDF écrit par Python sur volume partagé
- [ ] Chemin stocké en BDD lié au profil
- [ ] Fichier présent après import réussi
- [ ] Tests sans PHP

## Blocked by

- `import-02-post-import-async`

## User stories

15
