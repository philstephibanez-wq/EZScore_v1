from __future__ import annotations

from ezscore.persistence.db import connection


def create_playlist(*, owner_type: str, owner_id: int, name: str, description: str, created_by: int) -> int:
    if owner_type not in {"user", "group"}:
        raise ValueError("owner_type invalide")
    with connection() as conn:
        cur = conn.execute(
            "INSERT INTO playlists(owner_type,owner_id,name,description,created_by) VALUES(?,?,?,?,?)",
            (owner_type, owner_id, name.strip(), description.strip(), created_by),
        )
        return int(cur.lastrowid)


def list_personal(user_id: int) -> list[dict]:
    with connection() as conn:
        rows = conn.execute("SELECT * FROM playlists WHERE owner_type='user' AND owner_id=? ORDER BY name COLLATE NOCASE", (user_id,)).fetchall()
        return [dict(r) for r in rows]


def list_group(group_id: int) -> list[dict]:
    with connection() as conn:
        rows = conn.execute("SELECT * FROM playlists WHERE owner_type='group' AND owner_id=? ORDER BY name COLLATE NOCASE", (group_id,)).fetchall()
        return [dict(r) for r in rows]
