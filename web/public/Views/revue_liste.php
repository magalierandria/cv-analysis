<?php
// 1. Définition de la base pour la navigation sécurisée
define('BASE_URL', '/cv-analysis/web/public');

// 2. Initialisation des variables pour éviter les erreurs d'exécution
$recherche = $recherche ?? '';
$statut = $statut ?? '';
$competences = $competences ?? [];
$toutesLesCompetences = $toutesLesCompetences ?? [];
$profils = $profils ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Liste des profils</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #2563eb; --bg: #f8fafc; }
        body { background-color: var(--bg); font-family: 'Inter', system-ui, sans-serif; }
        .navbar-custom { background: white; border-radius: 12px; padding: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .nav-pills .nav-link { color: #64748b; font-weight: 500; }
        .nav-pills .nav-link.active { background-color: var(--primary); }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .badge-status { padding: 0.5em 1em; border-radius: 50px; font-weight: 600; font-size: 0.75rem; }
        .badge-competence { background: #e0e7ff; color: #3730a3; border-radius: 50px; padding: 3px 10px; font-size: 0.8rem; margin: 2px; display: inline-block; }
        .profil-card { transition: 0.15s; }
        .profil-card:hover { box-shadow: 0 6px 14px -2px rgba(0,0,0,0.15); }
        .competences-filtre { max-height: 160px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center mb-4">
            <div class="col-md-10">
                <nav class="navbar-custom">
                    <div class="nav nav-pills nav-fill">
                        <a class="nav-link" href="<?= BASE_URL ?>/"><i class="bi bi-upload"></i> Import</a>
                        <a class="nav-link active" href="<?= BASE_URL ?>/revue"><i class="bi bi-list-check"></i> Revue</a>
                        <a class="nav-link text-danger" href="<?= BASE_URL ?>/logout">
                            <i class="bi bi-box-arrow-right"></i> Déconnexion
                        </a>
                    </div>
                </nav>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card p-4 mb-4">
                    <h3 class="fw-bold mb-4">Liste des profils</h3>

                    <form method="GET" action="<?= BASE_URL ?>/revue" class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small">Recherche</label>
                            <input type="text" name="q" class="form-control" placeholder="Nom, titre, description, formation, secteur…"
                                   value="<?= htmlspecialchars($recherche) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Statut</label>
                            <select name="statut" class="form-select">
                                <option value="">Tous</option>
                                <option value="à valider" <?= $statut === 'à valider' ? 'selected' : '' ?>>À valider</option>
                                <option value="validé" <?= $statut === 'validé' ? 'selected' : '' ?>>Validé</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Compétences</label>
                            <div class="competences-filtre">
                                <?php if (empty($toutesLesCompetences)): ?>
                                    <span class="text-muted small">Aucune compétence en base.</span>
                                <?php endif; ?>
                                <?php foreach ($toutesLesCompetences as $competence): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="competences[]"
                                               id="comp-<?= htmlspecialchars($competence) ?>"
                                               value="<?= htmlspecialchars($competence) ?>"
                                               <?= in_array($competence, $competences, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="comp-<?= htmlspecialchars($competence) ?>">
                                            <?= htmlspecialchars($competence) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Filtrer</button>
                            <a href="<?= BASE_URL ?>/revue" class="btn btn-outline-secondary">Réinitialiser</a>
                        </div>
                    </form>
                </div>

                <?php if (empty($profils)): ?>
                    <div class="card p-4 text-center text-muted">
                        Aucun profil ne correspond à ces critères.
                    </div>
                <?php else: ?>
                    <?php foreach ($profils as $profil): ?>
                        <a href="<?= BASE_URL ?>/revue/<?= $profil['id'] ?>" class="text-decoration-none text-dark">
                            <div class="card p-3 mb-3 profil-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold fs-5"><?= htmlspecialchars($profil['nom'] ?? 'Sans nom') ?></div>
                                        <div class="text-muted"><?= htmlspecialchars($profil['titre'] ?? '') ?></div>
                                    </div>
                                    <span class="badge-status <?= ($profil['statut'] ?? '') === 'validé' ? 'bg-success text-white' : 'bg-warning text-dark' ?>">
                                        <?= htmlspecialchars($profil['statut'] ?? 'à valider') ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>