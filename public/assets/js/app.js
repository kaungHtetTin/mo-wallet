(() => {
    'use strict';
    const root = document.documentElement;
    const read = (key, fallback) => { try { return localStorage.getItem(key) || fallback; } catch { return fallback; } };
    const write = (key, value) => { try { localStorage.setItem(key, value); } catch {} };
    if (read('mowallet.design', '') !== 'emerald-v3') {
        write('mowallet.density', 'compact');
        write('mowallet.design', 'emerald-v3');
    }
    root.dataset.theme = read('mowallet.theme', 'light') === 'dark' ? 'dark' : 'light';
    if (!document.body.classList.contains('auth-body')) root.dataset.density = read('mowallet.density', 'compact') === 'comfortable' ? 'comfortable' : 'compact';
    const updateThemeButtons = () => document.querySelectorAll('[data-theme-toggle]').forEach(button => button.setAttribute('aria-pressed', String(root.dataset.theme === 'dark')));
    updateThemeButtons();
    document.querySelectorAll('[data-theme-toggle]').forEach(button => button.addEventListener('click', () => {
        root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark'; write('mowallet.theme', root.dataset.theme); updateThemeButtons();
    }));
    document.querySelectorAll('[data-density-select]').forEach(select => {
        select.value = root.dataset.density;
        select.addEventListener('change', () => { root.dataset.density = select.value; write('mowallet.density', select.value); });
    });
    const filterMedia = matchMedia('(max-width:760px)');
    document.querySelectorAll('[data-filter-disclosure]').forEach(disclosure => {
        const sync = () => {
            disclosure.open = !filterMedia.matches || disclosure.dataset.hasErrors === 'true';
        };
        sync();
        filterMedia.addEventListener('change', sync);
        disclosure.addEventListener('toggle', () => {
            if (!filterMedia.matches && !disclosure.open) disclosure.open = true;
        });
    });
    const navToggle = document.querySelector('[data-nav-toggle]');
    const sidebar = document.querySelector('.sidebar');
    const mobileNav = matchMedia('(max-width:760px)');
    const syncNav = () => {
        const open = document.body.classList.contains('nav-open');
        if (sidebar) sidebar.inert = mobileNav.matches && !open;
        navToggle?.setAttribute('aria-expanded', String(mobileNav.matches ? open : !document.body.classList.contains('sidebar-collapsed')));
    };
    const closeNav = () => { document.body.classList.remove('nav-open'); syncNav(); };
    syncNav();
    navToggle?.addEventListener('click', () => {
        if (mobileNav.matches) {
            const open = document.body.classList.toggle('nav-open'); syncNav();
            if (open) document.querySelector('.sidebar a')?.focus();
        } else { document.body.classList.toggle('sidebar-collapsed'); syncNav(); }
    });
    document.querySelector('[data-nav-close]')?.addEventListener('click', closeNav);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && document.body.classList.contains('nav-open')) { closeNav(); navToggle?.focus(); }
        if (event.key === 'Tab' && document.body.classList.contains('nav-open')) {
            const links = [...document.querySelectorAll('.sidebar a, .sidebar button')];
            const first = links[0], last = links[links.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    matchMedia('(min-width:761px)').addEventListener('change', closeNav);
    const dialog = document.querySelector('#confirm-dialog'); let pendingForm;
    const confirmTitle = document.querySelector('#confirm-title');
    const confirmAccept = document.querySelector('[data-confirm-accept]');
    const defaultConfirmTitle = confirmTitle?.textContent;
    const defaultConfirmLabel = confirmAccept?.textContent;
    document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.confirmed === 'true') return;
        event.preventDefault(); pendingForm = form;
        document.querySelector('#confirm-message').textContent = form.dataset.confirm;
        confirmTitle.textContent = form.dataset.confirmTitle || defaultConfirmTitle;
        confirmAccept.textContent = form.dataset.confirmLabel || defaultConfirmLabel;
        dialog.showModal(); document.querySelector('[data-confirm-cancel]').focus();
    }));
    document.querySelector('[data-confirm-cancel]')?.addEventListener('click', () => dialog.close());
    document.querySelector('[data-confirm-accept]')?.addEventListener('click', () => {
        if (pendingForm) { pendingForm.dataset.confirmed = 'true'; dialog.close(); pendingForm.requestSubmit(); }
    });
    const transactionForm = document.querySelector('[data-transaction-form]');
    const debtForm = document.querySelector('[data-debt-form]');
    const filterCategories = (form, kind) => {
        const category = form.querySelector('[name=category_id]'); if (!category) return;
        for (const option of category.options) {
            const invalid = !!option.dataset.kind && option.dataset.kind !== 'both' && option.dataset.kind !== kind;
            option.hidden = invalid; option.disabled = invalid;
            if (invalid && option.selected) category.value = '';
        }
    };
    if (transactionForm) {
        const kind = transactionForm.querySelector('[name=kind]');
        const sync = () => {
            const transfer = kind.value === 'transfer';
            for (const [selector, active] of [['[data-business-fields]', !transfer], ['[data-transfer-fields]', transfer]]) {
                const group = transactionForm.querySelector(selector); group.hidden = !active;
                group.querySelectorAll('select,input').forEach(field => field.disabled = !active);
            }
            if (!transfer) filterCategories(transactionForm, kind.value);
        };
        kind.addEventListener('change', sync); sync();
    }
    if (debtForm) {
        const direction = debtForm.querySelector('[name=direction]');
        const sync = () => filterCategories(debtForm, direction.value === 'receivable' ? 'income' : 'expense');
        direction.addEventListener('change', sync); sync();
    }
    document.querySelectorAll('.field-error').forEach(error => {
        if (error.textContent.trim()) error.closest('.field')?.querySelector('input,select,textarea')?.setAttribute('aria-invalid', 'true');
    });
    document.querySelector('input[aria-invalid=true],select[aria-invalid=true],textarea[aria-invalid=true]')?.focus();
    document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
        if (form.dataset.confirm && form.dataset.confirmed !== 'true') return;
        const button = form.querySelector('button.primary');
        if (button && !button.name) { button.disabled = true; button.dataset.submitBusy = 'true'; }
    }));
    addEventListener('pageshow', () => document.querySelectorAll('button[data-submit-busy]').forEach(button => { button.disabled = false; delete button.dataset.submitBusy; }));
})();
