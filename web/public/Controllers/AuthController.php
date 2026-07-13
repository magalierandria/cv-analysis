<?php
namespace Controllers;

use PDO;

class AuthController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Affiche la vue de connexion
     */
    public function showLogin() {
        require __DIR__ . '/../Views/login.php';
    }

    /**
     * Traite la soumission du formulaire de connexion
     */
    public function login() {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $this->pdo->prepare("SELECT id, mot_de_passe FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Vérification simple (sans hash pour le moment comme dans ton code)
        if ($user && ($password === $user['mot_de_passe'])) {
            $_SESSION['utilisateur_id'] = $user['id'];
            
            // Redirection vers la racine de l'application
            header('Location: /cv-analysis/web/public/');
            exit;
        } else {
            // Message d'erreur simple
            echo "Identifiants incorrects. <a href='/cv-analysis/web/public/login'>Retour</a>";
        }
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        // Détruit toutes les données de la session
        $_SESSION = [];
        session_destroy();
        
        // Redirection vers la page de login
        header('Location: /cv-analysis/web/public/login');
        exit;
    }
}