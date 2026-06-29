import os
import traceback
from fastapi import APIRouter, UploadFile, File, HTTPException
from dotenv import load_dotenv

# Charger les variables d'environnement
load_dotenv()

router = APIRouter()

@router.post("/import-cv")
async def import_cv(file: UploadFile = File(...)):
    print("--- Début de la fonction import_cv ---")
    try:
        # 1. Vérification du fichier
        if not file.filename.endswith('.pdf'):
            raise HTTPException(status_code=400, detail="Seuls les fichiers PDF sont acceptés")

        # 2. Lecture du contenu
        content = await file.read()
        print(f"--- Fichier '{file.filename}' reçu, taille : {len(content)} octets ---")

        # --- ICI TU AJOUTERAS TON LOGIQUE D'ANALYSE MISTRAL ---
        # Exemple de log pour vérifier que Mistral est prêt
        api_key = os.getenv("MISTRAL_API_KEY")
        print(f"--- Clé API chargée : {api_key is not None} ---")

        return {
            "status": "success",
            "message": f"Fichier {file.filename} analysé avec succès"
        }

    except Exception as e:
        print("--- ERREUR DÉTECTÉE ---")
        traceback.print_exc() # Affiche l'erreur complète dans le terminal VS Code
        raise HTTPException(status_code=500, detail=str(e))