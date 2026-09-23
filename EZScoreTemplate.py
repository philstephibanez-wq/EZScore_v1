from __future__ import annotations

from pathlib import Path
from typing import Any

from jinja2 import Environment, FileSystemLoader, StrictUndefined, select_autoescape


class ScoreTemplateRenderer:
    """Minimal SCORE template renderer for EZScore_v1.

    .score files remain ordinary text templates; Jinja is only the rendering engine.
    Presentation belongs in templates, not in Python pages/services.
    """

    def __init__(self, root: str | Path):
        self.root = Path(root)
        self.env = Environment(
            loader=FileSystemLoader(str(self.root)),
            undefined=StrictUndefined,
            autoescape=select_autoescape(default_for_string=True, default=True),
            trim_blocks=True,
            lstrip_blocks=True,
        )

    def render(self, template_path: str, context: dict[str, Any] | None = None) -> str:
        return self.env.get_template(template_path).render(**(context or {}))
