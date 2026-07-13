<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | Novity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f1f5f9; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-card { 
            width: 100%; 
            max-width: 400px; 
            padding: 40px; 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); 
        }
        .brand-title { 
            font-weight: 800; 
            color: #0f172a; 
            margin-bottom: 2rem; 
            text-align: center;
            letter-spacing: -1px;
        }
        .form-control { 
            padding: 12px; 
            border-radius: 10px; 
            border: 1px solid #e2e8f0;
        }
        .form-control:focus { 
            border-color: #2563eb; 
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); 
        }
        .btn-primary { 
            background-color: #2563eb; 
            border: none; 
            padding: 14px; 
            font-weight: 600; 
            border-radius: 10px;
            transition: transform 0.1s, background-color 0.2s;
        }
        .btn-primary:hover { background-color: #1d4ed8; }
        .btn-primary:active { transform: scale(0.98); }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <i class="bi bi-shield-lock" style="font-size: 2.5rem; color: #2563eb;"></i>
    </div>
    <h2 class="brand-title">Connexion Novity</h2>
    
    <form action="/cv-analysis/web/public/login/submit" method="POST">
        <div class="mb-3">
            <label class="form-label text-muted small">Adresse Email</label>
            <input type="email" name="email" class="form-control" placeholder="nom@entreprise.com" required>
        </div>
        <div class="mb-4">
            <label class="form-label text-muted small">Mot de passe</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-box-arrow-in-right me-2"></i> Se connecter
        </button>
    </form>
</div>

</body>
</html>