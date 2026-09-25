(() => {
    const progressRoot = document.querySelector('[data-stem-progress]');
    const jobStatusEl = document.querySelector('[data-stem-job-status]');
    const progressMessageEl = document.querySelector('[data-stem-progress-message]');
    const progressPercentEl = document.querySelector('[data-stem-progress-percent]');
    let progressBarEl = document.querySelector('[data-stem-progress-bar]');
    const progressWaitEl = document.querySelector('[data-stem-progress-wait]');
    const progressMetaEl = document.querySelector('[data-stem-progress-meta]');
    const progressNoteEl = document.querySelector('[data-stem-progress-note]');
    const statusUrl = progressRoot?.dataset.stemStatusUrl || null;

    const reanalyzeForm = document.querySelector('[data-stem-reanalyze-form]');
    const reanalyzeModal = document.querySelector('[data-stem-reanalyze-modal]');
    const reanalyzeCancel = document.querySelector('[data-stem-reanalyze-cancel]');
    const reanalyzeConfirm = document.querySelector('[data-stem-reanalyze-confirm]');
    let reanalyzeConfirmed = false;

    const closeReanalyzeModal = () => {
        if (!reanalyzeModal) return;
        reanalyzeModal.hidden = true;
        document.body.classList.remove('stem-modal-open');
    };

    const openReanalyzeModal = () => {
        if (!reanalyzeModal) return;
        reanalyzeModal.hidden = false;
        document.body.classList.add('stem-modal-open');
        window.setTimeout(() => reanalyzeConfirm?.focus(), 0);
    };

    reanalyzeForm?.addEventListener('submit', (event) => {
        if (reanalyzeConfirmed) return;
        event.preventDefault();
        openReanalyzeModal();
    });

    reanalyzeCancel?.addEventListener('click', closeReanalyzeModal);

    reanalyzeConfirm?.addEventListener('click', () => {
        if (!reanalyzeForm) return;
        reanalyzeConfirmed = true;
        closeReanalyzeModal();
        reanalyzeForm.requestSubmit();
    });

    reanalyzeModal?.addEventListener('click', (event) => {
        if (event.target === reanalyzeModal) closeReanalyzeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && reanalyzeModal && !reanalyzeModal.hidden) {
            closeReanalyzeModal();
        }
    });

    if (!progressRoot || !statusUrl) return;

    let timer = null;
    let requestRunning = false;
    let terminalHandled = false;

    const stopPolling = () => {
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
    };

    const statusLabels = {
        queued: 'EN ATTENTE',
        running: 'EN COURS',
        completed: 'TERMINÉ',
        failed: 'ÉCHEC',
        cancelled: 'ANNULÉ'
    };

    const render = (payload) => {
        const progress = payload.progress || null;
        const jobStatus = payload.job_status || null;

        if (jobStatusEl && jobStatus) {
            jobStatusEl.textContent = statusLabels[jobStatus] || String(jobStatus).toUpperCase();
            jobStatusEl.className = `stem-job-badge state-${jobStatus}`;
            jobStatusEl.setAttribute('data-stem-job-status', '');
        }

        if (progress) {
            const pct = Number(progress.percent);

            if (progressMessageEl && progress.message) {
                progressMessageEl.textContent = String(progress.message);
            }

            if (progressWaitEl && jobStatus === 'running') {
                progressWaitEl.hidden = true;
            }

            if (Number.isFinite(pct)) {
                if (progressPercentEl) {
                    progressPercentEl.hidden = false;
                    progressPercentEl.textContent = `${pct} %`;
                }

                if (!progressBarEl && progressWaitEl && progressWaitEl.parentElement) {
                    const bar = document.createElement('progress');
                    bar.max = 100;
                    bar.value = pct;
                    bar.setAttribute('data-stem-progress-bar', '');
                    progressWaitEl.parentElement.insertBefore(bar, progressWaitEl.nextSibling);
                    progressBarEl = bar;
                } else if (progressBarEl) {
                    progressBarEl.value = pct;
                }
            }

            if (progressMetaEl) {
                const parts = [];
                if (progress.stage) parts.push(String(progress.stage));
                if (Number.isFinite(Number(progress.engine_percent))) {
                    parts.push(`Moteur : ${Number(progress.engine_percent)} %`);
                }
                if (Number.isFinite(Number(progress.elapsed_seconds))) {
                    parts.push(`Temps étape : ${Number(progress.elapsed_seconds).toFixed(1)} s`);
                }
                if (progress.updated_at) {
                    const d = new Date(progress.updated_at);
                    if (!Number.isNaN(d.getTime())) {
                        parts.push(`Activité : ${d.toLocaleTimeString()}`);
                    }
                }
                progressMetaEl.innerHTML = parts.map((value) => `<small>${value}</small>`).join('');
            }
        }

        if (!terminalHandled && (jobStatus === 'completed' || jobStatus === 'failed' || jobStatus === 'cancelled')) {
            terminalHandled = true;
            stopPolling();

            if (progressNoteEl) {
                progressNoteEl.textContent = jobStatus === 'completed'
                    ? 'Analyse terminée. Chargement du résultat…'
                    : 'Analyse terminée avec une erreur.';
            }

            // One single reload reveals the persisted result/error.
            // After reload, job_active is false, so this polling script has no status hook
            // and cannot create a reload loop.
            window.setTimeout(() => window.location.reload(), 500);
        }
    };

    const refresh = async () => {
        if (requestRunning || terminalHandled) return;
        requestRunning = true;

        try {
            const response = await fetch(statusUrl, {
                headers: {'Accept': 'application/json'},
                cache: 'no-store',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            render(await response.json());
        } catch (error) {
            if (progressNoteEl) {
                progressNoteEl.textContent = `Mise à jour temporairement indisponible (${error.message}).`;
            }
        } finally {
            requestRunning = false;
        }
    };

    refresh();
    timer = window.setInterval(refresh, 2000);

    window.addEventListener('beforeunload', stopPolling);
})();
