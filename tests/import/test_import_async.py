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
