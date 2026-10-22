(() => {
    'use strict';
    const form = document.querySelector('[data-branding-form]');
    if (!form) return;
    const inputs = [...form.querySelectorAll('[data-branding-file]')];
    const save = form.querySelector('[data-branding-save]');
    const sync = () => save.disabled = !inputs.some(input => input.files.length > 0);
    for (const input of inputs) {
        const preview = form.querySelector(`[data-branding-preview="${input.dataset.brandingFile}"]`);
        const error = form.querySelector(`[data-branding-error="${input.dataset.brandingFile}"]`);
        const original = preview.src;
        input.addEventListener('change', () => {
            error.textContent = ''; preview.src = original; input.removeAttribute('aria-invalid');
            const file = input.files[0];
            if (!file) { sync(); return; }
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2097152) {
                error.textContent = file.size > 2097152 ? input.dataset.tooLarge : input.dataset.invalid;
                input.setAttribute('aria-invalid', 'true');
                input.value = ''; sync(); return;
            }
            const reader = new FileReader();
            reader.addEventListener('load', () => { if (input.files[0] === file) preview.src = reader.result; });
            reader.addEventListener('error', () => { error.textContent = input.dataset.invalid; input.setAttribute('aria-invalid', 'true'); input.value = ''; sync(); });
            reader.readAsDataURL(file); sync();
        });
    }
    sync();
    addEventListener('pageshow', sync);
})();
