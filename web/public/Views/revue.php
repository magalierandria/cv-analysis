<?php
/** @var array|null $profil */
$activeNav = 'revue';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revue de Profil - Novity</title>
    <link rel="stylesheet" href="/css/app.css?v=<?= @filemtime(__DIR__ . '/../css/app.css') ?: time() ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="flex h-screen gap-4 p-4 pt-16 lg:pl-64 lg:pt-4">
    <div class="flex-[2] overflow-hidden rounded-lg border border-border bg-muted">
        <?php if (!empty($profil['pdf_source'])): ?>
            <iframe src="/pdfs/<?= (int) $profil['id'] ?>" width="100%" height="100%" class="border-0"></iframe>
        <?php else: ?>
            <div class="flex h-full items-center justify-center text-muted-foreground">Aucun PDF disponible</div>
        <?php endif; ?>
    </div>

    <div class="card flex-1 overflow-y-auto p-6">
        <h1 class="mb-4 text-xl font-bold">Validation du profil</h1>

        <?php if (isset($profil)): ?>
            <form action="/profil/update" method="POST" class="space-y-3">
                <input type="hidden" name="id" value="<?= htmlspecialchars($profil['id'] ?? '') ?>">

                <div>
                    <label class="label mb-1 block">Nom complet</label>
                    <input type="text" name="nom" class="input" value="<?= htmlspecialchars($profil['nom'] ?? '') ?>">
                </div>

                <div>
                    <label class="label mb-1 block">Titre</label>
                    <input type="text" name="titre" class="input" value="<?= htmlspecialchars($profil['titre'] ?? '') ?>">
                </div>

                <div>
                    <label class="label mb-1 block">Email</label>
                    <input type="text" class="input bg-muted" value="<?= htmlspecialchars($profil['email'] ?? '—') ?>" readonly>
                </div>

                <div>
                    <label class="label mb-1 block">LinkedIn</label>
                    <input type="text" class="input bg-muted" value="<?= htmlspecialchars($profil['linkedin'] ?? '—') ?>" readonly>
                </div>

                <div>
                    <label class="label mb-1 block">Description</label>
                    <textarea class="input bg-muted" rows="3" readonly><?= htmlspecialchars($profil['description'] ?? '—') ?></textarea>
                </div>

                <div>
                    <label class="label mb-1 block">Statut</label>
                    <select name="statut" class="input">
                        <option value="à valider" <?= ($profil['statut'] ?? '') == 'à valider' ? 'selected' : '' ?>>À valider</option>
                        <option value="validé" <?= ($profil['statut'] ?? '') == 'validé' ? 'selected' : '' ?>>Validé</option>
                    </select>
                </div>

                <button type="submit" class="btn-default w-full">Enregistrer et Valider</button>
            </form>

            <!-- ===================== EXPERIENCES ===================== -->
            <div class="mt-6" x-data="{ addOpen: false }">
                <div class="flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-primary">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/briefcase.svg" class="h-4 w-4" alt="">
                        Expériences
                    </h2>
                    <button class="btn-outline btn-sm" type="button" @click="addOpen = !addOpen">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/plus.svg" class="h-4 w-4" alt="">
                        Ajouter
                    </button>
                </div>

                <div x-show="addOpen" x-cloak class="mt-3 rounded-md border border-dashed border-border bg-muted p-4">
                    <form action="/experience/add" method="POST" class="space-y-2">
                        <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                        <div>
                            <label class="label mb-1 block text-xs">Poste</label>
                            <input type="text" name="poste" class="input">
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Entreprise</label>
                            <input type="text" name="entreprise" class="input">
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Secteur</label>
                            <input type="text" name="secteur" class="input">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="label mb-1 block text-xs">Début</label>
                                <input type="text" name="date_debut" class="input" placeholder="ex. 2019 ou 2019-03">
                            </div>
                            <div>
                                <label class="label mb-1 block text-xs">Fin</label>
                                <input type="text" name="date_fin" class="input" placeholder="ex. 2022 ou en cours">
                            </div>
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Description</label>
                            <textarea name="description" class="input" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn-default btn-sm w-full">Ajouter l'expérience</button>
                    </form>
                </div>
            </div>

            <div class="mt-3 space-y-3">
                <?php if (!empty($profil['experiences'])): ?>
                    <?php foreach ($profil['experiences'] as $exp): ?>
                        <div class="relative rounded-md border border-border bg-muted p-3" x-data="{ editOpen: false }">
                            <div class="absolute right-2 top-2 flex gap-1">
                                <button class="btn-outline btn-sm" type="button" @click="editOpen = !editOpen">
                                    <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/pencil.svg" class="h-4 w-4" alt="">
                                </button>
                                <form action="/experience/delete/<?= $exp['id'] ?>" method="POST" onsubmit="return confirm('Supprimer cette expérience ?');">
                                    <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                    <button class="btn-outline btn-sm" type="submit">
                                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/trash-2.svg" class="h-4 w-4" alt="">
                                    </button>
                                </form>
                            </div>

                            <div class="font-semibold"><?= htmlspecialchars($exp['poste'] ?? '') ?></div>
                            <div class="text-sm text-muted-foreground">
                                <?= htmlspecialchars($exp['entreprise'] ?? '') ?>
                                <?php if (!empty($exp['secteur'])): ?> — <?= htmlspecialchars($exp['secteur']) ?><?php endif; ?>
                            </div>
                            <div class="mb-2 text-sm text-muted-foreground">
                                <?= $exp['date_debut'] ? htmlspecialchars($exp['date_debut']) : '?' ?>
                                —
                                <?= $exp['date_fin'] ? htmlspecialchars($exp['date_fin']) : 'Présent' ?>
                            </div>
                            <?php if (!empty($exp['description'])): ?>
                                <p class="mb-2 text-sm"><?= nl2br(htmlspecialchars($exp['description'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($exp['competences'])): ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($exp['competences'] as $comp): ?>
                                        <span class="badge-secondary"><?= htmlspecialchars($comp) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div x-show="editOpen" x-cloak class="mt-3 rounded-md border border-dashed border-border bg-background p-4">
                                <form action="/experience/update/<?= $exp['id'] ?>" method="POST" class="space-y-2">
                                    <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                    <div>
                                        <label class="label mb-1 block text-xs">Poste</label>
                                        <input type="text" name="poste" class="input" value="<?= htmlspecialchars($exp['poste'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Entreprise</label>
                                        <input type="text" name="entreprise" class="input" value="<?= htmlspecialchars($exp['entreprise'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Secteur</label>
                                        <input type="text" name="secteur" class="input" value="<?= htmlspecialchars($exp['secteur'] ?? '') ?>">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="label mb-1 block text-xs">Début</label>
                                            <input type="text" name="date_debut" class="input" placeholder="ex. 2019 ou 2019-03" value="<?= htmlspecialchars($exp['date_debut'] ?? '') ?>">
                                        </div>
                                        <div>
                                            <label class="label mb-1 block text-xs">Fin</label>
                                            <input type="text" name="date_fin" class="input" placeholder="ex. 2022 ou en cours" value="<?= htmlspecialchars($exp['date_fin'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Description</label>
                                        <textarea name="description" class="input" rows="2"><?= htmlspecialchars($exp['description'] ?? '') ?></textarea>
                                    </div>
                                    <button type="submit" class="btn-default btn-sm w-full">Enregistrer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-muted-foreground">Aucune expérience renseignée.</p>
                <?php endif; ?>
            </div>

            <!-- ===================== FORMATIONS ===================== -->
            <div class="mt-6" x-data="{ addOpen: false }">
                <div class="flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-primary">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/graduation-cap.svg" class="h-4 w-4" alt="">
                        Formations
                    </h2>
                    <button class="btn-outline btn-sm" type="button" @click="addOpen = !addOpen">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/plus.svg" class="h-4 w-4" alt="">
                        Ajouter
                    </button>
                </div>

                <div x-show="addOpen" x-cloak class="mt-3 rounded-md border border-dashed border-border bg-muted p-4">
                    <form action="/formation/add" method="POST" class="space-y-2">
                        <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                        <div>
                            <label class="label mb-1 block text-xs">Intitulé</label>
                            <input type="text" name="intitule" class="input">
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Établissement</label>
                            <input type="text" name="etablissement" class="input">
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Type</label>
                            <select name="type" class="input">
                                <option value="">—</option>
                                <option value="diplôme">Diplôme</option>
                                <option value="certification">Certification</option>
                            </select>
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Année</label>
                            <input type="number" name="annee" class="input">
                        </div>
                        <button type="submit" class="btn-default btn-sm w-full">Ajouter la formation</button>
                    </form>
                </div>
            </div>

            <div class="mt-3 space-y-3">
                <?php if (!empty($profil['formations'])): ?>
                    <?php foreach ($profil['formations'] as $form): ?>
                        <div class="relative rounded-md border border-border bg-muted p-3" x-data="{ editOpen: false }">
                            <div class="absolute right-2 top-2 flex gap-1">
                                <button class="btn-outline btn-sm" type="button" @click="editOpen = !editOpen">
                                    <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/pencil.svg" class="h-4 w-4" alt="">
                                </button>
                                <form action="/formation/delete/<?= $form['id'] ?>" method="POST" onsubmit="return confirm('Supprimer cette formation ?');">
                                    <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                    <button class="btn-outline btn-sm" type="submit">
                                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/trash-2.svg" class="h-4 w-4" alt="">
                                    </button>
                                </form>
                            </div>

                            <div class="font-semibold"><?= htmlspecialchars($form['intitule'] ?? '') ?></div>
                            <div class="text-sm text-muted-foreground">
                                <?= htmlspecialchars($form['etablissement'] ?? '') ?>
                                <?php if (!empty($form['type'])): ?> — <?= htmlspecialchars($form['type']) ?><?php endif; ?>
                                <?php if (!empty($form['annee'])): ?> (<?= htmlspecialchars($form['annee']) ?>)<?php endif; ?>
                            </div>

                            <div x-show="editOpen" x-cloak class="mt-3 rounded-md border border-dashed border-border bg-background p-4">
                                <form action="/formation/update/<?= $form['id'] ?>" method="POST" class="space-y-2">
                                    <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                    <div>
                                        <label class="label mb-1 block text-xs">Intitulé</label>
                                        <input type="text" name="intitule" class="input" value="<?= htmlspecialchars($form['intitule'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Établissement</label>
                                        <input type="text" name="etablissement" class="input" value="<?= htmlspecialchars($form['etablissement'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Type</label>
                                        <select name="type" class="input">
                                            <option value="">—</option>
                                            <option value="diplôme" <?= ($form['type'] ?? '') == 'diplôme' ? 'selected' : '' ?>>Diplôme</option>
                                            <option value="certification" <?= ($form['type'] ?? '') == 'certification' ? 'selected' : '' ?>>Certification</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Année</label>
                                        <input type="number" name="annee" class="input" value="<?= htmlspecialchars($form['annee'] ?? '') ?>">
                                    </div>
                                    <button type="submit" class="btn-default btn-sm w-full">Enregistrer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-muted-foreground">Aucune formation renseignée.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <p class="text-destructive">Profil introuvable.</p>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
