<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Dépôt de CV</title>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow p-4 mx-auto" style="max-width: 500px;">
            <h2 class="mb-4">Dépôt d'un nouveau CV</h2>
            <form action="/cv-analysis/web/public/import/upload" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="cv_file" class="form-label">Sélectionnez le fichier PDF :</label>
                    <input type="file" name="cv_file" id="cv_file" class="form-control" accept=".pdf" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Déposer le CV</button>
            </form>
        </div>
    </div>
</body>
</html>