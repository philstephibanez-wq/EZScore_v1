#!/usr/bin/env python3
from __future__ import annotations

from datetime import datetime
from pathlib import Path
import shutil

ROOT = Path(__file__).resolve().parents[1]
STAMP = datetime.now().strftime("%Y%m%d-%H%M%S")
BACKUP = ROOT / "var" / "backup" / f"r31-2-reader-login-{STAMP}"

def backup(path: Path) -> None:
    if not path.exists():
        return
    dst = BACKUP / path.relative_to(ROOT)
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(path, dst)

def save(path: Path, new: str, label: str) -> None:
    old = path.read_text(encoding="utf-8")
    if old == new:
        print(f"[OK] {label}: already applied")
        return
    backup(path)
    path.write_text(new, encoding="utf-8", newline="\n")
    print(f"[OK] {label}")

def patch_security() -> None:
    path = ROOT / "config/packages/security.yaml"
    text = path.read_text(encoding="utf-8")

    if "always_use_default_target_path: true" in text:
        print("[OK] Reader login landing: already applied")
        return

    anchor = """            form_login:
                login_path: app_login
                check_path: app_login
                enable_csrf: true
                default_target_path: app_catalog
"""
    replacement = """            form_login:
                login_path: app_login
                check_path: app_login
                enable_csrf: true
                default_target_path: app_catalog
                always_use_default_target_path: true
"""
    if anchor not in text:
        raise RuntimeError("security.yaml form_login anchor not found")

    save(path, text.replace(anchor, replacement, 1), "Reader login always lands on catalog")

def main() -> int:
    patch_security()
    print(f"[OK] Backup: {BACKUP}")
    print("[OK] R31.2 reader login landing fix applied.")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
