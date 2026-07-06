<?php
namespace Models;

use PDO;

class ImportModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getDerniersImports($limit = 10) {
        $sql = "SELECT ij.*, p.nom AS candidat_nom
                FROM import_jobs ij
                LEFT JOIN profils p ON p.id = ij.profile_id
                ORDER BY ij.cree_le DESC
                LIMIT $limit";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function enregistrerImport($nomFichier) {
        // 1. Créer un NOUVEAU profil pour ce CV (nom = NULL, sera rempli plus tard par l'extraction IA)
        $sqlProfil = 'INSERT INTO profils (importe_par_user_id, pdf_source, statut)
                      VALUES (?, ?, ?) RETURNING id';
        $stmtProfil = $this->db->prepare($sqlProfil);
        $stmtProfil->execute([1, $nomFichier, 'à valider']);
        $profilId = $stmtProfil->fetchColumn();

        // 2. Créer le job d'import lié à CE NOUVEAU profil (plus jamais 1 en dur)
        $sqlJob = 'INSERT INTO import_jobs (statut, importe_par_user_id, profile_id, nom_fichier, cree_le)
                   VALUES (?, ?, ?, ?, NOW())';
        $stmtJob = $this->db->prepare($sqlJob);
        return $stmtJob->execute(['en cours', 1, $profilId, $nomFichier]);
    }
}