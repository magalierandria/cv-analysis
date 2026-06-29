from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
# On importe le routeur depuis le fichier import_cv.py situé dans le dossier routes
from routes import import_cv

# 1. Initialisation de l'application FastAPI
app = FastAPI(title="CV-Analysis API")

# 2. Configuration du Middleware CORS
# Cela autorise votre interface web (dashboard.html) à communiquer avec l'API
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Autorise toutes les origines
    allow_credentials=True,
    allow_methods=["*"],  # Autorise toutes les méthodes (POST, GET, etc.)
    allow_headers=["*"],  # Autorise tous les en-têtes
)

# 3. Inclusion du routeur
# Cela lie les fonctions définies dans import_cv.py à l'application principale
app.include_router(import_cv.router)

# 4. Route de test racine (optionnel)
@app.get("/")
async def root():
    return {"message": "API CV-Analysis est en ligne"}