from __future__ import annotations

from ezscore.persistence.db import connection


def list_groups() -> list[dict]:
    with connection() as conn:
        rows = conn.execute("SELECT * FROM groups ORDER BY name COLLATE NOCASE").fetchall()
        return [dict(r) for r in rows]


def create_group(name: str, description: str = "") -> int:
    with connection() as conn:
        cur = conn.execute("INSERT INTO groups(name,description) VALUES(?,?)", (name.strip(), description.strip()))
        return int(cur.lastrowid)


def list_members(group_id: int) -> list[dict]:
    with connection() as conn:
        rows = conn.execute(
            """SELECT u.id,u.display_name,u.email,u.role,gm.member_role
               FROM group_members gm JOIN users u ON u.id=gm.user_id
               WHERE gm.group_id=? ORDER BY u.display_name COLLATE NOCASE""",
            (group_id,),
        ).fetchall()
        return [dict(r) for r in rows]


def add_member(group_id: int, user_id: int, member_role: str = "member") -> None:
    if member_role not in {"owner", "manager", "member"}:
        raise ValueError("Rôle de groupe invalide")
    with connection() as conn:
        conn.execute(
            "INSERT INTO group_members(group_id,user_id,member_role) VALUES(?,?,?) ON CONFLICT(group_id,user_id) DO UPDATE SET member_role=excluded.member_role",
            (group_id, user_id, member_role),
        )
