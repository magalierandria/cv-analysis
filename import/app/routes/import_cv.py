import os
import json
from fastapi import APIRouter, UploadFile, File, HTTPException
import fitz
from mistralai.client import Mistral
from dotenv import load_dotenv

# Charger les variables du fichier .env
load_dotenv()

router = APIRouter()

# Configuration Mistral
client = Mistral(api_key=os.getenv("MISTRAL_API_KEY"))
MODEL_NAME = "mistral-tiny"

def extraire_donnees_cv(texte_cv: str):
    """Envoie le texte au LLM pour obtenir un JSON structuré."""
    prompt = f"""
    Analyse ce CV et extrais les informations suivantes en format JSON strict :
    {{
        "nom": "string",
        "email": "string",
        "telephone": "string",
        "competences": ["liste"],
        "experience": ["liste"]
    }}
    
    Texte du CV : {texte_cv}
    """
    
    response = client.chat.complete(
        model=MODEL_NAME,
        messages=[{"role": "user", "content": prompt}],
        response_format={"type": "json_object"}
    )
    # On retourne le contenu JSON analysé
    return json.loads(response.choices[0].message.content)

@router.post("/import-cv")
async def import_cv(file: UploadFile = File(...)):
    try:
        # 1. Lecture du PDF
        content = await file.read()
        doc = fitz.open(stream=content, filetype="pdf")
        texte_complet = "\n".join([page.get_text() for page in doc])
        doc.close()

        # 2. Appel à l'IA pour structurer (cette ligne est la clé !)
        data_json = extraire_donnees_cv(texte_complet)
        
        # 3. Retourne les données formatées en JSON par Mistral
        return {
            "message": "CV analysé avec succès",
            "donnees_extraites": data_json
        }
    except Exception as e:
        # Si une erreur survient (ex: clé API invalide, erreur Mistral)
        raise HTTPException(status_code=500, detail=f"Erreur lors de l'analyse : {str(e)}")