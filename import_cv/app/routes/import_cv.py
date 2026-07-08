import io
import json
import logging
import os
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

    data = json.loads(response.choices[0].message.content)
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
                            exp.date_debut,
                            exp.date_fin,
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
                        (profil_id, form.intitule, form.etablissement, form.type, form.annee),
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
