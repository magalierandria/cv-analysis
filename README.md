# Base de profils (CV)

Application interne pour constituer et explorer une base de profils structurés à partir de CV PDF, avec revue humaine avant validation.

## Stack

| Composant | Technologie |
| --- | --- |
| Web | PHP 8.3, MVC maison, Apache + mod_php |
| Import / IA | Python 3.12, FastAPI, PyMuPDF |
| BDD | PostgreSQL 16, SQL brut (PDO / psycopg3) |
| Infra | Docker Compose |

Voir [docs/adr/0001-stack-technique-v1.md](docs/adr/0001-stack-technique-v1.md) pour les décisions d'architecture.

## Prérequis

- [Docker](https://docs.docker.com/get-docker/) et Docker Compose v2

## Démarrage rapide

```bash
docker compose up -d --build
```

Services disponibles :

| Service | URL / accès |
| --- | --- |
| Web (PHP) | http://localhost:8080 |
| Import (FastAPI) | http://localhost:8000/health |
| PostgreSQL | `localhost:5432` — base `cv_analysis`, user `cv`, password `cv` |

Les migrations SQL s'appliquent automatiquement au démarrage via le service `migrate`.

## Structure du monorepo

```
├── web/              # Application PHP (UI, auth, CRUD, revue)
├── import/           # Service FastAPI (import async, extraction, LLM)
├── migrations/       # Fichiers SQL versionnés (neutres PHP/Python)
├── scripts/          # Scripts d'infra (ex. run-migrations.sh)
├── data/pdfs/        # Volume partagé pour les PDF (gitignored)
├── tests/            # Tests d'intégration bootstrap
├── docs/             # Spécification et ADR
└── CONTEXT.md        # Glossaire du domaine
```

## Tests

Avec les conteneurs démarrés :

```bash
python3 -m venv tests/.venv
tests/.venv/bin/pip install -r tests/requirements.txt
tests/.venv/bin/pytest tests/bootstrap/ -v
```

## Documentation

- [CONTEXT.md](CONTEXT.md) — vocabulaire métier (profil, import, revue, validation…)
- [docs/spec.md](docs/spec.md) — spécification fonctionnelle v1
- [.scratch/base-profils-cv-v1/PRD.md](.scratch/base-profils-cv-v1/PRD.md) — PRD et user stories
