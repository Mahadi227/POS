document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsWarehousesRoot');
    if (!root) return;
    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = WmsUI;

    let allItems = [];
    let lastSummary = null;

    const TYPE_KEYS = {
        central: 'wms_wh_type_central',
        regional: 'wms_wh_type_regional',
        store: 'wms_wh_type_store',
        distribution: 'wms_wh_type_distribution',
        cold_storage: 'wms_wh_type_cold_storage',
        temporary: 'wms_wh_type_temporary',
    };

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function statusBadge(status) {
        const active = status === 'active';
        return `<span class="cr-badge cr-badge--${active ? 'ok' : 'off'}">${esc(active ? t('wms_status_active') : t('wms_status_inactive'))}</span>`;
    }

    function setKpis(summary = {}) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsWhTotal', summary.total ?? '—');
        set('wmsWhActive', summary.active ?? '—');
        set('wmsWhUnits', Number(summary.total_units || 0).toLocaleString());
        set('wmsWhValue', money(summary.total_value));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function filters() {
        return {
            q: (document.getElementById('wmsWhSearch')?.value || '').trim(),
            status: document.getElementById('wmsWhStatus')?.value || 'all',
        };
    }

    function applyFilters() {
        const { q, status } = filters();
        const qLower = q.toLowerCase();
        const items = allItems.filter((w) => {
            if (status !== 'all' && w.status !== status) return false;
            if (!qLower) return true;
            const hay = [w.warehouse_code, w.name, w.city, w.store_name, w.manager_name, w.warehouse_type].join(' ').toLowerCase();
            return hay.includes(qLower);
        });
        renderTable(items);
    }

    function renderTable(items) {
        if (!items.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_warehouses'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table"><thead><tr>
            <th>${esc(t('wms_wh_code'))}</th><th>${esc(t('wms_wh_name'))}</th><th>${esc(t('wms_wh_type'))}</th>
            <th>${esc(t('wms_wh_manager'))}</th><th>${esc(t('wms_col_store'))}</th><th>${esc(t('wms_col_units'))}</th>
            <th>${esc(t('wms_stat_inv_value'))}</th><th>${esc(t('col_status'))}</th><th></th>
        </tr></thead><tbody>${items.map((w) => `
            <tr>
                <td><strong>${esc(w.warehouse_code)}</strong></td>
                <td>${esc(w.name)}</td>
                <td>${esc(typeLabel(w.warehouse_type))}</td>
                <td>${esc(w.manager_name || '—')}</td>
                <td>${esc(w.store_name || '—')}</td>
                <td>${Number(w.total_units || 0).toLocaleString()}</td>
                <td>${esc(money(w.stock_value))}</td>
                <td>${statusBadge(w.status)}</td>
                <td><a href="edit_warehouse.php?id=${w.id}" class="cr-btn cr-btn--ghost">${esc(t('view_all'))}</a></td>
            </tr>`).join('')}</tbody></table></div>`;
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.add('is-loading'));
        try {
            const status = document.getElementById('wmsWhStatus')?.value || 'all';
            const res = await AdminAPI.getWmsWarehouses({ status });
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allItems = res.data || [];
            lastSummary = res.summary || null;
            setKpis(lastSummary || {});
            applyFilters();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    document.getElementById('wmsWhRefresh')?.addEventListener('click', load);
    document.getElementById('wmsWhSearch')?.addEventListener('input', applyFilters);
    document.getElementById('wmsWhStatus')?.addEventListener('change', load);
    document.addEventListener('wms:refresh', load);
    document.addEventListener('store-switched', load);
    load();
});
