<?php
namespace Models;

use PDO;

class ImportModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getDerniersImports($limit = 10) {
        $stmt = $this->db->query("SELECT * FROM import_jobs ORDER BY cree_le DESC LIMIT $limit");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

public function enregistrerImport($nomFichier) {
    // Utilisation des colonnes existantes et d'une valeur de statut autorisée
    // Note : importe_par_user_id est "not null", donc on lui donne une valeur (1)
    $sql = 'INSERT INTO "import_jobs" ("statut", "importe_par_user_id", "profile_id", "nom_fichier", "cree_le") 
            VALUES (?, ?, ?, ?, NOW())';
    
    $stmt = $this->db->prepare($sql);
    
    // On utilise 'en cours' car c'est une valeur autorisée par votre contrainte CHECK
    return $stmt->execute(['en cours', 1, 1, $nomFichier]);
}
}