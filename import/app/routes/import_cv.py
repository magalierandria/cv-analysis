from fastapi import APIRouter, UploadFile, File, HTTPException
import pymupdf
import os
import psycopg2
import json
from mistralai.client import Mistral

router = APIRouter()

@router.post("/import-cv")
async def import_cv(file: UploadFile = File(...)):
    try:
        # 1. Lecture du fichier binaire
        content = await file.read()
        if len(content) == 0:
            raise HTTPException(status_code=400, detail="Le fichier est vide.")

        # 2. Extraction du texte via PyMuPDF
        try:
            doc = pymupdf.open(stream=content, filetype="pdf")
            texte = ""
            for page in doc:
                texte += page.get_text()
            doc.close()
        except Exception as e:
            raise HTTPException(status_code=422, detail="Impossible de lire le PDF.")

        # 3. Analyse avec Mistral AI
        client = Mistral(api_key=os.getenv("MISTRAL_API_KEY"))
        prompt = f"Extrais les infos du CV en JSON (nom, email, tel) : {texte}"
        
        response = client.chat.complete(
            model="mistral-small-latest",
            messages=[{"role": "user", "content": prompt}]
        )
        ai_data = json.loads(response.choices[0].message.content)

        # 4. Enregistrement en base de données
        conn = psycopg2.connect(os.getenv("DATABASE_URL"))
        cur = conn.cursor()
        try:
            cur.execute(
                "INSERT INTO candidats (nom, email) VALUES (%s, %s)",
                (ai_data.get("nom"), ai_data.get("email"))
            )
            conn.commit()
        except Exception as e:
            conn.rollback()
            # Gestion explicite du doublon
            if "unique constraint" in str(e).lower():
                raise HTTPException(status_code=409, detail="Doublon : Ce candidat existe déjà.")
            raise e
        finally:
            cur.close()
            conn.close()

        return {"data": ai_data, "message": "Succès"}

    except HTTPException as he:
        raise he
    except Exception as e:
        print(f"DEBUG ERREUR: {str(e)}")
        raise HTTPException(status_code=500, detail="Une erreur technique est survenue.")