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


def _creer_profil_complet(email: str, user_id: int) -> tuple[int, int]:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO profils (nom, email, statut, importe_par_user_id, pdf_source)
                VALUES ('Profil A Supprimer', %s, 'à valider', %s, 'test.pdf')
                ON CONFLICT (email) DO UPDATE SET statut = EXCLUDED.statut
                RETURNING id
                """,
                (email, user_id),
            )
            profil_id = cur.fetchone()[0]

            cur.execute(
                "INSERT INTO experiences (profil_id, entreprise, poste) VALUES (%s, %s, %s) RETURNING id",
                (profil_id, "ACME", "Développeur"),
            )
            experience_id = cur.fetchone()[0]

            cur.execute(
                "INSERT INTO competences (experience_id, libelle) VALUES (%s, %s)",
                (experience_id, "PHP"),
            )

            cur.execute(
                "INSERT INTO formations (profil_id, intitule) VALUES (%s, %s)",
                (profil_id, "Master Informatique"),
            )

            cur.execute(
                """
                INSERT INTO import_jobs (statut, importe_par_user_id, profile_id, nom_fichier)
                VALUES ('réussi', %s, %s, %s)
                RETURNING id
                """,
                (user_id, profil_id, "cv-a-supprimer.pdf"),
            )
            import_job_id = cur.fetchone()[0]
        conn.commit()
    return profil_id, import_job_id


@pytest.fixture(scope="module")
def user_id() -> int:
    return _creer_utilisateur(
        "test-suppression-profil@example.com", "bonmotdepasse", "utilisateur"
    )


@pytest.fixture(scope="module")
def web_client(user_id: int) -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={
                "email": "test-suppression-profil@example.com",
                "mot_de_passe": "bonmotdepasse",
            },
        )
        yield client


def test_delete_profil_removes_it_and_nullifies_import_job_link(
    web_client: httpx.Client, user_id: int
) -> None:
    profil_id, import_job_id = _creer_profil_complet(
        "test-delete-profil-1@example.com", user_id
    )

    response = web_client.post(f"/profil/delete/{profil_id}", data={})
    assert response.status_code == 302
    assert response.headers["location"] == "/revue"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM profils WHERE id = %s", (profil_id,))
            assert cur.fetchone() is None

            cur.execute(
                "SELECT profile_id FROM import_jobs WHERE id = %s", (import_job_id,)
            )
            row = cur.fetchone()
            assert row is not None
            assert row[0] is None


def test_delete_profil_via_get_is_rejected(
    web_client: httpx.Client, user_id: int
) -> None:
    profil_id, _ = _creer_profil_complet("test-delete-profil-2@example.com", user_id)

    response = web_client.get(f"/profil/delete/{profil_id}")
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM profils WHERE id = %s", (profil_id,))
            assert cur.fetchone() is not None
