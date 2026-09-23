from __future__ import annotations

import streamlit as st

from EZScoreTemplate import ScoreTemplateRenderer
from ezscore.auth.service import create_first_admin
from ezscore.core.config import APP_DIR

SCORE = ScoreTemplateRenderer(APP_DIR)


def render() -> None:
    st.markdown(SCORE.render("templates/auth/first-run.score"), unsafe_allow_html=True)
    with st.form("first_admin"):
        name = st.text_input("Nom affiché", placeholder="Administrateur")
        email = st.text_input("E-mail", placeholder="admin@example.com")
        password = st.text_input("Mot de passe", type="password")
        password2 = st.text_input("Confirmer le mot de passe", type="password")
        submitted = st.form_submit_button("Créer l’administrateur", type="primary", width="stretch")
    if submitted:
        if password != password2:
            st.error("Les mots de passe ne correspondent pas.")
            return
        ok, error = create_first_admin(email=email, display_name=name, password=password)
        if not ok:
            st.error(error)
            return
        st.success("Administrateur créé.")
        st.rerun()
