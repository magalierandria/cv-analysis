import psycopg

from app.config import DATABASE_URL


def connect() -> psycopg.Connection:
    return psycopg.connect(DATABASE_URL)


def check_bdd_connection() -> bool:
    try:
        with connect() as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT 1")
                cur.fetchone()
        return True
    except psycopg.Error:
        return False
