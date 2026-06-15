Status: done

# 00 — Bootstrap infra partagée

## Parent

`.scratch/base-profils-cv-v1/PRD.md` — `docs/adr/0001-stack-technique-v1.md`

## What to build

Fondation commune aux tracks **WEB** et **IMPORT** : monorepo (`web/`, `import/`, `migrations/`), `docker-compose.yml` (PostgreSQL 16, services `web`, `import`, `migrate`), migrations SQL neutres, volume `data/pdfs/` partagé.

Schéma initial : `utilisateurs`, `profils`, `experiences`, `competences`, `formations`, `import_jobs`, `schema_migrations`. Contrainte unicité `profils.email`.

Squelettes vides `web/` et `import/` — pas encore de MVC ni de routes métier.

## Acceptance criteria

- [x] `docker-compose up` démarre `db`, `web`, `import`, `migrate`
- [x] Schéma PostgreSQL appliqué via `migrations/`
- [x] Volume PDF monté sur `web` et `import`
- [x] Conteneurs `web` et `import` démarrent (pages/health minimales acceptables)

## Blocked by

None — can start immediately.
