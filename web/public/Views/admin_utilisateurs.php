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

        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card p-4 mb-4">
                    <h3 class="fw-bold mb-4">Créer un compte</h3>

                    <?php if (!empty($erreur)): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($erreur) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="/admin/utilisateurs/create" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Mot de passe</label>
                            <input type="password" name="mot_de_passe" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Rôle</label>
                            <select name="role" class="form-select">
                                <option value="utilisateur">Utilisateur</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Créer</button>
                        </div>
                    </form>
                </div>

                <div class="card p-4">
                    <h5 class="mb-3">Comptes existants</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
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
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Désactivé</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($u['actif']): ?>
                                                <form method="POST" action="/admin/utilisateurs/<?= $u['id'] ?>/desactiver"
                                                      onsubmit="return confirm('Désactiver ce compte ?');">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Désactiver</button>
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
        </div>
    </div>
</body>
</html>
