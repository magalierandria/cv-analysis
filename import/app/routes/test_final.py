from fastapi import APIRouter
router = APIRouter()

@router.get("/test")
async def test():
    return {"message": "Ceci est le NOUVEAU fichier"}