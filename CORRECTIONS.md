# Corrections — branche `feature_import`

## 1. Import path cassé dans Docker
**Fichier :** `import/app/main.py:3`

L'import `from routes import import_cv` ne fonctionne pas quand le conteneur lance uvicorn avec `WORKDIR /app` car `routes` n'est pas dans `sys.path`. Il faut utiliser le chemin absolu depuis le package `app`.

```python
# Avant
from routes import import_cv

# Après
from app.routes import import_cv
```

---

## 2. Service `migrate` commenté — schéma DB absent au démarrage
**Fichier :** `docker-compose.yml`

Les services `web` et `import` démarrent sans attendre les migrations. Sur un environnement frais (CI, nouveau dev, `docker volume rm`), la première requête DB tombe avec `relation "..." does not exist`.

Deux options :

**Option A — Rétablir le service migrate (recommandé)**
```yaml
  migrate:
    image: postgres:16-alpine
    depends_on:
      db:
        condition: service_healthy
    volumes:
      - ./migrations:/migrations:ro
      - ./scripts/run-migrations.sh:/run-migrations.sh:ro
    environment:
      PGHOST: db
      PGUSER: cv
      PGPASSWORD: cv
      PGDATABASE: cv_analysis
    entrypoint: ["/bin/sh", "/run-migrations.sh"]

  web:
    depends_on:
      db:
        condition: service_healthy
      migrate:
        condition: service_completed_successfully

  import:
    depends_on:
      db:
        condition: service_healthy
      migrate:
        condition: service_completed_successfully
```

**Option B — Migrations intégrées au démarrage de l'app**
Si les migrations sont gérées par Alembic ou similaire, les lancer dans l'entrypoint du service `import` avant uvicorn.

---

## 3. Appel Mistral synchrone dans un endpoint async
**Fichier :** `import/app/routes/import_cv.py:50`

`extraire_donnees_cv()` est synchrone et fait un appel HTTP bloquant (`client.chat.complete`). Appelée directement depuis `async def`, elle bloque l'event loop entier pendant la durée de l'appel Mistral (5–30s). Toutes les autres requêtes sont gelées pendant ce temps.

```python
# Avant
data_json = extraire_donnees_cv(texte_complet)

# Après — offloader dans un thread pour libérer l'event loop
import asyncio

data_json = await asyncio.to_thread(extraire_donnees_cv, texte_complet)
```

Même logique pour `fitz.open()` et `page.get_text()` qui sont CPU-bound :
```python
# Extraire le texte dans un thread aussi
def lire_pdf(content: bytes) -> str:
    doc = fitz.open(stream=content, filetype="pdf")
    try:
        return "\n".join([page.get_text() for page in doc])
    finally:
        doc.close()

texte_complet = await asyncio.to_thread(lire_pdf, content)
```

---

## 4. `fitz.Document` non fermé en cas d'exception
**Fichier :** `import/app/routes/import_cv.py:45-47`

Si `page.get_text()` lève une exception, `doc.close()` n'est jamais appelé → fuite de descripteurs de fichiers sous charge.

```python
# Avant
doc = fitz.open(stream=content, filetype="pdf")
texte_complet = "\n".join([page.get_text() for page in doc])
doc.close()

# Après
doc = fitz.open(stream=content, filetype="pdf")
try:
    texte_complet = "\n".join([page.get_text() for page in doc])
finally:
    doc.close()
```

> Note : si tu appliques la correction #3, la fonction `lire_pdf` avec `try/finally` couvre déjà ce cas.

---

## 5. Validation du type de fichier supprimée
**Fichier :** `import/app/routes/import_cv.py`

L'ancien `main.py` validait l'extension `.pdf` et retournait un 400 explicite. Ce guard a été supprimé sans être remplacé.

```python
@router.post("/import-cv")
async def import_cv(file: UploadFile = File(...)):
    # Ajouter cette validation en tête
    if not file.filename or not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Seuls les fichiers PDF sont acceptés.")

    # ... reste de la fonction
```

---

## 6. Clé API Mistral silencieusement `None`
**Fichier :** `import/app/routes/import_cv.py:14`

`os.getenv("MISTRAL_API_KEY")` retourne `None` si la variable d'environnement est absente. Le service démarre sans erreur puis crash à la première requête avec un message d'auth opaque.

```python
# Avant
client = Mistral(api_key=os.getenv("MISTRAL_API_KEY"))

# Après — valider au démarrage
api_key = os.getenv("MISTRAL_API_KEY")
if not api_key:
    raise RuntimeError("La variable d'environnement MISTRAL_API_KEY est manquante.")
client = Mistral(api_key=api_key)
```

---

## 7. `str(e)` exposé dans la réponse HTTP 500
**Fichier :** `import/app/routes/import_cv.py:59`

Le message d'erreur brut est retourné dans le corps de la réponse. Il peut contenir des informations sensibles (clé API, connection string, chemin de fichier).

```python
# Avant
raise HTTPException(status_code=500, detail=f"Erreur lors de l'analyse : {str(e)}")

# Après — logger l'erreur serveur, retourner un message générique
import logging
logger = logging.getLogger(__name__)

# Dans le except :
logger.exception("Erreur lors du traitement du CV")
raise HTTPException(status_code=500, detail="Erreur interne lors de l'analyse du CV.")
```

---

## 8. Pas de limite de taille sur l'upload
**Fichier :** `import/app/routes/import_cv.py:44`

`await file.read()` charge tout le fichier en mémoire sans limite. Un fichier de plusieurs centaines de Mo peut provoquer un OOM.

```python
MAX_FILE_SIZE = 10 * 1024 * 1024  # 10 MB

content = await file.read()
if len(content) > MAX_FILE_SIZE:
    raise HTTPException(status_code=413, detail="Fichier trop volumineux (max 10 MB).")
```

---

## 9. `response.choices[0]` sans guard
**Fichier :** `import/app/routes/import_cv.py:38`

Si Mistral retourne une liste `choices` vide (rate limit, content filter), `choices[0]` lève un `IndexError` non géré.

```python
# Avant
return json.loads(response.choices[0].message.content)

# Après
if not response.choices:
    raise ValueError("Mistral n'a retourné aucun résultat (choices vide).")
return json.loads(response.choices[0].message.content)
```

---

## 10. `test_final.py` — code mort à supprimer
**Fichier :** `import/app/routes/test_final.py`

Ce fichier définit un router qui n'est jamais monté dans `main.py`. Le endpoint `/test` est inaccessible. À supprimer.

```bash
git rm import/app/routes/test_final.py
```

---

## 11. Modèle Mistral déprécié
**Fichier :** `import/app/routes/import_cv.py:15`

`mistral-tiny` est un ancien nom de modèle déprécié.

```python
# Avant
MODEL_NAME = "mistral-tiny"

# Après
MODEL_NAME = "mistral-small-latest"
```

---

## Récapitulatif des priorités

| # | Priorité | Fichier | Impact |
|---|----------|---------|--------|
| 1 | Bloquant | `import/app/main.py` | Service ne démarre pas en Docker |
| 2 | Bloquant | `docker-compose.yml` | Schéma DB absent, crash au démarrage |
| 3 | Haute | `import/app/routes/import_cv.py` | Event loop bloqué, service non scalable |
| 4 | Haute | `import/app/routes/import_cv.py` | Fuite de file descriptors sous charge |
| 5 | Haute | `import/app/routes/import_cv.py` | N'importe quel type de fichier accepté |
| 6 | Haute | `import/app/routes/import_cv.py` | Crash opaque si clé API manquante |
| 7 | Haute | `import/app/routes/import_cv.py` | Fuite d'informations sensibles |
| 8 | Moyenne | `import/app/routes/import_cv.py` | Risque OOM sur gros fichiers |
| 9 | Moyenne | `import/app/routes/import_cv.py` | IndexError non géré |
| 10 | Basse | `import/app/routes/test_final.py` | Code mort |
| 11 | Basse | `import/app/routes/import_cv.py` | Modèle déprécié |
