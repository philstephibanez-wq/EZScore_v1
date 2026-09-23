from __future__ import annotations

import streamlit as st

from EZScoreTemplate import ScoreTemplateRenderer
from ezscore.auth.session import logout_user
from ezscore.core.config import APP_DIR
from ezscore.ui.routing import navigation_for

SCORE = ScoreTemplateRenderer(APP_DIR)


def render(user) -> str:
    st.markdown(SCORE.render("templates/shell/app.score"), unsafe_allow_html=True)
    with st.sidebar:
        st.markdown(SCORE.render("templates/shell/sidebar.score", {"user": user}), unsafe_allow_html=True)
        page = st.radio("Navigation", navigation_for(user.role), label_visibility="collapsed")
        st.divider()
        if st.button("Déconnexion", width="stretch"):
            logout_user()
            st.rerun()
    return page
