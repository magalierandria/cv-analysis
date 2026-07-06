<?php
namespace Models;

class ProfilModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function getProfilById($id) {
        $stmt = $this->db->prepare("SELECT * FROM profils WHERE id = ?");
        $stmt->execute([$id]);
        $profil = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$profil) {
            return null;
        }

        $profil['experiences'] = $this->getExperiencesByProfilId($id);
        $profil['formations']  = $this->getFormationsByProfilId($id);

        return $profil;
    }

    public function updateProfil($id, $data) {
        $stmt = $this->db->prepare("UPDATE profils SET nom = ?, titre = ?, statut = ? WHERE id = ?");
        return $stmt->execute([$data['nom'], $data['titre'], $data['statut'], $id]);
    }

    // ---------- EXPERIENCES ----------

    public function getExperiencesByProfilId($profilId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM experiences WHERE profil_id = ? ORDER BY ordre ASC, date_debut DESC"
        );
        $stmt->execute([$profilId]);
        $experiences = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($experiences as &$exp) {
            $exp['competences'] = $this->getCompetencesByExperienceId($exp['id']);
        }

        return $experiences;
    }

    public function getExperienceById($id) {
        $stmt = $this->db->prepare("SELECT * FROM experiences WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function addExperience($profilId, $data) {
        $stmt = $this->db->prepare(
            "INSERT INTO experiences (profil_id, entreprise, poste, secteur, date_debut, date_fin, description)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $profilId,
            $data['entreprise'] ?: null,
            $data['poste'] ?: null,
            $data['secteur'] ?: null,
            $data['date_debut'] ?: null,
            $data['date_fin'] ?: null,
            $data['description'] ?: null,
        ]);
    }

    public function updateExperience($id, $data) {
        $stmt = $this->db->prepare(
            "UPDATE experiences SET entreprise = ?, poste = ?, secteur = ?, date_debut = ?, date_fin = ?, description = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            $data['entreprise'] ?: null,
            $data['poste'] ?: null,
            $data['secteur'] ?: null,
            $data['date_debut'] ?: null,
            $data['date_fin'] ?: null,
            $data['description'] ?: null,
            $id,
        ]);
    }

    public function deleteExperience($id) {
        $stmt = $this->db->prepare("DELETE FROM experiences WHERE id = ?");
        return $stmt->execute([$id]);
    }

    private function getCompetencesByExperienceId($experienceId) {
        $stmt = $this->db->prepare("SELECT libelle FROM competences WHERE experience_id = ?");
        $stmt->execute([$experienceId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    // ---------- FORMATIONS ----------

    public function getFormationsByProfilId($profilId) {
        $stmt = $this->db->prepare("SELECT * FROM formations WHERE profil_id = ? ORDER BY annee DESC");
        $stmt->execute([$profilId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getFormationById($id) {
        $stmt = $this->db->prepare("SELECT * FROM formations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function addFormation($profilId, $data) {
        $stmt = $this->db->prepare(
            "INSERT INTO formations (profil_id, intitule, etablissement, type, annee)
             VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $profilId,
            $data['intitule'] ?: null,
            $data['etablissement'] ?: null,
            $data['type'] ?: null,
            $data['annee'] ?: null,
        ]);
    }

    public function updateFormation($id, $data) {
        $stmt = $this->db->prepare(
            "UPDATE formations SET intitule = ?, etablissement = ?, type = ?, annee = ? WHERE id = ?"
        );
        return $stmt->execute([
            $data['intitule'] ?: null,
            $data['etablissement'] ?: null,
            $data['type'] ?: null,
            $data['annee'] ?: null,
            $id,
        ]);
    }

    public function deleteFormation($id) {
        $stmt = $this->db->prepare("DELETE FROM formations WHERE id = ?");
        return $stmt->execute([$id]);
    }
}