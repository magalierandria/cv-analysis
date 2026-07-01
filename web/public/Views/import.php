<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; color: #333; }
        .card { border: none; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.07); }
        .drop-zone { border: 2px dashed #0d6efd; border-radius: 15px; padding: 30px; text-align: center; background: #f8fbff; cursor: pointer; transition: 0.3s; }
        .drop-zone:hover { background: #eff6ff; }
        .btn-novity { padding: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        #file-preview { display: none; background: #fdfdfd; border: 1px solid #dee2e6; border-radius: 12px; padding: 15px; }
        footer { margin-top: 40px; font-size: 0.8rem; color: #aaa; text-align: center; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card p-4">
                    <h2 class="fw-bold text-primary text-center">Novity AI</h2>
                    <p class="text-center text-muted mb-4">Analyse intelligente de candidatures</p>

                    <form action="/cv-analysis/web/public/import/upload" method="POST" enctype="multipart/form-data">
                        <div id="drop-zone-container">
                            <label for="cv_file" class="drop-zone w-100">
                                <i class="bi bi-file-earmark-pdf fs-1 text-primary"></i>
                                <p class="mt-2 mb-0"><b>Cliquez ou glissez</b> votre CV (PDF)</p>
                            </label>
                            <input type="file" name="cv_file" id="cv_file" class="d-none" accept=".pdf" onchange="showFile(this)">
                        </div>

                        <div id="file-preview" class="mt-3 align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill text-success fs-2 me-3"></i>
                                <div>
                                    <div id="file-name" class="fw-bold">...</div>
                                    <small id="file-size" class="text-muted"></small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="resetFile()">Annuler</button>
                        </div>

                        <button type="submit" id="submit-btn" class="btn btn-primary w-100 btn-novity mt-4 d-none">
                            <i class="bi bi-cpu"></i> Lancer l'analyse
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-12 mt-4">
                <div class="card p-4">
                    <h5 class="mb-3"><i class="bi bi-clock-history"></i> Historique des dernières analyses</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Nom du fichier</th>
                                    <th>Date d'import</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($historique)): ?>
                                    <?php foreach ($historique as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['nom_fichier']) ?></td>
                                        <td><?= htmlspecialchars($item['date_import']) ?></td>
                                        <td>
                                            <span class="badge <?= $item['statut'] == 'succes' ? 'bg-success' : 'bg-warning' ?>">
                                                <?= htmlspecialchars($item['statut']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center">Aucun CV importé pour le moment.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <footer>© 2026 Novity - Solution d'aide au recrutement assisté par IA</footer>
    </div>

    <script>
        function showFile(input) {
            const preview = document.getElementById('file-preview');
            const dropZone = document.getElementById('drop-zone-container');
            const submitBtn = document.getElementById('submit-btn');
            
            if (input.files && input.files[0]) {
                document.getElementById('file-name').textContent = input.files[0].name;
                document.getElementById('file-size').textContent = (input.files[0].size / 1024 / 1024).toFixed(2) + ' MB';
                dropZone.style.display = 'none';
                preview.style.display = 'flex';
                submitBtn.classList.remove('d-none');
            }
        }
        function resetFile() {
            document.getElementById('cv_file').value = '';
            document.getElementById('drop-zone-container').style.display = 'block';
            document.getElementById('file-preview').style.display = 'none';
            document.getElementById('submit-btn').classList.add('d-none');
        }
    </script>
</body>
</html>