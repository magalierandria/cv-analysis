<?php
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
use Models\ImportModel;

// 2. Connexion à la base de données
try {
    $dsn = "pgsql:host=127.0.0.1;port=5433;dbname=cv_analysis";
    $pdo = new PDO($dsn, 'cv', 'cv');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// 3. Routage
$uri = $_SERVER['REQUEST_URI'];

if (strpos($uri, '/import/upload') !== false) {
    $importController = new ImportController($pdo);
    $importController->upload();
}
elseif (strpos($uri, '/profil/update') !== false) {
    $revueController = new RevueController($pdo);
    $revueController->updateProfil();
}
elseif (preg_match('/\/experience\/add$/', $uri)) {
    $revueController = new RevueController($pdo);
    $revueController->addExperience();
}
elseif (preg_match('/\/experience\/update\/(\d+)$/', $uri, $matches)) {
    $revueController = new RevueController($pdo);
    $revueController->updateExperience($matches[1]);
}
elseif (preg_match('/\/experience\/delete\/(\d+)$/', $uri, $matches)) {
    $revueController = new RevueController($pdo);
    $revueController->deleteExperience($matches[1]);
}
elseif (preg_match('/\/formation\/add$/', $uri)) {
    $revueController = new RevueController($pdo);
    $revueController->addFormation();
}
elseif (preg_match('/\/formation\/update\/(\d+)$/', $uri, $matches)) {
    $revueController = new RevueController($pdo);
    $revueController->updateFormation($matches[1]);
}
elseif (preg_match('/\/formation\/delete\/(\d+)$/', $uri, $matches)) {
    $revueController = new RevueController($pdo);
    $revueController->deleteFormation($matches[1]);
}
elseif (preg_match('/\/revue\/(\d+)$/', $uri, $matches)) {
    $revueController = new RevueController($pdo);
    $revueController->show($matches[1]);
}
else {
    $importController = new ImportController($pdo);
    $importController->index();
}