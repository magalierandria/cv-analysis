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
require_once __DIR__ . '/Models/UtilisateurModel.php';
require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Controllers/AdminController.php';

use Controllers\ImportController;
use Controllers\RevueController;
use Controllers\AuthController;
use Controllers\AdminController;

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

// Garde d'authentification : tout sauf /login exige une session active
if (!preg_match('#^/login/?$#', $uri) && !isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Déclaration de la variable pour stocker les résultats des expressions régulières
$matches = [];

if (preg_match('/^\/login\/?$/', $uri) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new AuthController($pdo))->login();
}
elseif (preg_match('/^\/login\/?$/', $uri)) {
    (new AuthController($pdo))->showLogin();
}
elseif (preg_match('/^\/logout\/?$/', $uri) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new AuthController($pdo))->logout();
}
elseif (strpos($uri, '/import/upload') !== false) {
    (new ImportController($pdo))->upload();
}
elseif (preg_match('/\/import\/status\/(\d+)$/', $uri, $matches)) {
    (new ImportController($pdo))->status($matches[1]);
}
elseif (preg_match('/\/import\/job\/(\d+)\/status$/', $uri, $matches)) {
    (new ImportController($pdo))->jobStatusJson($matches[1]);
}
elseif (preg_match('/\/import\/delete-all$/', $uri) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new ImportController($pdo))->deleteAllJobs();
}
elseif (preg_match('/\/import\/delete\/(\d+)$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new ImportController($pdo))->deleteJob($matches[1]);
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
elseif (preg_match('/\/profil\/delete\/(\d+)$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new RevueController($pdo))->deleteProfil($matches[1]);
}
elseif (preg_match('/\/revue\/?$/', $uri)) {
    (new RevueController($pdo))->index();
}
elseif (preg_match('/\/revue\/(\d+)$/', $uri, $matches)) {
    (new RevueController($pdo))->show($matches[1]);
}
elseif (preg_match('/^\/pdfs\/(\d+)$/', $uri, $matches)) {
    (new RevueController($pdo))->servirPdf($matches[1]);
}
elseif (preg_match('/^\/admin\/utilisateurs\/create$/', $uri) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new AdminController($pdo))->creerUtilisateur();
}
elseif (preg_match('/^\/admin\/utilisateurs\/(\d+)\/desactiver$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new AdminController($pdo))->desactiverUtilisateur($matches[1]);
}
elseif (preg_match('/^\/admin\/utilisateurs\/(\d+)\/modifier$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new AdminController($pdo))->modifierUtilisateur($matches[1]);
}
elseif (preg_match('/^\/admin\/utilisateurs\/?$/', $uri)) {
    (new AdminController($pdo))->listerUtilisateurs();
}
else {
    (new ImportController($pdo))->index();
}