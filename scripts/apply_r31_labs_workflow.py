#!/usr/bin/env python3
from __future__ import annotations

from datetime import datetime
from pathlib import Path
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
STAMP = datetime.now().strftime("%Y%m%d-%H%M%S")
BACKUP = ROOT / "var" / "backup" / f"r31-labs-workflow-{STAMP}"

def backup(path: Path) -> None:
    if not path.exists():
        return
    dst = BACKUP / path.relative_to(ROOT)
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(path, dst)

def install(payload_rel: str, target_rel: str | None = None) -> None:
    target_rel = target_rel or payload_rel
    src = ROOT / "scripts/_r31_payload" / payload_rel
    dst = ROOT / target_rel
    content = src.read_text(encoding="utf-8")
    if dst.exists() and dst.read_text(encoding="utf-8") == content:
        print(f"[OK] {target_rel}: already applied")
        return
    backup(dst)
    dst.parent.mkdir(parents=True, exist_ok=True)
    dst.write_text(content, encoding="utf-8", newline="\n")
    print(f"[OK] {target_rel}")

def install_workflow() -> None:
    src = ROOT / "scripts/_r31_workflow_tabs.html.twig"
    dst = ROOT / "templates/layout/song/_workflow_tabs.html.twig"
    content = src.read_text(encoding="utf-8")
    if dst.exists() and dst.read_text(encoding="utf-8") == content:
        print("[OK] workflow tabs: already applied")
        return
    backup(dst)
    dst.write_text(content, encoding="utf-8", newline="\n")
    print("[OK] seven-step workflow tabs")

def patch_routes() -> None:
    path = ROOT / "config/routes.yaml"
    text = path.read_text(encoding="utf-8")
    if "localized_song_labs:" in text:
        print("[OK] routes: already applied")
        return
    anchor = """localized_song_stems:
    resource: ../src/Controller/SongStemController.php
    type: attribute
    prefix:
        fr: /fr
        en: /en
"""
    block = anchor + """
localized_song_labs:
    resource: ../src/Controller/SongLabController.php
    type: attribute
    prefix:
        fr: /fr
        en: /en
"""
    if anchor not in text:
        raise RuntimeError("routes.yaml SongStemController anchor not found")
    backup(path)
    path.write_text(text.replace(anchor, block, 1), encoding="utf-8", newline="\n")
    print("[OK] localized SongLab routes")

def patch_css() -> None:
    path = ROOT / "public/assets/css/workflow-tabs-r23-2.css"
    text = path.read_text(encoding="utf-8")
    marker = "/* R31 LABS WORKFLOW */"
    if marker in text:
        print("[OK] workflow CSS: already applied")
        return
    extra = (ROOT / "scripts/_r31_workflow.css").read_text(encoding="utf-8")
    backup(path)
    path.write_text(text.rstrip() + "\n\n" + extra.strip() + "\n", encoding="utf-8", newline="\n")
    print("[OK] responsive Labs workflow CSS")

def patch_stemslab() -> None:
    for rel in ("translations/stems.fr.yaml", "translations/stems.en.yaml"):
        path = ROOT / rel
        text = path.read_text(encoding="utf-8")
        old = "title: 'STEMS — %title%'"
        new = "title: 'StemsLab — %title%'"
        if new in text:
            print(f"[OK] {rel}: already renamed")
        elif old in text:
            backup(path)
            path.write_text(text.replace(old, new, 1), encoding="utf-8", newline="\n")
            print(f"[OK] {rel}: StemsLab title")
        else:
            raise RuntimeError(f"Expected STEMS title not found in {rel}")

    path = ROOT / "templates/stems/index.html.twig"
    text = path.read_text(encoding="utf-8")
    new = text.replace(
        "{% block title %}STEMS — {{ song.title }} — EZScore_v1{% endblock %}",
        "{% block title %}StemsLab — {{ song.title }} — EZScore_v1{% endblock %}",
        1,
    )
    new = re.sub(
        r'workflow-tabs-r23-2\.css\?v=[^"]+',
        'workflow-tabs-r23-2.css?v=20260926r31',
        new,
        count=1,
    )
    if new != text:
        backup(path)
        path.write_text(new, encoding="utf-8", newline="\n")
        print("[OK] StemsLab page title/cache")
    else:
        print("[OK] StemsLab template already compatible")

def main() -> int:
    install("src/Controller/SongLabController.php")
    install("templates/song/lab_placeholder.html.twig")
    install("translations/labs.fr.yaml")
    install("translations/labs.en.yaml")
    install_workflow()
    patch_routes()
    patch_css()
    patch_stemslab()
    print(f"[OK] Backup: {BACKUP}")
    print("[OK] R31 Labs workflow shell applied.")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
