<?php
/** @var array $utilisateurs */
/** @var string|null $erreur */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Administration des comptes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #2563eb; --bg: #f8fafc; }
        body { background-color: var(--bg); font-family: 'Inter', system-ui, sans-serif; }
        .navbar-custom { background: white; border-radius: 12px; padding: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .nav-pills .nav-link { color: #64748b; font-weight: 500; }
        .nav-pills .nav-link.active { background-color: var(--primary); }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center mb-4">
            <div class="col-md-10">
                <nav class="navbar-custom d-flex justify-content-between align-items-center px-2">
                    <div class="nav nav-pills nav-fill flex-grow-1 me-2">
                        <a class="nav-link" href="/"><i class="bi bi-upload"></i> Import</a>
                        <a class="nav-link" href="/revue"><i class="bi bi-list-check"></i> Revue</a>
                        <a class="nav-link active" href="/admin/utilisateurs"><i class="bi bi-people"></i> Admin</a>
                    </div>
                    <form method="POST" action="/logout" class="m-0">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Déconnexion</button>
                    </form>
                </nav>
            </div>
        </div>

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
