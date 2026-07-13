<?php
// Définition de la base pour garantir la stabilité des liens
define('BASE_URL', '/cv-analysis/web/public');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Revue de Profil | Novity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8fafc; height: 100vh; margin: 0; overflow: hidden; }
        .revue-container { display: flex; height: 100vh; gap: 0; }
        
        /* Zone PDF : occupe 2/3 de l'écran, fond blanc, sans bordure */
        .pdf-viewer { flex: 2; background: #fff; border-right: 1px solid #e2e8f0; }
        
        /* Zone Formulaire : occupe 1/3, défilement interne */
        .form-validation { flex: 1; padding: 25px; background: #fff; overflow-y: auto; }
        
        .section-title { font-weight: 600; margin-top: 1.5rem; color: #2563eb; }
    </style>
</head>
<body>

<div class="revue-container">
    <div class="pdf-viewer">
        <?php if (!empty($profil['pdf_source'])): ?>
            <iframe src="<?= BASE_URL ?>/data/pdfs/<?= htmlspecialchars($profil['pdf_source']) ?>" 
                    width="100%" height="100%" style="border:none;">
            </iframe>
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                Aucun PDF disponible pour ce profil.
            </div>
        <?php endif; ?>
    </div>

    <div class="form-validation">
        <div class="d-flex justify-content-between mb-4">
            <a href="<?= BASE_URL ?>/revue" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Retour à la liste
            </a>
            <a href="<?= BASE_URL ?>/logout" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
        </div>

        <h3 class="mb-4 fw-bold">Validation du profil</h3>

        <?php if (isset($profil)): ?>
            <form action="<?= BASE_URL ?>/profil/update" method="POST">
                <input type="hidden" name="id" value="<?= htmlspecialchars($profil['id'] ?? '') ?>">

                <div class="mb-3">
                    <label class="form-label text-muted small">Nom complet</label>
                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($profil['nom'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small">Titre</label>
                    <input type="text" name="titre" class="form-control" value="<?= htmlspecialchars($profil['titre'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small">Email</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($profil['email'] ?? '—') ?>" readonly>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted small">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="à valider" <?= ($profil['statut'] ?? '') == 'à valider' ? 'selected' : '' ?>>À valider</option>
                        <option value="validé" <?= ($profil['statut'] ?? '') == 'validé' ? 'selected' : '' ?>>Validé</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2">Enregistrer et Valider</button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">Profil introuvable.</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>