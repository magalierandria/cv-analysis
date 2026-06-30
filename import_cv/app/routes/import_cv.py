import os
import io
import json
import pymupdf
from typing import List, Optional
from fastapi import APIRouter, UploadFile, File, HTTPException
from pydantic import BaseModel, EmailStr
from mistralai.client import Mistral
from dotenv import load_dotenv
import psycopg2
from psycopg2.extras import RealDictCursor

load_dotenv()

router = APIRouter()

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
async def extraire_donnees_cv(texte: str) -> CVExtraction:
    client = Mistral(api_key=os.getenv("MISTRAL_API_KEY"))
    prompt = f"Extrais les informations du CV suivant au format JSON strict.\n{texte}"
    
    response = await client.chat.complete_async(
        model="mistral-small-latest",
        messages=[{"role": "user", "content": prompt}],
        response_format={"type": "json_object"}
    )
    
    data = json.loads(response.choices[0].message.content)
    return CVExtraction(**data)

@router.post("/import-cv")
async def import_cv(file: UploadFile = File(...)):
    if not file.filename.endswith('.pdf'):
        raise HTTPException(status_code=400, detail="Seuls les PDF sont acceptés")

    # Lecture sécurisée du contenu
    content = await file.read()
    try:
        with pymupdf.open(stream=io.BytesIO(content), filetype="pdf") as doc:
            texte = "\n".join(page.get_text() for page in doc)
    except Exception:
        raise HTTPException(status_code=422, detail="Impossible de lire le PDF.")

    # Extraction via IA
    try:
        ai_data = await extraire_donnees_cv(texte)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur lors de l'analyse : {str(e)}")

    # Insertion en base de données
    conn = psycopg2.connect(os.getenv("DATABASE_URL"))
    try:
        with conn:
            with conn.cursor() as cur:
                # 1. User système
                cur.execute("INSERT INTO utilisateurs (email, mot_de_passe) VALUES (%s, %s) ON CONFLICT (email) DO UPDATE SET email = EXCLUDED.email RETURNING id", ("system@import", ""))
                system_user_id = cur.fetchone()[0]

                # 2. Profil
                cur.execute("""INSERT INTO profils (nom, email, linkedin, titre, description, importe_par_user_id, pdf_source)
                               VALUES (%s, %s, %s, %s, %s, %s, %s) RETURNING id""",
                            (ai_data.nom, ai_data.email, ai_data.linkedin, ai_data.titre, ai_data.description, system_user_id, file.filename))
                profil_id = cur.fetchone()[0]

                # 3. Expériences & Compétences
                for exp in ai_data.experiences:
                    cur.execute("""INSERT INTO experiences (profil_id, entreprise, poste, secteur, date_debut, date_fin, description)
                                   VALUES (%s, %s, %s, %s, %s, %s, %s) RETURNING id""",
                                (profil_id, exp.entreprise, exp.poste, exp.secteur, exp.date_debut, exp.date_fin, exp.description))
                    exp_id = cur.fetchone()[0]
                    for libelle in exp.competences:
                        cur.execute("INSERT INTO competences (experience_id, libelle) VALUES (%s, %s)", (exp_id, libelle))

                # 4. Formations
                for form in ai_data.formations:
                    cur.execute("INSERT INTO formations (profil_id, intitule, etablissement, type, annee) VALUES (%s, %s, %s, %s, %s)",
                                (profil_id, form.intitule, form.etablissement, form.type, form.annee))
        
        return {"status": "success", "profil_id": profil_id}
    
    except Exception as e:
        import traceback
        traceback.print_exc()
        conn.rollback()
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        conn.close()