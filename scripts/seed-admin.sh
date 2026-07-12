#!/bin/sh
set -e

if [ $# -ne 2 ]; then
    echo "Usage: $0 <email> <mot_de_passe>" >&2
    exit 1
fi

EMAIL="$1"
MOT_DE_PASSE="$2"

HASH=$(docker compose exec -T web php -r "echo password_hash('$MOT_DE_PASSE', PASSWORD_BCRYPT);")

docker compose exec -T db psql -U cv -d cv_analysis -v ON_ERROR_STOP=1 -c \
    "INSERT INTO utilisateurs (email, mot_de_passe, role) VALUES ('$EMAIL', '$HASH', 'admin')
     ON CONFLICT (email) DO UPDATE SET mot_de_passe = EXCLUDED.mot_de_passe, role = 'admin';"

echo "Compte admin créé/mis à jour : $EMAIL"
