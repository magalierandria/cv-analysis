<?php
/** @var array|null $profil */
$activeNav = 'revue';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Revue de Profil - Novity</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="bg-background font-sans text-foreground">

<div class="revue-container">
    <div class="pdf-viewer">
        <?php if (!empty($profil['pdf_source'])): ?>
            <iframe src="/pdfs/<?= (int) $profil['id'] ?>" width="100%" height="100%"></iframe>
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-center h-100 text-muted">Aucun PDF disponible</div>
        <?php endif; ?>
    </div>

    <div class="form-validation shadow-sm">
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <a href="/cv-analysis/web/public/" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
            <form method="POST" action="/logout" class="m-0">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Déconnexion</button>
            </form>
        </div>
        <h3 class="mb-4">Validation du profil</h3>

        <?php if (isset($profil)): ?>
            <form action="/cv-analysis/web/public/profil/update" method="POST">
                <input type="hidden" name="id" value="<?= htmlspecialchars($profil['id'] ?? '') ?>">

                <div class="mb-3">
                    <label class="form-label">Nom complet</label>
                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($profil['nom'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Titre</label>
                    <input type="text" name="titre" class="form-control" value="<?= htmlspecialchars($profil['titre'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($profil['email'] ?? '—') ?>" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">LinkedIn</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($profil['linkedin'] ?? '—') ?>" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" rows="3" readonly><?= htmlspecialchars($profil['description'] ?? '—') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="à valider" <?= ($profil['statut'] ?? '') == 'à valider' ? 'selected' : '' ?>>À valider</option>
                        <option value="validé" <?= ($profil['statut'] ?? '') == 'validé' ? 'selected' : '' ?>>Validé</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100">Enregistrer et Valider</button>
            </form>

            <!-- ===================== EXPERIENCES ===================== -->
            <div class="section-title">
                <span><i class="bi bi-briefcase"></i> Expériences</span>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#add-experience-form">
                    <i class="bi bi-plus-lg"></i> Ajouter
                </button>
            </div>

            <?php if (!empty($profil['experiences'])): ?>
                <?php foreach ($profil['experiences'] as $exp): ?>
                    <div class="exp-card">
                        <div class="card-actions">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-exp-<?= $exp['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="/cv-analysis/web/public/experience/delete/<?= $exp['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette expérience ?');">
                                <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>

                        <div class="fw-bold"><?= htmlspecialchars($exp['poste'] ?? '') ?></div>
                        <div class="text-muted small">
                            <?= htmlspecialchars($exp['entreprise'] ?? '') ?>
                            <?php if (!empty($exp['secteur'])): ?> — <?= htmlspecialchars($exp['secteur']) ?><?php endif; ?>
                        </div>
                        <div class="text-muted small mb-2">
                            <?= $exp['date_debut'] ? htmlspecialchars($exp['date_debut']) : '?' ?>
                            —
                            <?= $exp['date_fin'] ? htmlspecialchars($exp['date_fin']) : 'Présent' ?>
                        </div>
                        <?php if (!empty($exp['description'])): ?>
                            <p class="small mb-2"><?= nl2br(htmlspecialchars($exp['description'])) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($exp['competences'])): ?>
                            <div>
                                <?php foreach ($exp['competences'] as $comp): ?>
                                    <span class="badge-competence"><?= htmlspecialchars($comp) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Formulaire de modification (masqué par défaut) -->
                        <div class="collapse mt-3" id="edit-exp-<?= $exp['id'] ?>">
                            <form action="/cv-analysis/web/public/experience/update/<?= $exp['id'] ?>" method="POST" class="add-form">
                                <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                <div class="mb-2">
                                    <label class="form-label small">Poste</label>
                                    <input type="text" name="poste" class="form-control form-control-sm" value="<?= htmlspecialchars($exp['poste'] ?? '') ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Entreprise</label>
                                    <input type="text" name="entreprise" class="form-control form-control-sm" value="<?= htmlspecialchars($exp['entreprise'] ?? '') ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Secteur</label>
                                    <input type="text" name="secteur" class="form-control form-control-sm" value="<?= htmlspecialchars($exp['secteur'] ?? '') ?>">
                                </div>
                                <div class="row mb-2">
                                    <div class="col">
                                        <label class="form-label small">Début</label>
                                        <input type="text" name="date_debut" class="form-control form-control-sm" placeholder="ex. 2019 ou 2019-03" value="<?= htmlspecialchars($exp['date_debut'] ?? '') ?>">
                                    </div>
                                    <div class="col">
                                        <label class="form-label small">Fin</label>
                                        <input type="text" name="date_fin" class="form-control form-control-sm" placeholder="ex. 2022 ou en cours" value="<?= htmlspecialchars($exp['date_fin'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Description</label>
                                    <textarea name="description" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($exp['description'] ?? '') ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary w-100">Enregistrer</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted small">Aucune expérience renseignée.</p>
            <?php endif; ?>

            <!-- Formulaire d'ajout d'expérience (masqué par défaut) -->
            <div class="collapse" id="add-experience-form">
                <form action="/cv-analysis/web/public/experience/add" method="POST" class="add-form">
                    <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                    <div class="mb-2">
                        <label class="form-label small">Poste</label>
                        <input type="text" name="poste" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Entreprise</label>
                        <input type="text" name="entreprise" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Secteur</label>
                        <input type="text" name="secteur" class="form-control form-control-sm">
                    </div>
                    <div class="row mb-2">
                        <div class="col">
                            <label class="form-label small">Début</label>
                            <input type="text" name="date_debut" class="form-control form-control-sm" placeholder="ex. 2019 ou 2019-03">
                        </div>
                        <div class="col">
                            <label class="form-label small">Fin</label>
                            <input type="text" name="date_fin" class="form-control form-control-sm" placeholder="ex. 2022 ou en cours">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">Ajouter l'expérience</button>
                </form>
            </div>

            <!-- ===================== FORMATIONS ===================== -->
            <div class="section-title">
                <span><i class="bi bi-mortarboard"></i> Formations</span>
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#add-formation-form">
                    <i class="bi bi-plus-lg"></i> Ajouter
                </button>
            </div>

            <?php if (!empty($profil['formations'])): ?>
                <?php foreach ($profil['formations'] as $form): ?>
                    <div class="formation-card">
                        <div class="card-actions">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#edit-form-<?= $form['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form action="/cv-analysis/web/public/formation/delete/<?= $form['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cette formation ?');">
                                <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>

                        <div class="fw-bold"><?= htmlspecialchars($form['intitule'] ?? '') ?></div>
                        <div class="text-muted small">
                            <?= htmlspecialchars($form['etablissement'] ?? '') ?>
                            <?php if (!empty($form['type'])): ?> — <?= htmlspecialchars($form['type']) ?><?php endif; ?>
                            <?php if (!empty($form['annee'])): ?> (<?= htmlspecialchars($form['annee']) ?>)<?php endif; ?>
                        </div>

                        <!-- Formulaire de modification (masqué par défaut) -->
                        <div class="collapse mt-3" id="edit-form-<?= $form['id'] ?>">
                            <form action="/cv-analysis/web/public/formation/update/<?= $form['id'] ?>" method="POST" class="add-form">
                                <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                <div class="mb-2">
                                    <label class="form-label small">Intitulé</label>
                                    <input type="text" name="intitule" class="form-control form-control-sm" value="<?= htmlspecialchars($form['intitule'] ?? '') ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Établissement</label>
                                    <input type="text" name="etablissement" class="form-control form-control-sm" value="<?= htmlspecialchars($form['etablissement'] ?? '') ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Type</label>
                                    <select name="type" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        <option value="diplôme" <?= ($form['type'] ?? '') == 'diplôme' ? 'selected' : '' ?>>Diplôme</option>
                                        <option value="certification" <?= ($form['type'] ?? '') == 'certification' ? 'selected' : '' ?>>Certification</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Année</label>
                                    <input type="number" name="annee" class="form-control form-control-sm" value="<?= htmlspecialchars($form['annee'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary w-100">Enregistrer</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted small">Aucune formation renseignée.</p>
            <?php endif; ?>

            <div class="collapse" id="add-formation-form">
                <form action="/cv-analysis/web/public/formation/add" method="POST" class="add-form">
                    <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                    <div class="mb-2">
                        <label class="form-label small">Intitulé</label>
                        <input type="text" name="intitule" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Établissement</label>
                        <input type="text" name="etablissement" class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Type</label>
                        <select name="type" class="form-select form-select-sm">
                            <option value="">—</option>
                            <option value="diplôme">Diplôme</option>
                            <option value="certification">Certification</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Année</label>
                        <input type="number" name="annee" class="form-control form-control-sm">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">Ajouter la formation</button>
                </form>
            </div>

        <?php else: ?>
            <p class="text-danger">Profil introuvable.</p>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>