<?php

namespace Controllers;

use Models\ImportModel;

class ImportController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function index() {
        $importModel = new ImportModel($this->db);
        $historique = $importModel->getDerniersImports();
        require_once __DIR__ . '/../Views/import.php';
    }

    public function upload() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception("Méthode non autorisée.");
            }

            if (!isset($_FILES['cv_file']) || $_FILES['cv_file']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception("Erreur lors de l'upload du fichier.");
            }

            $jobId = $this->envoyerAuServicePython($_FILES['cv_file']);

            header('Location: /import/status/' . $jobId);
            exit;
        } catch (\Exception $e) {
            die("Erreur : " . htmlspecialchars($e->getMessage()));
        }
    }

    private function envoyerAuServicePython(array $fichier): int {
        $importServiceUrl = getenv('IMPORT_SERVICE_URL') ?: 'http://import:8000';

        $curlFile = new \CURLFile(
            $fichier['tmp_name'],
            $fichier['type'] ?: 'application/pdf',
            $fichier['name']
        );

        $ch = curl_init($importServiceUrl . '/import');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => $curlFile,
            'importe_par_user_id' => (int) $_SESSION['user_id'],
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        if ($response === false) {
            $erreur = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Impossible de contacter le service d'analyse : $erreur");
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($httpCode !== 202 || !isset($data['job_id'])) {
            $detail = $data['detail'] ?? "Réponse inattendue du service d'analyse.";
            throw new \Exception($detail);
        }

        return (int) $data['job_id'];
    }

    public function status($jobId) {
        $jobId = (int) $jobId;
        require_once __DIR__ . '/../Views/import_status.php';
    }

    public function jobStatusJson($jobId) {
        $importModel = new ImportModel($this->db);
        $job = $importModel->getJobById((int) $jobId);

        header('Content-Type: application/json');

        if (!$job) {
            http_response_code(404);
            echo json_encode(['error' => 'Job introuvable.']);
            return;
        }

        echo json_encode($job);
    }
}