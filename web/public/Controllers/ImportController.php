<?php
namespace Controllers;

use PDO;

class ImportController {
    private $db;

    // On rend $db optionnel pour éviter l'erreur de constructeur
    public function __construct($db = null) {
        $this->db = $db;
    }

    public function index() {
        $historique = [];
        
        // On vérifie que la connexion existe avant de requêter
        if ($this->db instanceof PDO) {
            $stmt = $this->db->query("SELECT * FROM cv_analyses ORDER BY date_import DESC LIMIT 10");
            $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        require_once __DIR__ . '/../Views/import.php';
    }

    public function processImport() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cv_file'])) {
            $file = $_FILES['cv_file'];
            $uploadDir = __DIR__ . '/../../../data/pdfs/';
            
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $fileName = basename($file['name']);
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                if ($this->db instanceof PDO) {
                    $stmt = $this->db->prepare("INSERT INTO cv_analyses (nom_fichier, date_import, statut) VALUES (?, NOW(), 'succes')");
                    $stmt->execute([$fileName]);
                }
                header('Location: /cv-analysis/web/public/import');
                exit;
            }
        }
        echo "<h1>Erreur lors du dépôt</h1>";
    }
}