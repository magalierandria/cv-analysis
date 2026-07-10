<?php
// On démarre la session ici
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Liste des pages publiques (ici juste le login)
$public_pages = ['/cv-analysis/web/public/login.php'];

// Si l'utilisateur n'est pas connecté et n'est pas sur une page publique, on redirige
if (!isset($_SESSION['utilisateur_id']) && !in_array($_SERVER['REQUEST_URI'], $public_pages)) {
    header('Location: login.php');
    exit;
}