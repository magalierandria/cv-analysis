import os
import time

import bcrypt
import httpx
import psycopg
import pytest

WEB_URL = os.environ.get("WEB_URL", "http://localhost:8080")
DATABASE_URL = os.environ.get(
    "DATABASE_URL", "postgresql://cv:cv@localhost:5433/cv_analysis"
)

BLANK_PDF = b"""%PDF-1.1
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >> endobj
trailer << /Root 1 0 R /Size 4 >>
%%EOF
"""


def _creer_utilisateur(email: str, mot_de_passe: str, role: str) -> None:
    hash_ = bcrypt.hashpw(mot_de_passe.encode(), bcrypt.gensalt()).decode()
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO utilisateurs (email, mot_de_passe, role)
                VALUES (%s, %s, %s)
                ON CONFLICT (email) DO UPDATE SET
                    mot_de_passe = EXCLUDED.mot_de_passe,
                    role = EXCLUDED.role,
                    actif = true
                """,
                (email, hash_, role),
            )
        conn.commit()


@pytest.fixture(scope="module")
def web_client() -> httpx.Client:
    _creer_utilisateur("test-import-flow@example.com", "bonmotdepasse", "utilisateur")
    with httpx.Client(base_url=WEB_URL, timeout=15.0) as client:
        client.post(
            "/login",
            data={"email": "test-import-flow@example.com", "mot_de_passe": "bonmotdepasse"},
        )
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
