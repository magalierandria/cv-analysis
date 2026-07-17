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

    public function getJobById($id) {
        $sql = "SELECT statut, profile_id, error_message FROM import_jobs WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        return $job ?: null;
    }

    public function deleteJob($id) {
        $stmt = $this->db->prepare("DELETE FROM import_jobs WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function deleteAllJobs() {
        return $this->db->exec("DELETE FROM import_jobs");
    }
}
