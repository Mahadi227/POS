/**
 * Cash registers — shared helpers
 */
window.CashRegistersUI = (() => {
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
        return `${Number(n || 0).toLocaleString(locale())} ${currency()}`;
    }

    function showError(msg) {
        const banner = document.getElementById('crError');
        if (!banner) return;
        banner.classList.add('is-visible');
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        document.getElementById('crError')?.classList.remove('is-visible');
    }

    function setMigrationHint(ready) {
        const el = document.getElementById('crMigrationHint');
        if (el) el.hidden = !!ready;
    }

    function updateLastUpdated() {
        const el = document.getElementById('lastUpdated');
        if (!el) return;
        const time = new Date().toLocaleTimeString(locale(), { hour: '2-digit', minute: '2-digit' });
        el.textContent = `${t('last_updated')} · ${time}`;
    }

    function exportCsv(filename, rows) {
        if (!rows?.length) return;
        const csv = rows.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        a.click();
    }

    return { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv };
})();

document.addEventListener('DOMContentLoaded', () => {
    if (window.CR_MODULE_READY) {
        document.getElementById('crMigrationHint')?.setAttribute('hidden', '');
    }
});
