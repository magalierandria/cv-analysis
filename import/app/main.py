import os

from fastapi import FastAPI

app = FastAPI(title="Import CV")


@app.get("/health")
def health() -> dict[str, str | bool]:
  pdf_dir = "/data/pdfs"
  return {
    "status": "ok",
    "pdf_volume_mounted": os.path.isdir(pdf_dir) and os.access(pdf_dir, os.W_OK),
  }
