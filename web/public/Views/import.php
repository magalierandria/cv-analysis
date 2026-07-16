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
    <link rel="stylesheet" href="/css/app.css?v=<?= @filemtime(__DIR__ . '/../css/app.css') ?: time() ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="min-h-screen px-4 pb-10 pt-16 lg:pl-64 lg:pt-10">
        <div class="card mb-6">
            <div class="card-header text-center">
                <h1 class="card-title text-2xl">Novity CV Analyser</h1>
                <p class="text-sm text-muted-foreground">Analyse intelligente de candidatures</p>
            </div>
            <div class="card-content pt-0">
                <form id="upload-form" action="/import/upload" method="POST" enctype="multipart/form-data">
                    <div id="drop-zone-container">
                        <label for="cv_file" class="flex w-full cursor-pointer flex-col items-center rounded-lg border-2 border-dashed border-border bg-background px-6 py-10 text-center transition-colors hover:border-primary hover:bg-muted">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/upload-cloud.svg" class="mb-3 h-8 w-8" alt="">
                            <p class="text-sm">Glissez votre CV ou <b>parcourez</b></p>
                        </label>
                        <input type="file" name="cv_file" id="cv_file" class="hidden" accept=".pdf" onchange="showFile(this)">
                    </div>

                    <div id="file-preview" class="mb-3 mt-3 hidden items-center justify-between rounded-lg border border-border bg-muted p-3">
                        <div class="flex items-center gap-3">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/file-text.svg" class="h-6 w-6" alt="">
                            <div>
                                <div id="file-name" class="text-sm font-medium">...</div>
                                <small id="file-size" class="text-muted-foreground"></small>
                            </div>
                        </div>
                        <button type="button" class="btn-outline btn-sm" onclick="resetFile()">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/x.svg" class="h-4 w-4" alt="">
                        </button>
                    </div>

                    <button type="submit" id="submit-btn" class="btn-default mt-3 hidden w-full">
                        <span id="submit-btn-icon" class="inline-flex items-center gap-2">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/cpu.svg" class="h-4 w-4" alt="">
                            Lancer l'analyse
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2 text-base">
                    <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/history.svg" class="h-4 w-4" alt="">
                    Historique des analyses
                </h2>
            </div>
            <div class="card-content overflow-x-auto pt-0">
                <table class="table-shadcn">
                    <thead>
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
                                            <a href="/revue/<?= $item['profile_id'] ?>" class="font-medium text-primary hover:underline">
                                                <?= htmlspecialchars($item['candidat_nom'] ?? 'En attente d’analyse') ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($item['candidat_nom'] ?? 'En attente d’analyse') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['profile_id'])): ?>
                                            <a href="/revue/<?= $item['profile_id'] ?>" class="text-primary hover:underline">
                                                <?= htmlspecialchars($item['nom_fichier']) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($item['nom_fichier'] ?? '—') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($item['cree_le'])) ?></td>
                                    <td>
                                        <span class="<?= match ($item['statut']) {
                                            'réussi' => 'badge-success',
                                            'échoué' => 'badge-destructive',
                                            default => 'badge-warning',
                                        } ?>">
                                            <?= htmlspecialchars($item['statut']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted-foreground">Aucun CV importé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        function showFile(input) {
            if (input.files && input.files[0]) {
                document.getElementById('file-name').textContent = input.files[0].name;
                document.getElementById('file-size').textContent = (input.files[0].size / 1024 / 1024).toFixed(2) + ' MB';
                document.getElementById('drop-zone-container').classList.add('hidden');
                document.getElementById('file-preview').classList.remove('hidden');
                document.getElementById('file-preview').classList.add('flex');
                document.getElementById('submit-btn').classList.remove('hidden');
            }
        }
        function resetFile() {
            document.getElementById('cv_file').value = '';
            document.getElementById('drop-zone-container').classList.remove('hidden');
            document.getElementById('file-preview').classList.add('hidden');
            document.getElementById('file-preview').classList.remove('flex');
            document.getElementById('submit-btn').classList.add('hidden');
        }

        document.getElementById('upload-form').addEventListener('submit', function () {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            document.getElementById('submit-btn-icon').innerHTML =
                '<span class="h-4 w-4 animate-spin rounded-full border-2 border-primary-foreground border-t-transparent"></span> Envoi en cours…';
        });
    </script>
</body>
</html>
