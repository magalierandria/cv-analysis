import os

from fastapi import APIRouter

from app.config import PDF_DIR
from app.db import check_bdd_connection

router = APIRouter()


@router.get("/health")
def health() -> dict[str, str | bool]:
    return {
        "status": "ok",
        "bdd_connected": check_bdd_connection(),
        "pdf_volume_mounted": os.path.isdir(PDF_DIR) and os.access(PDF_DIR, os.W_OK),
    }
