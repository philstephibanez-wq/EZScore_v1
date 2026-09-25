(() => {
    const consoleEl = document.querySelector('[data-stem-log-console]');
    const statusEl = document.querySelector('[data-stem-log-status]');
    const refreshButton = document.querySelector('[data-stem-log-refresh]');
    const jobStatusEl = document.querySelector('[data-stem-job-status]');
    const progressMessageEl = document.querySelector('[data-stem-progress-message]');
    const progressPercentEl = document.querySelector('[data-stem-progress-percent]');
    let progressBarEl = document.querySelector('[data-stem-progress-bar]');
    const progressWaitEl = document.querySelector('[data-stem-progress-wait]');
    const progressMetaEl = document.querySelector('[data-stem-progress-meta]');
    const url = consoleEl?.dataset.logUrl || null;
    let timer = null;
    let requestRunning = false;

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

    if (!consoleEl || !url) return;

    const nearBottom = () =>
        consoleEl.scrollHeight - consoleEl.scrollTop - consoleEl.clientHeight < 80;

    const renderProgress = (progress) => {
        if (!statusEl) return;
        if (!progress) {
            statusEl.textContent = 'Aucune progression Python disponible.';
            return;
        }

        const parts = [];
        if (progress.stage) parts.push(String(progress.stage));
        if (Number.isFinite(Number(progress.percent))) parts.push(`${Number(progress.percent)} %`);
        if (Number.isFinite(Number(progress.engine_percent))) parts.push(`moteur ${Number(progress.engine_percent)} %`);
        if (Number.isFinite(Number(progress.elapsed_seconds))) parts.push(`${Number(progress.elapsed_seconds).toFixed(1)} s`);
        statusEl.textContent = parts.length ? parts.join(' · ') : 'Worker actif.';
    };

    const statusLabels = {
        queued: 'EN ATTENTE',
        running: 'EN COURS',
        completed: 'TERMINÉ',
        failed: 'ÉCHEC',
        cancelled: 'ANNULÉ'
    };

    const renderMainProgress = (payload) => {
        const progress = payload.progress || null;
        const jobStatus = payload.job_status || null;

        if (jobStatusEl && jobStatus) {
            jobStatusEl.textContent = statusLabels[jobStatus] || jobStatus.toUpperCase();
            jobStatusEl.className = `stem-job-badge state-${jobStatus}`;
            jobStatusEl.setAttribute('data-stem-job-status', '');
        }

        if (!progress) return;

        const pct = Number(progress.percent);
        if (progressMessageEl && progress.message) progressMessageEl.textContent = String(progress.message);

        if (progressWaitEl && jobStatus === 'running') progressWaitEl.hidden = true;

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
            if (Number.isFinite(Number(progress.engine_percent))) parts.push(`Moteur : ${Number(progress.engine_percent)} %`);
            if (Number.isFinite(Number(progress.elapsed_seconds))) parts.push(`Temps étape : ${Number(progress.elapsed_seconds).toFixed(1)} s`);
            if (progress.updated_at) {
                const d = new Date(progress.updated_at);
                if (!Number.isNaN(d.getTime())) parts.push(`Activité : ${d.toLocaleTimeString()}`);
            }
            progressMetaEl.innerHTML = parts.map(v => `<small>${v}</small>`).join('');
        }
    };

    const refresh = async () => {
        if (requestRunning) return;
        requestRunning = true;
        const stickToBottom = nearBottom();

        try {
            const response = await fetch(url, {
                headers: {'Accept': 'application/json'},
                cache: 'no-store',
                credentials: 'same-origin'
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const payload = await response.json();
            renderProgress(payload.progress || null);
            renderMainProgress(payload);

            if (payload.exists && payload.content) {
                consoleEl.textContent = payload.content;
                consoleEl.classList.remove('is-error');
                if (stickToBottom) consoleEl.scrollTop = consoleEl.scrollHeight;
            } else {
                consoleEl.textContent = 'Aucun log disponible pour le moment.';
            }
        } catch (error) {
            consoleEl.classList.add('is-error');
            if (statusEl) statusEl.textContent = `Lecture log impossible : ${error.message}`;
        } finally {
            requestRunning = false;
        }
    };

    refreshButton?.addEventListener('click', refresh);
    refresh();
    timer = window.setInterval(refresh, 2000);

    window.addEventListener('beforeunload', () => {
        if (timer) window.clearInterval(timer);
    });
})();
