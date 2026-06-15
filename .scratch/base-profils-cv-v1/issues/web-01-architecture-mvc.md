Status: ready-for-agent

# WEB-01 — Architecture MVC maison

## Parent

`.scratch/base-profils-cv-v1/PRD.md` — `docs/adr/0001-stack-technique-v1.md`

## What to build

Mettre en place l'ossature MVC PHP (sans framework) : front controller `public/index.php`, routing explicite, structure contrôleurs / vues / repositories PDO, `.htaccess` Apache (`try_files` → front controller). Connexion BDD via PDO isolée dans les repositories.

Aucune feature métier — une route de test suffit à prouver que le routing fonctionne.

## Acceptance criteria

- [ ] Toute requête HTTP passe par le front controller
- [ ] Structure MVC documentée (rôles contrôleur, vue, repository)
- [ ] PDO connecté à PostgreSQL via docker-compose
- [ ] Route de test retourne une réponse HTTP 200
- [ ] Conventions alignées ADR-0001 (Apache + mod_php, zéro Composer)

## Blocked by

- `00-bootstrap-infra-partagee`
