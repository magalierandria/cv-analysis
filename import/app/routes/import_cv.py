from fastapi import APIRouter, UploadFile, File, HTTPException
import pymupdf
import os

import re
import json
import psycopg2
from mistralai.client import Mistral

router = APIRouter()


def _extract_json(text: str) -> dict:
    text = text.strip()
    text = re.sub(r"^```(?:json)?\s*", "", text)
    text = re.sub(r"\s*```$", "", text)
    return json.loads(text.strip())


PROMPT_TEMPLATE = """\
Extrais les informations du CV suivant.
Réponds UNIQUEMENT avec un objet JSON valide, sans markdown, sans explication.
Structure exacte :
{{
  "nom": "string",
  "email": "string",
  "tel": "string",
  "titre": "string",
  "linkedin": "string",
  "description": "string",
  "experiences": [
    {{
      "entreprise": "string",
      "poste": "string",
      "secteur": "string",
      "date_debut": "YYYY-MM-DD ou null",
      "date_fin": "YYYY-MM-DD ou null",
      "description": "string",
      "competences": ["string"]
    }}
  ],
  "formations": [
    {{
      "intitule": "string",
      "etablissement": "string",
      "type": "diplôme ou certification ou null",
      "annee": 2024
    }}
  ]
}}

CV :
{texte}"""


@router.post("/import-cv")
async def import_cv(file: UploadFile = File(...)):
    try:
        content = await file.read()
        if len(content) == 0:
            raise HTTPException(status_code=400, detail="Le fichier est vide.")

        try:
            doc = pymupdf.open(stream=content, filetype="pdf")
            texte = "\n".join(page.get_text() for page in doc)
            doc.close()
        except Exception:
            raise HTTPException(status_code=422, detail="Impossible de lire le PDF.")

        client = Mistral(api_key=os.getenv("MISTRAL_API_KEY"))
        response = client.chat.complete(
            model="mistral-small-latest",
            messages=[{"role": "user", "content": PROMPT_TEMPLATE.format(texte=texte)}],
        )
        raw = response.choices[0].message.content
        print(f"DEBUG MISTRAL RAW: {raw[:300]}")

        try:
            ai_data = _extract_json(raw)
        except Exception as e:
            print(f"DEBUG JSON PARSE ERROR: {e} — raw: {raw[:300]}")
            raise HTTPException(status_code=422, detail="Mistral n'a pas renvoyé un JSON valide.")

        conn = psycopg2.connect(os.getenv("DATABASE_URL"))
        cur = conn.cursor()
        try:
            # Utilisateur système pour les imports sans authentification
            cur.execute(
                "INSERT INTO utilisateurs (email, mot_de_passe) VALUES (%s, %s) "
                "ON CONFLICT (email) DO UPDATE SET email = EXCLUDED.email RETURNING id",
                ("system@import", ""),
            )
            system_user_id = cur.fetchone()[0]

            cur.execute(
                """INSERT INTO profils (nom, email, linkedin, titre, description,
                       importe_par_user_id, pdf_source)
                   VALUES (%s, %s, %s, %s, %s, %s, %s)
                   RETURNING id""",
                (
                    ai_data.get("nom"),
                    ai_data.get("email"),
                    ai_data.get("linkedin"),
                    ai_data.get("titre"),
                    ai_data.get("description"),
                    system_user_id,
                    file.filename or "upload.pdf",
                ),
            )
            profil_id = cur.fetchone()[0]

            for i, exp in enumerate(ai_data.get("experiences", [])):
                cur.execute(
                    """INSERT INTO experiences
                           (profil_id, entreprise, poste, secteur,
                            date_debut, date_fin, description, ordre)
                       VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                       RETURNING id""",
                    (
                        profil_id,
                        exp.get("entreprise"),
                        exp.get("poste"),
                        exp.get("secteur"),
                        exp.get("date_debut") or None,
                        exp.get("date_fin") or None,
                        exp.get("description"),
                        i,
                    ),
                )
                exp_id = cur.fetchone()[0]
                for libelle in exp.get("competences", []):
                    cur.execute(
                        "INSERT INTO competences (experience_id, libelle) VALUES (%s, %s)",
                        (exp_id, libelle),
                    )

            for formation in ai_data.get("formations", []):
                type_val = formation.get("type")
                if type_val not in ("diplôme", "certification"):
                    type_val = None
                cur.execute(
                    """INSERT INTO formations
                           (profil_id, intitule, etablissement, type, annee)
                       VALUES (%s, %s, %s, %s, %s)""",
                    (
                        profil_id,
                        formation.get("intitule"),
                        formation.get("etablissement"),
                        type_val,
                        formation.get("annee"),
                    ),
                )

            conn.commit()
        except Exception as e:
            conn.rollback()
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
