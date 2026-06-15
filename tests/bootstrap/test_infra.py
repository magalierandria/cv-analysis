import os

import httpx
import psycopg
import pytest

WEB_URL = os.environ.get("WEB_URL", "http://localhost:8080")
IMPORT_URL = os.environ.get("IMPORT_URL", "http://localhost:8000")
DATABASE_URL = os.environ.get(
    "DATABASE_URL", "postgresql://cv:cv@localhost:5432/cv_analysis"
)

EXPECTED_TABLES = (
    "utilisateurs",
    "profils",
    "experiences",
    "competences",
    "formations",
    "import_jobs",
    "schema_migrations",
)


@pytest.fixture(scope="module")
def web_client() -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        yield client


@pytest.fixture(scope="module")
def import_client() -> httpx.Client:
    with httpx.Client(base_url=IMPORT_URL, timeout=10.0) as client:
        yield client


def test_web_container_starts(web_client: httpx.Client) -> None:
    response = web_client.get("/")
    assert response.status_code == 200
    assert response.text.strip() == "OK"


def test_import_container_starts(import_client: httpx.Client) -> None:
    response = import_client.get("/health")
    assert response.status_code == 200
    assert response.json()["status"] == "ok"


def test_schema_applied_via_migrations() -> None:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT version FROM schema_migrations ORDER BY version"
            )
            versions = [row[0] for row in cur.fetchall()]
            assert "001_initial_schema" in versions

            cur.execute(
                """
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = 'public'
                ORDER BY table_name
                """
            )
            tables = {row[0] for row in cur.fetchall()}
            for table in EXPECTED_TABLES:
                assert table in tables


def test_pdf_volume_mounted_on_web_and_import(
    web_client: httpx.Client,
    import_client: httpx.Client,
) -> None:
    web_health = web_client.get("/health.php")
    import_health = import_client.get("/health")

    assert web_health.status_code == 200
    assert web_health.json()["pdf_volume_mounted"] is True
    assert import_health.status_code == 200
    assert import_health.json()["pdf_volume_mounted"] is True
