<?php

// 1. Dépendances 
require_once __DIR__ . '/Models/ImportModel.php';
require_once __DIR__ . '/Controllers/ImportController.php';
require_once __DIR__ . '/Controllers/RevueController.php';

use Controllers\ImportController;
use Controllers\RevueController;
use Models\ImportModel;

// 2. Connexion à la base de données
try {
    $dsn = "pgsql:host=127.0.0.1;port=5433;dbname=cv_analysis";
    $pdo = new PDO($dsn, 'cv', 'cv');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 3. Création du contrôleur avec la connexion injectée
$controller = new ImportController($pdo);

// 4. Routage : on analyse l'URL pour savoir quoi appeler
// 4. Routage propre
$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/import/upload') !== false) {
    $importController = new Controllers\ImportController($pdo);
    $importController->upload();
} 
elseif (strpos($uri, '/revue') !== false) {
    $revueController = new Controllers\RevueController($pdo);
    $revueController->index();
} 
else {
    $importController = new Controllers\ImportController($pdo);
    $importController->index();
}