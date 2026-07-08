import os
import time

import httpx
import psycopg
import pytest

IMPORT_URL = os.environ.get("IMPORT_URL", "http://localhost:8001")
DATABASE_URL = os.environ.get(
    "DATABASE_URL", "postgresql://cv:cv@localhost:5433/cv_analysis"
)

# Répertoire hôte sur lequel le volume /data/pdfs du service import est monté
# (voir docker-compose.yml: ./data/pdfs:/data/pdfs).
PDF_HOST_DIR = os.environ.get(
    "PDF_HOST_DIR",
    os.path.join(os.path.dirname(os.path.dirname(os.path.dirname(__file__))), "data", "pdfs"),
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


def test_import_sanitizes_path_traversal_filename(import_client: httpx.Client) -> None:
    """Un nom de fichier malveillant contenant des séparateurs de répertoire et
    des séquences ".." ne doit jamais permettre d'écrire en dehors de PDF_DIR.

    Avant correctif : le nom de fichier brut du client est utilisé tel quel
    pour construire le chemin sur le volume partagé
    (`f"{job_id}_{file.filename}"`), ce qui casse la résolution du chemin dès
    qu'il contient des "/" ou "..", et provoque un 500 au lieu d'un stockage
    sain dans PDF_DIR.
    """
    malicious_filename = "../../../tmp/evil.pdf"
    response = import_client.post(
        "/import",
        files={"file": (malicious_filename, BLANK_PDF, "application/pdf")},
        data={"importe_par_user_id": 1},
    )

    assert response.status_code == 202, response.text
    job_id = response.json()["job_id"]

    # Le fichier stocké doit résider strictement dans PDF_HOST_DIR (le montage
    # hôte de PDF_DIR), avec un nom de fichier assaini (basename uniquement).
    entries = os.listdir(PDF_HOST_DIR)
    matching = [e for e in entries if e.startswith(f"{job_id}_")]
    assert len(matching) == 1, f"fichier attendu introuvable dans {PDF_HOST_DIR}: {entries}"
    stored_name = matching[0]
    assert ".." not in stored_name
    assert "/" not in stored_name

    stored_path = os.path.abspath(os.path.join(PDF_HOST_DIR, stored_name))
    assert (
        os.path.commonpath([os.path.abspath(PDF_HOST_DIR), stored_path])
        == os.path.abspath(PDF_HOST_DIR)
    )

    # Aucun fichier ne doit avoir fui en dehors de PDF_HOST_DIR (ex: dans
    # data/tmp/evil.pdf, ou tout autre chemin résolu par traversée de
    # répertoires).
    data_dir = os.path.dirname(PDF_HOST_DIR)
    for root, _dirs, files in os.walk(data_dir):
        if root == PDF_HOST_DIR or root.startswith(PDF_HOST_DIR + os.sep):
            continue
        assert "evil.pdf" not in files, f"fichier échappé trouvé dans {root}"

    # Pas de nettoyage : le fichier appartient au conteneur (root) et les
    # autres tests de ce module ne nettoient pas non plus leurs artefacts
    # dans PDF_HOST_DIR.
