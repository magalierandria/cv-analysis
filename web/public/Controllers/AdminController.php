<?php
namespace Controllers;

use Models\UtilisateurModel;

class AdminController {
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

    public function listerUtilisateurs() {
        $this->verifierAdmin();
        $model = new UtilisateurModel($this->db);
        $utilisateurs = $model->listerUtilisateurs();
        $erreur = $_SESSION['erreur_admin'] ?? null;
        unset($_SESSION['erreur_admin']);
        require_once __DIR__ . '/../Views/admin_utilisateurs.php';
    }

    public function creerUtilisateur() {
        $this->verifierAdmin();

        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $role = $_POST['role'] ?? 'utilisateur';

        if ($email === '' || $motDePasse === '' || !in_array($role, ['admin', 'utilisateur'], true)) {
            $_SESSION['erreur_admin'] = 'Champs invalides.';
            header('Location: /admin/utilisateurs');
            exit;
        }

        $model = new UtilisateurModel($this->db);
        try {
            $model->creerUtilisateur($email, $motDePasse, $role);
        } catch (\PDOException $e) {
            $_SESSION['erreur_admin'] = 'Impossible de créer ce compte (email déjà utilisé ?).';
        }

        header('Location: /admin/utilisateurs');
        exit;
    }

    public function desactiverUtilisateur($id) {
        $this->verifierAdmin();
        $model = new UtilisateurModel($this->db);
        $model->desactiverUtilisateur((int) $id);
        header('Location: /admin/utilisateurs');
        exit;
    }

    public function modifierUtilisateur($id) {
        $this->verifierAdmin();

        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'utilisateur';
        $actif = isset($_POST['actif']);

        if ($email === '' || !in_array($role, ['admin', 'utilisateur'], true)) {
            $_SESSION['erreur_admin'] = 'Champs invalides.';
            header('Location: /admin/utilisateurs');
            exit;
        }

        $model = new UtilisateurModel($this->db);
        try {
            $model->modifierUtilisateur((int) $id, $email, $role, $actif);
        } catch (\PDOException $e) {
            $_SESSION['erreur_admin'] = 'Impossible de modifier ce compte (email déjà utilisé ?).';
        }

        header('Location: /admin/utilisateurs');
        exit;
    }
}
