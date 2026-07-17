import os

import bcrypt
import httpx
import psycopg
import pytest

WEB_URL = os.environ.get("WEB_URL", "http://localhost:8080")
DATABASE_URL = os.environ.get(
    "DATABASE_URL", "postgresql://cv:cv@localhost:5433/cv_analysis"
)


def _creer_utilisateur(email: str, mot_de_passe: str, role: str) -> int:
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
                RETURNING id
                """,
                (email, hash_, role),
            )
            user_id = cur.fetchone()[0]
        conn.commit()
    return user_id


def _creer_import_job(nom_fichier: str, user_id: int) -> int:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO import_jobs (statut, importe_par_user_id, nom_fichier)
                VALUES ('réussi', %s, %s)
                RETURNING id
                """,
                (user_id, nom_fichier),
            )
            job_id = cur.fetchone()[0]
        conn.commit()
    return job_id


@pytest.fixture(scope="module")
def user_id() -> int:
    return _creer_utilisateur(
        "test-suppression-import@example.com", "bonmotdepasse", "utilisateur"
    )


@pytest.fixture(scope="module")
def web_client(user_id: int) -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={
                "email": "test-suppression-import@example.com",
                "mot_de_passe": "bonmotdepasse",
            },
        )
        yield client


def test_delete_job_removes_it(web_client: httpx.Client, user_id: int) -> None:
    job_id = _creer_import_job("cv-suppr-1.pdf", user_id)

    response = web_client.post(f"/import/delete/{job_id}", data={})
    assert response.status_code == 302
    assert response.headers["location"] == "/"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM import_jobs WHERE id = %s", (job_id,))
            assert cur.fetchone() is None


def test_delete_job_via_get_is_rejected(
    web_client: httpx.Client, user_id: int
) -> None:
    job_id = _creer_import_job("cv-suppr-2.pdf", user_id)

    response = web_client.get(f"/import/delete/{job_id}")
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM import_jobs WHERE id = %s", (job_id,))
            assert cur.fetchone() is not None


def test_delete_all_jobs_clears_history(
    web_client: httpx.Client, user_id: int
) -> None:
    _creer_import_job("cv-suppr-3.pdf", user_id)
    _creer_import_job("cv-suppr-4.pdf", user_id)

    response = web_client.post("/import/delete-all", data={})
    assert response.status_code == 302
    assert response.headers["location"] == "/"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT count(*) FROM import_jobs")
            assert cur.fetchone()[0] == 0


def test_delete_all_via_get_is_rejected(web_client: httpx.Client, user_id: int) -> None:
    job_id = _creer_import_job("cv-suppr-5.pdf", user_id)

    response = web_client.get("/import/delete-all")
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM import_jobs WHERE id = %s", (job_id,))
            assert cur.fetchone() is not None
