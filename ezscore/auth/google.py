from __future__ import annotations

import streamlit as st

from ezscore.auth.repository import attach_google, find_by_email
from ezscore.auth.session import login_user


def configured() -> bool:
    try:
        auth = st.secrets.get("auth", {})
        return bool(auth and auth.get("google"))
    except Exception:
        return False


def consume_google_login() -> tuple[bool, str]:
    """Map an authenticated Google identity to an existing EZScore user.

    For security, Google does not auto-create arbitrary users after first run.
    An admin must provision the email first, then that user can bind Google SSO.
    """
    try:
        if not bool(getattr(st.user, "is_logged_in", False)):
            return False, ""
        email = str(getattr(st.user, "email", "") or "").strip().lower()
        if not email:
            return False, "Google n'a pas fourni d'adresse e-mail."
        row = find_by_email(email)
        if not row or not bool(row.get("active")):
            return False, "Compte Google reconnu mais aucun utilisateur EZScore actif ne possède cet e-mail."
        subject = str(getattr(st.user, "sub", "") or "").strip()
        avatar = str(getattr(st.user, "picture", "") or "").strip()
        if subject:
            attach_google(int(row["id"]), subject, avatar)
        login_user(int(row["id"]))
        return True, ""
    except Exception as exc:
        return False, f"SSO Google indisponible : {type(exc).__name__}: {exc}"


def start_google_login() -> None:
    st.login("google")
