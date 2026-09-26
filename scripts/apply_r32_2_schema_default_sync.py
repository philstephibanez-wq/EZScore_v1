#!/usr/bin/env python3
from __future__ import annotations

from datetime import datetime
from pathlib import Path
import shutil

ROOT = Path(__file__).resolve().parents[1]
SONG = ROOT / "src" / "Domain" / "Song" / "Song.php"
STAMP = datetime.now().strftime("%Y%m%d-%H%M%S")
BACKUP = ROOT / "var" / "backup" / f"r32-2-schema-default-sync-{STAMP}"

OLD = """    #[ORM\\Column(name: 'chord_analysis_level', length: 16)]
    private string $chordAnalysisLevel = 'intermediate';
"""

NEW = """    #[ORM\\Column(name: 'chord_analysis_level', length: 16, options: ['default' => 'intermediate'])]
    private string $chordAnalysisLevel = 'intermediate';
"""

def main() -> int:
    if not SONG.is_file():
        raise RuntimeError(f"Missing {SONG}")

    text = SONG.read_text(encoding="utf-8")

    if NEW in text:
        print("[OK] Doctrine default already synchronized.")
        return 0

    if OLD not in text:
        raise RuntimeError(
            "Expected ChordsLab chord_analysis_level mapping not found. "
            "No unsafe guess was applied."
        )

    backup = BACKUP / SONG.relative_to(ROOT)
    backup.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(SONG, backup)

    SONG.write_text(text.replace(OLD, NEW, 1), encoding="utf-8", newline="\n")

    print("[OK] chord_analysis_level Doctrine default synchronized with SQLite migration.")
    print(f"[OK] Backup: {backup}")
    print("[OK] R32.2 schema mapping fix applied.")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
