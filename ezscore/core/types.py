from __future__ import annotations

from dataclasses import dataclass
from typing import Literal

Role = Literal["admin", "editor", "reader"]


@dataclass(frozen=True)
class CurrentUser:
    user_id: int
    email: str
    display_name: str
    role: Role
    avatar_url: str = ""
