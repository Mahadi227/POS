/**
 * WMS — shared UI helpers
 */
window.WmsUI = (() => {
    const i18n = () => window.ADMIN_I18N || {};
    const locale = () => window.ADMIN_CONFIG?.locale || 'fr-FR';
    const currency = () => window.ADMIN_PAGE?.currency || 'FCFA';

    function t(key, ...args) {
        let str = i18n()[key] || key;
        args.forEach((v) => { str = str.replace('%s', v); });
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
        const b = document.getElementById('wmsError');
        if (!b) return;
        b.classList.add('is-visible');
        const t = b.querySelector('.ad-error-text');
        if (t) t.textContent = msg;
    }

    function hideError() {
        document.getElementById('wmsError')?.classList.remove('is-visible');
    }

    function setMigrationHint(ready) {
        const el = document.getElementById('wmsMigrationHint');
        if (el) el.hidden = !!ready;
    }

    function updateLastUpdated() {
        const el = document.getElementById('lastUpdated');
        if (!el) return;
        el.textContent = `${t('last_updated')} · ${new Date().toLocaleTimeString(locale(), { hour: '2-digit', minute: '2-digit' })}`;
    }

    function exportCsv(filename, rows) {
        if (!rows?.length) return;
        const csv = rows.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = filename;
        a.click();
    }

    async function loadWarehouseOptions(selectEl) {
        if (!selectEl) return;
        const res = await AdminAPI.getWmsWarehouses();
        const items = res.data || [];
        const cur = selectEl.value;
        selectEl.innerHTML = `<option value="">${esc(t('wms_all_warehouses'))}</option>` +
            items.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
        if (cur) selectEl.value = cur;
    }

    return { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions };
})();
