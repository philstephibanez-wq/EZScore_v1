#!/usr/bin/env python3
from __future__ import annotations

import re
import shutil
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
WORKER = ROOT / "worker_app" / "ezscore_analysis_worker.pyw"

if not WORKER.is_file():
    raise SystemExit(f"Worker not found: {WORKER}")

source = WORKER.read_text(encoding="utf-8")

if "R27.2 PLAYBACK PROXY STAGE" in source:
    print("[OK] Worker already patched for R27.2.")
    raise SystemExit(0)

anchor = '''        self.current_process = None

        if return_code == 0:
            try:
                self.api.post(f"/internal/analysis/desktop/jobs/{job_id}/complete", {})
'''

replacement = '''        self.current_process = None

        # R27.2 PLAYBACK PROXY STAGE
        # Desktop Worker executes stems_only.py directly and bypasses
        # App\\Service\\SongStemWorker. Build Opus proxies here before complete.
        proxy_error = None

        if return_code == 0:
            proxy_script = self.root / "analysis" / "build_playback_proxies.py"

            if not proxy_script.is_file():
                return_code = 90
                proxy_error = "playback_proxy_builder_missing"
                self.log("ERREUR: build_playback_proxies.py introuvable.")
            else:
                proxy_command = [
                    self.engine_python,
                    str(proxy_script),
                    "--source", str(paths["source"]),
                    "--storage-root", str(paths["storage_root"]),
                    "--progress-file", str(paths["progress_file"]),
                ]

                self.log("Génération des proxies Opus 192 kb/s lancée.")

                proxy_proc = subprocess.Popen(
                    proxy_command,
                    cwd=str(self.root),
                    env=env,
                    stdout=subprocess.PIPE,
                    stderr=subprocess.STDOUT,
                    text=True,
                    encoding="utf-8",
                    errors="replace",
                    bufsize=1,
                    creationflags=WINDOWS_NO_WINDOW if os.name == "nt" else 0,
                )
                self.current_process = proxy_proc

                if proxy_proc.stdout is not None:
                    for line in proxy_proc.stdout:
                        line = line.rstrip()
                        if line:
                            self.log("[OPUS] " + line)

                proxy_return_code = proxy_proc.wait()
                self.current_process = None

                if proxy_return_code != 0:
                    return_code = proxy_return_code
                    proxy_error = f"playback_proxy_exit_{proxy_return_code}"
                    self.log(f"Échec génération Opus: {proxy_error}")
                else:
                    self.log("Proxies Opus générés.")

        if return_code == 0:
            try:
                self.api.post(f"/internal/analysis/desktop/jobs/{job_id}/complete", {})
'''

if anchor not in source:
    raise SystemExit(
        "R27.2 patch refused: expected Worker completion block not found. "
        "No file was modified."
    )

patched = source.replace(anchor, replacement, 1)

old_error = '''        else:
            error = f"stems_python_exit_{return_code}"
            self.log(f"Job #{job_id} en échec: {error}")
'''
new_error = '''        else:
            error = proxy_error or f"stems_python_exit_{return_code}"
            self.log(f"Job #{job_id} en échec: {error}")
'''

if old_error not in patched:
    raise SystemExit(
        "R27.2 patch refused: expected Worker failure block not found. "
        "No file was modified."
    )

patched = patched.replace(old_error, new_error, 1)
patched = re.sub(
    r'APP_VERSION\s*=\s*"[^"]+"',
    'APP_VERSION = "R27.2"',
    patched,
    count=1,
)

backup_dir = ROOT / "var" / "backup"
backup_dir.mkdir(parents=True, exist_ok=True)
backup = backup_dir / (
    "ezscore_analysis_worker_before_r27_2_"
    + datetime.now().strftime("%Y%m%d-%H%M%S")
    + ".pyw"
)
shutil.copy2(WORKER, backup)
WORKER.write_text(patched, encoding="utf-8", newline="\n")

print(f"[OK] Worker patched: {WORKER}")
print(f"[OK] Backup: {backup}")
print("[OK] R27.2 Opus proxy stage installed.")
