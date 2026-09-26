#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
from datetime import datetime
import shutil

ROOT = Path(__file__).resolve().parents[1]
STRAY = ROOT / "src" / "Controller_SongLabController.php"
CANONICAL = ROOT / "src" / "Controller" / "SongLabController.php"
STAMP = datetime.now().strftime("%Y%m%d-%H%M%S")
BACKUP = ROOT / "var" / "backup" / f"r32-1-duplicate-controller-{STAMP}"

EXPECTED = "final class SongLabController extends AbstractController"

def main() -> int:
    if not CANONICAL.is_file():
        raise RuntimeError(
            f"Canonical controller missing: {CANONICAL}. "
            "No cleanup was applied."
        )

    canonical_text = CANONICAL.read_text(encoding="utf-8")
    if EXPECTED not in canonical_text:
        raise RuntimeError(
            f"Canonical SongLabController signature not found in {CANONICAL}. "
            "No cleanup was applied."
        )

    if not STRAY.exists():
        print("[OK] No stray duplicate controller found.")
        print("[OK] R32.1 cleanup already satisfied.")
        return 0

    stray_text = STRAY.read_text(encoding="utf-8")
    if EXPECTED not in stray_text or "namespace App\\Controller;" not in stray_text:
        raise RuntimeError(
            f"Unexpected file at {STRAY}; refusing to delete it automatically."
        )

    backup = BACKUP / "src" / STRAY.name
    backup.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(STRAY, backup)

    STRAY.unlink()

    if STRAY.exists():
        raise RuntimeError(f"Failed to remove {STRAY}")

    print(f"[OK] Removed stray duplicate controller: {STRAY}")
    print(f"[OK] Backup created: {backup}")
    print("[OK] Canonical controller kept: src/Controller/SongLabController.php")
    print("[OK] R32.1 duplicate-controller cleanup applied.")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
