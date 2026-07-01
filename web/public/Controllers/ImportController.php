<?php
namespace Controllers;

class ImportController {
    // Affiche l'écran de dépôt
    public function index() {
        require_once __DIR__ . '/../Views/import.php';
    }

    // Gère le dépôt du fichier PDF
    public function processImport() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cv_file'])) {
            $file = $_FILES['cv_file'];
            $uploadDir = __DIR__ . '/../../../data/pdfs/';
            
            // S'assurer que le dossier existe
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . basename($file['name']);

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Ici sera déclenché le "Job d'import" prochainement
                echo "<h1>Dépôt réussi</h1>";
                echo "<p>Le fichier " . htmlspecialchars($file['name']) . " est bien stocké.</p>";
                echo '<a href="/cv-analysis/web/public/import">Effectuer un autre dépôt</a>';
            } else {
                echo "<h1>Erreur</h1><p>Échec lors du dépôt du fichier.</p>";
            }
        } else {
            echo "<h1>Erreur</h1><p>Aucun fichier reçu.</p>";
        }
    }
}