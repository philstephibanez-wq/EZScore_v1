(() => {
    'use strict';

    const root = document.querySelector('[data-stem-mixer]');
    if (!root) return;

    if (typeof window.EZScoreAudioEngine !== 'function') {
        const state = root.querySelector('[data-mixer-state]');
        if (state) state.textContent = 'AudioEngine indisponible';
        return;
    }

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
        ready: root.dataset.i18nReady || 'Ready',
        playing: root.dataset.i18nPlaying || 'Playing',
        paused: root.dataset.i18nPaused || 'Paused',
        finished: root.dataset.i18nFinished || 'Finished',
        audioErrorTemplate: root.dataset.i18nAudioErrorTemplate || 'Audio error: __ERROR__'
    };

    const fmtTemplate = (value, replacements) => {
        let out = value;
        Object.entries(replacements).forEach(([key, replacement]) => {
            out = out.split(key).join(String(replacement));
        });
        return out;
    };

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

    let saveTimer = 0;
    let disposed = false;

    const engine = new window.EZScoreAudioEngine({
        masterTrackKey: 'original',
        syncThresholdSeconds: 0.060,
        syncIntervalMs: 250,
    });

    const tracks = new Map();

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
        if (disposed || !saveUrl || !csrfToken) return;
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
            if (!disposed && saveStateEl) saveStateEl.textContent = t.saveFailed;
        }
    };

    const scheduleSave = () => {
        window.clearTimeout(saveTimer);
        if (saveStateEl) saveStateEl.textContent = t.modified;
        saveTimer = window.setTimeout(saveSettings, 450);
    };

    const persisted = (() => {
        try {
            return JSON.parse(root.dataset.mixSettings || '{}') || {};
        } catch (_) {
            return {};
        }
    })();

    root.querySelectorAll('[data-mixer-track]').forEach((row) => {
        const key = row.dataset.trackKey;
        const enabled = row.querySelector('[data-track-enabled]');
        const volume = row.querySelector('[data-track-volume]');
        const volumeOutput = row.querySelector('[data-track-volume-output]');
        const lowInput = row.querySelector('[data-track-low]');
        const lowOutput = row.querySelector('[data-track-low-output]');
        const midInput = row.querySelector('[data-track-mid]');
        const midOutput = row.querySelector('[data-track-mid-output]');
        const highInput = row.querySelector('[data-track-high]');
        const highOutput = row.querySelector('[data-track-high-output]');
        const reset = row.querySelector('[data-track-reset]');
        const loader = row.querySelector('[data-track-loader]');
        const status = row.querySelector('[data-track-load-status]');

        const saved = persisted.tracks?.[key];
        if (saved && typeof saved === 'object') {
            if (typeof saved.enabled === 'boolean') enabled.checked = saved.enabled;
            if (Number.isFinite(Number(saved.volume))) volume.value = String(saved.volume);
            if (Number.isFinite(Number(saved.low))) lowInput.value = String(saved.low);
            if (Number.isFinite(Number(saved.mid))) midInput.value = String(saved.mid);
            if (Number.isFinite(Number(saved.high))) highInput.value = String(saved.high);
        }

        const track = {
            key,
            row,
            enabled,
            volume,
            volumeOutput,
            lowInput,
            lowOutput,
            midInput,
            midOutput,
            highInput,
            highOutput,
            reset,
            loader,
            status,
        };

        tracks.set(key, track);

        engine.addTrack({
            key,
            label: row.querySelector('strong')?.textContent?.trim() || key,
            url: row.dataset.trackUrl,
            enabled: enabled.checked,
            volume: Number(volume.value),
        });

        engine.setTrackEq(key, {
            low: Number(lowInput.value),
            mid: Number(midInput.value),
            high: Number(highInput.value),
        });

        const syncOutputs = () => {
            volumeOutput.textContent = `${Math.round(Number(volume.value) * 100)}%`;
            lowOutput.textContent = dbText(lowInput.value);
            midOutput.textContent = dbText(midInput.value);
            highOutput.textContent = dbText(highInput.value);
        };

        syncOutputs();

        enabled.addEventListener('change', () => {
            engine.setTrackEnabled(key, enabled.checked);
            scheduleSave();
        });

        volume.addEventListener('input', () => {
            volumeOutput.textContent = `${Math.round(Number(volume.value) * 100)}%`;
            engine.setTrackVolume(key, Number(volume.value));
            scheduleSave();
        });

        [
            [lowInput, lowOutput],
            [midInput, midOutput],
            [highInput, highOutput],
        ].forEach(([input, output]) => {
            input.addEventListener('input', () => {
                output.textContent = dbText(input.value);
                engine.setTrackEq(key, {
                    low: Number(lowInput.value),
                    mid: Number(midInput.value),
                    high: Number(highInput.value),
                });
                scheduleSave();
            });
        });

        reset.addEventListener('click', () => {
            lowInput.value = '0';
            midInput.value = '0';
            highInput.value = '0';
            syncOutputs();
            engine.setTrackEq(key, {low: 0, mid: 0, high: 0});
            scheduleSave();
        });
    });

    if (masterVolumeEl && Number.isFinite(Number(persisted.master_volume))) {
        masterVolumeEl.value = String(persisted.master_volume);
    }
    if (rateEl && Number.isFinite(Number(persisted.playback_rate))) {
        rateEl.value = String(persisted.playback_rate);
    }

    engine.setMasterVolume(Number(masterVolumeEl?.value || 1));
    engine.setPlaybackRate(Number(rateEl?.value || 1));

    if (masterVolumeOutput && masterVolumeEl) {
        masterVolumeOutput.textContent = `${Math.round(Number(masterVolumeEl.value) * 100)}%`;
    }

    masterVolumeEl?.addEventListener('input', () => {
        engine.setMasterVolume(Number(masterVolumeEl.value));
        masterVolumeOutput.textContent = `${Math.round(Number(masterVolumeEl.value) * 100)}%`;
        scheduleSave();
    });

    rateEl?.addEventListener('change', () => {
        engine.setPlaybackRate(Number(rateEl.value));
        scheduleSave();
    });

    playButton?.addEventListener('click', async () => {
        try {
            await engine.play();
        } catch (error) {
            if (stateEl) {
                stateEl.textContent = fmtTemplate(t.audioErrorTemplate, {
                    '__ERROR__': error instanceof Error ? error.message : String(error)
                });
            }
        }
    });

    pauseButton?.addEventListener('click', () => engine.pause());
    stopButton?.addEventListener('click', () => engine.stop());

    seekEl?.addEventListener('input', () => {
        const duration = engine.duration();
        if (!duration) return;
        engine.seek((Number(seekEl.value) / 1000) * duration);
    });

    engine.addEventListener('statechange', (event) => {
        const state = event.detail?.state;
        if (!stateEl) return;

        if (state === 'playing') stateEl.textContent = t.playing;
        else if (state === 'paused') stateEl.textContent = t.paused;
        else if (state === 'ready') stateEl.textContent = t.ready;
        else if (state === 'loading') stateEl.textContent = root.dataset.i18nLoading || 'Loading…';
    });

    engine.addEventListener('ended', () => {
        if (stateEl) stateEl.textContent = t.finished;
    });

    engine.addEventListener('error', (event) => {
        if (!stateEl) return;
        stateEl.textContent = fmtTemplate(t.audioErrorTemplate, {
            '__ERROR__': event.detail?.error || 'unknown'
        });
    });

    engine.addEventListener('trackstate', (event) => {
        const key = event.detail?.key;
        const state = event.detail?.state;
        const track = tracks.get(key);
        if (!track) return;

        const isBusy = state === 'loading' || state === 'buffering';

        if (track.loader) {
            track.loader.hidden = !isBusy;
        }

        if (track.status) {
            if (state === 'loading') track.status.textContent = 'chargement';
            else if (state === 'buffering') track.status.textContent = 'buffering';
            else if (state === 'error') track.status.textContent = 'erreur';
            else if (state === 'playing') track.status.textContent = 'lecture';
            else if (state === 'ready') track.status.textContent = 'prêt';
            else track.status.textContent = '';
        }

        track.row.classList.toggle('is-loading', isBusy);
        track.row.classList.toggle('is-error', state === 'error');
    });

    engine.addEventListener('timeupdate', (event) => {
        const time = Number(event.detail?.time || 0);
        const duration = Number(event.detail?.duration || 0);

        if (timeEl) timeEl.textContent = `${fmt(time)} / ${fmt(duration)}`;

        if (seekEl && duration > 0) {
            seekEl.value = String(Math.round((time / duration) * 1000));
        }
    });

    const dispose = () => {
        if (disposed) return;
        disposed = true;
        window.clearTimeout(saveTimer);
        engine.dispose();
    };

    window.addEventListener('pagehide', dispose, {once: true});
    window.addEventListener('beforeunload', dispose, {once: true});

    if (stateEl) stateEl.textContent = t.ready;
})();
