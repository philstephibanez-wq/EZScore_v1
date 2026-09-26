#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
STORAGE = ROOT / "var" / "storage" / "stems"


def human_bytes(value: int) -> str:
    units = ["B", "KB", "MB", "GB", "TB"]
    size = float(value)
    for unit in units:
        if size < 1024.0 or unit == units[-1]:
            return f"{size:.2f} {unit}"
        size /= 1024.0
    return f"{value} B"


def atomic_json(path: Path, payload: dict) -> None:
    temp = path.with_suffix(path.suffix + ".tmp")
    temp.write_text(
        json.dumps(payload, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    temp.replace(path)


def remove_manifest_key(path: Path, section: str, key: str, apply: bool) -> bool:
    if not path.is_file():
        return False
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except Exception:
        print(f"[WARN] Invalid manifest skipped: {path}")
        return False

    bucket = payload.get(section)
    if not isinstance(bucket, dict) or key not in bucket:
        return False

    del bucket[key]

    if apply:
        atomic_json(path, payload)

    return True


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Remove redundant global vocals WAV/Opus files from EZScore persistent STEM storage."
    )
    parser.add_argument(
        "--apply",
        action="store_true",
        help="Actually delete files. Without this flag the script is a dry-run.",
    )
    args = parser.parse_args()

    if not STORAGE.is_dir():
        print(f"[OK] No STEM storage directory: {STORAGE}")
        return 0

    candidates: list[Path] = []
    skipped: list[Path] = []
    manifest_updates = 0
    bytes_to_free = 0

    for run_dir in STORAGE.glob("song-*/*/runs/run-*"):
        if not run_dir.is_dir():
            continue

        lead = run_dir / "lead_vocals.wav"
        backing = run_dir / "backing_vocals.wav"
        vocals = run_dir / "vocals.wav"

        if vocals.is_file():
            if lead.is_file() and lead.stat().st_size > 0 and backing.is_file() and backing.stat().st_size > 0:
                candidates.append(vocals)
                bytes_to_free += vocals.stat().st_size
                if remove_manifest_key(run_dir / "manifest.json", "stems", "vocals", args.apply):
                    manifest_updates += 1
            else:
                skipped.append(vocals)

    for playback_dir in STORAGE.glob("song-*/*/playback/run-*"):
        if not playback_dir.is_dir():
            continue

        lead = playback_dir / "lead_vocals.opus"
        backing = playback_dir / "backing_vocals.opus"
        vocals = playback_dir / "vocals.opus"

        if vocals.is_file():
            if lead.is_file() and lead.stat().st_size > 0 and backing.is_file() and backing.stat().st_size > 0:
                candidates.append(vocals)
                bytes_to_free += vocals.stat().st_size
                if remove_manifest_key(playback_dir / "manifest.json", "tracks", "vocals", args.apply):
                    manifest_updates += 1
            else:
                skipped.append(vocals)

    mode = "APPLY" if args.apply else "DRY-RUN"
    print(f"[{mode}] redundant files: {len(candidates)}")
    print(f"[{mode}] reclaimable disk space: {human_bytes(bytes_to_free)}")
    print(f"[{mode}] manifests to update: {manifest_updates}")
    print(f"[{mode}] protected files skipped: {len(skipped)}")

    if skipped:
        for path in skipped[:20]:
            print(f"[KEEP] missing valid lead/backing pair: {path}")
        if len(skipped) > 20:
            print(f"[KEEP] ... {len(skipped) - 20} more")

    if not args.apply:
        print("[DRY-RUN] Nothing deleted. Re-run with --apply to perform cleanup.")
        return 0

    reclaimed = 0
    removed = 0

    for path in candidates:
        try:
            size = path.stat().st_size
            path.unlink()
            reclaimed += size
            removed += 1
            print(f"[DELETE] {path}")
        except FileNotFoundError:
            pass

    print(f"[OK] files deleted: {removed}")
    print(f"[OK] disk space reclaimed: {human_bytes(reclaimed)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
