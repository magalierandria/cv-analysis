<?php

namespace Controllers;

use Models\ImportModel;

class ImportController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function index() {
        $importModel = new ImportModel($this->db);
        
        // On récupère l'historique pour l'afficher dans le tableau de la vue
        $historique = $importModel->getDerniersImports(); // Assurez-vous que cette méthode existe dans votre modèle

        // On inclut la vue (le fichier import.php que vous avez fourni)
        require_once __DIR__ . '/../Views/import.php'; 
    }

    public function upload() {
        // Cette méthode gère spécifiquement le POST du formulaire
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception("Méthode non autorisée.");
            }

            if (!isset($_FILES['cv_file']) || $_FILES['cv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception("Erreur lors de l'upload du fichier.");
            }

            $nomFichier = $_FILES['cv_file']['name'];
            
            // Logique de traitement (ex: sauvegarde, analyse IA...)
            $importModel = new ImportModel($this->db);
            $succes = $importModel->enregistrerImport($nomFichier);

            if ($succes) {
                // Redirection après succès pour éviter la soumission multiple
                header('Location: /cv-analysis/web/public/?status=success');
                exit;
            } else {
                throw new \Exception("Erreur lors de l'enregistrement en base.");
            }

        } catch (\Exception $e) {
            die("Erreur : " . $e->getMessage());
        }
    }
}