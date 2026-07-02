/**
 * Accounting workspace — shared helpers
 */
window.AccountingUI = (() => {
    const i18n = () => window.ADMIN_I18N || {};
    const locale = () => window.ADMIN_CONFIG?.locale || 'fr-FR';
    const currency = () => window.ADMIN_PAGE?.currency || 'FCFA';

    function t(key, ...args) {
        let str = i18n()[key] || key;
        args.forEach((val) => { str = str.replace('%s', val); });
        return str;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function money(n) {
        const amount = Number(n || 0).toLocaleString(locale(), {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
        return `${amount} ${currency()}`;
    }

    function currencyCode() {
        return currency();
    }

    function showError(msg) {
        const banner = document.getElementById('accError');
        if (!banner) return;
        banner.classList.add('is-visible');
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        document.getElementById('accError')?.classList.remove('is-visible');
    }

    function setMigrationHint(ready) {
        const el = document.getElementById('accMigrationHint');
        if (!el) return;
        let isReady;
        if (ready === false || ready === 0 || ready === '0') {
            isReady = false;
        } else if (ready === true || ready === 1 || ready === '1') {
            isReady = true;
        } else {
            isReady = window.ACC_MODULE_READY === true;
        }
        el.hidden = isReady;
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (window.ACC_MODULE_READY) {
            setMigrationHint(true);
        }
    });

    function updateLastUpdated() {
        const el = document.getElementById('lastUpdated');
        if (el) {
            el.textContent = `${t('last_updated')}: ${new Date().toLocaleTimeString(locale())}`;
        }
    }

    function exportCsv(filename, rows) {
        const csv = rows.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
    }

    return { t, esc, money, currencyCode, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv };
})();
