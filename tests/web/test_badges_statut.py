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


def _creer_profil(nom: str, email: str, statut: str, user_id: int) -> None:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO profils (nom, email, statut, importe_par_user_id, pdf_source)
                VALUES (%s, %s, %s, %s, 'test.pdf')
                ON CONFLICT (email) DO UPDATE SET statut = EXCLUDED.statut
                """,
                (nom, email, statut, user_id),
            )
        conn.commit()


def _creer_import_job(nom_fichier: str, statut: str, user_id: int) -> None:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO import_jobs (statut, importe_par_user_id, nom_fichier)
                VALUES (%s, %s, %s)
                """,
                (statut, user_id, nom_fichier),
            )
        conn.commit()


@pytest.fixture(scope="module")
def user_id() -> int:
    return _creer_utilisateur(
        "test-badges-statut@example.com", "bonmotdepasse", "utilisateur"
    )


@pytest.fixture(scope="module")
def web_client(user_id: int) -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={
                "email": "test-badges-statut@example.com",
                "mot_de_passe": "bonmotdepasse",
            },
        )
        yield client


def test_profil_valide_affiche_badge_success(
    web_client: httpx.Client, user_id: int
) -> None:
    _creer_profil(
        "Test Badge Valide", "test-badge-valide@example.com", "validé", user_id
    )

    response = web_client.get("/revue")
    assert response.status_code == 200
    assert "badge-success" in response.text


def test_profil_a_valider_affiche_badge_warning(
    web_client: httpx.Client, user_id: int
) -> None:
    _creer_profil(
        "Test Badge Attente", "test-badge-attente@example.com", "à valider", user_id
    )

    response = web_client.get("/revue")
    assert response.status_code == 200
    assert "badge-warning" in response.text
