// R21 — local audio + cover preview before submit.
(() => {
    const audioInput = document.querySelector('[data-audio-input]');
    const audioPreview = document.querySelector('[data-audio-preview]');
    const audioPlayer = document.querySelector('[data-audio-player]');
    const audioName = document.querySelector('[data-audio-preview-name]');

    let audioObjectUrl = null;

    const clearAudioPreview = () => {
        if (!audioPlayer || !audioPreview || !audioName) return;

        audioPlayer.pause();
        audioPlayer.removeAttribute('src');
        audioPlayer.load();
        audioPreview.hidden = true;
        audioName.textContent = '';

        if (audioObjectUrl) {
            URL.revokeObjectURL(audioObjectUrl);
            audioObjectUrl = null;
        }
    };

    if (audioInput && audioPreview && audioPlayer && audioName) {
        audioInput.addEventListener('change', () => {
            clearAudioPreview();

            const file = audioInput.files && audioInput.files[0];
            if (!file) return;

            audioObjectUrl = URL.createObjectURL(file);
            audioPlayer.src = audioObjectUrl;
            audioName.textContent = file.name;
            audioPreview.hidden = false;
        });
    }

    const coverInput = document.querySelector('[data-cover-input]');
    const coverPreview = document.querySelector('[data-cover-preview]');
    let coverObjectUrl = null;

    if (coverInput && coverPreview) {
        coverInput.addEventListener('change', () => {
            if (coverObjectUrl) {
                URL.revokeObjectURL(coverObjectUrl);
                coverObjectUrl = null;
            }

            const file = coverInput.files && coverInput.files[0];
            if (!file) return;

            coverObjectUrl = URL.createObjectURL(file);

            const image = document.createElement('img');
            image.src = coverObjectUrl;
            image.alt = file.name;
            image.decoding = 'async';

            coverPreview.replaceChildren(image);
        });
    }

    window.addEventListener('beforeunload', () => {
        if (audioObjectUrl) URL.revokeObjectURL(audioObjectUrl);
        if (coverObjectUrl) URL.revokeObjectURL(coverObjectUrl);
    });
})();
