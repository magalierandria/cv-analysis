import os

import httpx
import pytest

IMPORT_URL = os.environ.get("IMPORT_URL", "http://localhost:8000")


@pytest.fixture(scope="module")
def import_client() -> httpx.Client:
    with httpx.Client(base_url=IMPORT_URL, timeout=10.0) as client:
        yield client


def test_health_returns_200(import_client: httpx.Client) -> None:
    response = import_client.get("/health")
    assert response.status_code == 200


def test_health_reports_bdd_connected(import_client: httpx.Client) -> None:
    response = import_client.get("/health")
    assert response.json()["bdd_connected"] is True


def test_health_reports_pdf_volume_mounted(import_client: httpx.Client) -> None:
    response = import_client.get("/health")
    body = response.json()
    assert body["status"] == "ok"
    assert body["pdf_volume_mounted"] is True
