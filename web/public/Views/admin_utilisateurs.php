<?php
/** @var array $utilisateurs */
/** @var string|null $erreur */
$activeNav = 'admin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Administration des comptes</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-4xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>

        <div class="card mb-6">
            <div class="card-content">
                <h1 class="mb-4 text-xl font-bold">Créer un compte</h1>

                <?php if (!empty($erreur)): ?>
                    <div class="alert-destructive mb-4"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>

                <form method="POST" action="/admin/utilisateurs/create" class="grid gap-4 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <label class="label mb-1 block">Email</label>
                        <input type="email" name="email" class="input" required>
                    </div>
                    <div class="md:col-span-4">
                        <label class="label mb-1 block">Mot de passe</label>
                        <input type="password" name="mot_de_passe" class="input" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="label mb-1 block">Rôle</label>
                        <select name="role" class="input">
                            <option value="utilisateur">Utilisateur</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="flex items-end md:col-span-2">
                        <button type="submit" class="btn-default w-full">Créer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-content overflow-x-auto">
                <h2 class="mb-3 text-base font-semibold">Comptes existants</h2>
                <table class="table-shadcn">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['role']) ?></td>
                                <td>
                                    <?php if ($u['actif']): ?>
                                        <span class="badge-default">Actif</span>
                                    <?php else: ?>
                                        <span class="badge-secondary">Désactivé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['actif']): ?>
                                        <form method="POST" action="/admin/utilisateurs/<?= $u['id'] ?>/desactiver"
                                              onsubmit="return confirm('Désactiver ce compte ?');">
                                            <button type="submit" class="btn-outline btn-sm">Désactiver</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
