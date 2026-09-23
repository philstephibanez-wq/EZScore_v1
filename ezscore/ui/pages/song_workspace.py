from __future__ import annotations

from pathlib import Path

import streamlit as st

from EZScoreTemplate import ScoreTemplateRenderer
from ezscore.core.config import APP_DIR
from ezscore.ui.viewmodels.song_workspace import build

SCORE = ScoreTemplateRenderer(APP_DIR)
_TEMPLATE_DIR = Path(APP_DIR) / "templates" / "song"

_COMPONENT = st.components.v2.component(
    "ezscore_v1_song_workspace_r1",
    html=SCORE.render("templates/song/workspace.score", {
        "song": {"title":"—","artist":"—","editor":"—","time_signature":"—","capo":"—","strumming":"—"},
        "stems": [], "measures": [], "words": [], "permissions": {"readonly": True},
    }),
    css=(_TEMPLATE_DIR / "workspace.css").read_text(encoding="utf-8"),
    js=(_TEMPLATE_DIR / "workspace.js").read_text(encoding="utf-8"),
    isolate_styles=True,
)


def render(user) -> None:
    vm = build(user)
    # SCORE remains the presentation source. The component HTML is rendered per call
    # so mock content is visible while CSS/JS stay separate templates.
    html = SCORE.render("templates/song/workspace.score", vm)
    dynamic = st.components.v2.component(
        "ezscore_v1_song_workspace_dynamic_r1",
        html=html,
        css=(_TEMPLATE_DIR / "workspace.css").read_text(encoding="utf-8"),
        js=(_TEMPLATE_DIR / "workspace.js").read_text(encoding="utf-8"),
        isolate_styles=True,
    )
    dynamic(
        data={"role": user.role},
        default={"mock_step": 1, "mock_mixer_event": ""},
        key=f"workspace_{user.user_id}",
        width="stretch",
        height=1080,
    )
