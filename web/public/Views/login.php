<?php
/** @var string|null $erreur */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --novity-noir: #0A0A0A; --novity-vert: #16A34A; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; }
        .login-split { display: flex; min-height: 100vh; }
        .login-image {
            flex: 1;
            background-image: linear-gradient(rgba(10, 10, 10, 0.55), rgba(10, 10, 10, 0.55)),
                url('/images/novity-equipe.webp');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: flex-end;
            padding: 40px;
        }
        .login-image h2 { color: #fff; font-weight: 700; }
        .login-form-side {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: 40px;
        }
        .login-card { width: 100%; max-width: 380px; }
        .btn-novity { background-color: var(--novity-vert); border-color: var(--novity-vert); }
        .btn-novity:hover { background-color: #128a3e; border-color: #128a3e; }
        .form-control:focus { border-color: var(--novity-vert); box-shadow: 0 0 0 0.2rem rgba(22, 163, 74, 0.25); }
        @media (max-width: 768px) { .login-image { display: none; } }
    </style>
</head>
<body>
    <div class="login-split">
        <div class="login-image">
            <h2>Novity CV Analyser</h2>
        </div>
        <div class="login-form-side">
            <div class="login-card">
                <img src="/images/novity-logo-noir.svg" alt="Novity" class="d-block mx-auto mb-4" height="24">
                <h3 class="fw-bold mb-4" style="color: var(--novity-noir);">Connexion</h3>

                <?php if (!empty($erreur)): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>

                <form method="POST" action="/login">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="mot_de_passe" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-novity w-100 text-white">Se connecter</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
