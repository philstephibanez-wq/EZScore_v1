(() => {
    const banner = document.querySelector('[data-analysis-worker-banner]');
    if (!banner) return;

    const url = banner.dataset.statusUrl;
    if (!url) return;

    let timer = null;
    let requestRunning = false;

    const setOffline = (offline) => {
        banner.hidden = !offline;
        document.documentElement.classList.toggle('analysis-worker-offline', offline);
    };

    const refresh = async () => {
        if (requestRunning) return;
        requestRunning = true;

        try {
            const response = await fetch(url, {
                headers: {'Accept': 'application/json'},
                cache: 'no-store',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            setOffline(payload.online !== true);
        } catch (_) {
            // If EZScore cannot verify the worker, analyses must be considered unavailable.
            setOffline(true);
        } finally {
            requestRunning = false;
        }
    };

    refresh();
    timer = window.setInterval(refresh, 5000);

    window.addEventListener('beforeunload', () => {
        if (timer) window.clearInterval(timer);
    });
})();
