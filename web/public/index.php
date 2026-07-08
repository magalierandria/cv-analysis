<?php
// Démarrage de la session pour les messages de retour (UX important)
session_start();

spl_autoload_register(function ($class) {
    $path = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

// 1. Dépendances 
require_once __DIR__ . '/Models/ImportModel.php';
require_once __DIR__ . '/Controllers/RevueController.php';
require_once __DIR__ . '/Models/ProfilModel.php';
require_once __DIR__ . '/Controllers/ImportController.php';

use Controllers\ImportController;
use Controllers\RevueController;

// 2. Connexion à la base de données
try {
    $databaseUrl = getenv('DATABASE_URL');
    if ($databaseUrl) {
        $parts = parse_url($databaseUrl);
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim($parts['path'], '/')
        );
        $user = $parts['user'] ?? 'cv';
        $pass = $parts['pass'] ?? 'cv';
    } else {
        $dsn = "pgsql:host=127.0.0.1;port=5433;dbname=cv_analysis";
        $user = 'cv';
        $pass = 'cv';
    }
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 3. Routage
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/cv-analysis/web/public', '', $uri);

// Déclaration de la variable pour stocker les résultats des expressions régulières
$matches = []; 

if (strpos($uri, '/import/upload') !== false) {
    (new ImportController($pdo))->upload();
}
elseif (preg_match('/\/import\/status\/(\d+)$/', $uri, $matches)) {
    (new ImportController($pdo))->status($matches[1]);
}
elseif (preg_match('/\/import\/job\/(\d+)\/status$/', $uri, $matches)) {
    (new ImportController($pdo))->jobStatusJson($matches[1]);
}
elseif (strpos($uri, '/profil/update') !== false) {
    (new RevueController($pdo))->updateProfil();
}
// Correction : utilisation de $matches pour toutes les expressions régulières
elseif (preg_match('/\/experience\/add$/', $uri)) {
    (new RevueController($pdo))->addExperience();
}
elseif (preg_match('/\/experience\/update\/(\d+)$/', $uri, $matches)) {
    (new RevueController($pdo))->updateExperience($matches[1]);
}
elseif (preg_match('/\/experience\/delete\/(\d+)$/', $uri, $matches)) {
    (new RevueController($pdo))->deleteExperience($matches[1]);
}
elseif (preg_match('/\/formation\/add$/', $uri)) {
    (new RevueController($pdo))->addFormation();
}
elseif (preg_match('/\/formation\/update\/(\d+)$/', $uri, $matches)) {
    (new RevueController($pdo))->updateFormation($matches[1]);
}
elseif (preg_match('/\/formation\/delete\/(\d+)$/', $uri, $matches)) {
    (new RevueController($pdo))->deleteFormation($matches[1]);
}
elseif (preg_match('/\/revue\/?$/', $uri)) {
    (new RevueController($pdo))->index();
}
elseif (preg_match('/\/revue\/(\d+)$/', $uri, $matches)) {
    (new RevueController($pdo))->show($matches[1]);
}
else {
    (new ImportController($pdo))->index();
}