from __future__ import annotations

from pathlib import Path

import streamlit as st

from ezscore.auth.service import needs_first_run
from ezscore.auth.session import current_user
from ezscore.core.config import APP_DIR
from ezscore.persistence.db import init_db
from ezscore.ui.pages import admin_groups, admin_users, catalog, first_run, login, playlists, song_workspace
from ezscore.ui.shell import render as render_shell


def _global_css() -> None:
    css = (Path(APP_DIR) / "templates" / "song" / "workspace.css").read_text(encoding="utf-8")
    st.markdown(f"<style>{css}</style>", unsafe_allow_html=True)


def run() -> None:
    st.set_page_config(page_title="EZScore_v1", page_icon="🎼", layout="wide")
    init_db()
    _global_css()

    if needs_first_run():
        first_run.render()
        return

    user = current_user()
    if user is None:
        login.render()
        return

    page = render_shell(user)
    if page == "Répertoire":
        catalog.render()
    elif page == "Analyse mock":
        song_workspace.render(user)
    elif page == "Playlists":
        playlists.render(user)
    elif page == "Administration · Utilisateurs" and user.role == "admin":
        admin_users.render(user)
    elif page == "Administration · Groupes" and user.role == "admin":
        admin_groups.render()
    else:
        st.error("Accès refusé.")
