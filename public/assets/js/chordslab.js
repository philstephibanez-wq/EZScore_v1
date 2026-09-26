(() => {
    'use strict';

    const root = document.querySelector('[data-chordslab]');
    if (!root) return;

    const events = (() => {
        try { return JSON.parse(root.dataset.events || '[]'); }
        catch (_) { return []; }
    })();

    let capo = Number(root.dataset.capo || 0);
    let signature = root.dataset.timeSignature || '4/4';
    const measuresEl = root.querySelector('[data-chordslab-measures]');
    const diagramEl = root.querySelector('[data-chord-diagram]');
    const diagramToggle = document.querySelector('[data-chordslab-diagram]');
    const capoSelect = document.querySelector('[data-chordslab-capo]');
    const timeSigSelect = document.querySelector('[data-chordslab-timesig]');

    if (!measuresEl || !events.length) return;

    const NOTE_TO_PC = {C:0,'C#':1,Db:1,D:2,'D#':3,Eb:3,E:4,F:5,'F#':6,Gb:6,G:7,'G#':8,Ab:8,A:9,'A#':10,Bb:10,B:11};
    const PCS_SHARP = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];
    const PCS_FLAT = ['C','Db','D','Eb','E','F','Gb','G','Ab','A','Bb','B'];

    const SHAPES = {
        C:'x32010', Cm:'x35543', C7:'x32310',
        D:'xx0232', Dm:'xx0231', D7:'xx0212',
        E:'022100', Em:'022000', E7:'020100',
        F:'133211', Fm:'133111', F7:'131211',
        G:'320003', Gm:'355333', G7:'320001',
        A:'x02220', Am:'x02210', A7:'x02020',
        B:'x24442', Bm:'x24432', B7:'x21202',
    };

    function parseSignature(value) {
        const match = /^(\d+)\/(\d+)$/.exec(value);
        return match ? {num:Number(match[1]), den:Number(match[2])} : {num:4, den:4};
    }

    function displayChord(chord) {
        if (!chord || chord === '.') return chord || '.';
        const match = /^([A-G](?:#|b)?)(.*)$/.exec(chord);
        if (!match) return chord;
        const [, rootNote, suffix] = match;
        const pc = NOTE_TO_PC[rootNote];
        if (pc === undefined) return chord;
        const next = (pc - capo + 120) % 12;
        const names = rootNote.includes('b') ? PCS_FLAT : PCS_SHARP;
        return names[next] + suffix;
    }

    function buildMeasures() {
        const sig = parseSignature(signature);
        const sorted = [...events].sort((a,b) => (a.start_ms - b.start_ms) || (a.id - b.id));
        const maxMeasure = Math.max(...sorted.map(e => Number.isInteger(e.measure_index) ? e.measure_index : 0));
        const measures = [];
        let current = null;
        let sourceEventId = null;

        for (let measure = 0; measure <= maxMeasure; measure++) {
            const slots = Array.from({length:sig.num}, () => ({text:'-', eventId:sourceEventId, startMs:null}));
            const inMeasure = sorted.filter(e => (e.measure_index ?? 0) === measure);

            if (current !== null) {
                slots[0] = {text:displayChord(current), eventId:sourceEventId, startMs:null, carry:true};
            } else {
                slots[0] = {text:'.', eventId:null, startMs:null, carry:true};
            }

            for (const event of inMeasure) {
                const beat = Math.max(0, Math.min(sig.num - 1, Number(event.beat_index ?? 0)));
                current = event.effective || event.original || '.';
                sourceEventId = event.id;
                slots[beat] = {text:displayChord(current), eventId:event.id, startMs:event.start_ms, carry:false};
                for (let i=beat+1;i<sig.num;i++) {
                    if (!inMeasure.some(candidate => Number(candidate.beat_index ?? 0) === i)) {
                        slots[i] = {text:'-', eventId:sourceEventId, startMs:null};
                    }
                }
            }

            measures.push({index:measure, slots});
        }

        return measures;
    }

    function render() {
        const measures = buildMeasures();
        measuresEl.innerHTML = '';

        for (const measure of measures) {
            const box = document.createElement('div');
            box.className = 'chord-measure';
            box.dataset.measure = String(measure.index);

            const number = document.createElement('small');
            number.className = 'chord-measure-number';
            number.textContent = String(measure.index + 1);
            box.appendChild(number);

            const notation = document.createElement('div');
            notation.className = 'chord-measure-notation';

            for (let i=0;i<measure.slots.length;i++) {
                const slot = measure.slots[i];
                const span = document.createElement('button');
                span.type = 'button';
                span.className = 'chord-slot';
                span.dataset.beat = String(i);
                if (slot.eventId) span.dataset.eventId = String(slot.eventId);
                if (slot.startMs !== null) span.dataset.startMs = String(slot.startMs);
                span.textContent = slot.text;
                if (slot.text !== '-' && slot.text !== '.' && slot.eventId) {
                    span.classList.add('editable');
                    span.title = 'Modifier cet accord';
                }
                notation.appendChild(span);
            }

            box.appendChild(notation);
            measuresEl.appendChild(box);
        }
    }

    function currentEventAt(ms) {
        let current = null;
        for (const event of events) {
            if (event.start_ms <= ms && (!current || event.start_ms >= current.start_ms)) current = event;
        }
        return current;
    }

    function highlightAt(seconds) {
        const ms = seconds * 1000;
        const current = currentEventAt(ms);
        document.querySelectorAll('.chord-slot.is-current').forEach(el => el.classList.remove('is-current'));
        document.querySelectorAll('.chord-measure.is-current').forEach(el => el.classList.remove('is-current'));
        if (!current) {
            updateDiagram(null);
            return;
        }

        const selector = `.chord-slot[data-event-id="${current.id}"]`;
        const slot = measuresEl.querySelector(selector);
        if (slot) {
            slot.classList.add('is-current');
            const measure = slot.closest('.chord-measure');
            measure?.classList.add('is-current');
            measure?.scrollIntoView({behavior:'smooth', inline:'center', block:'nearest'});
        }
        updateDiagram(displayChord(current.effective || current.original || '.'));
    }

    function updateDiagram(chord) {
        if (!diagramEl || !diagramToggle?.checked || !chord || chord === '.') {
            if (diagramEl) diagramEl.hidden = true;
            return;
        }

        diagramEl.hidden = false;
        const simple = chord.replace(/\/.*$/, '');
        const shape = SHAPES[simple];
        if (!shape) {
            diagramEl.innerHTML = `<strong>${escapeHtml(chord)}</strong><small>Diagramme non disponible</small>`;
            return;
        }

        const strings = shape.split('');
        let circles = '';
        strings.forEach((fret, stringIndex) => {
            const x = 18 + stringIndex * 18;
            if (fret === 'x') {
                circles += `<text x="${x}" y="12" text-anchor="middle" font-size="10">×</text>`;
            } else if (fret === '0') {
                circles += `<circle cx="${x}" cy="10" r="4" fill="none" stroke="currentColor"/>`;
            } else {
                const y = 27 + (Number(fret) - 1) * 18;
                circles += `<circle cx="${x}" cy="${y}" r="5" fill="currentColor"/>`;
            }
        });

        diagramEl.innerHTML = `<strong>${escapeHtml(chord)}</strong>
            <svg viewBox="0 0 120 105" role="img" aria-label="${escapeHtml(chord)}">
                <g stroke="currentColor" fill="none">
                    <path d="M18 18V90M36 18V90M54 18V90M72 18V90M90 18V90M108 18V90"/>
                    <path d="M18 18H108M18 36H108M18 54H108M18 72H108M18 90H108"/>
                </g>${circles}
            </svg>`;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
    }

    async function editEvent(eventId, button) {
        const event = events.find(e => String(e.id) === String(eventId));
        if (!event) return;

        const currentValue = event.effective || event.original || '';
        const input = document.createElement('input');
        input.className = 'chord-inline-input';
        input.value = currentValue;
        input.maxLength = 32;
        button.replaceWith(input);
        input.focus();
        input.select();

        const cancel = () => render();
        const save = async () => {
            const chord = input.value.trim();
            if (!chord || chord === currentValue) {
                render();
                return;
            }

            const url = root.dataset.editUrlTemplate.replace('__EVENT__', String(eventId));
            const response = await fetch(url, {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json'},
                credentials:'same-origin',
                body:JSON.stringify({_token:root.dataset.editToken, chord}),
            });

            if (!response.ok) {
                input.classList.add('is-error');
                return;
            }

            const data = await response.json();
            event.override = data.override;
            event.effective = data.effective;
            render();
        };

        input.addEventListener('keydown', e => {
            if (e.key === 'Enter') save();
            if (e.key === 'Escape') cancel();
        });
        input.addEventListener('blur', save, {once:true});
    }

    measuresEl.addEventListener('click', event => {
        const button = event.target.closest('.chord-slot[data-event-id]');
        if (!button || !button.classList.contains('editable')) return;
        editEvent(button.dataset.eventId, button);
    });

    document.querySelector('[data-stem-mixer]')?.addEventListener('ezscore:audio-timeupdate', event => {
        highlightAt(Number(event.detail?.time || 0));
    });

    capoSelect?.addEventListener('change', () => {
        capo = Number(capoSelect.value || 0);
        render();
    });

    timeSigSelect?.addEventListener('change', () => {
        signature = timeSigSelect.value || '4/4';
        render();
    });

    diagramToggle?.addEventListener('change', () => {
        if (!diagramToggle.checked && diagramEl) diagramEl.hidden = true;
    });

    render();
})();
