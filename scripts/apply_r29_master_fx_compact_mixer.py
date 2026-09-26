#!/usr/bin/env python3
from __future__ import annotations
import re
import shutil
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BACKUP = ROOT / "var" / "backup" / ("r29-master-fx-" + datetime.now().strftime("%Y%m%d-%H%M%S"))
PAYLOAD = ROOT / "payload"

def save(path: Path, new: str, label: str) -> None:
    old = path.read_text(encoding="utf-8")
    if old == new:
        print(f"[OK] {label}: already applied")
        return
    dst = BACKUP / path.relative_to(ROOT)
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(path, dst)
    path.write_text(new, encoding="utf-8", newline="\n")
    print(f"[OK] {label}")

def patch_engine() -> None:
    p = ROOT / "public/assets/js/audio/ezscore-audio-engine.js"
    t = p.read_text(encoding="utf-8")
    if "R29 MASTER FX CHAIN" in t:
        print("[OK] AudioEngine master FX already applied")
        return
    old = """            this.masterGain = this.context.createGain();
            this.masterGain.gain.value = 1;
            this.masterGain.connect(this.context.destination);
"""
    new = """            // R29 MASTER FX CHAIN
            this.masterInput = this.context.createGain();
            this.masterLow = this.context.createBiquadFilter();
            this.masterLow.type = 'lowshelf';
            this.masterLow.frequency.value = 110;
            this.masterMid = this.context.createBiquadFilter();
            this.masterMid.type = 'peaking';
            this.masterMid.frequency.value = 900;
            this.masterMid.Q.value = 1.0;
            this.masterHigh = this.context.createBiquadFilter();
            this.masterHigh.type = 'highshelf';
            this.masterHigh.frequency.value = 3600;
            this.masterCompressor = this.context.createDynamicsCompressor();
            this.masterCompressor.threshold.value = 0;
            this.masterCompressor.knee.value = 18;
            this.masterCompressor.ratio.value = 1;
            this.masterCompressor.attack.value = 0.015;
            this.masterCompressor.release.value = 0.18;
            this.masterLimiter = this.context.createDynamicsCompressor();
            this.masterLimiter.threshold.value = -1;
            this.masterLimiter.knee.value = 0;
            this.masterLimiter.ratio.value = 20;
            this.masterLimiter.attack.value = 0.003;
            this.masterLimiter.release.value = 0.08;
            this.masterGain = this.context.createGain();
            this.masterGain.gain.value = 1;
            this.masterInput.connect(this.masterLow);
            this.masterLow.connect(this.masterMid);
            this.masterMid.connect(this.masterHigh);
            this.masterHigh.connect(this.masterCompressor);
            this.masterCompressor.connect(this.masterLimiter);
            this.masterLimiter.connect(this.masterGain);
            this.masterGain.connect(this.context.destination);
"""
    if old not in t:
        raise RuntimeError("AudioEngine constructor anchor not found")
    t = t.replace(old, new, 1)
    if "gain.connect(this.masterGain);" not in t:
        raise RuntimeError("Track output anchor not found")
    t = t.replace("gain.connect(this.masterGain);", "gain.connect(this.masterInput);", 1)
    marker = "        setMasterVolume(value) {\n"
    methods = """        setMasterEq({low, mid, high}) {
            const now = this.context.currentTime;
            if (Number.isFinite(Number(low))) this.masterLow.gain.setTargetAtTime(Number(low), now, 0.02);
            if (Number.isFinite(Number(mid))) this.masterMid.gain.setTargetAtTime(Number(mid), now, 0.02);
            if (Number.isFinite(Number(high))) this.masterHigh.gain.setTargetAtTime(Number(high), now, 0.02);
        }

        setMasterCompression(value) {
            const amount = Math.max(0, Math.min(100, Number(value) || 0)) / 100;
            const now = this.context.currentTime;
            this.masterCompressor.threshold.setTargetAtTime(-28 * amount, now, 0.02);
            this.masterCompressor.ratio.setTargetAtTime(1 + 3.5 * amount, now, 0.02);
            this.masterCompressor.knee.setTargetAtTime(18 + 8 * amount, now, 0.02);
        }

"""
    if marker not in t:
        raise RuntimeError("setMasterVolume anchor not found")
    t = t.replace(marker, methods + marker, 1)
    old_dispose = """            try { this.masterGain.disconnect(); } catch (_) {}
            try { this.context.close(); } catch (_) {}
"""
    new_dispose = """            try { this.masterInput.disconnect(); } catch (_) {}
            try { this.masterLow.disconnect(); } catch (_) {}
            try { this.masterMid.disconnect(); } catch (_) {}
            try { this.masterHigh.disconnect(); } catch (_) {}
            try { this.masterCompressor.disconnect(); } catch (_) {}
            try { this.masterLimiter.disconnect(); } catch (_) {}
            try { this.masterGain.disconnect(); } catch (_) {}
            try { this.context.close(); } catch (_) {}
"""
    if old_dispose not in t:
        raise RuntimeError("AudioEngine dispose anchor not found")
    t = t.replace(old_dispose, new_dispose, 1)
    save(p, t, "AudioEngine master FX chain")

def patch_template() -> None:
    p = ROOT / "templates/stems/index.html.twig"
    t = p.read_text(encoding="utf-8")
    t = re.sub(
        r'<div class="stem-mixer-grid stem-mixer-head" aria-hidden="true">.*?</div>',
        '<div class="stem-mixer-grid stem-mixer-head" aria-hidden="true">\n'
        '        <span>{{ \'stems.mixer.track\'|trans({}, \'stems\') }}</span>'
        '<span>{{ \'stems.mixer.enabled\'|trans({}, \'stems\') }}</span>'
        '<span>{{ \'stems.mixer.volume\'|trans({}, \'stems\') }}</span>\n'
        '    </div>',
        t, count=1, flags=re.S
    )
    t, n = re.subn(
        r'(<label class="stem-mixer-toggle">.*?</label>\s*)'
        r'(<label class="stem-mixer-slider">\s*<input type="range" min="0" max="1\.25".*?</label>)\s*'
        r'<label class="stem-mixer-slider">.*?data-track-low.*?</label>\s*'
        r'<label class="stem-mixer-slider">.*?data-track-mid.*?</label>\s*'
        r'<label class="stem-mixer-slider">.*?data-track-high.*?</label>\s*'
        r'<button type="button" data-track-reset>.*?</button>',
        r'\1\2', t, count=1, flags=re.S
    )
    if n == 0 and "data-track-low" in t:
        raise RuntimeError("Per-track EQ block not recognized")
    master = (PAYLOAD / "r29-master-block.twig").read_text(encoding="utf-8")
    t, n = re.subn(
        r'<div class="stem-master-row">.*?</div>\s*(?=<div class="stem-transport">)',
        master,
        t, count=1, flags=re.S
    )
    if n == 0 and "stem-master-fx" not in t:
        raise RuntimeError("Master row not found")
    t = re.sub(r'stems\.css\?v=[^"]+', 'stems.css?v=20260926r29', t)
    t = re.sub(r'ezscore-audio-engine\.js\?v=[^"]+', 'ezscore-audio-engine.js?v=20260926r29', t)
    t = re.sub(r'stems-mixer\.js\?v=[^"]+', 'stems-mixer.js?v=20260926r29', t)
    save(p, t, "Compact responsive mixer template")

def patch_css() -> None:
    p = ROOT / "public/assets/css/stems.css"
    t = p.read_text(encoding="utf-8")
    marker = "R29 compact professional mixer + master FX"
    if marker in t:
        print("[OK] R29 CSS already applied")
        return
    css = (PAYLOAD / "r29-master.css").read_text(encoding="utf-8")
    save(p, t.rstrip() + "\n\n" + css + "\n", "Responsive mixer CSS")

def patch_controller() -> None:
    p = ROOT / "src/Controller/SongStemController.php"
    t = p.read_text(encoding="utf-8")
    pat = re.compile(r'    private function sanitizeMixSettings\(array \$settings\): array\s*\{.*?\n    \}\n\n    private function clampFloat', re.S)
    rep = """    private function sanitizeMixSettings(array $settings): array
    {
        $allowedTracks = [
            'original',
            'lead_vocals',
            'backing_vocals',
            'drums',
            'bass',
            'guitar',
            'piano',
            'other',
        ];
        $tracks = [];
        $rawTracks = is_array($settings['tracks'] ?? null) ? $settings['tracks'] : [];
        foreach ($allowedTracks as $track) {
            $raw = is_array($rawTracks[$track] ?? null) ? $rawTracks[$track] : [];
            $tracks[$track] = [
                'enabled' => (bool) ($raw['enabled'] ?? ($track === 'original')),
                'volume' => $this->clampFloat($raw['volume'] ?? ($track === 'original' ? 1.0 : 0.72), 0.0, 1.25),
            ];
        }
        $eq = is_array($settings['master_eq'] ?? null) ? $settings['master_eq'] : [];
        return [
            'schema_version' => 'ezscore.stem_mix.v2',
            'master_volume' => $this->clampFloat($settings['master_volume'] ?? 1.0, 0.0, 1.25),
            'playback_rate' => $this->clampFloat($settings['playback_rate'] ?? 1.0, 0.75, 1.25),
            'master_eq' => [
                'low' => $this->clampFloat($eq['low'] ?? 0.0, -12.0, 12.0),
                'mid' => $this->clampFloat($eq['mid'] ?? 0.0, -12.0, 12.0),
                'high' => $this->clampFloat($eq['high'] ?? 0.0, -12.0, 12.0),
            ],
            'master_compression' => $this->clampFloat($settings['master_compression'] ?? 0.0, 0.0, 100.0),
            'tracks' => $tracks,
        ];
    }

    private function clampFloat"""
    new, n = pat.subn(rep, t, count=1)
    if n != 1:
        raise RuntimeError("sanitizeMixSettings not found")
    save(p, new, "Persist master FX v2")

def patch_translations() -> None:
    values = {
        "stems.fr.yaml": [
            ("stems.mixer.master_fx", "Traitement master"),
            ("stems.mixer.compression", "Compression"),
            ("stems.mixer.limiter", "Limiteur actif"),
            ("stems.mixer.reset_master_fx", "Réinitialiser"),
        ],
        "stems.en.yaml": [
            ("stems.mixer.master_fx", "Master processing"),
            ("stems.mixer.compression", "Compression"),
            ("stems.mixer.limiter", "Limiter active"),
            ("stems.mixer.reset_master_fx", "Reset"),
        ],
    }
    for name, items in values.items():
        p = ROOT / "translations" / name
        t = p.read_text(encoding="utf-8")
        for key, value in items:
            if not re.search(r'(?m)^' + re.escape(key) + r'\s*:', t):
                t = t.rstrip() + f"\n{key}: '{value}'\n"
        save(p, t, "translations " + name)

def install_js() -> None:
    src = PAYLOAD / "r29-stems-mixer.js"
    dst = ROOT / "public/assets/js/stems-mixer.js"
    backup = BACKUP / dst.relative_to(ROOT)
    backup.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(dst, backup)
    shutil.copy2(src, dst)
    print("[OK] stems-mixer.js")

def main() -> int:
    patch_engine()
    patch_template()
    patch_css()
    patch_controller()
    patch_translations()
    install_js()
    print(f"[OK] backup: {BACKUP}")
    print("[OK] R29 master FX + compact responsive mixer applied.")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
