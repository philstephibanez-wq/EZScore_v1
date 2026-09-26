#!/usr/bin/env python3
from __future__ import annotations

from datetime import datetime
from pathlib import Path
import re, shutil

ROOT = Path(__file__).resolve().parents[1]
STAMP = datetime.now().strftime("%Y%m%d-%H%M%S")
BACKUP = ROOT / "var" / "backup" / f"r32-3-ui-i18n-{STAMP}"

def backup(path: Path) -> None:
    if not path.exists():
        return
    dst = BACKUP / path.relative_to(ROOT)
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(path, dst)

def save(path: Path, text: str, label: str) -> None:
    old = path.read_text(encoding="utf-8")
    if old == text:
        print(f"[OK] {label}: already applied")
        return
    backup(path)
    path.write_text(text, encoding="utf-8", newline="\n")
    print(f"[OK] {label}")

def patch_global_workflow_css() -> None:
    path = ROOT / "templates/base.html.twig"
    text = path.read_text(encoding="utf-8")
    href = '<link rel="stylesheet" href="/assets/css/workflow-tabs-r23-2.css?v=20260926r32_3">'
    if href in text:
        print("[OK] global workflow CSS: already applied")
        return
    anchor = '<link rel="stylesheet" href="/assets/css/layout.css?v=20260925r13">\n'
    if anchor not in text:
        raise RuntimeError("base.html.twig layout.css anchor not found")
    save(path, text.replace(anchor, anchor + "    " + href + "\n", 1), "global workflow CSS")

def patch_controller_timesigs() -> None:
    path = ROOT / "src/Controller/SongLabController.php"
    text = path.read_text(encoding="utf-8")
    old = """    private const TIME_SIGNATURES = [
        '2/2', '2/4',
"""
    new = """    private const TIME_SIGNATURES = [
        'auto',
        '2/2', '2/4',
"""
    if new in text:
        print("[OK] time signature auto option: already applied")
        return
    if old not in text:
        raise RuntimeError("SongLabController TIME_SIGNATURES anchor not found")
    save(path, text.replace(old, new, 1), "time signature auto option")

def patch_chordslab_template() -> None:
    path = ROOT / "templates/song/chordslab.html.twig"
    text = path.read_text(encoding="utf-8")
    original = text

    # ChordsLab translations live in chordslab.{locale}.yaml => explicit domain.
    keys = [
        "chordslab.lead", "chordslab.back", "chordslab.key",
        "chordslab.analysis_level", "chordslab.show_diagram",
        "chordslab.level_note", "chordslab.prompter", "chordslab.timeline",
        "chordslab.edit_help", "chordslab.no_timeline_title",
        "chordslab.no_timeline_body", "chordslab.go_analysis",
        "chordslab.tracks", "chordslab.master_fx",
        "chordslab.player_unavailable", "chordslab.player_unavailable_body",
        "chordslab.reset.button", "chordslab.reset.confirm",
    ]
    for key in keys:
        text = text.replace(f"'{key}'|trans", f"'{key}'|trans({{}}, 'chordslab')")

    # Dynamic chord level keys need the same domain.
    text = text.replace(
        "{{ ('chordslab.level.' ~ level)|trans }}",
        "{{ ('chordslab.level.' ~ level)|trans({}, 'chordslab') }}"
    )

    # Avoid duplicated icons when stems translations already contain glyphs/text.
    text = text.replace(
        "<button type=\"button\" data-mixer-play>▶ {{ 'stems.mixer.play'|trans({}, 'stems') }}</button>",
        "<button type=\"button\" data-mixer-play>{{ 'stems.mixer.play'|trans({}, 'stems') }}</button>"
    )
    text = text.replace(
        "<button type=\"button\" data-mixer-pause>Ⅱ {{ 'stems.mixer.pause'|trans({}, 'stems') }}</button>",
        "<button type=\"button\" data-mixer-pause>{{ 'stems.mixer.pause'|trans({}, 'stems') }}</button>"
    )
    text = text.replace(
        "<button type=\"button\" data-mixer-stop>■ {{ 'stems.mixer.stop'|trans({}, 'stems') }}</button>",
        "<button type=\"button\" data-mixer-stop>{{ 'stems.mixer.stop'|trans({}, 'stems') }}</button>"
    )

    if text == original:
        print("[OK] ChordsLab template i18n: already compatible")
        return
    save(path, text, "ChordsLab translation domains + transport labels")

def patch_flash_translation_domain() -> None:
    path = ROOT / "templates/base.html.twig"
    text = path.read_text(encoding="utf-8")
    old = """{% for message in messages %}<div class="flash flash-{{ label }}">{{ message starts with 'event.' ? message|trans({}, 'event') : message|trans }}</div>{% endfor %}"""
    new = """{% for message in messages %}<div class="flash flash-{{ label }}">{{ message starts with 'event.' ? message|trans({}, 'event') : (message starts with 'chordslab.' ? message|trans({}, 'chordslab') : message|trans) }}</div>{% endfor %}"""
    if new in text:
        print("[OK] ChordsLab flash domain: already applied")
        return
    if old not in text:
        raise RuntimeError("base.html.twig flash renderer anchor not found")
    save(path, text.replace(old, new, 1), "ChordsLab flash translation domain")

def main() -> int:
    patch_global_workflow_css()
    patch_controller_timesigs()
    patch_chordslab_template()
    patch_flash_translation_domain()
    print(f"[OK] Backup: {BACKUP}")
    print("[OK] R32.3 UI / i18n fix applied.")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
