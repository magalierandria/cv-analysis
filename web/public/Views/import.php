<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity AI | Analyse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #2563eb; --bg: #f8fafc; }
        body { background-color: var(--bg); font-family: 'Inter', system-ui, sans-serif; }
        
        .navbar-custom { background: white; border-radius: 12px; padding: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .nav-pills .nav-link { color: #64748b; font-weight: 500; }
        .nav-pills .nav-link.active { background-color: var(--primary); }
        
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        
        .drop-zone { 
            border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px; 
            text-align: center; background: #fff; cursor: pointer; transition: 0.3s; 
        }
        .drop-zone:hover { border-color: var(--primary); background: #f0f7ff; }
        
        .badge-status { padding: 0.5em 1em; border-radius: 50px; font-weight: 600; font-size: 0.75rem; }
    </style>
</head>
<body>
    <div class="container py-5">
        <!-- Navigation -->
        <div class="row justify-content-center mb-4">
            <div class="col-md-8">
                <nav class="navbar-custom">
                    <div class="nav nav-pills nav-fill">
                        <a class="nav-link active" href="/cv-analysis/web/public/"><i class="bi bi-upload"></i> Import</a>
                        <a class="nav-link" href="/cv-analysis/web/public/revue"><i class="bi bi-list-check"></i> Revue</a>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Section Import -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card p-4 mb-4">
                    <h3 class="fw-bold text-center">Novity AI</h3>
                    <p class="text-center text-muted mb-4">Analyse intelligente de candidatures</p>

                    <form action="/cv-analysis/web/public/import/upload" method="POST" enctype="multipart/form-data">
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
                            <i class="bi bi-cpu"></i> Lancer l'analyse
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
                                            <!-- Lien dynamique vers la page de revue avec l'ID -->
                                            <a href="/cv-analysis/web/public/revue/<?= htmlspecialchars($item['id']) ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($item['nom_fichier']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($item['cree_le']) ?></td>
                                        <td>
                                            <span class="badge-status <?= $item['statut'] == 'reussi' ? 'bg-success text-white' : 'bg-warning text-dark' ?>">
                                                <?= htmlspecialchars($item['statut']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center text-muted">Aucun CV importé.</td></tr>
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
    </script>
</body>
</html>