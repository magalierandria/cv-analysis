#!/bin/sh
set -e

psql -v ON_ERROR_STOP=1 <<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    version TEXT PRIMARY KEY,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
SQL

for migration in /migrations/*.sql; do
    [ -f "$migration" ] || continue
    version=$(basename "$migration" .sql)
    applied=$(psql -tAc "SELECT 1 FROM schema_migrations WHERE version = '$version'")
    if [ "$applied" != "1" ]; then
        echo "Applying $version..."
        psql -v ON_ERROR_STOP=1 -f "$migration"
        psql -v ON_ERROR_STOP=1 -c "INSERT INTO schema_migrations (version) VALUES ('$version')"
    else
        echo "Skipping $version (already applied)"
    fi
done

echo "Migrations complete."
