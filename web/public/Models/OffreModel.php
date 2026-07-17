<?php
namespace Models;

use PDO;

class OffreModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function creerOffre($titre, $description, $userId, array $competences) {
        $stmt = $this->db->prepare(
            "INSERT INTO offres (titre, description, cree_par_user_id) VALUES (?, ?, ?) RETURNING id"
        );
        $stmt->execute([$titre, $description, $userId]);
        $offreId = (int) $stmt->fetchColumn();
        $this->remplacerCompetencesOffre($offreId, $competences);
        return $offreId;
    }

    public function modifierOffre($id, $titre, $description, array $competences) {
        $stmt = $this->db->prepare("UPDATE offres SET titre = ?, description = ? WHERE id = ?");
        $stmt->execute([$titre, $description, $id]);
        $this->remplacerCompetencesOffre($id, $competences);
    }

    public function deleteOffre($id) {
        $stmt = $this->db->prepare("DELETE FROM offres WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function listerOffres() {
        $stmt = $this->db->query(
            "SELECT id, titre, description, date_creation FROM offres ORDER BY date_creation DESC"
        );
        $offres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($offres as &$offre) {
            $offre['competences'] = $this->getCompetencesOffre($offre['id']);
        }
        return $offres;
    }

    public function getOffreById($id) {
        $stmt = $this->db->prepare(
            "SELECT id, titre, description, date_creation FROM offres WHERE id = ?"
        );
        $stmt->execute([$id]);
        $offre = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$offre) {
            return null;
        }
        $offre['competences'] = $this->getCompetencesOffre($id);
        return $offre;
    }

    private function remplacerCompetencesOffre($offreId, array $competences) {
        $stmt = $this->db->prepare("DELETE FROM offre_competences WHERE offre_id = ?");
        $stmt->execute([$offreId]);
        $stmt = $this->db->prepare("INSERT INTO offre_competences (offre_id, libelle) VALUES (?, ?)");
        foreach ($competences as $comp) {
            $comp = trim($comp);
            if ($comp !== '') {
                $stmt->execute([$offreId, $comp]);
            }
        }
    }

    private function getCompetencesOffre($offreId) {
        $stmt = $this->db->prepare(
            "SELECT libelle FROM offre_competences WHERE offre_id = ? ORDER BY id ASC"
        );
        $stmt->execute([$offreId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getCompetencesProfil($profilId) {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT c.libelle FROM competences c
             JOIN experiences e ON e.id = c.experience_id
             WHERE e.profil_id = ?"
        );
        $stmt->execute([$profilId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Score de chaque profil pour une offre donnée, trié décroissant. */
    public function calculerMatchingProfils($offreId) {
        $competencesRequises = $this->getCompetencesOffre($offreId);

        $stmt = $this->db->query("SELECT id, nom, titre, statut FROM profils ORDER BY nom ASC");
        $profils = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($profils as $profil) {
            $competencesProfilLower = array_map(
                fn($c) => mb_strtolower(trim($c)),
                $this->getCompetencesProfil($profil['id'])
            );

            $matched = [];
            $missing = [];
            foreach ($competencesRequises as $comp) {
                if (in_array(mb_strtolower(trim($comp)), $competencesProfilLower, true)) {
                    $matched[] = $comp;
                } else {
                    $missing[] = $comp;
                }
            }

            $score = count($competencesRequises) > 0
                ? (int) round(count($matched) / count($competencesRequises) * 100)
                : 0;

            $results[] = [
                'profil' => $profil,
                'score' => $score,
                'matched' => $matched,
                'missing' => $missing,
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return $results;
    }
}
