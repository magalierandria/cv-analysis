Status: done

# IMPORT-01 — Service FastAPI skeleton

## Parent

`.scratch/base-profils-cv-v1/PRD.md` — `docs/adr/0001-stack-technique-v1.md`

## What to build

Service Python indépendant de PHP : application FastAPI dans `import/`, `GET /health`, connexion PostgreSQL via psycopg3, structure de modules prête pour le pipeline. Testable via curl/httpx sans aucun composant WEB.

## Acceptance criteria

- [x] `GET /health` retourne 200
- [x] Service démarre dans docker-compose
- [x] Connexion PostgreSQL fonctionnelle
- [x] Tests API health

## Blocked by

- `00-bootstrap-infra-partagee`
