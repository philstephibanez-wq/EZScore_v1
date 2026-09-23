from ezscore.persistence.db import init_db


def migrate() -> None:
    init_db()
