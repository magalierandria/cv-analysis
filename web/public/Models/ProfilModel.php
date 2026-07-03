class ProfilModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function getProfilById($id) {
        $stmt = $this->db->prepare("SELECT * FROM profils WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function updateProfil($id, $data) {
        $stmt = $this->db->prepare("UPDATE profils SET nom = ?, titre = ?, statut = ? WHERE id = ?");
        return $stmt->execute([$data['nom'], $data['titre'], $data['statut'], $id]);
    }
}