from __future__ import annotations

from ezscore.core.types import CurrentUser


def permissions_for(user: CurrentUser) -> dict[str, bool]:
    admin = user.role == "admin"
    editor = user.role in {"admin", "editor"}
    return {
        "can_admin_users": admin,
        "can_manage_group": admin,
        "can_assign_editor": admin,
        "can_edit_song": editor,
        "can_publish": editor,
        "can_use_step2": editor,
        "can_manage_personal_playlist": True,
        "can_manage_group_playlist": user.role in {"admin", "editor"},
        "readonly": user.role == "reader",
    }
