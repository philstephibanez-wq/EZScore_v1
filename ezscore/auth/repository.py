from __future__ import annotations

from ezscore.persistence.db import connection


def count_users() -> int:
    with connection() as conn:
        return int(conn.execute("SELECT COUNT(*) FROM users").fetchone()[0])


def create_user(*, email: str, display_name: str, password_hash: str | None, role: str, avatar_url: str = "", google_subject: str | None = None) -> int:
    with connection() as conn:
        cur = conn.execute(
            "INSERT INTO users(email,display_name,password_hash,role,avatar_url,google_subject) VALUES(?,?,?,?,?,?)",
            (email.strip().lower(), display_name.strip(), password_hash, role, avatar_url, google_subject),
        )
        return int(cur.lastrowid)


def find_by_email(email: str):
    with connection() as conn:
        row = conn.execute("SELECT * FROM users WHERE email=? COLLATE NOCASE", (email.strip(),)).fetchone()
        return dict(row) if row else None


def find_by_id(user_id: int):
    with connection() as conn:
        row = conn.execute("SELECT * FROM users WHERE id=?", (user_id,)).fetchone()
        return dict(row) if row else None


def attach_google(user_id: int, subject: str, avatar_url: str = "") -> None:
    with connection() as conn:
        conn.execute(
            "UPDATE users SET google_subject=?, avatar_url=CASE WHEN ?<>'' THEN ? ELSE avatar_url END WHERE id=?",
            (subject, avatar_url, avatar_url, user_id),
        )
