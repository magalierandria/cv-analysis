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


def test_protected_route_redirects_to_login_when_not_authenticated() -> None:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        response = client.get("/")
        assert response.status_code == 302
        assert response.headers["location"] == "/login"


def test_logout_revokes_access() -> None:
    _creer_utilisateur("test-auth-logout@example.com", "bonmotdepasse", "utilisateur")

    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={"email": "test-auth-logout@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        assert client.get("/").status_code == 200

        client.post("/logout")

        response = client.get("/")
        assert response.status_code == 302
        assert response.headers["location"] == "/login"


def test_non_admin_cannot_access_admin_page() -> None:
    _creer_utilisateur("test-auth-standard@example.com", "bonmotdepasse", "utilisateur")

    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={"email": "test-auth-standard@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        response = client.get("/admin/utilisateurs")
        assert response.status_code == 403


def test_admin_can_create_and_deactivate_user() -> None:
    _creer_utilisateur("test-auth-admin@example.com", "bonmotdepasse", "admin")

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "DELETE FROM utilisateurs WHERE email = %s",
                ("test-auth-cree-par-admin@example.com",),
            )
        conn.commit()

    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={"email": "test-auth-admin@example.com", "mot_de_passe": "bonmotdepasse"},
        )

        response = client.get("/admin/utilisateurs")
        assert response.status_code == 200

        response = client.post(
            "/admin/utilisateurs/create",
            data={
                "email": "test-auth-cree-par-admin@example.com",
                "mot_de_passe": "unautremotdepasse",
                "role": "utilisateur",
            },
        )
        assert response.status_code == 302

        with psycopg.connect(DATABASE_URL) as conn:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT id, actif FROM utilisateurs WHERE email = %s",
                    ("test-auth-cree-par-admin@example.com",),
                )
                user_id, actif = cur.fetchone()
                assert actif is True

        response = client.post(f"/admin/utilisateurs/{user_id}/desactiver")
        assert response.status_code == 302

        with psycopg.connect(DATABASE_URL) as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT actif FROM utilisateurs WHERE id = %s", (user_id,))
                assert cur.fetchone()[0] is False


def test_login_prefixed_uri_does_not_bypass_auth_guard() -> None:
    # Régression : l'ancienne garde utilisait strpos($uri, '/login') !== 0,
    # ce qui laissait passer toute URI commençant simplement par "/login",
    # comme /login/import/job/1/status, sans jamais vérifier la session.
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        response = client.get("/login/import/job/1/status")
        assert response.status_code == 302
        assert response.headers["location"] == "/login"


def test_admin_create_user_via_get_is_rejected() -> None:
    # Régression : les routes de mutation admin n'exigeaient à l'origine
    # aucune méthode HTTP particulière, ce qui permettait de créer un
    # compte via une simple requête GET (ex. <img src="...">).
    _creer_utilisateur("test-auth-admin-get@example.com", "bonmotdepasse", "admin")
    target_email = "test-auth-cree-via-get@example.com"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "DELETE FROM utilisateurs WHERE email = %s",
                (target_email,),
            )
        conn.commit()

    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={
                "email": "test-auth-admin-get@example.com",
                "mot_de_passe": "bonmotdepasse",
            },
        )

        response = client.get(
            "/admin/utilisateurs/create",
            params={
                "email": target_email,
                "mot_de_passe": "unautremotdepasse",
                "role": "utilisateur",
            },
        )
        # Si la faille était présente, cette requête GET correspondrait tout
        # de même à la route de mutation /admin/utilisateurs/create (qui
        # redirige toujours vers /admin/utilisateurs, que la création
        # réussisse ou échoue faute de champs $_POST). Avec la garde
        # REQUEST_METHOD === 'POST' en place, la requête GET ne correspond
        # plus du tout à cette route et retombe sur une autre page (200).
        assert not (
            response.status_code == 302
            and response.headers.get("location") == "/admin/utilisateurs"
        )

        with psycopg.connect(DATABASE_URL) as conn:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT id FROM utilisateurs WHERE email = %s",
                    (target_email,),
                )
                assert cur.fetchone() is None
