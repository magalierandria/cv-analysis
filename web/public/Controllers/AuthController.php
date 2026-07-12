<?php
namespace Controllers;

use Models\UtilisateurModel;

class AuthController {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function showLogin() {
        if (isset($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }
        $erreur = $_SESSION['erreur_login'] ?? null;
        unset($_SESSION['erreur_login']);
        require_once __DIR__ . '/../Views/login.php';
    }

    public function login() {
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        $model = new UtilisateurModel($this->db);
        $utilisateur = $model->verifierIdentifiants($email, $motDePasse);

        if (!$utilisateur) {
            $_SESSION['erreur_login'] = 'Email ou mot de passe incorrect.';
            header('Location: /login');
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $utilisateur['id'];
        $_SESSION['role'] = $utilisateur['role'];

        header('Location: /');
        exit;
    }

    public function logout() {
        $_SESSION = [];
        session_destroy();
        header('Location: /login');
        exit;
    }
}
