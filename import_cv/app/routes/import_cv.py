import io
import json
import logging
import os
import re
import pymupdf
from typing import List, Optional
from fastapi import APIRouter, BackgroundTasks, File, Form, HTTPException, UploadFile
from pydantic import BaseModel
from mistralai.client import Mistral
from dotenv import load_dotenv
import psycopg2

from app.config import DATABASE_URL, PDF_DIR

load_dotenv()

logger = logging.getLogger(__name__)

router = APIRouter()

MAX_FILE_SIZE = 10 * 1024 * 1024  # 10 MB

MISTRAL_API_KEY = os.getenv("MISTRAL_API_KEY")
if not MISTRAL_API_KEY:
    raise RuntimeError("La variable d'environnement MISTRAL_API_KEY est manquante.")

mistral_client = Mistral(api_key=MISTRAL_API_KEY)


# --- Schémas Pydantic pour la validation ---
class Experience(BaseModel):
    entreprise: str
    poste: str
    secteur: Optional[str] = None
    date_debut: Optional[str] = None
    date_fin: Optional[str] = None
    description: Optional[str] = None
    competences: List[str] = []


class Formation(BaseModel):
    intitule: str
    etablissement: str
    type: Optional[str] = None
    annee: Optional[int] = None


class CVExtraction(BaseModel):
    nom: str
    email: Optional[str] = None
    tel: Optional[str] = None
    titre: Optional[str] = None
    linkedin: Optional[str] = None
    description: Optional[str] = None
    experiences: List[Experience] = []
    formations: List[Formation] = []


# --- Fonction IA ---
CV_EXTRACTION_SCHEMA = CVExtraction.model_json_schema()


_DATE_ANNEE = re.compile(r"^\d{4}$")
_DATE_ANNEE_MOIS = re.compile(r"^\d{4}-\d{2}$")
_DATE_COMPLETE = re.compile(r"^\d{4}-\d{2}-\d{2}$")

_TYPES_FORMATION_VALIDES = {"diplôme", "certification"}


def _normaliser_type_formation(valeur: Optional[str]) -> Optional[str]:
    """La colonne formations.type est contrainte à 'diplôme'/'certification'.
    L'IA renvoie parfois une formulation libre (ex: "Formation initiale") :
    on la ramène à NULL plutôt que de faire échouer tout l'import."""
    if not valeur:
        return None
    valeur = valeur.strip().lower()
    return valeur if valeur in _TYPES_FORMATION_VALIDES else None


def _parser_date(valeur: Optional[str]) -> Optional[str]:
    """Normalise les dates extraites par l'IA vers un format DATE Postgres.
    Le CV peut contenir "2021", "2021-10" ou du texte libre ("Aujourd'hui",
    "En cours") pour un poste toujours actif : ce texte n'est pas une date
    valide, on le convertit en NULL (déjà interprété comme "Présent" par
    la vue)."""
    if not valeur:
        return None
    valeur = valeur.strip()
    if _DATE_COMPLETE.match(valeur):
        return valeur
    if _DATE_ANNEE_MOIS.match(valeur):
        return f"{valeur}-01"
    if _DATE_ANNEE.match(valeur):
        return f"{valeur}-01-01"
    return None


def _nettoyer_cles(valeur):
    """Mistral renvoie parfois les clés JSON avec une espace finale parasite
    (ex: "email " au lieu de "email"), ce qui fait que Pydantic les ignore
    silencieusement en tant que clés inconnues. On les normalise ici."""
    if isinstance(valeur, dict):
        return {cle.strip(): _nettoyer_cles(v) for cle, v in valeur.items()}
    if isinstance(valeur, list):
        return [_nettoyer_cles(item) for item in valeur]
    return valeur


async def extraire_donnees_cv(texte: str) -> CVExtraction:
    prompt = f"Extrais les informations du CV suivant au format JSON strict.\n{texte}"

    response = await mistral_client.chat.complete_async(
        model="mistral-small-latest",
        messages=[{"role": "user", "content": prompt}],
        response_format={
            "type": "json_schema",
            "json_schema": {
                "name": "cv_extraction",
                "schema": CV_EXTRACTION_SCHEMA,
                "strict": True,
            },
        },
    )

    data = _nettoyer_cles(json.loads(response.choices[0].message.content))
    return CVExtraction(**data)


def _lire_texte_pdf(chemin_pdf: str) -> str:
    with pymupdf.open(chemin_pdf) as doc:
        return "\n".join(page.get_text() for page in doc)


def _marquer_job_echoue(job_id: int, message: str) -> None:
    conn = psycopg2.connect(DATABASE_URL)
    try:
        with conn:
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE import_jobs SET statut = 'échoué', error_message = %s WHERE id = %s",
                    (message, job_id),
                )
    finally:
        conn.close()


async def traiter_import(
    job_id: int, chemin_pdf: str, nom_fichier_stocke: str, importe_par_user_id: int
) -> None:
    try:
        texte = _lire_texte_pdf(chemin_pdf)
    except Exception:
        logger.exception("Erreur lors de la lecture du PDF pour le job %s", job_id)
        _marquer_job_echoue(job_id, "Impossible de lire le PDF.")
        return

    if not texte.strip():
        _marquer_job_echoue(job_id, "PDF vide ou texte non extractible.")
        return

    try:
        ai_data = await extraire_donnees_cv(texte)
    except Exception:
        logger.exception("Erreur lors de l'analyse IA du CV pour le job %s", job_id)
        _marquer_job_echoue(job_id, "Erreur lors de l'analyse du CV.")
        return

    conn = psycopg2.connect(DATABASE_URL)
    try:
        with conn:
            with conn.cursor() as cur:
                profil_id = None
                if ai_data.email:
                    cur.execute("SELECT id FROM profils WHERE email = %s", (ai_data.email,))
                    ligne_existante = cur.fetchone()
                    if ligne_existante:
                        profil_id = ligne_existante[0]

                if profil_id is not None:
                    # CV déjà connu pour cet email : on met à jour le profil existant
                    # avec la nouvelle version plutôt que d'échouer sur la contrainte
                    # unique. Le contenu ayant changé, on remet le profil "à valider".
                    cur.execute(
                        """UPDATE profils
                           SET nom = %s, linkedin = %s, titre = %s, description = %s,
                               importe_par_user_id = %s, pdf_source = %s,
                               statut = 'à valider', valide_par_user_id = NULL, date_validation = NULL
                           WHERE id = %s""",
                        (
                            ai_data.nom,
                            ai_data.linkedin,
                            ai_data.titre,
                            ai_data.description,
                            importe_par_user_id,
                            nom_fichier_stocke,
                            profil_id,
                        ),
                    )
                    cur.execute("DELETE FROM experiences WHERE profil_id = %s", (profil_id,))
                    cur.execute("DELETE FROM formations WHERE profil_id = %s", (profil_id,))
                else:
                    cur.execute(
                        """INSERT INTO profils (nom, email, linkedin, titre, description, importe_par_user_id, pdf_source)
                           VALUES (%s, %s, %s, %s, %s, %s, %s) RETURNING id""",
                        (
                            ai_data.nom,
                            ai_data.email,
                            ai_data.linkedin,
                            ai_data.titre,
                            ai_data.description,
                            importe_par_user_id,
                            nom_fichier_stocke,
                        ),
                    )
                    profil_id = cur.fetchone()[0]

                for exp in ai_data.experiences:
                    cur.execute(
                        """INSERT INTO experiences (profil_id, entreprise, poste, secteur, date_debut, date_fin, description)
                           VALUES (%s, %s, %s, %s, %s, %s, %s) RETURNING id""",
                        (
                            profil_id,
                            exp.entreprise,
                            exp.poste,
                            exp.secteur,
                            _parser_date(exp.date_debut),
                            _parser_date(exp.date_fin),
                            exp.description,
                        ),
                    )
                    exp_id = cur.fetchone()[0]
                    for libelle in exp.competences:
                        cur.execute(
                            "INSERT INTO competences (experience_id, libelle) VALUES (%s, %s)",
                            (exp_id, libelle),
                        )

                for form in ai_data.formations:
                    cur.execute(
                        "INSERT INTO formations (profil_id, intitule, etablissement, type, annee) VALUES (%s, %s, %s, %s, %s)",
                        (profil_id, form.intitule, form.etablissement, _normaliser_type_formation(form.type), form.annee),
                    )

                cur.execute(
                    "UPDATE import_jobs SET statut = 'réussi', profile_id = %s WHERE id = %s",
                    (profil_id, job_id),
                )
    except Exception:
        logger.exception("Erreur lors de l'insertion du profil pour le job %s", job_id)
        conn.rollback()
        _marquer_job_echoue(job_id, "Erreur interne lors de l'enregistrement du profil.")
    finally:
        conn.close()


@router.post("/import", status_code=202)
async def import_cv(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    importe_par_user_id: int = Form(...),
):
    if not file.filename or not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Seuls les fichiers PDF sont acceptés.")

    content = await file.read()
    if len(content) > MAX_FILE_SIZE:
        raise HTTPException(status_code=413, detail="Fichier trop volumineux (max 10 MB).")

    conn = psycopg2.connect(DATABASE_URL)
    try:
        with conn:
            with conn.cursor() as cur:
                cur.execute(
                    "INSERT INTO import_jobs (statut, importe_par_user_id, nom_fichier) VALUES ('en cours', %s, %s) RETURNING id",
                    (importe_par_user_id, file.filename),
                )
                job_id = cur.fetchone()[0]
    finally:
        conn.close()

    # Le nom de fichier fourni par le client (Content-Disposition) n'est pas
    # fiable : il peut contenir des séparateurs de répertoire ou des séquences
    # ".." (path traversal). On ne garde que le nom de base pour construire le
    # chemin sur le volume partagé ; le nom original brut reste stocké tel
    # quel dans la colonne `import_jobs.nom_fichier` (usage d'affichage
    # uniquement, jamais utilisé comme chemin fichier).
    nom_fichier_assaini = os.path.basename(file.filename)
    nom_fichier_stocke = f"{job_id}_{nom_fichier_assaini}"
    chemin_pdf = os.path.join(PDF_DIR, nom_fichier_stocke)
    try:
        with open(chemin_pdf, "wb") as f:
            f.write(content)
    except OSError:
        logger.exception("Impossible d'écrire le PDF pour le job %s", job_id)
        _marquer_job_echoue(job_id, "Erreur lors de la sauvegarde du fichier.")
        raise HTTPException(status_code=500, detail="Erreur lors de la sauvegarde du fichier.")

    background_tasks.add_task(
        traiter_import, job_id, chemin_pdf, nom_fichier_stocke, importe_par_user_id
    )

    return {"job_id": job_id}
