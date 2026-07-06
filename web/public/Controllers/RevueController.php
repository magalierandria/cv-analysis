<?php
namespace Controllers;

use Models\ProfilModel;

class RevueController {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function show($id) {
        $model = new ProfilModel($this->db);
        $profil = $model->getProfilById($id);

        include __DIR__ . '/../Views/revue.php';
    }

    public function index() {
        include __DIR__ . '/../Views/revue.php';
    }

    public function updateProfil() {
        $model = new ProfilModel($this->db);
        $id = $_POST['id'] ?? null;

        $model->updateProfil($id, [
            'nom'    => $_POST['nom'] ?? '',
            'titre'  => $_POST['titre'] ?? '',
            'statut' => $_POST['statut'] ?? 'à valider',
        ]);

        header('Location: /cv-analysis/web/public/revue/' . $id);
        exit;
    }

    // ---------- EXPERIENCES ----------

    public function addExperience() {
        $model = new ProfilModel($this->db);
        $profilId = $_POST['profil_id'] ?? null;

        $model->addExperience($profilId, $_POST);

        header('Location: /cv-analysis/web/public/revue/' . $profilId);
        exit;
    }

    public function updateExperience($id) {
        $model = new ProfilModel($this->db);
        $profilId = $_POST['profil_id'] ?? null;

        $model->updateExperience($id, $_POST);

        header('Location: /cv-analysis/web/public/revue/' . $profilId);
        exit;
    }

    public function deleteExperience($id) {
        $model = new ProfilModel($this->db);
        $profilId = $_POST['profil_id'] ?? null;

        $model->deleteExperience($id);

        header('Location: /cv-analysis/web/public/revue/' . $profilId);
        exit;
    }

    // ---------- FORMATIONS ----------

    public function addFormation() {
        $model = new ProfilModel($this->db);
        $profilId = $_POST['profil_id'] ?? null;

        $model->addFormation($profilId, $_POST);

        header('Location: /cv-analysis/web/public/revue/' . $profilId);
        exit;
    }

    public function updateFormation($id) {
        $model = new ProfilModel($this->db);
        $profilId = $_POST['profil_id'] ?? null;

        $model->updateFormation($id, $_POST);

        header('Location: /cv-analysis/web/public/revue/' . $profilId);
        exit;
    }

    public function deleteFormation($id) {
        $model = new ProfilModel($this->db);
        $profilId = $_POST['profil_id'] ?? null;

        $model->deleteFormation($id);

        header('Location: /cv-analysis/web/public/revue/' . $profilId);
        exit;
    }
}