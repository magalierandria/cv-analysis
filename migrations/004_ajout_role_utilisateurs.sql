ALTER TABLE utilisateurs
    ADD COLUMN role TEXT NOT NULL DEFAULT 'utilisateur'
        CHECK (role IN ('admin', 'utilisateur')),
    ADD COLUMN actif BOOLEAN NOT NULL DEFAULT true;
