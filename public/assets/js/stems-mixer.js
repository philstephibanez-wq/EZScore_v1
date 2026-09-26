(() => {
    const root = document.querySelector('[data-stem-mixer]');
    if (!root) return;

    const AudioContextCtor = window.AudioContext || window.webkitAudioContext;
    const stateEl = root.querySelector('[data-mixer-state]');
    const saveStateEl = root.querySelector('[data-mix-save-state]');
    const seekEl = root.querySelector('[data-mixer-seek]');
    const timeEl = root.querySelector('[data-mixer-time]');
    const playButton = root.querySelector('[data-mixer-play]');
    const pauseButton = root.querySelector('[data-mixer-pause]');
    const stopButton = root.querySelector('[data-mixer-stop]');
    const rateEl = root.querySelector('[data-mixer-rate]');
    const masterVolumeEl = root.querySelector('[data-master-volume]');
    const masterVolumeOutput = root.querySelector('[data-master-volume-output]');
    const saveUrl = root.dataset.mixSaveUrl;
    const csrfToken = root.dataset.mixToken;
    const t = {
        saved: root.dataset.i18nSaved || 'Saved',
        modified: root.dataset.i18nModified || 'Modified',
        saving: root.dataset.i18nSaving || 'Saving…',
        saveFailed: root.dataset.i18nSaveFailed || 'Save failed',
        webAudioUnavailable: root.dataset.i18nWebAudioUnavailable || 'Web Audio unavailable',
        ready: root.dataset.i18nReady || 'Ready',
        playing: root.dataset.i18nPlaying || 'Playing',
        paused: root.dataset.i18nPaused || 'Paused',
        finished: root.dataset.i18nFinished || 'Finished',
        loadingTemplate: root.dataset.i18nLoadingTemplate || 'Loading __DONE__/__TOTAL__',
        loadedPartialTemplate: root.dataset.i18nLoadedPartialTemplate || '__DONE__/__TOTAL__ tracks loaded',
        audioErrorTemplate: root.dataset.i18nAudioErrorTemplate || 'Audio error: __ERROR__'
    };
    const fmtTemplate = (value, replacements) => {
        let out = value;
        Object.entries(replacements).forEach(([key, replacement]) => {
            out = out.split(key).join(String(replacement));
        });
        return out;
    };

    if (!AudioContextCtor) {
        if (stateEl) stateEl.textContent = t.webAudioUnavailable;
        return;
    }

    const context = new AudioContextCtor({latencyHint: 'interactive'});
    const master = context.createGain();
    master.gain.value = 1;
    master.connect(context.destination);

    const tracks = new Map();
    let duration = 0;
    let position = 0;
    let startedAt = 0;
    let playing = false;
    let rate = 1;
    let raf = 0;
    let saveTimer = 0;

    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
    const fmt = (seconds) => {
        const safe = Math.max(0, Number(seconds) || 0);
        const minutes = Math.floor(safe / 60);
        const rest = Math.floor(safe % 60).toString().padStart(2, '0');
        return `${minutes}:${rest}`;
    };

    const dbText = (value) => {
        const n = Number(value) || 0;
        return `${n > 0 ? '+' : ''}${n.toFixed(n % 1 ? 1 : 0)} dB`;
    };

    const stopSources = () => {
        tracks.forEach((track) => {
            if (track.source) {
                try { track.source.stop(); } catch (_) {}
                try { track.source.disconnect(); } catch (_) {}
                track.source = null;
            }
        });
    };

    const currentPosition = () => {
        if (!playing) return position;
        return clamp(position + ((context.currentTime - startedAt) * rate), 0, duration || 0);
    };

    const setTrackGain = (track) => {
        const target = track.enabled.checked ? Number(track.volume.value) : 0;
        track.gain.gain.setTargetAtTime(target, context.currentTime, 0.01);
    };

    const applyEq = (track) => {
        track.low.gain.setTargetAtTime(Number(track.lowInput.value), context.currentTime, 0.01);
        track.mid.gain.setTargetAtTime(Number(track.midInput.value), context.currentTime, 0.01);
        track.high.gain.setTargetAtTime(Number(track.highInput.value), context.currentTime, 0.01);
    };

    const createSource = (track, offset) => {
        if (!track.buffer) return;
        const source = context.createBufferSource();
        source.buffer = track.buffer;
        source.playbackRate.value = rate;
        source.connect(track.low);
        source.start(0, Math.min(offset, Math.max(0, track.buffer.duration - 0.001)));
        track.source = source;
    };

    const restartSources = (offset) => {
        stopSources();
        if (!playing) return;
        tracks.forEach((track) => createSource(track, offset));
        position = offset;
        startedAt = context.currentTime;
    };

    const updateClock = () => {
        const pos = currentPosition();

        if (timeEl) timeEl.textContent = `${fmt(pos)} / ${fmt(duration)}`;
        if (seekEl && duration > 0) seekEl.value = String(Math.round((pos / duration) * 1000));

        if (playing && duration > 0 && pos >= duration - 0.02) {
            playing = false;
            position = 0;
            stopSources();
            if (seekEl) seekEl.value = '0';
            if (stateEl) stateEl.textContent = t.finished;
        }

        raf = window.requestAnimationFrame(updateClock);
    };

    const serializeSettings = () => {
        const out = {
            schema_version: 'ezscore.stem_mix.v1',
            master_volume: Number(masterVolumeEl?.value || 1),
            playback_rate: Number(rateEl?.value || 1),
            tracks: {}
        };

        tracks.forEach((track, key) => {
            out.tracks[key] = {
                enabled: track.enabled.checked,
                volume: Number(track.volume.value),
                low: Number(track.lowInput.value),
                mid: Number(track.midInput.value),
                high: Number(track.highInput.value)
            };
        });

        return out;
    };

    const saveSettings = async () => {
        if (!saveUrl || !csrfToken) return;
        if (saveStateEl) saveStateEl.textContent = t.saving;

        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    _token: csrfToken,
                    settings: serializeSettings()
                })
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            if (saveStateEl) saveStateEl.textContent = t.saved;
        } catch (_) {
            if (saveStateEl) saveStateEl.textContent = t.saveFailed;
        }
    };

    const scheduleSave = () => {
        window.clearTimeout(saveTimer);
        if (saveStateEl) saveStateEl.textContent = t.modified;
        saveTimer = window.setTimeout(saveSettings, 450);
    };

    const applyPersisted = () => {
        let settings = {};
        try {
            settings = JSON.parse(root.dataset.mixSettings || '{}') || {};
        } catch (_) {}

        if (masterVolumeEl && Number.isFinite(Number(settings.master_volume))) {
            masterVolumeEl.value = String(settings.master_volume);
        }
        if (rateEl && Number.isFinite(Number(settings.playback_rate))) {
            rateEl.value = String(settings.playback_rate);
        }

        const rawTracks = settings.tracks || {};
        tracks.forEach((track, key) => {
            const saved = rawTracks[key];
            if (!saved || typeof saved !== 'object') return;

            if (typeof saved.enabled === 'boolean') track.enabled.checked = saved.enabled;
            if (Number.isFinite(Number(saved.volume))) track.volume.value = String(saved.volume);
            if (Number.isFinite(Number(saved.low))) track.lowInput.value = String(saved.low);
            if (Number.isFinite(Number(saved.mid))) track.midInput.value = String(saved.mid);
            if (Number.isFinite(Number(saved.high))) track.highInput.value = String(saved.high);
        });
    };

    const syncOutputs = () => {
        tracks.forEach((track) => {
            track.volumeOutput.textContent = `${Math.round(Number(track.volume.value) * 100)}%`;
            track.lowOutput.textContent = dbText(track.lowInput.value);
            track.midOutput.textContent = dbText(track.midInput.value);
            track.highOutput.textContent = dbText(track.highInput.value);
            setTrackGain(track);
            applyEq(track);
        });

        if (masterVolumeEl) {
            master.gain.value = Number(masterVolumeEl.value);
            if (masterVolumeOutput) masterVolumeOutput.textContent = `${Math.round(Number(masterVolumeEl.value) * 100)}%`;
        }

        rate = Number(rateEl?.value || 1);
    };

    root.querySelectorAll('[data-mixer-track]').forEach((row) => {
        const low = context.createBiquadFilter();
        low.type = 'lowshelf';
        low.frequency.value = 180;

        const mid = context.createBiquadFilter();
        mid.type = 'peaking';
        mid.frequency.value = 1200;
        mid.Q.value = 0.9;

        const high = context.createBiquadFilter();
        high.type = 'highshelf';
        high.frequency.value = 5500;

        const gain = context.createGain();
        low.connect(mid);
        mid.connect(high);
        high.connect(gain);
        gain.connect(master);

        const track = {
            key: row.dataset.trackKey,
            url: row.dataset.trackUrl,
            row,
            buffer: null,
            source: null,
            low,
            mid,
            high,
            gain,
            enabled: row.querySelector('[data-track-enabled]'),
            volume: row.querySelector('[data-track-volume]'),
            volumeOutput: row.querySelector('[data-track-volume-output]'),
            lowInput: row.querySelector('[data-track-low]'),
            lowOutput: row.querySelector('[data-track-low-output]'),
            midInput: row.querySelector('[data-track-mid]'),
            midOutput: row.querySelector('[data-track-mid-output]'),
            highInput: row.querySelector('[data-track-high]'),
            highOutput: row.querySelector('[data-track-high-output]'),
            reset: row.querySelector('[data-track-reset]')
        };

        tracks.set(track.key, track);

        track.enabled.addEventListener('change', () => {
            setTrackGain(track);
            scheduleSave();
        });

        track.volume.addEventListener('input', () => {
            track.volumeOutput.textContent = `${Math.round(Number(track.volume.value) * 100)}%`;
            setTrackGain(track);
            scheduleSave();
        });

        [
            [track.lowInput, track.lowOutput],
            [track.midInput, track.midOutput],
            [track.highInput, track.highOutput]
        ].forEach(([input, output]) => {
            input.addEventListener('input', () => {
                output.textContent = dbText(input.value);
                applyEq(track);
                scheduleSave();
            });
        });

        track.reset.addEventListener('click', () => {
            track.lowInput.value = '0';
            track.midInput.value = '0';
            track.highInput.value = '0';
            track.lowOutput.textContent = '0 dB';
            track.midOutput.textContent = '0 dB';
            track.highOutput.textContent = '0 dB';
            applyEq(track);
            scheduleSave();
        });
    });

    applyPersisted();
    syncOutputs();

    masterVolumeEl?.addEventListener('input', () => {
        master.gain.setTargetAtTime(Number(masterVolumeEl.value), context.currentTime, 0.01);
        masterVolumeOutput.textContent = `${Math.round(Number(masterVolumeEl.value) * 100)}%`;
        scheduleSave();
    });

    rateEl?.addEventListener('change', () => {
        const pos = currentPosition();
        rate = Number(rateEl.value);
        if (playing) restartSources(pos);
        scheduleSave();
    });

    playButton?.addEventListener('click', async () => {
        if (!duration) return;
        if (context.state === 'suspended') await context.resume();
        if (playing) return;

        if (position >= duration - 0.02) position = 0;
        playing = true;
        startedAt = context.currentTime;
        tracks.forEach((track) => createSource(track, position));
        if (stateEl) stateEl.textContent = t.playing;
    });

    pauseButton?.addEventListener('click', () => {
        if (!playing) return;
        position = currentPosition();
        playing = false;
        stopSources();
        if (stateEl) stateEl.textContent = t.paused;
    });

    stopButton?.addEventListener('click', () => {
        playing = false;
        position = 0;
        stopSources();
        if (seekEl) seekEl.value = '0';
        if (stateEl) stateEl.textContent = t.ready;
    });

    seekEl?.addEventListener('input', () => {
        if (!duration) return;
        const newPosition = (Number(seekEl.value) / 1000) * duration;
        position = newPosition;
        if (playing) restartSources(newPosition);
    });

    const loadTrack = async (track) => {
        const response = await fetch(track.url, {
            credentials: 'same-origin',
            cache: 'force-cache'
        });
        if (!response.ok) throw new Error(`${track.key}: HTTP ${response.status}`);
        const bytes = await response.arrayBuffer();
        track.buffer = await context.decodeAudioData(bytes);
        duration = Math.max(duration, track.buffer.duration);
    };

    const loadAll = async () => {
        const all = Array.from(tracks.values());
        let done = 0;

        if (stateEl) stateEl.textContent = fmtTemplate(t.loadingTemplate, {'__DONE__': 0, '__TOTAL__': all.length});

        const results = await Promise.allSettled(all.map(async (track) => {
            await loadTrack(track);
            done += 1;
            if (stateEl) stateEl.textContent = fmtTemplate(t.loadingTemplate, {'__DONE__': done, '__TOTAL__': all.length});
        }));

        const failures = results.filter((result) => result.status === 'rejected');
        if (failures.length) {
            if (stateEl) stateEl.textContent = fmtTemplate(t.loadedPartialTemplate, {'__DONE__': all.length - failures.length, '__TOTAL__': all.length});
        } else {
            if (stateEl) stateEl.textContent = t.ready;
        }

        if (timeEl) timeEl.textContent = `0:00 / ${fmt(duration)}`;
    };

    loadAll().catch((error) => {
        if (stateEl) stateEl.textContent = fmtTemplate(t.audioErrorTemplate, {'__ERROR__': error.message});
    });

    raf = window.requestAnimationFrame(updateClock);

    window.addEventListener('beforeunload', () => {
        window.cancelAnimationFrame(raf);
        stopSources();
        window.clearTimeout(saveTimer);
    });
})();
