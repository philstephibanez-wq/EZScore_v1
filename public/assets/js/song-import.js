(() => {
    const input = document.querySelector('input[name="cover"]');
    const preview = document.querySelector('[data-cover-preview]');
    if (!input || !preview) return;
    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        if (!file) { preview.innerHTML = '<span aria-hidden="true">♫</span>'; return; }
        const url = URL.createObjectURL(file);
        const img = document.createElement('img');
        img.src = url; img.alt = '';
        img.onload = () => URL.revokeObjectURL(url);
        preview.innerHTML = ''; preview.appendChild(img);
    });
})();
