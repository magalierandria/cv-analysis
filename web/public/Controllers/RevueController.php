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

    public function servirPdf($id) {
        $model = new ProfilModel($this->db);
        $profil = $model->getProfilById($id);

        if (!$profil || empty($profil['pdf_source'])) {
            http_response_code(404);
            die("PDF introuvable.");
        }

        $chemin = '/var/www/html/data/pdfs/' . basename($profil['pdf_source']);

        if (!is_file($chemin)) {
            http_response_code(404);
            die("PDF introuvable.");
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($profil['pdf_source']) . '"');
        header('Content-Length: ' . filesize($chemin));
        readfile($chemin);
        exit;
    }

    public function index() {
        $model = new ProfilModel($this->db);

        $recherche = trim($_GET['q'] ?? '');
        $statut = $_GET['statut'] ?? '';
        $competences = $_GET['competences'] ?? [];
        if (!is_array($competences)) {
            $competences = [];
        }

        $profils = $model->rechercherProfils($recherche, $statut, $competences);
        $toutesLesCompetences = $model->listerToutesLesCompetences();

        include __DIR__ . '/../Views/revue_liste.php';
    }

    public function updateProfil() {
        $model = new ProfilModel($this->db);
        $id = $_POST['id'] ?? null;

        $model->updateProfil($id, [
            'nom'    => $_POST['nom'] ?? '',
            'titre'  => $_POST['titre'] ?? '',
            'statut' => $_POST['statut'] ?? 'à valider',
            'valide_par_user_id' => $_SESSION['user_id'] ?? null,
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