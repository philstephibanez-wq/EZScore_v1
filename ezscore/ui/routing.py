from __future__ import annotations


def navigation_for(role: str) -> list[str]:
    base = ["Répertoire", "Analyse mock", "Playlists"]
    if role == "admin":
        base += ["Administration · Utilisateurs", "Administration · Groupes"]
    return base
