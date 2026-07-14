<?php
/** @var array $historique */
$activeNav = 'import';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | CV Analyser</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-3xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>

        <!-- Section Import -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card p-4 mb-4">
                    <h3 class="fw-bold text-center">Novity CV Analyser</h3>
                    <p class="text-center text-muted mb-4">Analyse intelligente de candidatures</p>

                    <form id="upload-form" action="/cv-analysis/web/public/import/upload" method="POST" enctype="multipart/form-data">
                        <div id="drop-zone-container">
                            <label for="cv_file" class="drop-zone w-100">
                                <i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
                                <p class="mt-2">Glissez votre CV ou <b>parcourez</b></p>
                            </label>
                            <input type="file" name="cv_file" id="cv_file" class="d-none" accept=".pdf" onchange="showFile(this)">
                        </div>

                        <div id="file-preview" class="d-none p-3 border rounded mb-3 bg-light align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-3 me-3"></i>
                                <div>
                                    <div id="file-name" class="fw-bold small">...</div>
                                    <small id="file-size" class="text-muted"></small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="resetFile()">✕</button>
                        </div>

                        <button type="submit" id="submit-btn" class="btn btn-primary w-100 d-none">
                            <span id="submit-btn-icon"><i class="bi bi-cpu"></i> Lancer l'analyse</span>
                        </button>
                    </form>
                </div>

                <!-- Section Historique avec liens cliquables -->
                <div class="card p-4">
                    <h5 class="mb-3"><i class="bi bi-clock-history"></i> Historique des analyses</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
    <thead class="table-light">
        <tr>
            <th>Candidat</th>
            <th>Nom du fichier</th>
            <th>Date</th>
            <th>Statut</th>
        </tr>
    </thead>
   <tbody>
    <?php if (!empty($historique)): ?>
        <?php foreach ($historique as $item): ?>
        <tr>
            <td>
                <?php if (!empty($item['profile_id'])): ?>
                    <a href="/cv-analysis/web/public/revue/<?= $item['profile_id'] ?>">
                        <?= htmlspecialchars($item['candidat_nom'] ?? 'En attente d’analyse') ?>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($item['candidat_nom'] ?? 'En attente d’analyse') ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if (!empty($item['profile_id'])): ?>
                    <a href="/cv-analysis/web/public/revue/<?= $item['profile_id'] ?>">
                        <?= htmlspecialchars($item['nom_fichier']) ?>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($item['nom_fichier'] ?? '—') ?>
                <?php endif; ?>
            </td>
            <td><?= date('d/m/Y H:i', strtotime($item['cree_le'])) ?></td>
            <td>
                <span class="badge-status <?= $item['statut'] == 'réussi' ? 'bg-success text-white' : 'bg-warning text-dark' ?>">
                    <?= htmlspecialchars($item['statut']) ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr><td colspan="4" class="text-center text-muted">Aucun CV importé.</td></tr>
    <?php endif; ?>
</tbody>
</table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showFile(input) {
            if (input.files && input.files[0]) {
                document.getElementById('file-name').textContent = input.files[0].name;
                document.getElementById('file-size').textContent = (input.files[0].size / 1024 / 1024).toFixed(2) + ' MB';
                document.getElementById('drop-zone-container').classList.add('d-none');
                document.getElementById('file-preview').classList.remove('d-none');
                document.getElementById('submit-btn').classList.remove('d-none');
            }
        }
        function resetFile() {
            document.getElementById('cv_file').value = '';
            document.getElementById('drop-zone-container').classList.remove('d-none');
            document.getElementById('file-preview').classList.add('d-none');
            document.getElementById('submit-btn').classList.add('d-none');
        }

        document.getElementById('upload-form').addEventListener('submit', function () {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            document.getElementById('submit-btn-icon').innerHTML =
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Envoi en cours…';
        });
    </script>
</body>
</html>