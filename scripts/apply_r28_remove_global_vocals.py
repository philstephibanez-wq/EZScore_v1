#!/usr/bin/env python3
from __future__ import annotations

import os
import re
import shutil
from datetime import datetime
from pathlib import Path

ROOT = Path(os.environ.get("EZSCORE_PROJECT_ROOT") or Path(__file__).resolve().parents[1]).resolve()
STAMP = datetime.now().strftime("%Y%m%d-%H%M%S")
BACKUP_DIR = ROOT / "var" / "backup" / f"r28-1-remove-global-vocals-{STAMP}"


def backup(path: Path) -> None:
    relative = path.relative_to(ROOT)
    target = BACKUP_DIR / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(path, target)


def write_if_changed(path: Path, original: str, updated: str, label: str) -> None:
    if original == updated:
        print(f"[OK] {label}: already applied")
        return
    backup(path)
    path.write_text(updated, encoding="utf-8", newline="\n")
    print(f"[OK] {label}: {path.relative_to(ROOT)}")


def remove_python_tuple_item(path: Path, variable: str, item: str, label: str) -> None:
    text = path.read_text(encoding="utf-8")
    pattern = re.compile(
        rf"({re.escape(variable)}\s*=\s*\(\s*)(.*?)(\n\))",
        re.DOTALL,
    )
    match = pattern.search(text)
    if not match:
        raise RuntimeError(f"{label}: tuple {variable} not found in {path}")

    body = match.group(2)
    line_pattern = re.compile(
        rf'^\s*"{re.escape(item)}",\s*\r?\n?',
        re.MULTILINE,
    )
    new_body, count = line_pattern.subn("", body, count=1)

    if count == 0:
        print(f"[OK] {label}: already absent")
        return

    updated = text[:match.start(2)] + new_body + text[match.end(2):]
    write_if_changed(path, text, updated, label)


def remove_php_array_item(path: Path, marker: str, item: str, label: str) -> None:
    text = path.read_text(encoding="utf-8")
    start = text.find(marker)
    if start < 0:
        raise RuntimeError(f"{label}: marker not found in {path}")

    array_start = text.find("[", start)
    array_end = text.find("];", array_start)
    if array_start < 0 or array_end < 0:
        raise RuntimeError(f"{label}: array boundaries not found in {path}")

    before = text[:array_start + 1]
    body = text[array_start + 1:array_end]
    after = text[array_end:]

    pattern = re.compile(
        rf"^\s*'{re.escape(item)}',\s*\r?\n?",
        re.MULTILINE,
    )
    new_body, count = pattern.subn("", body, count=1)

    if count == 0:
        print(f"[OK] {label}: already absent")
        return

    updated = before + new_body + after
    write_if_changed(path, text, updated, label)


def remove_twig_track(path: Path, key: str, label: str) -> None:
    text = path.read_text(encoding="utf-8")
    lines = text.splitlines(keepends=True)
    kept = []
    removed = 0

    needle = "{'key':'" + key + "'"
    for line in lines:
        if needle in line:
            removed += 1
            continue
        kept.append(line)

    if removed == 0:
        print(f"[OK] {label}: already absent")
        return

    updated = "".join(kept)
    write_if_changed(path, text, updated, label)


def ensure_vocals_deleted_after_split(path: Path) -> None:
    text = path.read_text(encoding="utf-8")

    cleanup_line = '        (payload / "vocals.wav").unlink(missing_ok=True)\n'
    if cleanup_line in text:
        print("[OK] Delete temporary vocals.wav after vocal split: already applied")
        return

    anchor = '        shutil.copy2(backing, payload / "backing_vocals.wav")\n'
    if anchor not in text:
        raise RuntimeError(
            "Delete temporary vocals.wav after vocal split: backing copy anchor not found"
        )

    updated = text.replace(
        anchor,
        anchor
        + '\n'
        + '        # vocals.wav is only an intermediate input for MelBand-RoFormer.\n'
        + cleanup_line,
        1,
    )
    write_if_changed(
        path,
        text,
        updated,
        "Delete temporary vocals.wav after vocal split",
    )


def main() -> int:
    required = [
        ROOT / "analysis" / "stems_only.py",
        ROOT / "analysis" / "build_playback_proxies.py",
        ROOT / "src" / "Service" / "SongStemStorage.php",
        ROOT / "src" / "Service" / "SongStemPlaybackStorage.php",
        ROOT / "src" / "Controller" / "SongStemController.php",
        ROOT / "templates" / "stems" / "index.html.twig",
    ]
    missing = [str(p) for p in required if not p.is_file()]
    if missing:
        raise RuntimeError("Missing required files:\n" + "\n".join(missing))

    BACKUP_DIR.mkdir(parents=True, exist_ok=True)

    stems = ROOT / "analysis" / "stems_only.py"
    remove_python_tuple_item(
        stems,
        "FINAL_STEMS",
        "vocals",
        "Remove vocals from persistent STEM set",
    )
    ensure_vocals_deleted_after_split(stems)

    remove_python_tuple_item(
        ROOT / "analysis" / "build_playback_proxies.py",
        "TRACKS",
        "vocals",
        "Remove vocals.opus from proxy track set",
    )

    remove_php_array_item(
        ROOT / "src" / "Service" / "SongStemStorage.php",
        "public const STEMS",
        "vocals",
        "Remove vocals from SongStemStorage::STEMS",
    )

    remove_php_array_item(
        ROOT / "src" / "Service" / "SongStemPlaybackStorage.php",
        "public const TRACKS",
        "vocals",
        "Remove vocals from playback TRACKS",
    )

    remove_php_array_item(
        ROOT / "src" / "Controller" / "SongStemController.php",
        "$allowedTracks",
        "vocals",
        "Remove vocals from persisted mixer allow-list",
    )

    remove_twig_track(
        ROOT / "templates" / "stems" / "index.html.twig",
        "vocals",
        "Remove global vocals row from mixer",
    )

    print(f"[OK] Backup created: {BACKUP_DIR}")
    print("[OK] R28.1 source cleanup applied.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
