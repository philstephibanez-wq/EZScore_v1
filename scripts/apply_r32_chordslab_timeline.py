#!/usr/bin/env python3
from __future__ import annotations
from pathlib import Path
from datetime import datetime
import re, shutil

ROOT = Path(__file__).resolve().parents[1]
BACKUP = ROOT/"var"/"backup"/("r32-chordslab-"+datetime.now().strftime("%Y%m%d-%H%M%S"))

def backup(path: Path):
    if not path.exists():
        return
    dst=BACKUP/path.relative_to(ROOT)
    dst.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(path,dst)

def install(rel: str):
    src=ROOT/"scripts/_payload"/rel
    dst=ROOT/rel
    content=src.read_text(encoding="utf-8")
    if dst.exists() and dst.read_text(encoding="utf-8")==content:
        print(f"[OK] {rel}: already applied"); return
    backup(dst); dst.parent.mkdir(parents=True,exist_ok=True)
    dst.write_text(content,encoding="utf-8",newline="\n")
    print(f"[OK] {rel}")

def patch_song():
    path=ROOT/"src/Domain/Song/Song.php"
    text=path.read_text(encoding="utf-8")
    if "private string $chordAnalysisLevel" not in text:
        anchor="""    #[ORM\\Column]
    private int $capo = 0;
"""
        addition=anchor+"""
    #[ORM\\Column(name: 'key_signature', length: 16, nullable: true)]
    private ?string $keySignature = null;

    #[ORM\\Column(name: 'chord_analysis_level', length: 16)]
    private string $chordAnalysisLevel = 'intermediate';
"""
        if anchor not in text: raise RuntimeError("Song capo field anchor not found")
        text=text.replace(anchor,addition,1)

    old="""    public function setTimeSignature(string $timeSignature): self
    {
        $allowed = ['auto', '2/4', '3/4', '4/4', '5/4', '6/8', '9/8', '12/8'];
        if (!in_array($timeSignature, $allowed, true)) {
            throw new \\InvalidArgumentException(sprintf('Unsupported time signature "%s".', $timeSignature));
        }
        $this->timeSignature = $timeSignature;
        return $this->touch();
    }
"""
    new="""    public function setTimeSignature(string $timeSignature): self
    {
        if ($timeSignature !== 'auto') {
            if (!preg_match('/^(\\\\d{1,2})\\\\/(1|2|4|8|16|32)$/', $timeSignature, $matches)) {
                throw new \\InvalidArgumentException(sprintf('Unsupported time signature "%s".', $timeSignature));
            }

            $numerator = (int) $matches[1];
            if ($numerator < 1 || $numerator > 32) {
                throw new \\InvalidArgumentException(sprintf('Unsupported time signature "%s".', $timeSignature));
            }
        }

        $this->timeSignature = $timeSignature;
        return $this->touch();
    }
"""
    if old in text:
        text=text.replace(old,new,1)
    elif "preg_match('/^(\\\\d{1,2})" not in text:
        raise RuntimeError("Song time signature setter anchor not found")

    if "getKeySignature()" not in text:
        anchor="""    public function getCapo(): int { return $this->capo; }

"""
        methods="""    public function getKeySignature(): ?string { return $this->keySignature; }

    public function setKeySignature(?string $keySignature): self
    {
        if ($keySignature !== null) {
            $keySignature = trim($keySignature);
            if ($keySignature !== '' && !preg_match('/^[A-G](?:#|b)?(?:m)?$/', $keySignature)) {
                throw new \\InvalidArgumentException('Invalid musical key.');
            }
            if ($keySignature === '') {
                $keySignature = null;
            }
        }

        $this->keySignature = $keySignature;
        return $this->touch();
    }

    public function getChordAnalysisLevel(): string { return $this->chordAnalysisLevel; }

    public function setChordAnalysisLevel(string $level): self
    {
        if (!in_array($level, ['beginner', 'intermediate', 'expert'], true)) {
            throw new \\InvalidArgumentException('Invalid chord analysis level.');
        }

        $this->chordAnalysisLevel = $level;
        return $this->touch();
    }

"""
        if anchor not in text: raise RuntimeError("Song capo getter anchor not found")
        text=text.replace(anchor,methods+anchor,1)

    backup(path); path.write_text(text,encoding="utf-8",newline="\n")
    print("[OK] Song ChordsLab fields + extensible time signature")

def patch_mixer():
    path=ROOT/"public/assets/js/stems-mixer.js"
    text=path.read_text(encoding="utf-8")
    marker="ezscore:audio-timeupdate"
    if marker in text:
        print("[OK] mixer timeline bridge already applied"); return

    old="""    engine.addEventListener('timeupdate', (event) => {
        const time = Number(event.detail?.time || 0);
        const duration = Number(event.detail?.duration || 0);
        if (els.time) els.time.textContent = `${fmt(time)} / ${fmt(duration)}`;
        if (els.seek && duration > 0) {
            els.seek.value = String(Math.round((time / duration) * 1000));
        }
    });
"""
    new="""    engine.addEventListener('timeupdate', (event) => {
        const time = Number(event.detail?.time || 0);
        const duration = Number(event.detail?.duration || 0);
        if (els.time) els.time.textContent = `${fmt(time)} / ${fmt(duration)}`;
        if (els.seek && duration > 0) {
            els.seek.value = String(Math.round((time / duration) * 1000));
        }
        root.dispatchEvent(new CustomEvent('ezscore:audio-timeupdate', {
            detail: {time, duration},
        }));
    });
"""
    if old not in text: raise RuntimeError("stems-mixer timeupdate anchor not found")
    backup(path); path.write_text(text.replace(old,new,1),encoding="utf-8",newline="\n")
    print("[OK] player timeline event bridge")

def main():
    for rel in [
        "src/Domain/Song/SongTimelineEvent.php",
        "src/Domain/Song/SongTimelineEventRepository.php",
        "migrations/Version20260926130000.php",
        "templates/song/chordslab.html.twig",
        "public/assets/js/chordslab.js",
        "public/assets/css/chordslab.css",
        "translations/chordslab.fr.yaml",
        "translations/chordslab.en.yaml",
        "src/Controller/SongLabController.php",
    ]:
        install(rel)
    patch_song()
    patch_mixer()
    print(f"[OK] Backup: {BACKUP}")
    print("[OK] R32 ChordsLab canonical timeline applied.")

if __name__=="__main__":
    main()
