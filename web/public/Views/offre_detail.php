<?php
/** @var array $offre */
/** @var array $matchings */
$activeNav = 'offres';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | <?= htmlspecialchars($offre['titre']) ?></title>
    <link rel="stylesheet" href="/css/app.css?v=<?= @filemtime(__DIR__ . '/../css/app.css') ?: time() ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="min-h-screen px-4 pb-10 pt-16 lg:pl-72 lg:pt-10">
        <div class="card mb-6">
            <div class="card-content">
                <h1 class="text-xl font-bold"><?= htmlspecialchars($offre['titre']) ?></h1>
                <?php if (!empty($offre['description'])): ?>
                    <p class="mt-2 text-sm text-muted-foreground"><?= htmlspecialchars($offre['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($offre['competences'])): ?>
                    <div class="mt-3 flex flex-wrap gap-1">
                        <?php foreach ($offre['competences'] as $comp): ?>
                            <span class="badge-success"><?= htmlspecialchars($comp) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="mb-3 text-lg font-semibold">Candidats classés par correspondance</h2>
        <?php if (empty($matchings)): ?>
            <div class="card p-6 text-center text-muted-foreground">Aucun profil à comparer.</div>
        <?php else: ?>
            <div class="flex flex-col gap-3">
                <?php foreach ($matchings as $m): ?>
                    <?php
                        $profil = $m['profil'];
                        $score = $m['score'];
                        $ringColor = $score >= 75 ? 'text-success' : ($score >= 40 ? 'text-warning' : 'text-muted-foreground');
                        $rayon = 16;
                        $circonference = 2 * M_PI * $rayon;
                        $dashoffset = $circonference * (1 - $score / 100);
                    ?>
                    <a href="/revue/<?= $profil['id'] ?>" class="card flex items-center gap-4 p-4 transition-shadow hover:shadow-md">
                        <div class="relative h-12 w-12 shrink-0">
                            <svg viewBox="0 0 36 36" class="h-12 w-12 -rotate-90">
                                <circle cx="18" cy="18" r="<?= $rayon ?>" fill="none" stroke="currentColor" stroke-width="3" class="text-muted"></circle>
                                <circle cx="18" cy="18" r="<?= $rayon ?>" fill="none" stroke="currentColor" stroke-width="3"
                                        stroke-dasharray="<?= round($circonference, 2) ?>"
                                        stroke-dashoffset="<?= round($dashoffset, 2) ?>"
                                        stroke-linecap="round"
                                        class="<?= $ringColor ?>"></circle>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold"><?= $score ?>%</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <div class="text-base font-semibold"><?= htmlspecialchars($profil['nom'] ?? 'Sans nom') ?></div>
                                <span class="<?= $profil['statut'] === 'validé' ? 'badge-success' : 'badge-warning' ?>">
                                    <?= htmlspecialchars($profil['statut']) ?>
                                </span>
                            </div>
                            <div class="text-sm text-muted-foreground"><?= htmlspecialchars($profil['titre'] ?? '') ?></div>
                            <div class="mt-2 flex flex-wrap gap-1">
                                <?php foreach ($m['matched'] as $comp): ?>
                                    <span class="badge-success"><?= htmlspecialchars($comp) ?></span>
                                <?php endforeach; ?>
                                <?php foreach ($m['missing'] as $comp): ?>
                                    <span class="badge-secondary"><?= htmlspecialchars($comp) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
