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


@pytest.fixture(scope="module")
def admin_client() -> httpx.Client:
    _creer_utilisateur("test-modifier-admin@example.com", "bonmotdepasse", "admin")
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={
                "email": "test-modifier-admin@example.com",
                "mot_de_passe": "bonmotdepasse",
            },
        )
        yield client


def test_admin_can_modify_email_role_and_reactivate(
    admin_client: httpx.Client,
) -> None:
    target_id = _creer_utilisateur(
        "test-modifier-cible@example.com", "bonmotdepasse", "utilisateur"
    )
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE utilisateurs SET actif = false WHERE id = %s", (target_id,)
            )
        conn.commit()

    response = admin_client.post(
        f"/admin/utilisateurs/{target_id}/modifier",
        data={
            "email": "test-modifier-cible-v2@example.com",
            "role": "admin",
            "actif": "1",
        },
    )
    assert response.status_code == 302
    assert response.headers["location"] == "/admin/utilisateurs"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT email, role, actif FROM utilisateurs WHERE id = %s",
                (target_id,),
            )
            email, role, actif = cur.fetchone()
            assert email == "test-modifier-cible-v2@example.com"
            assert role == "admin"
            assert actif is True


def test_admin_modifier_with_duplicate_email_shows_error_and_does_not_change(
    admin_client: httpx.Client,
) -> None:
    existing_email = "test-modifier-existant@example.com"
    _creer_utilisateur(existing_email, "bonmotdepasse", "utilisateur")
    target_id = _creer_utilisateur(
        "test-modifier-cible2@example.com", "bonmotdepasse", "utilisateur"
    )

    response = admin_client.post(
        f"/admin/utilisateurs/{target_id}/modifier",
        data={"email": existing_email, "role": "utilisateur", "actif": "1"},
    )
    assert response.status_code == 302

    page = admin_client.get("/admin/utilisateurs")
    assert "Impossible de modifier ce compte" in page.text

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT email FROM utilisateurs WHERE id = %s", (target_id,))
            assert cur.fetchone()[0] == "test-modifier-cible2@example.com"


def test_admin_modifier_via_get_is_rejected(admin_client: httpx.Client) -> None:
    target_id = _creer_utilisateur(
        "test-modifier-get@example.com", "bonmotdepasse", "utilisateur"
    )

    response = admin_client.get(
        f"/admin/utilisateurs/{target_id}/modifier",
        params={"email": "hacked@example.com", "role": "admin", "actif": "1"},
    )
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT email, role FROM utilisateurs WHERE id = %s", (target_id,)
            )
            email, role = cur.fetchone()
            assert email == "test-modifier-get@example.com"
            assert role == "utilisateur"
