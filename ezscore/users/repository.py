from __future__ import annotations

from ezscore.persistence.db import connection


def list_users() -> list[dict]:
    with connection() as conn:
        rows = conn.execute("SELECT id,email,display_name,role,active,avatar_url FROM users ORDER BY display_name COLLATE NOCASE").fetchall()
        return [dict(r) for r in rows]


def set_user_role(user_id: int, role: str) -> None:
    if role not in {"admin", "editor", "reader"}:
        raise ValueError("Rôle invalide")
    with connection() as conn:
        conn.execute("UPDATE users SET role=? WHERE id=?", (role, user_id))


def set_user_active(user_id: int, active: bool) -> None:
    with connection() as conn:
        conn.execute("UPDATE users SET active=? WHERE id=?", (1 if active else 0, user_id))
