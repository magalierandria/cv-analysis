-- 1. Nettoyage complet pour repartir d'une base propre
TRUNCATE TABLE competences, experiences, formations, import_jobs, profils, utilisateurs RESTART IDENTITY CASCADE;

-- 2. Ajout de l'utilisateur
INSERT INTO utilisateurs (id, email, mot_de_passe) VALUES (1, 'admin@cv-analysis.com', 'hash_secure_123');

-- 3. Insertion des 5 profils
INSERT INTO profils (nom, email, linkedin, titre, description, statut, importe_par_user_id, date_import, valide_par_user_id, date_validation, pdf_source) VALUES
('Alice Durand', 'alice.d@email.com', 'in/alice-d', 'Data Scientist', 'Expert ML', 'validé', 1, NOW(), 1, NOW(), 'alice.pdf'),
('Bob Martin', 'bob.m@email.com', 'in/bob-m', 'Dev Fullstack', 'React/Node', 'à valider', 1, NOW(), NULL, NULL, 'bob.pdf'),
('Charlie Petit', 'charlie.p@email.com', 'in/charlie-p', 'UX Designer', 'Figma', 'validé', 1, NOW(), 1, NOW(), 'charlie.pdf'),
('Diane Leroy', 'diane.l@email.com', 'in/diane-l', 'DevOps', 'Kubernetes', 'validé', 1, NOW(), 1, NOW(), 'diane.pdf'),
('Erik Roux', 'erik.r@email.com', 'in/erik-r', 'Product Owner', 'Agile', 'à valider', 1, NOW(), NULL, NULL, 'erik.pdf');

-- 4. Insertion des expériences
INSERT INTO experiences (profil_id, entreprise, poste, secteur, date_debut, date_fin, description, ordre) VALUES
(1, 'AI Corp', 'Data Scientist', 'Tech', '2023-01-01', NULL, 'Développement modèles', 1),
(2, 'Web Agency', 'Développeur', 'Web', '2022-06-01', NULL, 'Maintenance site', 1),
(3, 'Design Studio', 'Designer', 'Art', '2021-09-01', '2023-09-01', 'Refonte interface', 1),
(4, 'Cloud IT', 'Ingénieur DevOps', 'IT', '2020-01-01', NULL, 'Gestion infrastructure', 1),
(5, 'Startup SAS', 'Product Owner', 'Gestion', '2022-01-01', NULL, 'Gestion backlog', 1);

-- 5. Insertion des compétences
INSERT INTO competences (experience_id, libelle) VALUES
(1, 'Python'), (1, 'PyTorch'),
(2, 'JavaScript'), (2, 'Node.js'),
(3, 'Figma'), (3, 'Adobe XD'),
(4, 'Docker'), (4, 'AWS'),
(5, 'Jira'), (5, 'Scrum');

-- 6. Insertion des formations
INSERT INTO formations (profil_id, intitule, etablissement, type, annee) VALUES
(1, 'Master IA', 'Polytechnique', 'diplôme', 2022),
(2, 'Licence Info', 'Fac Informatique', 'diplôme', 2021),
(3, 'BTS Design', 'École Arts', 'diplôme', 2020),
(4, 'Ingénieur Réseaux', 'Telecom Paris', 'diplôme', 2019),
(5, 'Master Management', 'HEC', 'diplôme', 2021);

-- 7. Log des imports (CORRIGÉ avec le statut 'réussi')
INSERT INTO import_jobs (statut, importe_par_user_id, profile_id, cree_le) VALUES
('réussi', 1, 1, NOW()),
('réussi', 1, 2, NOW()),
('réussi', 1, 3, NOW()),
('réussi', 1, 4, NOW()),
('réussi', 1, 5, NOW());