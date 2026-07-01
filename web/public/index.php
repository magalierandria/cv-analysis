<?php
// On cherche le contrôleur directement dans le dossier courant
$controllerPath = __DIR__ . '/Controllers/ImportController.php';

if (file_exists($controllerPath)) {
    require_once $controllerPath;
} else {
    die("ERREUR : Impossible de trouver le fichier à : " . $controllerPath);
}

// Connexion DB pour l'historique)
$pdo = null;
try {
    
    $pdo = new PDO('pgsql:host=localhost;dbname=novity_db', 'ton_user', 'ton_pass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // En cas d'erreur DB, le site s'affichera quand même
}

// Instanciation (le namespace est Controllers\)
$controller = new \Controllers\ImportController($pdo);

// Routage
$action = $_GET['action'] ?? 'index';
if ($action === 'upload') {
    $controller->processImport();
} else {
    $controller->index();
}