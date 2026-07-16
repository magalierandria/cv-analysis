<?php
/** @var array $profils */
/** @var string $recherche */
/** @var string $statut */
/** @var array $competences */
/** @var array $toutesLesCompetences */
$activeNav = 'revue';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Liste des profils</title>
    <link rel="stylesheet" href="/css/app.css?v=<?= @filemtime(__DIR__ . '/../css/app.css') ?: time() ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="min-h-screen px-4 pb-10 pt-16 lg:pl-64 lg:pt-10">
        <div class="card mb-6">
            <div class="card-content">
                <h1 class="mb-4 text-xl font-bold">Liste des profils</h1>

                <form method="GET" action="/revue" class="grid gap-4 md:grid-cols-12">
                    <div class="md:col-span-5">
                        <label class="label mb-1 block">Recherche</label>
                        <input type="text" name="q" class="input" placeholder="Nom, titre, description, formation, secteur…"
                               value="<?= htmlspecialchars($recherche) ?>">
                    </div>
                    <div class="md:col-span-3">
                        <label class="label mb-1 block">Statut</label>
                        <select name="statut" class="input">
                            <option value="">Tous</option>
                            <option value="à valider" <?= $statut === 'à valider' ? 'selected' : '' ?>>À valider</option>
                            <option value="validé" <?= $statut === 'validé' ? 'selected' : '' ?>>Validé</option>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="label mb-1 block">Compétences</label>
                        <div class="max-h-40 overflow-y-auto rounded-md border border-border p-2">
                            <?php if (empty($toutesLesCompetences)): ?>
                                <span class="text-sm text-muted-foreground">Aucune compétence en base.</span>
                            <?php endif; ?>
                            <?php foreach ($toutesLesCompetences as $competence): ?>
                                <label class="flex items-center gap-2 py-0.5 text-sm" for="comp-<?= htmlspecialchars($competence) ?>">
                                    <input type="checkbox" name="competences[]" id="comp-<?= htmlspecialchars($competence) ?>"
                                           value="<?= htmlspecialchars($competence) ?>"
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                           <?= in_array($competence, $competences, true) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($competence) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="flex gap-2 md:col-span-12">
                        <button type="submit" class="btn-default">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/search.svg" class="h-4 w-4" alt="">
                            Filtrer
                        </button>
                        <a href="/revue" class="btn-outline">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($profils)): ?>
            <div class="card p-6 text-center text-muted-foreground">
                Aucun profil ne correspond à ces critères.
            </div>
        <?php else: ?>
            <div class="flex flex-col gap-3">
                <?php foreach ($profils as $profil): ?>
                    <a href="/revue/<?= $profil['id'] ?>" class="card block p-4 transition-shadow hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-base font-semibold"><?= htmlspecialchars($profil['nom'] ?? 'Sans nom') ?></div>
                                <div class="text-sm text-muted-foreground"><?= htmlspecialchars($profil['titre'] ?? '') ?></div>
                            </div>
                            <span class="<?= $profil['statut'] === 'validé' ? 'badge-success' : 'badge-warning' ?>">
                                <?= htmlspecialchars($profil['statut']) ?>
                            </span>
                        </div>
                        <?php if (!empty($profil['description'])): ?>
                            <p class="mb-2 mt-2 text-sm text-muted-foreground"><?= htmlspecialchars($profil['description']) ?></p>
                        <?php endif; ?>
                        <?php
                            $competencesProfil = [];
                            foreach ($profil['experiences'] as $exp) {
                                foreach ($exp['competences'] as $comp) {
                                    $competencesProfil[$comp] = true;
                                }
                            }
                        ?>
                        <?php if (!empty($competencesProfil)): ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach (array_keys($competencesProfil) as $comp): ?>
                                    <span class="badge-secondary"><?= htmlspecialchars($comp) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
