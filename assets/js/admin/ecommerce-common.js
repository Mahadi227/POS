/**
 * E-commerce admin portal — shared UI helpers
 */
const EcommerceUI = (() => {
    const i18n = () => window.ADMIN_I18N || {};

    function t(key, ...args) {
        let s = i18n()[key] || key;
        args.forEach((a) => { s = s.replace('%s', a); });
        return s;
    }

    function esc(v) {
        const d = document.createElement('div');
        d.textContent = v == null ? '' : String(v);
        return d.innerHTML;
    }

    function money(amount) {
        const cfg = window.ECOM_PAGE || {};
        const cur = cfg.currency || 'EUR';
        const locale = window.ADMIN_CONFIG?.locale || 'en-US';
        try {
            return new Intl.NumberFormat(locale, { style: 'currency', currency: cur }).format(Number(amount) || 0);
        } catch {
            return `${cur} ${Number(amount || 0).toFixed(2)}`;
        }
    }

    function formatDate(value) {
        if (!value) return '—';
        return AdminAPI.formatDate(value, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function toast(msg) {
        let el = document.getElementById('ecomToast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'ecomToast';
            el.className = 'ecom-toast';
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.classList.add('is-visible');
        clearTimeout(el._timer);
        el._timer = setTimeout(() => el.classList.remove('is-visible'), 2600);
    }

    function updateLastUpdated() {
        const el = document.getElementById('lastUpdated');
        if (el) el.textContent = t('last_updated') + ': ' + new Date().toLocaleTimeString(window.ADMIN_CONFIG?.locale || 'en-US');
    }

    function bindModalClose(dialog) {
        if (!dialog) return;
        dialog.querySelectorAll('[data-close-modal]').forEach((btn) => {
            btn.addEventListener('click', () => dialog.close());
        });
    }

    return { t, esc, money, formatDate, toast, updateLastUpdated, bindModalClose };
})();
