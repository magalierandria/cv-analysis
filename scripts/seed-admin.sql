-- Crée (ou met à jour) le compte admin par défaut :
--   email        : admin@novity.io
--   mot de passe : changeme123
--
-- Utile quand scripts/seed-admin.sh ne fonctionne pas (ex: problème de
-- quoting du hash bcrypt selon le shell). Hash bcrypt pré-généré, aucune
-- dépendance à PHP/docker exec nécessaire ici.
--
-- Usage :
--   docker compose exec -T db psql -U cv -d cv_analysis -f - < scripts/seed-admin.sql
-- ou, en étant déjà dans un psql interactif sur la base cv_analysis :
--   \i scripts/seed-admin.sql

INSERT INTO utilisateurs (email, mot_de_passe, role)
VALUES (
    'admin@novity.io',
    '$2y$10$2wd0ceeZ5cYFMRD6NihymuOfrsDPa4Rswt20oLpBg5Nl.j05KwqBy',
    'admin'
)
ON CONFLICT (email) DO UPDATE
SET mot_de_passe = EXCLUDED.mot_de_passe,
    role = 'admin',
    actif = true;
