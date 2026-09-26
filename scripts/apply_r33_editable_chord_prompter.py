#!/usr/bin/env python3
from __future__ import annotations
from datetime import datetime
from pathlib import Path
import shutil

ROOT=Path(__file__).resolve().parents[1]
PAYLOAD=ROOT/"scripts"/"_payload"
BACKUP=ROOT/"var"/"backup"/("r33-editable-prompter-"+datetime.now().strftime("%Y%m%d-%H%M%S"))

def backup(path):
    path=Path(path)
    if not path.exists():return
    dst=BACKUP/path.relative_to(ROOT);dst.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(path,dst)

def save(path,text,label):
    path=Path(path);old=path.read_text(encoding="utf-8")
    if old==text:print(f"[OK] {label}: already applied");return
    backup(path);path.write_text(text,encoding="utf-8",newline="\n");print(f"[OK] {label}")

def install(rel):
    src=PAYLOAD/rel;dst=ROOT/rel;content=src.read_text(encoding="utf-8")
    if dst.exists() and dst.read_text(encoding="utf-8")==content:print(f"[OK] {rel}: already applied");return
    backup(dst);dst.parent.mkdir(parents=True,exist_ok=True);dst.write_text(content,encoding="utf-8",newline="\n");print(f"[OK] {rel}")

def snip(name):return (PAYLOAD/"snippets"/name).read_text(encoding="utf-8")

def patch_repository():
    path=ROOT/"src/Domain/Song/SongTimelineEventRepository.php";text=path.read_text(encoding="utf-8")
    if "deleteMusicalAnalysisForSong" in text:print("[OK] timeline repository reset: already applied");return
    anchor="    /** @return list<SongTimelineEvent> */\n    public function findChordEvents(Song $song): array\n"
    if anchor not in text:raise RuntimeError("SongTimelineEventRepository anchor not found")
    save(path,text.replace(anchor,snip("repository_method.txt")+anchor,1),"timeline repository reset")

def patch_controller():
    path=ROOT/"src/Controller/SongLabController.php";text=path.read_text(encoding="utf-8")
    if "use App\\Service\\ChordTimelineAnalysisService;" not in text:
        anchor="use App\\Service\\SongStemPlaybackStorage;\n"
        if anchor not in text:raise RuntimeError("SongLabController import anchor not found")
        text=text.replace(anchor,snip("controller_import.txt")+anchor,1)
    if "app_song_chordslab_analyze" not in text:
        anchor="    #[Route('/chords/settings', name: 'app_song_chordslab_settings', methods: ['POST'])]\n"
        if anchor not in text:raise RuntimeError("SongLabController settings anchor not found")
        text=text.replace(anchor,snip("controller_route.txt")+anchor,1)
    save(path,text,"Chord analysis endpoint")

def patch_template():
    path=ROOT/"templates/song/chordslab.html.twig";text=path.read_text(encoding="utf-8")
    text=text.replace("{{ 'chordslab.show_diagram'|trans({}, 'chordslab') }}","{{ 'chordslab.guitar_chords'|trans({}, 'chordslab') }}")
    if "app_song_chordslab_analyze" not in text:
        old="""        {% if chord_events %}
        <form method="post"
              action="{{ path('app_song_chordslab_reset', {'_locale': app.request.locale, id: song.id}) }}"
              onsubmit="return confirm('{{ 'chordslab.reset.confirm'|trans({}, 'chordslab')|e('js') }}');">
            <input type="hidden" name="_token" value="{{ csrf_token('song_chordslab_reset_' ~ song.id) }}">
            <button type="submit">{{ 'chordslab.reset.button'|trans({}, 'chordslab') }}</button>
        </form>
        {% endif %}
"""
        if old not in text:raise RuntimeError("ChordsLab action anchor not found")
        text=text.replace(old,snip("template_actions.txt"),1)
    text=text.replace("""            <a href="{{ path('app_song_analysis_lab', {'_locale': app.request.locale, id: song.id}) }}">{{ 'chordslab.go_analysis'|trans({}, 'chordslab') }}</a>""","""            <small>{{ 'chordslab.analyze.note'|trans({}, 'chordslab') }}</small>""")
    text=text.replace("/assets/js/chordslab.js?v=20260926r32","/assets/js/chordslab.js?v=20260926r33")
    save(path,text,"ChordsLab editable prompter UI")

def patch_translations():
    for locale,sname in (("fr","trans_fr.txt"),("en","trans_en.txt")):
        path=ROOT/"translations"/f"chordslab.{locale}.yaml";text=path.read_text(encoding="utf-8")
        if "  guitar_chords:" in text and "  analyze:" in text:print(f"[OK] chordslab.{locale}.yaml: already applied");continue
        if not text.startswith("chordslab:\n"):raise RuntimeError(f"Unexpected translation structure: {path}")
        save(path,text.rstrip()+"\n"+snip(sname),f"R33 {locale} translations")

def patch_css():
    path=ROOT/"public/assets/css/chordslab.css";text=path.read_text(encoding="utf-8")
    if ".chordslab-prompter-actions" in text:print("[OK] R33 CSS: already applied");return
    save(path,text.rstrip()+"\n\n"+snip("css_add.txt"),"R33 ChordsLab CSS")

def patch_flash():
    path=ROOT/"templates/base.html.twig";text=path.read_text(encoding="utf-8")
    if "message starts with 'chordslab.analyze.failed|'" in text:print("[OK] analysis error flash: already applied");return
    old="""{% for message in messages %}<div class="flash flash-{{ label }}">{{ message starts with 'event.' ? message|trans({}, 'event') : (message starts with 'chordslab.' ? message|trans({}, 'chordslab') : message|trans) }}</div>{% endfor %}"""
    new="""{% for message in messages %}
            {% if message starts with 'chordslab.analyze.failed|' %}
                {% set parts = message|split('|', 2) %}
                <div class="flash flash-{{ label }}">{{ 'chordslab.analyze.failed'|trans({'%error%': parts[1]|default('')}, 'chordslab') }}</div>
            {% else %}
                <div class="flash flash-{{ label }}">{{ message starts with 'event.' ? message|trans({}, 'event') : (message starts with 'chordslab.' ? message|trans({}, 'chordslab') : message|trans) }}</div>
            {% endif %}
        {% endfor %}"""
    if old not in text:raise RuntimeError("base flash anchor not found")
    save(path,text.replace(old,new,1),"ChordsLab analysis error flash")

def main():
    for rel in ("analysis/chord_timeline_analysis.py","analysis/requirements-chords.txt","src/Service/ChordTimelineAnalysisService.php","public/assets/js/chordslab.js"):install(rel)
    patch_repository();patch_controller();patch_template();patch_translations();patch_css();patch_flash()
    print(f"[OK] Backup: {BACKUP}");print("[OK] R33 editable ChordsLab prompter applied.")

if __name__=="__main__":main()
