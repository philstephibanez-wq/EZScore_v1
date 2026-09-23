from pathlib import Path

APP_DIR = Path(__file__).resolve().parents[2]
DATA_DIR = APP_DIR / "data"
DB_PATH = DATA_DIR / "ezscore_v1.db"
TEMPLATES_DIR = APP_DIR / "templates"
