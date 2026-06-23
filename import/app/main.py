from fastapi import FastAPI, UploadFile, File, HTTPException
import fitz  # PyMuPDF
import io

app = FastAPI()
@app.get("/")
def read_root():
    return {"message": "Bravo ! FastAPI fonctionne."}

@app.get("/health")
def health_check():
    return {"status": "ok", "service": "cv-analysis"}

# --- Endpoint d'import ---
@app.post("/import-cv")
async def import_cv(file: UploadFile = File(...)):
    # Vérification du format
    if not file.filename.endswith('.pdf'):
        raise HTTPException(status_code=400, detail="Seuls les fichiers PDF sont acceptés.")

    try:
        content = await file.read()
        doc = fitz.open(stream=content, filetype="pdf")
        
        texte_extraits = [page.get_text() for page in doc]
        texte_complet = "\n".join(texte_extraits)
        doc.close()

        return {
            "message": "CV importé avec succès",
            "nom_fichier": file.filename,
            "contenu": texte_complet
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur lors du traitement : {str(e)}")