from __future__ import annotations

import streamlit as st

from EZScoreTemplate import ScoreTemplateRenderer
from ezscore.core.config import APP_DIR
from ezscore.groups.service import list_groups
from ezscore.playlists.service import create_playlist, list_group, list_personal

SCORE = ScoreTemplateRenderer(APP_DIR)


def render(user) -> None:
    st.markdown(SCORE.render("templates/playlists/list.score"), unsafe_allow_html=True)
    tab_personal, tab_group = st.tabs(["Personnelles", "Groupes"])
    with tab_personal:
        with st.form("new_personal_playlist"):
            name = st.text_input("Nom", key="pp_name")
            description = st.text_input("Description", key="pp_desc")
            submitted = st.form_submit_button("Créer")
        if submitted:
            create_playlist(owner_type="user", owner_id=user.user_id, name=name, description=description, created_by=user.user_id)
            st.rerun()
        for p in list_personal(user.user_id):
            st.info(f"{p['name']} — {p.get('description') or 'sans description'}")

    with tab_group:
        groups = list_groups()
        if not groups:
            st.caption("Aucun groupe.")
        else:
            lookup = {g["name"]: int(g["id"]) for g in groups}
            selected = st.selectbox("Groupe", list(lookup))
            gid = lookup[selected]
            if user.role in {"admin", "editor"}:
                with st.form("new_group_playlist"):
                    name = st.text_input("Nom", key="gp_name")
                    description = st.text_input("Description", key="gp_desc")
                    submitted = st.form_submit_button("Créer pour le groupe")
                if submitted:
                    create_playlist(owner_type="group", owner_id=gid, name=name, description=description, created_by=user.user_id)
                    st.rerun()
            for p in list_group(gid):
                st.info(f"{p['name']} — {p.get('description') or 'sans description'}")
