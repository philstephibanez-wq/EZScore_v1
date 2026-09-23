from __future__ import annotations

import streamlit as st

from EZScoreTemplate import ScoreTemplateRenderer
from ezscore.core.config import APP_DIR
from ezscore.groups.service import add_member, create_group, list_groups, list_members
from ezscore.users.service import list_users

SCORE = ScoreTemplateRenderer(APP_DIR)


def render() -> None:
    st.markdown(SCORE.render("templates/admin/groups.score"), unsafe_allow_html=True)
    with st.form("create_group"):
        name = st.text_input("Nom du groupe")
        description = st.text_input("Description")
        submitted = st.form_submit_button("Créer le groupe", type="primary")
    if submitted:
        try:
            create_group(name, description)
            st.rerun()
        except Exception as exc:
            st.error(str(exc))

    users = list_users()
    for group in list_groups():
        with st.expander(group["name"], expanded=False):
            st.caption(group.get("description") or "—")
            members = list_members(int(group["id"]))
            if members:
                st.table([{"Nom": m["display_name"], "E-mail": m["email"], "Rôle groupe": m["member_role"]} for m in members])
            options = {f"{u['display_name']} · {u['email']}": u["id"] for u in users if bool(u["active"])}
            if options:
                label = st.selectbox("Ajouter un membre", list(options), key=f"member_{group['id']}")
                role = st.selectbox("Rôle dans le groupe", ["member", "manager", "owner"], key=f"grole_{group['id']}")
                if st.button("Ajouter / mettre à jour", key=f"add_{group['id']}"):
                    add_member(int(group["id"]), int(options[label]), role)
                    st.rerun()
