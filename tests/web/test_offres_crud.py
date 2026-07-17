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


def _creer_offre_directe(titre: str, user_id: int, competences: list[str]) -> int:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO offres (titre, cree_par_user_id) VALUES (%s, %s) RETURNING id",
                (titre, user_id),
            )
            offre_id = cur.fetchone()[0]
            for comp in competences:
                cur.execute(
                    "INSERT INTO offre_competences (offre_id, libelle) VALUES (%s, %s)",
                    (offre_id, comp),
                )
        conn.commit()
    return offre_id


@pytest.fixture(scope="module")
def admin_id() -> int:
    return _creer_utilisateur("test-offres-admin@example.com", "bonmotdepasse", "admin")


@pytest.fixture(scope="module")
def admin_client(admin_id: int) -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={"email": "test-offres-admin@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        yield client


@pytest.fixture(scope="module")
def non_admin_client() -> httpx.Client:
    _creer_utilisateur("test-offres-nonadmin@example.com", "bonmotdepasse", "utilisateur")
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={"email": "test-offres-nonadmin@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        yield client


def test_admin_can_create_offer_with_competences(
    admin_client: httpx.Client, admin_id: int
) -> None:
    response = admin_client.post(
        "/offres/create",
        data={
            "titre": "Dev Frontend Senior",
            "description": "Poste basé à Paris",
            "competences": "React, TypeScript , GraphQL",
        },
    )
    assert response.status_code == 302
    assert response.headers["location"] == "/offres"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM offres WHERE titre = %s", ("Dev Frontend Senior",)
            )
            offre_id = cur.fetchone()[0]
            cur.execute(
                "SELECT libelle FROM offre_competences WHERE offre_id = %s ORDER BY id",
                (offre_id,),
            )
            libelles = [row[0] for row in cur.fetchall()]
            assert libelles == ["React", "TypeScript", "GraphQL"]


def test_non_admin_cannot_create_offer(non_admin_client: httpx.Client) -> None:
    response = non_admin_client.post(
        "/offres/create",
        data={"titre": "Offre Non Autorisée", "competences": "PHP"},
    )
    assert response.status_code == 403

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM offres WHERE titre = %s", ("Offre Non Autorisée",)
            )
            assert cur.fetchone() is None


def test_create_offer_via_get_is_rejected(admin_client: httpx.Client) -> None:
    response = admin_client.get(
        "/offres/create", params={"titre": "Offre Via Get", "competences": "PHP"}
    )
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM offres WHERE titre = %s", ("Offre Via Get",))
            assert cur.fetchone() is None


def test_admin_can_update_offer_replacing_competences(
    admin_client: httpx.Client, admin_id: int
) -> None:
    offre_id = _creer_offre_directe("Offre A Modifier", admin_id, ["PHP", "MySQL"])

    response = admin_client.post(
        f"/offres/{offre_id}/modifier",
        data={
            "titre": "Offre Modifiée",
            "description": "Nouvelle description",
            "competences": "Python, Django",
        },
    )
    assert response.status_code == 302
    assert response.headers["location"] == "/offres"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT titre, description FROM offres WHERE id = %s", (offre_id,)
            )
            titre, description = cur.fetchone()
            assert titre == "Offre Modifiée"
            assert description == "Nouvelle description"

            cur.execute(
                "SELECT libelle FROM offre_competences WHERE offre_id = %s ORDER BY id",
                (offre_id,),
            )
            libelles = [row[0] for row in cur.fetchall()]
            assert libelles == ["Python", "Django"]


def test_modifier_via_get_is_rejected(admin_client: httpx.Client, admin_id: int) -> None:
    offre_id = _creer_offre_directe("Offre Get Modifier", admin_id, ["Java"])

    response = admin_client.get(
        f"/offres/{offre_id}/modifier", params={"titre": "Piraté"}
    )
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT titre FROM offres WHERE id = %s", (offre_id,))
            assert cur.fetchone()[0] == "Offre Get Modifier"


def test_admin_can_delete_offer_cascades_competences(
    admin_client: httpx.Client, admin_id: int
) -> None:
    offre_id = _creer_offre_directe("Offre A Supprimer", admin_id, ["Ruby", "Rails"])

    response = admin_client.post(f"/offres/{offre_id}/delete", data={})
    assert response.status_code == 302
    assert response.headers["location"] == "/offres"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM offres WHERE id = %s", (offre_id,))
            assert cur.fetchone() is None
            cur.execute(
                "SELECT id FROM offre_competences WHERE offre_id = %s", (offre_id,)
            )
            assert cur.fetchone() is None


def test_delete_via_get_is_rejected(admin_client: httpx.Client, admin_id: int) -> None:
    offre_id = _creer_offre_directe("Offre Get Delete", admin_id, ["Go"])

    response = admin_client.get(f"/offres/{offre_id}/delete")
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM offres WHERE id = %s", (offre_id,))
            assert cur.fetchone() is not None
