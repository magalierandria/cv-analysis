from fastapi import FastAPI
import uvicorn
from routes import import_cv

app = FastAPI(title="CV-Analysis: Analyseur de CV avec Mistral AI")

app.include_router(import_cv.router)

@app.get("/")
def read_root():
    return {"message": "Serveur opérationnel."}

if __name__ == "__main__":
    uvicorn.run("main:app", host="127.0.0.1", port=8000, reload=True)