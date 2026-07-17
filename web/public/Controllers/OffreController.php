<?php
namespace Controllers;

use Models\OffreModel;

class OffreController {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    private function verifierAdmin() {
        if (($_SESSION['role'] ?? null) !== 'admin') {
            http_response_code(403);
            die("Accès réservé aux administrateurs.");
        }
    }

    public function index() {
        $model = new OffreModel($this->db);
        $offres = $model->listerOffres();
        include __DIR__ . '/../Views/offres_liste.php';
    }

    public function show($id) {
        $model = new OffreModel($this->db);
        $offre = $model->getOffreById($id);
        if (!$offre) {
            http_response_code(404);
            die("Offre introuvable.");
        }
        $matchings = $model->calculerMatchingProfils($id);
        include __DIR__ . '/../Views/offre_detail.php';
    }

    public function create() {
        $this->verifierAdmin();
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $competences = array_filter(array_map('trim', explode(',', $_POST['competences'] ?? '')));

        if ($titre !== '') {
            $model = new OffreModel($this->db);
            $model->creerOffre($titre, $description, $_SESSION['user_id'], $competences);
        }

        header('Location: /offres');
        exit;
    }

    public function update($id) {
        $this->verifierAdmin();
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $competences = array_filter(array_map('trim', explode(',', $_POST['competences'] ?? '')));

        if ($titre !== '') {
            $model = new OffreModel($this->db);
            $model->modifierOffre($id, $titre, $description, $competences);
        }

        header('Location: /offres');
        exit;
    }

    public function delete($id) {
        $this->verifierAdmin();
        $model = new OffreModel($this->db);
        $model->deleteOffre($id);
        header('Location: /offres');
        exit;
    }
}
