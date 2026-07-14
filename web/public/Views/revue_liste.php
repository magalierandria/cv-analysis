<?php
/** @var array $profils */
/** @var string $recherche */
/** @var string $statut */
/** @var array $competences */
/** @var array $toutesLesCompetences */
$activeNav = 'revue';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Liste des profils</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-4xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>

        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card p-4 mb-4">
                    <h3 class="fw-bold mb-4">Liste des profils</h3>

                    <form method="GET" action="/revue" class="row g-3">
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
                            <a href="/revue" class="btn btn-outline-secondary">Réinitialiser</a>
                        </div>
                    </form>
                </div>

                <?php if (empty($profils)): ?>
                    <div class="card p-4 text-center text-muted">
                        Aucun profil ne correspond à ces critères.
                    </div>
                <?php else: ?>
                    <?php foreach ($profils as $profil): ?>
                        <a href="/revue/<?= $profil['id'] ?>" class="text-decoration-none text-dark">
                            <div class="card p-3 mb-3 profil-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold fs-5"><?= htmlspecialchars($profil['nom'] ?? 'Sans nom') ?></div>
                                        <div class="text-muted"><?= htmlspecialchars($profil['titre'] ?? '') ?></div>
                                    </div>
                                    <span class="badge-status <?= $profil['statut'] === 'validé' ? 'bg-success text-white' : 'bg-warning text-dark' ?>">
                                        <?= htmlspecialchars($profil['statut']) ?>
                                    </span>
                                </div>
                                <?php if (!empty($profil['description'])): ?>
                                    <p class="small text-muted mb-2 mt-2"><?= htmlspecialchars($profil['description']) ?></p>
                                <?php endif; ?>
                                <?php
                                    $competencesProfil = [];
                                    foreach ($profil['experiences'] as $exp) {
                                        foreach ($exp['competences'] as $comp) {
                                            $competencesProfil[$comp] = true;
                                        }
                                    }
                                ?>
                                <?php if (!empty($competencesProfil)): ?>
                                    <div>
                                        <?php foreach (array_keys($competencesProfil) as $comp): ?>
                                            <span class="badge-competence"><?= htmlspecialchars($comp) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
