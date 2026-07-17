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


def _creer_profil_avec_competences(
    nom: str, email: str, user_id: int, competences: list[str]
) -> int:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO profils (nom, email, statut, importe_par_user_id, pdf_source)
                VALUES (%s, %s, 'validé', %s, 'test.pdf')
                ON CONFLICT (email) DO UPDATE SET nom = EXCLUDED.nom
                RETURNING id
                """,
                (nom, email, user_id),
            )
            profil_id = cur.fetchone()[0]
            cur.execute(
                "INSERT INTO experiences (profil_id, entreprise, poste) VALUES (%s, %s, %s) RETURNING id",
                (profil_id, "ACME", "Dev"),
            )
            experience_id = cur.fetchone()[0]
            for comp in competences:
                cur.execute(
                    "INSERT INTO competences (experience_id, libelle) VALUES (%s, %s)",
                    (experience_id, comp),
                )
        conn.commit()
    return profil_id


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
def user_id() -> int:
    return _creer_utilisateur(
        "test-offres-matching@example.com", "bonmotdepasse", "utilisateur"
    )


@pytest.fixture(scope="module")
def web_client(user_id: int) -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={
                "email": "test-offres-matching@example.com",
                "mot_de_passe": "bonmotdepasse",
            },
        )
        yield client


def test_matching_scores_and_ordering(web_client: httpx.Client, user_id: int) -> None:
    profil_bas = _creer_profil_avec_competences(
        "Profil Match Bas", "test-match-bas@example.com", user_id, ["React", "TypeScript"]
    )
    profil_haut = _creer_profil_avec_competences(
        "Profil Match Haut",
        "test-match-haut@example.com",
        user_id,
        ["React", "TypeScript", "GraphQL", "Jest"],
    )
    offre_id = _creer_offre_directe(
        "Offre Matching Test", user_id, ["React", "TypeScript", "GraphQL", "Jest"]
    )

    response = web_client.get(f"/offres/{offre_id}")
    assert response.status_code == 200

    texte = response.text
    assert "100%" in texte
    assert "50%" in texte
    # Le profil à 100% doit apparaître avant celui à 50% (ordre décroissant).
    assert texte.index("Profil Match Haut") < texte.index("Profil Match Bas")


def test_offre_detail_unknown_id_returns_404(web_client: httpx.Client) -> None:
    response = web_client.get("/offres/999999999")
    assert response.status_code == 404
