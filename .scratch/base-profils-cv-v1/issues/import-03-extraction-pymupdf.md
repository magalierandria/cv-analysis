Status: ready-for-agent

# IMPORT-03 — Extraction texte PyMuPDF

## Parent

`.scratch/base-profils-cv-v1/PRD.md`

## What to build

Remplacer le mock d'extraction par PyMuPDF : texte brut déterministe, jamais persisté, jamais envoyé au LLM sous forme de PDF. Testable via `POST /import` seul.

## Acceptance criteria

- [ ] Même PDF → même texte (test fixture)
- [ ] Texte brut absent de la BDD
- [ ] PDF illisible / texte vide → job `échoué`, aucun profil
- [ ] Tests sans PHP

## Blocked by

- `import-02-post-import-async`

## User stories

8, 16
