CREATE TABLE utilisateurs (
    id SERIAL PRIMARY KEY,
    email TEXT NOT NULL UNIQUE,
    mot_de_passe TEXT NOT NULL
);

CREATE TABLE profils (
    id SERIAL PRIMARY KEY,
    nom TEXT,
    email TEXT UNIQUE,
    linkedin TEXT,
    titre TEXT,
    description TEXT,
    statut TEXT NOT NULL DEFAULT 'à valider'
        CHECK (statut IN ('à valider', 'validé')),
    importe_par_user_id INTEGER NOT NULL REFERENCES utilisateurs (id),
    date_import TIMESTAMPTZ NOT NULL DEFAULT now(),
    valide_par_user_id INTEGER REFERENCES utilisateurs (id),
    date_validation TIMESTAMPTZ,
    pdf_source TEXT NOT NULL
);

CREATE TABLE experiences (
    id SERIAL PRIMARY KEY,
    profil_id INTEGER NOT NULL REFERENCES profils (id) ON DELETE CASCADE,
    entreprise TEXT,
    poste TEXT,
    secteur TEXT,
    date_debut DATE,
    date_fin DATE,
    description TEXT,
    ordre INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE competences (
    id SERIAL PRIMARY KEY,
    experience_id INTEGER NOT NULL REFERENCES experiences (id) ON DELETE CASCADE,
    libelle TEXT NOT NULL
);

CREATE TABLE formations (
    id SERIAL PRIMARY KEY,
    profil_id INTEGER NOT NULL REFERENCES profils (id) ON DELETE CASCADE,
    intitule TEXT,
    etablissement TEXT,
    type TEXT CHECK (type IS NULL OR type IN ('diplôme', 'certification')),
    annee INTEGER
);

CREATE TABLE import_jobs (
    id SERIAL PRIMARY KEY,
    statut TEXT NOT NULL DEFAULT 'en cours'
        CHECK (statut IN ('en cours', 'réussi', 'échoué')),
    importe_par_user_id INTEGER NOT NULL REFERENCES utilisateurs (id),
    profile_id INTEGER REFERENCES profils (id),
    error_message TEXT,
    cree_le TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_profils_statut ON profils (statut);
CREATE INDEX idx_profils_date_import ON profils (date_import DESC);
CREATE INDEX idx_competences_libelle ON competences (libelle);
CREATE INDEX idx_import_jobs_statut ON import_jobs (statut);
