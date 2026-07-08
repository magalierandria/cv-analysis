# Câblage async PHP → Python (import de CV) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Câbler le formulaire d'upload PHP au service Python d'extraction/LLM selon le flux asynchrone documenté dans l'ADR-0001 : PHP envoie le PDF, reçoit un `job_id` immédiatement, Python traite en tâche de fond, PHP affiche une page de suivi qui poll l'état du job en base jusqu'à complétion.

**Architecture:** FastAPI expose `POST /import` (remplace l'ancien `POST /import-cv` synchrone) qui crée le job, sauvegarde le PDF, programme une `BackgroundTasks` puis répond `202`. PHP transmet le fichier via cURL natif, redirige vers une page de suivi qui interroge un nouvel endpoint JSON PHP (`GET /import/job/{id}/status`) toutes les 2s en JS vanilla.

**Tech Stack:** FastAPI + BackgroundTasks + psycopg2 (Python), PHP 8.3 natif + cURL (PHP), PostgreSQL, pytest + httpx pour les tests d'intégration (déjà en place dans `tests/`).

## Global Constraints

- Taille max upload : 10 MB (`MAX_FILE_SIZE`, déjà en place côté Python — ne pas changer).
- Statuts de job valides (contrainte SQL existante) : `en cours`, `réussi`, `échoué` — aucun autre littéral n'est accepté par la colonne `import_jobs.statut`.
- Aucun profil n'est créé si l'extraction échoue techniquement (PDF illisible, texte vide, erreur LLM) — cf. `docs/spec.md` §3.3.
- `importe_par_user_id` reste en dur à `1` côté PHP (pas de système d'auth réel en v1 — hors scope de ce plan).
- Ne pas toucher à `Views/revue.php` / à l'affichage du PDF dans l'iframe (bug préexistant indépendant, hors scope).
- L'ancien endpoint `POST /import-cv` est remplacé, pas dupliqué — un seul chemin d'import doit exister.
- Chemin de stockage du PDF sur le volume partagé : `/data/pdfs/{job_id}_{nom_fichier_original}` (le préfixe `job_id` évite les collisions).

---

### Task 1: Python — endpoint `/import` asynchrone + tâche de fond

**Files:**
- Modify: `import_cv/app/routes/import_cv.py` (réécriture complète)
- Test: `tests/import/test_import_async.py` (nouveau)

**Interfaces:**
- Consumes: `app.config.DATABASE_URL` (str), `app.config.PDF_DIR` (str, déjà égal à `/data/pdfs`)
- Produces: route `POST /import` — réponse `202 {"job_id": <int>}` ; met à jour la table `import_jobs` (colonnes `statut`, `profile_id`, `error_message`, `nom_fichier`) et crée des lignes dans `profils`/`experiences`/`competences`/`formations` en cas de succès. Ces contrats sont consommés par le Controller PHP dans les tâches suivantes.

- [ ] **Step 1: Écrire le test qui échoue (validations + échec sur texte vide)**

Créer `tests/import/test_import_async.py` :

```python
import os
import time

import httpx
import psycopg
import pytest

IMPORT_URL = os.environ.get("IMPORT_URL", "http://localhost:8001")
DATABASE_URL = os.environ.get(
    "DATABASE_URL", "postgresql://cv:cv@localhost:5433/cv_analysis"
)

# PDF minimal valide, une page blanche sans texte extractible.
BLANK_PDF = b"""%PDF-1.1
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >> endobj
trailer << /Root 1 0 R /Size 4 >>
%%EOF
"""


@pytest.fixture(scope="module")
def import_client() -> httpx.Client:
    with httpx.Client(base_url=IMPORT_URL, timeout=10.0) as client:
        yield client


def _get_job(job_id: int) -> dict:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT statut, profile_id, error_message FROM import_jobs WHERE id = %s",
                (job_id,),
            )
            statut, profile_id, error_message = cur.fetchone()
            return {
                "statut": statut,
                "profile_id": profile_id,
                "error_message": error_message,
            }


def test_import_rejects_non_pdf_extension(import_client: httpx.Client) -> None:
    response = import_client.post(
        "/import",
        files={"file": ("cv.txt", b"contenu", "text/plain")},
        data={"importe_par_user_id": 1},
    )
    assert response.status_code == 400


def test_import_rejects_oversized_file(import_client: httpx.Client) -> None:
    gros_fichier = b"0" * (11 * 1024 * 1024)
    response = import_client.post(
        "/import",
        files={"file": ("cv.pdf", gros_fichier, "application/pdf")},
        data={"importe_par_user_id": 1},
    )
    assert response.status_code == 413


def test_import_creates_job_and_fails_on_empty_text(
    import_client: httpx.Client,
) -> None:
    response = import_client.post(
        "/import",
        files={"file": ("cv-vide.pdf", BLANK_PDF, "application/pdf")},
        data={"importe_par_user_id": 1},
    )
    assert response.status_code == 202
    job_id = response.json()["job_id"]
    assert isinstance(job_id, int)

    job = None
    for _ in range(20):
        job = _get_job(job_id)
        if job["statut"] != "en cours":
            break
        time.sleep(0.5)

    assert job["statut"] == "échoué"
    assert job["profile_id"] is None
    assert "vide" in job["error_message"].lower()
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Le service Python actuel n'a pas de route `/import` (seulement `/import-cv`), donc les requêtes vont échouer.

Run:
```bash
docker compose up -d --build
IMPORT_URL=http://localhost:8001 DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/import/test_import_async.py -v
```
(Créer le venv si besoin : `python3 -m venv tests/.venv && tests/.venv/bin/pip install -r tests/requirements.txt`)

Expected: `test_import_rejects_non_pdf_extension` et les autres échouent avec `404 Not Found` (route inexistante) ou `AssertionError`.

- [ ] **Step 3: Réécrire `import_cv/app/routes/import_cv.py`**

Remplacer tout le contenu du fichier par :

```python
import io
import json
import logging
import os
import pymupdf
from typing import List, Optional
from fastapi import APIRouter, BackgroundTasks, File, Form, HTTPException, UploadFile
from pydantic import BaseModel
from mistralai.client import Mistral
from dotenv import load_dotenv
import psycopg2

from app.config import DATABASE_URL, PDF_DIR

load_dotenv()

logger = logging.getLogger(__name__)

router = APIRouter()

MAX_FILE_SIZE = 10 * 1024 * 1024  # 10 MB

MISTRAL_API_KEY = os.getenv("MISTRAL_API_KEY")
if not MISTRAL_API_KEY:
    raise RuntimeError("La variable d'environnement MISTRAL_API_KEY est manquante.")

mistral_client = Mistral(api_key=MISTRAL_API_KEY)


# --- Schémas Pydantic pour la validation ---
class Experience(BaseModel):
    entreprise: str
    poste: str
    secteur: Optional[str] = None
    date_debut: Optional[str] = None
    date_fin: Optional[str] = None
    description: Optional[str] = None
    competences: List[str] = []


class Formation(BaseModel):
    intitule: str
    etablissement: str
    type: Optional[str] = None
    annee: Optional[int] = None


class CVExtraction(BaseModel):
    nom: str
    email: Optional[str] = None
    tel: Optional[str] = None
    titre: Optional[str] = None
    linkedin: Optional[str] = None
    description: Optional[str] = None
    experiences: List[Experience] = []
    formations: List[Formation] = []


# --- Fonction IA ---
async def extraire_donnees_cv(texte: str) -> CVExtraction:
    prompt = f"Extrais les informations du CV suivant au format JSON strict.\n{texte}"

    response = await mistral_client.chat.complete_async(
        model="mistral-small-latest",
        messages=[{"role": "user", "content": prompt}],
        response_format={"type": "json_object"},
    )

    data = json.loads(response.choices[0].message.content)
    return CVExtraction(**data)


def _lire_texte_pdf(chemin_pdf: str) -> str:
    with pymupdf.open(chemin_pdf) as doc:
        return "\n".join(page.get_text() for page in doc)


def _marquer_job_echoue(job_id: int, message: str) -> None:
    conn = psycopg2.connect(DATABASE_URL)
    try:
        with conn:
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE import_jobs SET statut = 'échoué', error_message = %s WHERE id = %s",
                    (message, job_id),
                )
    finally:
        conn.close()


async def traiter_import(
    job_id: int, chemin_pdf: str, nom_fichier_stocke: str, importe_par_user_id: int
) -> None:
    try:
        texte = _lire_texte_pdf(chemin_pdf)
    except Exception:
        logger.exception("Erreur lors de la lecture du PDF pour le job %s", job_id)
        _marquer_job_echoue(job_id, "Impossible de lire le PDF.")
        return

    if not texte.strip():
        _marquer_job_echoue(job_id, "PDF vide ou texte non extractible.")
        return

    try:
        ai_data = await extraire_donnees_cv(texte)
    except Exception:
        logger.exception("Erreur lors de l'analyse IA du CV pour le job %s", job_id)
        _marquer_job_echoue(job_id, "Erreur lors de l'analyse du CV.")
        return

    conn = psycopg2.connect(DATABASE_URL)
    try:
        with conn:
            with conn.cursor() as cur:
                cur.execute(
                    """INSERT INTO profils (nom, email, linkedin, titre, description, importe_par_user_id, pdf_source)
                       VALUES (%s, %s, %s, %s, %s, %s, %s) RETURNING id""",
                    (
                        ai_data.nom,
                        ai_data.email,
                        ai_data.linkedin,
                        ai_data.titre,
                        ai_data.description,
                        importe_par_user_id,
                        nom_fichier_stocke,
                    ),
                )
                profil_id = cur.fetchone()[0]

                for exp in ai_data.experiences:
                    cur.execute(
                        """INSERT INTO experiences (profil_id, entreprise, poste, secteur, date_debut, date_fin, description)
                           VALUES (%s, %s, %s, %s, %s, %s, %s) RETURNING id""",
                        (
                            profil_id,
                            exp.entreprise,
                            exp.poste,
                            exp.secteur,
                            exp.date_debut,
                            exp.date_fin,
                            exp.description,
                        ),
                    )
                    exp_id = cur.fetchone()[0]
                    for libelle in exp.competences:
                        cur.execute(
                            "INSERT INTO competences (experience_id, libelle) VALUES (%s, %s)",
                            (exp_id, libelle),
                        )

                for form in ai_data.formations:
                    cur.execute(
                        "INSERT INTO formations (profil_id, intitule, etablissement, type, annee) VALUES (%s, %s, %s, %s, %s)",
                        (profil_id, form.intitule, form.etablissement, form.type, form.annee),
                    )

                cur.execute(
                    "UPDATE import_jobs SET statut = 'réussi', profile_id = %s WHERE id = %s",
                    (profil_id, job_id),
                )
    except Exception:
        logger.exception("Erreur lors de l'insertion du profil pour le job %s", job_id)
        conn.rollback()
        _marquer_job_echoue(job_id, "Erreur interne lors de l'enregistrement du profil.")
    finally:
        conn.close()


@router.post("/import", status_code=202)
async def import_cv(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    importe_par_user_id: int = Form(...),
):
    if not file.filename or not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Seuls les fichiers PDF sont acceptés.")

    content = await file.read()
    if len(content) > MAX_FILE_SIZE:
        raise HTTPException(status_code=413, detail="Fichier trop volumineux (max 10 MB).")

    conn = psycopg2.connect(DATABASE_URL)
    try:
        with conn:
            with conn.cursor() as cur:
                cur.execute(
                    "INSERT INTO import_jobs (statut, importe_par_user_id, nom_fichier) VALUES ('en cours', %s, %s) RETURNING id",
                    (importe_par_user_id, file.filename),
                )
                job_id = cur.fetchone()[0]
    finally:
        conn.close()

    nom_fichier_stocke = f"{job_id}_{file.filename}"
    chemin_pdf = os.path.join(PDF_DIR, nom_fichier_stocke)
    try:
        with open(chemin_pdf, "wb") as f:
            f.write(content)
    except OSError:
        logger.exception("Impossible d'écrire le PDF pour le job %s", job_id)
        _marquer_job_echoue(job_id, "Erreur lors de la sauvegarde du fichier.")
        raise HTTPException(status_code=500, detail="Erreur lors de la sauvegarde du fichier.")

    background_tasks.add_task(
        traiter_import, job_id, chemin_pdf, nom_fichier_stocke, importe_par_user_id
    )

    return {"job_id": job_id}
```

- [ ] **Step 4: Monter le nouveau volume PDF pour le service `import` s'il ne l'a pas déjà**

Vérifier que `docker-compose.yml` monte bien `./data/pdfs:/data/pdfs` pour le service `import` (déjà le cas — aucune modification nécessaire ici).

- [ ] **Step 5: Rebuild et relancer le service, puis exécuter les tests**

Run:
```bash
docker compose up -d --build import
IMPORT_URL=http://localhost:8001 DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/import/test_import_async.py -v
```
Expected: `3 passed`.

- [ ] **Step 6: Vérifier que `/health` répond toujours et que le fichier est bien écrit sur le volume**

Run:
```bash
curl -s http://localhost:8001/health
ls data/pdfs/ | tail -5
```
Expected: `{"status":"ok","bdd_connected":true,"pdf_volume_mounted":true}` et un fichier `*_cv-vide.pdf` présent.

- [ ] **Step 7: Commit**

```bash
git add import_cv/app/routes/import_cv.py tests/import/test_import_async.py
git commit -m "feat(import): endpoint /import asynchrone avec traitement en tâche de fond"
```

---

### Task 2: PHP + Docker — `ImportController::upload()` appelle le service Python

**Files:**
- Modify: `web/public/Controllers/ImportController.php`
- Modify: `docker-compose.yml`

**Interfaces:**
- Consumes: `POST {IMPORT_SERVICE_URL}/import` (Task 1) — champ `file` (multipart) + `importe_par_user_id` (form field), réponse attendue `202 {"job_id": int}`.
- Produces: `ImportController::upload()` redirige en `302` vers `/import/status/{job_id}` en cas de succès. Cette URL est consommée par la Task 4 (route `status()`).

- [ ] **Step 1: Ajouter `IMPORT_SERVICE_URL` au service `web` dans `docker-compose.yml`**

Dans le bloc `web:` de `docker-compose.yml`, modifier :

```yaml
  web:
    build: ./web
    ports:
      - "8080:80"
    depends_on:
      db:
        condition: service_healthy
      migrate:
        condition: service_completed_successfully
    volumes:
      - ./data/pdfs:/data/pdfs
    environment:
      DATABASE_URL: postgresql://cv:cv@db:5432/cv_analysis
      IMPORT_SERVICE_URL: http://import:8000
```

(ajout de la ligne `IMPORT_SERVICE_URL: http://import:8000` — nom de service Docker interne, port interne 8000, pas le port hôte 8001)

- [ ] **Step 2: Réécrire `web/public/Controllers/ImportController.php`**

Remplacer le contenu du fichier par :

```php
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
            die("Erreur : " . $e->getMessage());
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
            'importe_par_user_id' => 1,
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
}
```

(les méthodes `status()` et `jobStatusJson()` seront ajoutées dans la Task 4, dans le même fichier)

- [ ] **Step 3: Rebuild et vérifier que le cURL fonctionne**

Run:
```bash
docker compose up -d --build web
printf '%%PDF-1.1\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >> endobj\ntrailer << /Root 1 0 R /Size 4 >>\n%%%%EOF\n' > /tmp/cv-test.pdf
curl -s -D - -o /dev/null -F "cv_file=@/tmp/cv-test.pdf;filename=cv-test.pdf;type=application/pdf" http://localhost:8080/import/upload
rm -f /tmp/cv-test.pdf
```
Expected: en-têtes contenant `HTTP/1.1 302 Found` et `Location: /import/status/<un nombre>` (la route `/import/status/...` n'existe pas encore côté PHP — ce n'est pas grave à ce stade, on vérifie seulement que le `Location` pointe vers la bonne URL avec un `job_id` numérique. Elle sera implémentée en Task 4.)

- [ ] **Step 4: Commit**

```bash
git add web/public/Controllers/ImportController.php docker-compose.yml
git commit -m "feat(web): upload() transmet le PDF au service Python via cURL et récupère un job_id"
```

---

### Task 3: PHP — `ImportModel::getJobById()` et suppression de `enregistrerImport()`

**Files:**
- Modify: `web/public/Models/ImportModel.php`

**Interfaces:**
- Produces: `ImportModel::getJobById(int $id): ?array` — retourne `['statut' => string, 'profile_id' => ?int, 'error_message' => ?string]` ou `null` si le job n'existe pas. Consommé par `ImportController::jobStatusJson()` dans la Task 4.
- `enregistrerImport()` est supprimée : depuis la Task 2, `ImportController::upload()` ne l'appelle plus (elle a été remplacée par l'appel HTTP vers Python), donc plus aucun appelant ne subsiste.

- [ ] **Step 1: Modifier `web/public/Models/ImportModel.php`**

Remplacer le contenu du fichier par :

```php
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
}
```

- [ ] **Step 2: Vérifier qu'aucun autre fichier n'appelle encore `enregistrerImport()`**

Run:
```bash
grep -rn "enregistrerImport" web/public/
```
Expected: aucun résultat (la Task 2 a déjà remplacé le seul appelant existant).

- [ ] **Step 3: Commit**

```bash
git add web/public/Models/ImportModel.php
git commit -m "refactor(web): ImportModel expose getJobById, retire enregistrerImport devenue obsolète"
```

---

### Task 4: PHP — page de suivi et endpoint de statut

**Files:**
- Modify: `web/public/Controllers/ImportController.php`
- Create: `web/public/Views/import_status.php`
- Modify: `web/public/index.php`

**Interfaces:**
- Consumes: `ImportModel::getJobById(int $id): ?array` (Task 3)
- Produces: route `GET /import/status/(\d+)` (page HTML), route `GET /import/job/(\d+)/status` (JSON `{statut, profile_id, error_message}` ou 404)

- [ ] **Step 1: Ajouter `status()` et `jobStatusJson()` à `ImportController.php`**

Ajouter ces deux méthodes à la fin de la classe `ImportController` (juste avant l'accolade fermante `}` finale, après `envoyerAuServicePython`) :

```php
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
```

- [ ] **Step 2: Créer `web/public/Views/import_status.php`**

```php
<?php
/** @var int $jobId */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Import en cours</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card p-4 text-center">
                    <div id="etat-en-cours">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <h5>Analyse du CV en cours…</h5>
                        <p class="text-muted">Cette page se met à jour automatiquement.</p>
                    </div>
                    <div id="etat-echoue" class="d-none">
                        <h5 class="text-danger">Échec de l'analyse</h5>
                        <p id="message-erreur" class="text-muted"></p>
                        <a href="/" class="btn btn-primary">Retour à l'import</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const jobId = <?= json_encode($jobId) ?>;

        function verifierStatut() {
            fetch('/import/job/' + jobId + '/status')
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.statut === 'réussi') {
                        window.location = '/revue/' + data.profile_id;
                    } else if (data.statut === 'échoué') {
                        document.getElementById('etat-en-cours').classList.add('d-none');
                        document.getElementById('etat-echoue').classList.remove('d-none');
                        document.getElementById('message-erreur').textContent = data.error_message || 'Erreur inconnue.';
                    } else {
                        setTimeout(verifierStatut, 2000);
                    }
                })
                .catch(function () { setTimeout(verifierStatut, 2000); });
        }

        verifierStatut();
    </script>
</body>
</html>
```

- [ ] **Step 3: Ajouter les deux nouvelles routes dans `web/public/index.php`**

Dans le bloc de routage, juste après le `if (strpos($uri, '/import/upload') !== false) { ... }` existant, ajouter :

```php
elseif (preg_match('/\/import\/status\/(\d+)$/', $uri, $matches)) {
    (new ImportController($pdo))->status($matches[1]);
}
elseif (preg_match('/\/import\/job\/(\d+)\/status$/', $uri, $matches)) {
    (new ImportController($pdo))->jobStatusJson($matches[1]);
}
```

(à insérer avant le `elseif (strpos($uri, '/profil/update') !== false) { ... }` qui suit déjà dans le fichier)

- [ ] **Step 4: Rebuild et test manuel**

Run:
```bash
docker compose up -d --build web
printf '%%PDF-1.1\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >> endobj\ntrailer << /Root 1 0 R /Size 4 >>\n%%%%EOF\n' > /tmp/cv-test.pdf
JOB_URL=$(curl -s -D - -o /dev/null -F "cv_file=@/tmp/cv-test.pdf;filename=cv-test.pdf;type=application/pdf" http://localhost:8080/import/upload | grep -i '^location:' | tr -d '\r' | awk '{print $2}')
echo "Redirection: $JOB_URL"
curl -s "http://localhost:8080${JOB_URL}"
JOB_ID=$(echo "$JOB_URL" | grep -oE '[0-9]+$')
sleep 2
curl -s "http://localhost:8080/import/job/${JOB_ID}/status"
rm -f /tmp/cv-test.pdf
```
Expected: la page `/import/status/{id}` renvoie du HTML avec le spinner, et `/import/job/{id}/status` renvoie `{"statut":"échoué","profile_id":null,"error_message":"PDF vide ou texte non extractible."}` (le PDF de test n'a pas de texte extractible, c'est le comportement attendu).

- [ ] **Step 5: Commit**

```bash
git add web/public/Controllers/ImportController.php web/public/Views/import_status.php web/public/index.php
git commit -m "feat(web): page de suivi d'import avec polling JS et endpoint de statut JSON"
```

---

### Task 5: Tests d'intégration bout-en-bout PHP ↔ Python

**Files:**
- Create: `tests/web/test_import_flow.py`

**Interfaces:**
- Consumes: `POST {WEB_URL}/import/upload`, `GET {WEB_URL}/import/job/{id}/status` (Tasks 3 et 4)

- [ ] **Step 1: Écrire le test**

Créer `tests/web/test_import_flow.py` :

```python
import os
import time

import httpx
import pytest

WEB_URL = os.environ.get("WEB_URL", "http://localhost:8080")

BLANK_PDF = b"""%PDF-1.1
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >> endobj
trailer << /Root 1 0 R /Size 4 >>
%%EOF
"""


@pytest.fixture(scope="module")
def web_client() -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=15.0) as client:
        yield client


def _upload(web_client: httpx.Client) -> int:
    response = web_client.post(
        "/import/upload",
        files={"cv_file": ("cv-vide.pdf", BLANK_PDF, "application/pdf")},
    )
    assert response.status_code in (302, 303)
    location = response.headers["location"]
    assert location.startswith("/import/status/")
    return int(location.rsplit("/", 1)[-1])


def test_upload_redirects_to_status_page(web_client: httpx.Client) -> None:
    job_id = _upload(web_client)
    assert job_id > 0


def test_job_status_endpoint_reports_echec_for_empty_pdf(
    web_client: httpx.Client,
) -> None:
    job_id = _upload(web_client)

    status = None
    for _ in range(20):
        response = web_client.get(f"/import/job/{job_id}/status")
        assert response.status_code == 200
        status = response.json()
        if status["statut"] != "en cours":
            break
        time.sleep(0.5)

    assert status["statut"] == "échoué"
    assert status["profile_id"] is None


def test_job_status_unknown_id_returns_404(web_client: httpx.Client) -> None:
    response = web_client.get("/import/job/999999999/status")
    assert response.status_code == 404
```

- [ ] **Step 2: Lancer les tests**

Run:
```bash
docker compose up -d
WEB_URL=http://localhost:8080 tests/.venv/bin/pytest tests/web/test_import_flow.py -v
```
Expected: `3 passed`.

- [ ] **Step 3: Lancer toute la suite de tests pour vérifier l'absence de régression**

Run:
```bash
WEB_URL=http://localhost:8080 IMPORT_URL=http://localhost:8001 \
DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/ -v
```
Expected: tous les tests passent, à l'exception possible de `test_web_container_starts` dans `tests/bootstrap/test_infra.py` qui était déjà en échec avant ce plan (attend `"OK"` sur `/`, alors que `/` rend la page d'import HTML) — régression préexistante, pas introduite par ce travail, à traiter séparément si besoin.

- [ ] **Step 4: Commit**

```bash
git add tests/web/test_import_flow.py
git commit -m "test(web): couverture bout-en-bout du flux d'upload -> job -> statut"
```

---

### Task 6: Vérification manuelle complète + nettoyage des outils de test morts

**Files:**
- Modify: `import_cv/test_ui.html` (référence l'ancien endpoint `/import-cv`)
- Modify: `import_cv/dashboard.html` (référence l'ancien endpoint `/import-cv`)

**Interfaces:** aucune (nettoyage + vérification manuelle, pas de nouveau contrat)

- [ ] **Step 1: Mettre à jour `import_cv/test_ui.html` et `import_cv/dashboard.html`**

Ces deux fichiers HTML statiques (outils de test manuel, non servis par l'application) référencent encore l'ancien endpoint `/import-cv`. Remplacer dans chaque fichier :

Dans `import_cv/test_ui.html`, ligne avec `const API_URL = "http://127.0.0.1:8000/import-cv";` → remplacer par `const API_URL = "http://127.0.0.1:8000/import";` (et ajouter le champ `importe_par_user_id` au `FormData` envoyé, à repérer dans le fichier au niveau de la construction de la requête).

Dans `import_cv/dashboard.html`, ligne avec `fetch("http://127.0.0.1:8001/import-cv", ...)` → remplacer par `fetch("http://127.0.0.1:8001/import", ...)` (et ajouter `importe_par_user_id` au `FormData` de la même façon).

- [ ] **Step 2: Rebuild complet de la stack**

Run:
```bash
docker compose down
docker compose up -d --build
docker compose ps -a
```
Expected: les 4 services (`db`, `migrate`, `import`, `web`) démarrent, `migrate` en `exited (0)`, les autres `running`.

- [ ] **Step 3: Test manuel avec un vrai CV PDF (appel Mistral réel)**

Fournir un vrai fichier PDF de CV (texte extractible) et lancer :
```bash
curl -s -D - -o /dev/null -F "cv_file=@<chemin-vers-un-vrai-cv.pdf>;type=application/pdf" http://localhost:8080/import/upload
```
Relever le `job_id` dans le header `Location`, puis interroger le statut jusqu'à complétion :
```bash
watch -n 2 "curl -s http://localhost:8080/import/job/<job_id>/status"
```
Expected: après quelques secondes (temps de réponse Mistral), `statut` passe à `réussi` avec un `profile_id` non nul. Vérifier ensuite que `GET http://localhost:8080/revue/<profile_id>` renvoie bien une page HTML avec les données extraites.

- [ ] **Step 4: Vérifier `/health` sur les deux services après le cycle complet**

Run:
```bash
curl -s http://localhost:8001/health
curl -s http://localhost:8080/health.php
```
Expected: `bdd_connected: true` et `pdf_volume_mounted: true` sur les deux.

- [ ] **Step 5: Commit**

```bash
git add import_cv/test_ui.html import_cv/dashboard.html
git commit -m "chore(import): met à jour les outils de test manuel vers le nouvel endpoint /import"
```
