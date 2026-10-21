(() => {
    'use strict';
    const picker = document.querySelector('[data-contact-picker]');
    if (picker) {
        const supported = window.isSecureContext && window.self === window.top && typeof navigator.contacts?.select === 'function';
        const status = document.querySelector('[data-contact-picker-status]');
        picker.disabled = !supported;
        status.textContent = supported ? '' : picker.dataset.unavailable;
        picker.addEventListener('click', async () => {
            picker.disabled = true;
            try {
                // Call immediately inside the click handler to retain user activation.
                const contacts = await navigator.contacts.select(['name', 'tel'], {multiple: true});
                if (!contacts.length) { status.textContent = picker.dataset.cancelled; return; }
                if (contacts.length > 500) { status.textContent = picker.dataset.limit; return; }
                const form = document.querySelector('[data-contact-picker-form]');
                form.elements.contacts.value = JSON.stringify(contacts.map(contact => ({name: contact.name || [], tel: contact.tel || []})));
                form.requestSubmit();
            } catch (error) {
                status.textContent = error.name === 'AbortError' ? picker.dataset.cancelled : picker.dataset.error;
            } finally { picker.disabled = false; }
        });
    }
    const preview = document.querySelector('[data-contact-preview]');
    if (preview) {
        const boxes = [...preview.querySelectorAll('input[name="selected[]"]:not(:disabled)')];
        const all = preview.querySelector('[data-contact-all]');
        const count = preview.querySelector('[data-selected-count]');
        const save = preview.querySelector('[data-import-save]');
        // Store only row indices so language changes and reloads keep the selection.
        const selectionKey = 'mowallet.contacts.' + preview.elements.draft.value;
        try {
            const saved = JSON.parse(sessionStorage.getItem(selectionKey));
            if (Array.isArray(saved)) {
                const indices = new Set(saved.map(String));
                boxes.forEach(box => box.checked = indices.has(box.value));
            }
        } catch {}
        const update = () => {
            const selected = boxes.filter(box => box.checked).length;
            all.checked = boxes.length > 0 && selected === boxes.length;
            all.indeterminate = selected > 0 && selected < boxes.length;
            save.disabled = selected === 0;
            count.textContent = `${selected} ${count.dataset.label}`;
            try { sessionStorage.setItem(selectionKey, JSON.stringify(boxes.filter(box => box.checked).map(box => box.value))); } catch {}
        };
        boxes.forEach(box => box.addEventListener('change', update));
        all.addEventListener('change', () => { boxes.forEach(box => box.checked = all.checked); update(); });
        update();
        addEventListener('pageshow', update);
    }
})();
