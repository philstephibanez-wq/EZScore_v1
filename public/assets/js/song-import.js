

// R13.2 — local audio pre-listen before import.
(() => {
    const input = document.querySelector('[data-audio-input]');
    const preview = document.querySelector('[data-audio-preview]');
    const player = document.querySelector('[data-audio-player]');
    const name = document.querySelector('[data-audio-preview-name]');

    if (!input || !preview || !player || !name) return;

    let objectUrl = null;

    const clearPreview = () => {
        player.pause();
        player.removeAttribute('src');
        player.load();
        preview.hidden = true;
        name.textContent = '';

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    };

    input.addEventListener('change', () => {
        clearPreview();

        const file = input.files && input.files[0];
        if (!file) return;

        objectUrl = URL.createObjectURL(file);
        player.src = objectUrl;
        name.textContent = file.name;
        preview.hidden = false;
    });

    window.addEventListener('beforeunload', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
    });
})();
