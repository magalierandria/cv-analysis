CREATE TABLE offres (
    id SERIAL PRIMARY KEY,
    titre TEXT NOT NULL,
    description TEXT,
    cree_par_user_id INTEGER NOT NULL REFERENCES utilisateurs (id),
    date_creation TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE offre_competences (
    id SERIAL PRIMARY KEY,
    offre_id INTEGER NOT NULL REFERENCES offres (id) ON DELETE CASCADE,
    libelle TEXT NOT NULL
);

CREATE INDEX idx_offre_competences_offre_id ON offre_competences (offre_id);
