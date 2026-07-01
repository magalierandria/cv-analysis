<?php
// web/public/index.php

header('Content-Type: text/html; charset=utf-8');

// Récupération et nettoyage de l'URL
$request = $_SERVER['REQUEST_URI'];
$basePath = '/cv-analysis/web/public';
$request = str_replace($basePath, '', $request);
$request = rtrim($request, '/');

// Chargement du contrôleur
require_once 'Controllers/ImportController.php';
$controller = new \Controllers\ImportController();

// Système de routage
if ($request === '/import' || $request === '') {
    $controller->index();
} elseif ($request === '/import/upload') {
    $controller->processImport();
} elseif ($request === '/health') {
    http_response_code(200);
    echo "OK";
} else {
    echo "<h1>Page introuvable</h1>";
    echo '<a href="/cv-analysis/web/public/import">Retour au Dépôt</a>';
}