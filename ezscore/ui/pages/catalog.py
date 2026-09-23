from __future__ import annotations

import streamlit as st

from ezscore.songs.service import list_songs


def render() -> None:
    st.subheader("Répertoire")
    st.caption("Catalogue mock persistant : la logique musicale sera reconnectée après validation UI.")
    for song in list_songs():
        with st.container(border=True):
            c1, c2 = st.columns([3, 1])
            with c1:
                st.markdown(f"**{song['title']}**")
                st.caption(song.get("artist") or "—")
            with c2:
                st.button("Ouvrir", key=f"song_{song['id']}", width="stretch")
