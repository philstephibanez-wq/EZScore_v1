#!/usr/bin/env python3
from pathlib import Path
import sys

root = Path(__file__).resolve().parents[1]
cdc = (root / "docs" / "CAHIER_DES_CHARGES.md").read_text(encoding="utf-8")
rec = (root / "recette.md").read_text(encoding="utf-8")

checks = {
    "CDC workflow Labs": "Import\nAnalyse\nÉdition\nStemsLab\nChordsLab\nLyricsLab\nPublication" in cdc,
    "CDC timeline source of truth": "La timeline est la source de vérité de ChordsLab." in cdc,
    "CDC chord carry explicit": "il doit être réaffiché explicitement" in cdc,
    "CDC diagram above current chord": "directement au-dessus de l'accord courant" in cdc,
    "CDC in-place edit": "modifiable directement en place" in cdc,
    "CDC reset": "Réinitialiser les accords" in cdc,
    "CDC capo semantics": "outil de simplification des formes d'accords" in cdc,
    "CDC key in cartouche": "Le cartouche de la chanson affiche la tonalité réelle" in cdc,
    "CDC future transposition separated": "La transposition est distincte du capo." in cdc,
    "CDC exhaustive time signatures": "13/16" in cdc and "7/4" in cdc and "6/8" in cdc,
    "CDC analysis levels": "Débutant\nIntermédiaire\nExpert" in cdc,
    "CDC collapsed panels": "sont repliés par défaut" in cdc,
    "CDC PC responsive": "### 38.1 PC" in cdc,
    "CDC tablet responsive": "### 38.2 Tablette" in cdc,
    "CDC smartphone responsive": "### 38.3 Smartphone" in cdc,
    "Recette workflow": "## 30. Recette ChordsLab / Labs" in rec,
    "Recette timeline": "### 30.2 Timeline comme source de vérité" in rec,
    "Recette smartphone": "### 30.15 Ergonomie smartphone" in rec,
    "Recette tablet": "### 30.14 Ergonomie tablette" in rec,
    "Recette PC": "### 30.13 Ergonomie PC" in rec,
    "Recette persistence": "### 30.16 Persistance" in rec,
    "Recette non regression": "### 30.17 Non-régression" in rec,
}

failed = False
for name, ok in checks.items():
    print(("OK" if ok else "FAIL") + ": " + name)
    failed |= not ok

if failed:
    sys.exit(1)

print(f"\n{len(checks)} R30 CDC/recette checks passed.")
