<?php
/** @var array $offres */
$activeNav = 'offres';
$estAdmin = ($_SESSION['role'] ?? null) === 'admin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Offres</title>
    <link rel="stylesheet" href="/css/app.css?v=<?= @filemtime(__DIR__ . '/../css/app.css') ?: time() ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="min-h-screen px-4 pb-10 pt-16 lg:pl-72 lg:pt-10">
        <?php if ($estAdmin): ?>
            <div class="card mb-6">
                <div class="card-content">
                    <h1 class="mb-4 text-xl font-bold">Créer une offre</h1>
                    <form method="POST" action="/offres/create" class="grid gap-4 md:grid-cols-12">
                        <div class="md:col-span-4">
                            <label class="label mb-1 block">Titre</label>
                            <input type="text" name="titre" class="input" required>
                        </div>
                        <div class="md:col-span-8">
                            <label class="label mb-1 block">Description</label>
                            <input type="text" name="description" class="input">
                        </div>
                        <div class="md:col-span-12">
                            <label class="label mb-1 block">Compétences requises</label>
                            <input type="text" name="competences" class="input" placeholder="React, TypeScript, GraphQL">
                            <p class="mt-1 text-xs text-muted-foreground">Séparez les compétences par des virgules.</p>
                        </div>
                        <div class="md:col-span-12">
                            <button type="submit" class="btn-default">Créer l'offre</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($offres)): ?>
            <div class="card p-6 text-center text-muted-foreground">Aucune offre pour le moment.</div>
        <?php else: ?>
            <div class="flex flex-col gap-3">
                <?php foreach ($offres as $offre): ?>
                    <div class="card relative p-4 transition-shadow hover:shadow-md" x-data="{ editOpen: false }">
                        <a href="/offres/<?= $offre['id'] ?>" class="absolute inset-0" aria-label="Voir l'offre <?= htmlspecialchars($offre['titre']) ?>"></a>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-base font-semibold"><?= htmlspecialchars($offre['titre']) ?></div>
                                <?php if (!empty($offre['description'])): ?>
                                    <div class="text-sm text-muted-foreground"><?= htmlspecialchars($offre['description']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($offre['competences'])): ?>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <?php foreach ($offre['competences'] as $comp): ?>
                                            <span class="badge-secondary"><?= htmlspecialchars($comp) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($estAdmin): ?>
                                <div class="relative z-10 flex shrink-0 items-center gap-2">
                                    <button type="button" class="btn-outline btn-sm" @click="editOpen = !editOpen">Modifier</button>
                                    <form method="POST" action="/offres/<?= $offre['id'] ?>/delete" onsubmit="return confirm('Supprimer cette offre ?');">
                                        <button type="submit" class="btn-outline btn-sm" aria-label="Supprimer l'offre">
                                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/trash-2.svg" class="h-4 w-4" alt="">
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($estAdmin): ?>
                            <div class="relative z-10 mt-3" x-show="editOpen" x-cloak>
                                <form method="POST" action="/offres/<?= $offre['id'] ?>/modifier" class="flex flex-wrap items-end gap-3 rounded-md border border-dashed border-border bg-muted p-4">
                                    <div>
                                        <label class="label mb-1 block text-xs">Titre</label>
                                        <input type="text" name="titre" class="input" value="<?= htmlspecialchars($offre['titre']) ?>" required>
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Description</label>
                                        <input type="text" name="description" class="input" value="<?= htmlspecialchars($offre['description'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Compétences requises</label>
                                        <input type="text" name="competences" class="input" value="<?= htmlspecialchars(implode(', ', $offre['competences'])) ?>">
                                    </div>
                                    <button type="submit" class="btn-default btn-sm">Enregistrer</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
