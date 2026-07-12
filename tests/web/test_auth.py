import os

import bcrypt
import httpx
import psycopg
import pytest

WEB_URL = os.environ.get("WEB_URL", "http://localhost:8080")
DATABASE_URL = os.environ.get(
    "DATABASE_URL", "postgresql://cv:cv@localhost:5433/cv_analysis"
)


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
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        yield client


def test_login_with_invalid_credentials_shows_error(web_client: httpx.Client) -> None:
    _creer_utilisateur("test-auth-invalide@example.com", "bonmotdepasse", "utilisateur")
    response = web_client.post(
        "/login",
        data={"email": "test-auth-invalide@example.com", "mot_de_passe": "mauvais"},
    )
    assert response.status_code == 302
    assert response.headers["location"] == "/login"


def test_login_with_valid_credentials_grants_access(web_client: httpx.Client) -> None:
    _creer_utilisateur("test-auth-valide@example.com", "bonmotdepasse", "utilisateur")

    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        response = client.post(
            "/login",
            data={"email": "test-auth-valide@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        assert response.status_code == 302
        assert response.headers["location"] == "/"
