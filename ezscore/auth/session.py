from __future__ import annotations

import streamlit as st

from ezscore.auth.repository import find_by_id
from ezscore.core.types import CurrentUser

SESSION_KEY = "ezscore_v1_user_id"


def login_user(user_id: int) -> None:
    st.session_state[SESSION_KEY] = int(user_id)


def logout_user() -> None:
    st.session_state.pop(SESSION_KEY, None)
    try:
        if bool(getattr(st.user, "is_logged_in", False)):
            st.logout()
    except Exception:
        pass


def current_user() -> CurrentUser | None:
    raw = st.session_state.get(SESSION_KEY)
    if raw is None:
        return None
    row = find_by_id(int(raw))
    if not row or not bool(row.get("active")):
        st.session_state.pop(SESSION_KEY, None)
        return None
    return CurrentUser(
        user_id=int(row["id"]),
        email=str(row["email"]),
        display_name=str(row["display_name"]),
        role=str(row["role"]),
        avatar_url=str(row.get("avatar_url") or ""),
    )
