from __future__ import annotations

from ezscore.auth.local import hash_password, verify_password
from ezscore.auth.repository import count_users, create_user, find_by_email
from ezscore.auth.session import login_user


def needs_first_run() -> bool:
    return count_users() == 0


def create_first_admin(*, email: str, display_name: str, password: str) -> tuple[bool, str]:
    if not needs_first_run():
        return False, "Initialisation déjà effectuée."
    email = email.strip().lower()
    display_name = display_name.strip()
    if "@" not in email:
        return False, "Adresse e-mail invalide."
    if len(display_name) < 2:
        return False, "Nom affiché trop court."
    if len(password) < 10:
        return False, "Le mot de passe doit contenir au moins 10 caractères."
    user_id = create_user(
        email=email,
        display_name=display_name,
        password_hash=hash_password(password),
        role="admin",
    )
    login_user(user_id)
    return True, ""


def local_login(*, email: str, password: str) -> tuple[bool, str]:
    row = find_by_email(email)
    if not row or not bool(row.get("active")):
        return False, "Identifiants invalides."
    if not verify_password(password, row.get("password_hash")):
        return False, "Identifiants invalides."
    login_user(int(row["id"]))
    return True, ""
