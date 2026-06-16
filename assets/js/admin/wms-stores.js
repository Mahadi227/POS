document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsStoresRoot');
    if (!root) return;

    const { t, esc, hideError, showError, updateLastUpdated } = WmsUI;
    let allStores = [];

    function setStats(items) {
        const total = items.length;
        const active = items.filter((s) => s.is_active !== false).length;
        const inactive = total - active;
        const set = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(value);
        };
        set('wmsStoresTotal', total);
        set('wmsStoresActive', active);
        set('wmsStoresInactive', inactive);
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function applyFilters() {
        const q = (document.getElementById('wmsStoresSearch')?.value || '').trim().toLowerCase();
        const status = document.getElementById('wmsStoresStatus')?.value || 'all';

        const items = allStores.filter((s) => {
            if (status === 'active' && s.is_active === false) return false;
            if (status === 'inactive' && s.is_active !== false) return false;
            if (!q) return true;
            const hay = [s.name, s.code, s.location, s.phone, s.email, s.currency].join(' ').toLowerCase();
            return hay.includes(q);
        });
        renderTable(items);
    }

    function renderTable(items) {
        if (!items.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('no_stores'))}</p>`;
            return;
        }

        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table"><thead><tr>
            <th>${esc(t('col_store'))}</th>
            <th>${esc(t('staff_count'))}</th>
            <th>${esc(t('product_count'))}</th>
            <th>${esc(t('col_status'))}</th>
        </tr></thead><tbody>${items.map((s) => `
            <tr>
                <td><strong>${esc(s.name || '—')}</strong><div class="cr-muted">${esc(s.code || '')} · ${esc(s.currency || 'FCFA')}</div></td>
                <td>${Number(s.staff_count || 0)}</td>
                <td>${Number(s.product_count || 0)}</td>
                <td><span class="cr-badge cr-badge--${s.is_active !== false ? 'ok' : 'off'}">${esc(s.is_active !== false ? t('store_active') : t('store_inactive'))}</span></td>
            </tr>
        `).join('')}</tbody></table></div>`;
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.listStores();
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            allStores = res.data || [];
            setStats(allStores);
            applyFilters();
            updateLastUpdated();
        } catch (e) {
            const message = e?.message || t('load_error');
            showError(message);
            root.innerHTML = `<p class="cr-empty">${esc(message)}</p>`;
        }
    }

    document.getElementById('wmsStoresRefresh')?.addEventListener('click', load);
    document.getElementById('wmsStoresSearch')?.addEventListener('input', applyFilters);
    document.getElementById('wmsStoresStatus')?.addEventListener('change', applyFilters);
    document.addEventListener('wms:refresh', load);
    load();
});
