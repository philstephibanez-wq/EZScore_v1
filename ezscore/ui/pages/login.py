from __future__ import annotations

import streamlit as st

from EZScoreTemplate import ScoreTemplateRenderer
from ezscore.auth.google import configured as google_configured, consume_google_login, start_google_login
from ezscore.auth.service import local_login
from ezscore.core.config import APP_DIR

SCORE = ScoreTemplateRenderer(APP_DIR)


def render() -> None:
    success, google_error = consume_google_login()
    if success:
        st.rerun()

    st.markdown(SCORE.render("templates/auth/login.score"), unsafe_allow_html=True)
    if google_error:
        st.warning(google_error)

    with st.form("local_login"):
        email = st.text_input("E-mail")
        password = st.text_input("Mot de passe", type="password")
        submitted = st.form_submit_button("Connexion", type="primary", width="stretch")
    if submitted:
        ok, error = local_login(email=email, password=password)
        if ok:
            st.rerun()
        st.error(error)

    st.divider()
    if google_configured():
        if st.button("Continuer avec Google", width="stretch"):
            start_google_login()
    else:
        st.caption("Google SSO non configuré. Voir `.streamlit/secrets.toml.example`.")
