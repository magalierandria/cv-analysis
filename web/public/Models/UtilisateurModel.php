<?php
namespace Models;

use PDO;

class UtilisateurModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function verifierIdentifiants($email, $motDePasse) {
        $stmt = $this->db->prepare(
            "SELECT id, email, mot_de_passe, role FROM utilisateurs WHERE email = ? AND actif = true"
        );
        $stmt->execute([$email]);
        $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$utilisateur || !password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
            return null;
        }

        return [
            'id' => (int) $utilisateur['id'],
            'email' => $utilisateur['email'],
            'role' => $utilisateur['role'],
        ];
    }

    public function creerUtilisateur($email, $motDePasse, $role) {
        $hash = password_hash($motDePasse, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare(
            "INSERT INTO utilisateurs (email, mot_de_passe, role) VALUES (?, ?, ?) RETURNING id"
        );
        $stmt->execute([$email, $hash, $role]);
        return (int) $stmt->fetchColumn();
    }

    public function listerUtilisateurs() {
        $stmt = $this->db->query(
            "SELECT id, email, role, actif FROM utilisateurs ORDER BY email ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function desactiverUtilisateur($id) {
        $stmt = $this->db->prepare("UPDATE utilisateurs SET actif = false WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function modifierUtilisateur($id, $email, $role, $actif) {
        $stmt = $this->db->prepare(
            "UPDATE utilisateurs SET email = ?, role = ?, actif = ? WHERE id = ?"
        );
        return $stmt->execute([$email, $role, $actif, $id]);
    }
}
