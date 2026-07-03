<?php
namespace Controllers;

use Models\ProfilModel;

class RevueController {
    private $db;

    // Utilisation d'un constructeur pour injecter la base de données
    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function show($id) {
        $model = new ProfilModel($this->db);
        $profil = $model->getProfilById($id);
        
        // Utilisation de __DIR__ pour garantir le bon chemin vers la vue
        include __DIR__ . '/../Views/revue.php';
    }

    // Gardez votre méthode index pour la route par défaut
    public function index() {
        include __DIR__ . '/../Views/revue.php';
    }
}