<?php
// 1. Démarrage et Autoloader
session_start();

spl_autoload_register(function ($class) {
    // Correction du chemin : le dossier racine est 'web/public'
    // Les classes sont dans 'web/public/Controllers/' ou 'web/public/Models/'
    $path = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

use Controllers\ImportController;
use Controllers\RevueController;
use Controllers\AuthController;

// 2. Connexion BDD
try {
    $dsn = "pgsql:host=127.0.0.1;port=5433;dbname=cv_analysis";
    $pdo = new PDO($dsn, 'cv', 'cv');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 3. Routage
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/cv-analysis/web/public', '', $uri);
$uri = trim($uri, '/');

// Middleware de sécurité
$publicRoutes = ['login', 'login/submit'];
if (!isset($_SESSION['utilisateur_id']) && !in_array($uri, $publicRoutes)) {
    header('Location: /cv-analysis/web/public/login');
    exit;
}

// Routage principal
$matches = [];
if ($uri === 'login') {
    (new AuthController($pdo))->showLogin();
} elseif ($uri === 'login/submit') {
    (new AuthController($pdo))->login();
} elseif ($uri === 'logout') {
    (new AuthController($pdo))->logout();
} elseif ($uri === 'import/upload') {
    (new ImportController($pdo))->upload();
} elseif (preg_match('/^import\/status\/(\d+)$/', $uri, $matches)) {
    (new ImportController($pdo))->status($matches[1]);
} elseif (preg_match('/^revue\/(\d+)$/', $uri, $matches)) {
    (new RevueController($pdo))->show($matches[1]);
} elseif ($uri === 'revue') {
    (new RevueController($pdo))->index();
} else {
    // Par défaut, redirige vers l'import (index)
    (new ImportController($pdo))->index();
}