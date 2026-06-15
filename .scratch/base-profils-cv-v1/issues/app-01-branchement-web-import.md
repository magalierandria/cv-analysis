Status: ready-for-agent

# APP-01 — Branchement WEB ↔ IMPORT

## Parent

`.scratch/base-profils-cv-v1/PRD.md`

## What to build

Connecter WEB-04 à IMPORT-06 : `ImportServiceClient` (cURL natif) envoie le PDF à `POST /import`, récupère `job_id`, la page de polling existante suit le job réel, redirection revue si `réussi`, erreur si `échoué`. Premier parcours import bout en bout via le navigateur.

## Acceptance criteria

- [ ] Dépôt PDF navigateur → FastAPI → profil `à valider` en BDD
- [ ] Page polling fonctionne avec jobs réels (plus de fixture manuelle)
- [ ] Redirection vers écran revue placeholder
- [ ] Échec import affiché côté WEB
- [ ] Tests E2E ou intégration WEB + IMPORT

## Blocked by

- `web-04-depot-pdf-polling-ui`
- `import-06-echecs-techniques`

## User stories

17, 18
