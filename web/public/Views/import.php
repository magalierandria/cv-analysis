<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; color: #333; }
        .upload-card { border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .drop-zone { border: 2px dashed #0d6efd; border-radius: 15px; padding: 40px; text-align: center; background: #f8fbff; transition: 0.3s; cursor: pointer; }
        .instructions { font-size: 0.9rem; color: #666; background: #fff3cd; padding: 15px; border-radius: 10px; }
        #file-preview { display: none; background: #fdfdfd; border: 1px solid #dee2e6; border-radius: 12px; padding: 20px; }
        .btn-novity { padding: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        footer { margin-top: 30px; font-size: 0.8rem; color: #aaa; text-align: center; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="card upload-card p-4 mx-auto" style="max-width: 600px;">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-primary">CV Analysis</h2>
                <p>Analyse intelligente de candidatures</p>
            </div>

            <div class="instructions mb-4">
                <p class="mb-1 fw-bold"><i class="bi bi-info-circle"></i> Instructions :</p>
                <ul class="mb-0 ps-3">
                    <li>Téléchargez uniquement des fichiers <b>PDF</b>.</li>
                    <li>La taille maximale est de <b>5 Mo</b>.</li>
                    <li>L'analyse prend environ 5 à 10 secondes.</li>
                </ul>
            </div>

            <form action="/cv-analysis/web/public/import/upload" method="POST" enctype="multipart/form-data">
                <div id="drop-zone-container">
                    <label for="cv_file" class="drop-zone w-100">
                        <i class="bi bi-file-earmark-pdf fs-1 text-primary"></i>
                        <p class="mt-2 mb-0"><b>Cliquez pour charger</b> ou glissez votre CV</p>
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

                <button type="submit" id="submit-btn" class="btn btn-primary w-100 btn-novity mt-4" style="display: none;">
                    <i class="bi bi-cpu"></i> Lancer l'analyse du profil
                </button>
            </form>
        </div>
        <footer>
            © 2026 Novity - Solution d'aide au recrutement assisté par IA
        </footer>
    </div>

    <script>
        function showFile(input) {
            const preview = document.getElementById('file-preview');
            const dropZone = document.getElementById('drop-zone-container');
            const submitBtn = document.getElementById('submit-btn');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                document.getElementById('file-name').textContent = file.name;
                document.getElementById('file-size').textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB';
                dropZone.style.display = 'none';
                preview.style.display = 'flex';
                submitBtn.style.display = 'block';
            }
        }
        function resetFile() {
            document.getElementById('cv_file').value = '';
            document.getElementById('drop-zone-container').style.display = 'block';
            document.getElementById('file-preview').style.display = 'none';
            document.getElementById('submit-btn').style.display = 'none';
        }
    </script>
</body>
</html>