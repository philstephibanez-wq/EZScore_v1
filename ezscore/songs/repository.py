from __future__ import annotations

from ezscore.persistence.db import connection

MOCK_SONGS = [
    ("Je te donne", "Jean-Jacques Goldman / Michael Jones"),
    ("La Bohème", "Charles Aznavour"),
    ("Tombe la neige", "Salvatore Adamo"),
]


def ensure_mock_songs() -> None:
    with connection() as conn:
        count = int(conn.execute("SELECT COUNT(*) FROM songs").fetchone()[0])
        if count == 0:
            conn.executemany("INSERT INTO songs(title,artist,status) VALUES(?,?,'mock')", MOCK_SONGS)


def list_songs() -> list[dict]:
    ensure_mock_songs()
    with connection() as conn:
        rows = conn.execute("SELECT * FROM songs ORDER BY title COLLATE NOCASE").fetchall()
        return [dict(r) for r in rows]
