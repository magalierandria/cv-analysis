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
