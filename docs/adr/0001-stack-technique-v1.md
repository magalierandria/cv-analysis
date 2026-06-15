# Stack technique v1

L'application est découpée en quatre composants : PHP (web), Python (import/IA), PostgreSQL (BDD partagée) et un fournisseur LLM externe. Nous avons choisi un **MVC maison en PHP natif** plutôt qu'un framework, pour garder la couche web légère tandis que toute la logique d'import reste côté Python.

L'**import est asynchrone** : PHP envoie le PDF à FastAPI, reçoit immédiatement un `job_id` (HTTP 202), et l'utilisateur attend sur une page de suivi qui interroge PostgreSQL par polling. FastAPI traite le job en arrière-plan via `BackgroundTasks` (extraction PyMuPDF → LLM → écriture profil). PHP lit l'état du job directement en BDD — pas d'API de polling côté Python.

Les deux runtimes accèdent à PostgreSQL en **SQL brut** (PDO + Repositories côté PHP, psycopg3 côté Python). Les migrations sont des **fichiers SQL versionnés neutres** (`migrations/NNN_*.sql`), appliqués par un service `migrate` au démarrage docker-compose. Le PDF est stocké sur un **volume local partagé** (`/data/pdfs/`) : Python seul écrit, PHP lit pour l'affichage en revue.

**Authentification** : sessions PHP natives + `password_hash` bcrypt. Pas d'inscription publique en v1 — comptes créés manuellement (seed SQL ou script CLI). Le conteneur web tourne sur **Apache + mod_php** (`php:8.3-apache`). PHP communique avec FastAPI via **cURL natif** (zéro dépendance Composer).

**Monorepo** : `web/` (PHP), `import/` (Python FastAPI), `migrations/`. Services Docker : `db`, `web`, `import`, `migrate`.

## Reporté

- **Fournisseur LLM** : interface abstraite côté Python ; choix du fournisseur reporté. Le texte brut ne quitte pas le réseau tant que le fournisseur n'est pas tranché (si LLM local envisagé).

## Considéré mais écarté

| Alternative | Raison de l'écart |
| --- | --- |
| Framework PHP (Symfony/Laravel) | Périmètre v1 limité ; MVC maison suffisant |
| Import HTTP synchrone | L'utilisateur attend de toute façon ; l'async évite les timeouts PHP et prépare l'import en lot |
| Worker Python séparé | Complexité inutile en v1 ; BackgroundTasks suffit pour imports unitaires |
| Redis pour l'état des jobs | PostgreSQL déjà présent ; pas de nouvelle infra |
| SQLAlchemy / ORM Python | Symétrie avec PHP (SQL brut des deux côtés) |
| Alembic | Migrations neutres SQL partagées entre les deux runtimes |
| Guzzle (PHP) | Zéro dépendance Composer ; cURL natif encapsulé |
| nginx + php-fpm | Apache choisi pour simplicité initiale du setup docker |
| Inscription publique | Outil interne équipe en v1 |
| OCR (CV scannés) | Hors périmètre v1 ; PyMuPDF seul, texte vide = échec technique |
