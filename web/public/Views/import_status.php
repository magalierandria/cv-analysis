<?php
/** @var int $jobId */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Import en cours</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card p-4 text-center">
                    <div id="etat-en-cours">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <h5>Analyse du CV en cours…</h5>
                        <p class="text-muted">Cette page se met à jour automatiquement.</p>
                    </div>
                    <div id="etat-echoue" class="d-none">
                        <h5 class="text-danger">Échec de l'analyse</h5>
                        <p id="message-erreur" class="text-muted"></p>
                        <a href="/" class="btn btn-primary">Retour à l'import</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const jobId = <?= json_encode($jobId) ?>;

        function verifierStatut() {
            fetch('/import/job/' + jobId + '/status')
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.statut === 'réussi') {
                        window.location = '/revue/' + data.profile_id;
                    } else if (data.statut === 'échoué') {
                        document.getElementById('etat-en-cours').classList.add('d-none');
                        document.getElementById('etat-echoue').classList.remove('d-none');
                        document.getElementById('message-erreur').textContent = data.error_message || 'Erreur inconnue.';
                    } else {
                        setTimeout(verifierStatut, 2000);
                    }
                })
                .catch(function () { setTimeout(verifierStatut, 2000); });
        }

        verifierStatut();
    </script>
</body>
</html>
