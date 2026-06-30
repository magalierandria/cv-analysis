import os

DATABASE_URL = os.environ.get(
    "DATABASE_URL", "postgresql://cv:cv@localhost:5432/cv_analysis"
)
PDF_DIR = "/data/pdfs"
