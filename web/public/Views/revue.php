<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Revue de Profil - Novity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .revue-container { display: flex; height: 90vh; gap: 20px; padding: 20px; }
        .pdf-viewer { flex: 2; border: 1px solid #dee2e6; border-radius: 10px; background: #eee; }
        .form-validation { flex: 1; padding: 20px; background: #fff; border: 1px solid #dee2e6; border-radius: 10px; }
    </style>
</head>
<body>

<div class="revue-container">
    <div class="pdf-viewer">
        <?php if (!empty($profil['pdf_source'])): ?>
            <iframe src="/data/pdfs/<?= htmlspecialchars($profil['pdf_source']) ?>" width="100%" height="100%"></iframe>
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-center h-100 text-muted">Aucun PDF disponible</div>
        <?php endif; ?>
    </div>

    <div class="form-validation shadow-sm">
        <div class="mb-3">
            <a href="/cv-analysis/web/public/" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Retour
            </a>
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
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <option value="à valider" <?= ($profil['statut'] ?? '') == 'à valider' ? 'selected' : '' ?>>À valider</option>
                        <option value="validé" <?= ($profil['statut'] ?? '') == 'validé' ? 'selected' : '' ?>>Validé</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary w-100">Enregistrer et Valider</button>
            </form>
        <?php else: ?>
            <p class="text-danger">Profil introuvable.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>