Status: ready-for-agent

# IMPORT-05 — Structuration LLM

## Parent

`.scratch/base-profils-cv-v1/PRD.md`

## What to build

Appel LLM réel (ou mock si IMPORT-04 ouvert) : texte brut → JSON structuré → profil complet (identité, expériences + compétences, formations) en BDD. Extraction permissive, FR/EN sans traduction. Schéma JSON documenté.

## Acceptance criteria

- [ ] Profil structuré complet persisté en BDD
- [ ] Champs manquants OK (nom/email absents acceptés)
- [ ] CV FR et EN sans traduction
- [ ] Tests API avec LLM mocké

## Blocked by

- `import-04-fournisseur-llm` (mock acceptable si décision reportée)

## User stories

9–15
