Status: ready-for-agent

# WEB-04 — Dépôt PDF & UI de suivi (sans FastAPI)

## Parent

`.scratch/base-profils-cv-v1/PRD.md`

## What to build

Formulaire de dépôt PDF unitaire, validation côté PHP (type fichier, taille). Page « Import en cours… » avec polling sur table `import_jobs` en BDD. **Pas d'appel FastAPI** à ce stade — le contrôleur peut simuler un `job_id` en fixture ou laisser la page prête à recevoir un vrai job.

Objectif : UI et logique de polling côté WEB prêtes avant branchement IMPORT.

## Acceptance criteria

- [ ] Formulaire upload PDF accessible depuis la liste
- [ ] Validation fichier (PDF uniquement)
- [ ] Page de suivi interroge `import_jobs` par polling
- [ ] Redirection prévue vers revue (route placeholder) si job `réussi`
- [ ] Message d'erreur prévu si job `échoué`
- [ ] Tests UI/polling avec jobs insérés directement en BDD (sans Python)

## Blocked by

- `web-03-layout-liste-vide`

## User stories

6, 7, 17
