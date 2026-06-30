
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
import uvicorn
from app.routes import import_cv

# 1. Initialisation de l'application FastAPI
app = FastAPI(title="CV-Analysis API")

# 2. Configuration du Middleware CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"], 
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# 3. Inclusion du routeur
app.include_router(import_cv.router)

# 4. Route de test
@app.get("/")
def read_root():
    return {"message": "Serveur opérationnel."}

if __name__ == "__main__":
    uvicorn.run("main:app", host="127.0.0.1", port=8000, reload=True)