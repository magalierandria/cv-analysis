Status: ready-for-agent

# IMPORT-06 — Échecs techniques

## Parent

`.scratch/base-profils-cv-v1/PRD.md`

## What to build

Couvrir timeout LLM, réponse invalide, PDF corrompu, texte vide. Job `échoué` avec message ; zéro profil créé. Testable via API seule.

## Acceptance criteria

- [ ] Chaque scénario d'échec produit job `échoué` sans profil
- [ ] `error_message` renseigné et exploitable côté WEB plus tard
- [ ] Tests par scénario

## Blocked by

- `import-05-structuration-llm`

## User stories

19, 20
