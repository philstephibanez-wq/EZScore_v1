from __future__ import annotations

import streamlit as st

from EZScoreTemplate import ScoreTemplateRenderer
from ezscore.auth.local import hash_password
from ezscore.auth.repository import create_user
from ezscore.core.config import APP_DIR
from ezscore.users.service import list_users, set_user_active, set_user_role

SCORE = ScoreTemplateRenderer(APP_DIR)


def render(current_user) -> None:
    st.markdown(SCORE.render("templates/admin/users.score"), unsafe_allow_html=True)

    with st.expander("Créer un utilisateur", expanded=False):
        with st.form("create_user"):
            c1, c2 = st.columns(2)
            name = c1.text_input("Nom affiché")
            email = c2.text_input("E-mail")
            role = st.selectbox("Rôle", ["reader", "editor", "admin"])
            password = st.text_input("Mot de passe local initial", type="password")
            submitted = st.form_submit_button("Créer", type="primary")
        if submitted:
            try:
                if len(password) < 10:
                    raise ValueError("Mot de passe : 10 caractères minimum.")
                create_user(email=email, display_name=name, password_hash=hash_password(password), role=role)
                st.success("Utilisateur créé.")
                st.rerun()
            except Exception as exc:
                st.error(str(exc))

    for row in list_users():
        with st.container(border=True):
            c1, c2, c3 = st.columns([2.2, 1.2, 1])
            c1.markdown(f"**{row['display_name']}**  \n{row['email']}")
            new_role = c2.selectbox("Rôle", ["reader", "editor", "admin"], index=["reader", "editor", "admin"].index(row["role"]), key=f"role_{row['id']}")
            active = c3.checkbox("Actif", value=bool(row["active"]), key=f"active_{row['id']}")
            if new_role != row["role"] or active != bool(row["active"]):
                if int(row["id"]) == current_user.user_id and (new_role != "admin" or not active):
                    st.warning("L’administrateur connecté ne peut pas se désactiver ou perdre son rôle depuis cette vue.")
                else:
                    set_user_role(int(row["id"]), new_role)
                    set_user_active(int(row["id"]), active)
                    st.rerun()
