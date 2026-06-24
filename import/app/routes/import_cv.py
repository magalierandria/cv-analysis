import asyncio
import logging
import os
import json
from fastapi import APIRouter, UploadFile, File, HTTPException
import fitz
from mistralai.client import Mistral
from dotenv import load_dotenv

load_dotenv()

logger = logging.getLogger(__name__)

router = APIRouter()

api_key = os.getenv("MISTRAL_API_KEY")
if not api_key:
    raise RuntimeError("La variable d'environnement MISTRAL_API_KEY est manquante.")
client = Mistral(api_key=api_key)

MODEL_NAME = "mistral-small-latest"
MAX_FILE_SIZE = 10 * 1024 * 1024  # 10 MB


def _lire_pdf(content: bytes) -> str:
    doc = fitz.open(stream=content, filetype="pdf")
    try:
        return "\n".join([page.get_text() for page in doc])
    finally:
        doc.close()


def _extraire_donnees_cv(texte_cv: str) -> dict:
    prompt = f"""
    Analyse ce CV et extrais les informations suivantes en format JSON strict :
    {{
        "nom": "string",
        "email": "string",
        "telephone": "string",
        "competences": ["liste de compétences techniques"],
        "experience": ["liste des postes avec entreprise et dates"],
        "formation": ["liste des diplômes avec établissement et année"],
        "langues": ["liste des langues avec niveau"],
        "certifications": ["liste des certifications avec année"]
    }}
    Si une information est absente du CV, utilise une liste vide [] ou une chaîne vide "".

    Texte du CV : {texte_cv}
    """

    response = client.chat.complete(
        model=MODEL_NAME,
        messages=[{"role": "user", "content": prompt}],
        response_format={"type": "json_object"}
    )
    if not response.choices:
        raise ValueError("Mistral n'a retourné aucun résultat (choices vide).")
    return json.loads(response.choices[0].message.content)


@router.post("/import-cv")
async def import_cv(file: UploadFile = File(...)):
    if not file.filename or not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Seuls les fichiers PDF sont acceptés.")

    content = await file.read()

    if len(content) > MAX_FILE_SIZE:
        raise HTTPException(status_code=413, detail="Fichier trop volumineux (max 10 MB).")

    try:
        texte_complet = await asyncio.to_thread(_lire_pdf, content)
        data_json = await asyncio.to_thread(_extraire_donnees_cv, texte_complet)
        return {
            "message": "CV analysé avec succès",
            "donnees_extraites": data_json
        }
    except Exception:
        logger.exception("Erreur lors du traitement du CV")
        raise HTTPException(status_code=500, detail="Erreur interne lors de l'analyse du CV.")
