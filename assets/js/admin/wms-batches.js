document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsBatRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = WmsUI;
    const canManage = !!window.ADMIN_PAGE?.canManage;

    let allBatches = [];
    let warehouses = [];
    let products = [];
    let detailId = null;

    const STATUS_KEYS = {
        active: 'wms_status_active',
        expired: 'wms_status_expired',
        recalled: 'wms_status_recalled',
        depleted: 'wms_status_depleted',
    };

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'active' ? 'ok' : (status === 'recalled' || status === 'expired' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function formatDate(val) {
        if (!val) return '—';
        return AdminAPI.formatDate(val, { dateStyle: 'short' });
    }

    function expiryCell(row) {
        const exp = row.expiry_date;
        if (!exp) return '—';
        const days = row.days_to_expiry != null ? Number(row.days_to_expiry) : null;
        let cls = '';
        if (days != null) {
            if (days < 0) cls = 'wms-expiry--past';
            else if (days <= 30) cls = 'wms-expiry--soon';
        }
        const hint = days != null ? ` <small class="wms-expiry-days">(${days}d)</small>` : '';
        return `<span class="wms-expiry ${cls}">${esc(formatDate(exp))}${hint}</span>`;
    }

    function setStats(summary) {
        const s = summary || {};
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsBatTotal', String(s.total ?? 0));
        set('wmsBatActive', String(s.active ?? 0));
        set('wmsBatExpiring', String(s.expiring_soon ?? 0));
        set('wmsBatExpired', String(s.expired ?? 0));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function renderTable(items) {
        if (!items.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wms-bat-table"><thead><tr>
            <th>${esc(t('wms_col_batch'))}</th>
            <th>${esc(t('wms_col_product'))}</th>
            <th>${esc(t('wms_nav_warehouses'))}</th>
            <th>${esc(t('wms_col_qty'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('wms_col_mfg'))}</th>
            <th>${esc(t('wms_col_expiry'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td><strong>${esc(r.batch_number)}</strong></td>
            <td>${esc(r.product_name)}<br><code class="wms-sku">${esc(r.sku || '')}</code></td>
            <td>${esc(r.warehouse_name || '—')}</td>
            <td>${Number(r.quantity || 0)}</td>
            <td>${esc(money(r.stock_value))}</td>
            <td>${esc(formatDate(r.manufacturing_date))}</td>
            <td>${expiryCell(r)}</td>
            <td>${statusBadge(r.status)}</td>
            <td class="cr-actions">
                <button type="button" class="cr-btn cr-btn--ghost" data-bat-view="${r.id}">${esc(t('wms_view_details'))}</button>
            </td>
        </tr>`).join('')}</tbody></table></div>`;

        root.querySelectorAll('[data-bat-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.batView)));
        });
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('is-open');
            el.setAttribute('aria-hidden', 'false');
        }
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('is-open');
            el.setAttribute('aria-hidden', 'true');
        }
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses();
        warehouses = res.status === 'success' ? (res.data || []) : [];
        const fill = (sel, placeholder) => {
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = `<option value="">${esc(placeholder || t('wms_select_warehouse'))}</option>` +
                warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
            if (cur) sel.value = cur;
        };
        fill(document.getElementById('wmsBatWarehouse'), t('wms_all_warehouses'));
        fill(document.getElementById('wmsBatFormWarehouse'));
    }

    async function loadProducts() {
        if (products.length) return;
        const res = await AdminAPI.getInventoryProducts();
        products = res.status === 'success' ? (res.data || []) : [];
        const sel = document.getElementById('wmsBatFormProduct');
        if (!sel) return;
        sel.innerHTML = `<option value="">${esc(t('wms_select_product'))}</option>` +
            products.map((p) => `<option value="${p.id}">${esc(p.name)}${p.sku ? ` (${p.sku})` : ''}</option>`).join('');
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.add('is-loading'));
        try {
            await loadWarehouses();
            const wh = document.getElementById('wmsBatWarehouse')?.value;
            const status = document.getElementById('wmsBatStatus')?.value;
            const q = document.getElementById('wmsBatSearch')?.value?.trim();
            const res = await AdminAPI.getWmsBatches(wh, status, q);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allBatches = res.data || [];
            setStats(res.summary);
            renderTable(allBatches);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
            document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
        }
    }

    function updateDetailActions(row) {
        const footer = document.getElementById('wmsBatDetailActions');
        if (!footer) return;
        const show = canManage && row && row.status === 'active';
        footer.hidden = !show;
    }

    async function openDetail(id) {
        detailId = id;
        const body = document.getElementById('wmsBatDetailBody');
        if (!body) return;
        body.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        updateDetailActions(null);
        openModal('wmsBatDetailModal');
        try {
            const res = await AdminAPI.getWmsBatch(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            document.getElementById('wmsBatDetailTitle').textContent = r.batch_number || t('wms_batch_details');
            const sub = document.getElementById('wmsBatDetailSubtitle');
            if (sub) sub.textContent = [r.product_name, r.sku, r.warehouse_name].filter(Boolean).join(' · ');
            const days = r.days_to_expiry != null ? Number(r.days_to_expiry) : null;
            body.innerHTML = `
                <dl class="wms-detail-grid">
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_product'))}</dt><dd>${esc(r.product_name)} <code class="wms-sku">${esc(r.sku || '')}</code></dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_qty'))}</dt><dd>${Number(r.quantity || 0)}</dd></div>
                    <div><dt>${esc(t('wms_unit_cost'))}</dt><dd>${esc(money(r.unit_cost))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.stock_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_barcode'))}</dt><dd>${esc(r.barcode || r.product_barcode || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_serial'))}</dt><dd>${esc(r.serial_number || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_mfg'))}</dt><dd>${esc(formatDate(r.manufacturing_date))}</dd></div>
                    <div><dt>${esc(t('wms_col_expiry'))}</dt><dd>${expiryCell(r)}</dd></div>
                    <div><dt>${esc(t('wms_days_to_expiry'))}</dt><dd>${days != null ? `${days} ${t('wms_days_short') || 'd'}` : '—'}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(r.created_at ? AdminAPI.formatDate(r.created_at, { dateStyle: 'short', timeStyle: 'short' }) : '—')}</dd></div>
                </dl>`;
            updateDetailActions(r);
        } catch (e) {
            body.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function updateStatus(status) {
        if (!detailId) return;
        const confirmKeys = {
            recalled: 'wms_confirm_recall',
            depleted: 'wms_confirm_deplete',
            expired: 'wms_confirm_mark_expired',
        };
        const msg = t(confirmKeys[status] || 'error');
        if (!window.confirm(msg)) return;
        try {
            const res = await AdminAPI.updateWmsBatchStatus(detailId, status);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            closeModal('wmsBatDetailModal');
            await load();
        } catch (e) {
            showError(e.message || t('error'));
        }
    }

    async function openCreate() {
        await loadProducts();
        await loadWarehouses();
        document.getElementById('wmsBatCreateForm')?.reset();
        openModal('wmsBatCreateModal');
    }

    let searchTimer;
    document.getElementById('wmsBatSearch')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 350);
    });
    document.getElementById('wmsBatWarehouse')?.addEventListener('change', load);
    document.getElementById('wmsBatStatus')?.addEventListener('change', load);
    document.getElementById('wmsBatRefresh')?.addEventListener('click', load);
    document.getElementById('wmsBatNewBtn')?.addEventListener('click', openCreate);
    document.getElementById('wmsBatCreateClose')?.addEventListener('click', () => closeModal('wmsBatCreateModal'));
    document.getElementById('wmsBatCreateCancel')?.addEventListener('click', () => closeModal('wmsBatCreateModal'));
    document.getElementById('wmsBatDetailClose')?.addEventListener('click', () => closeModal('wmsBatDetailModal'));
    document.getElementById('wmsBatRecallBtn')?.addEventListener('click', () => updateStatus('recalled'));
    document.getElementById('wmsBatDepleteBtn')?.addEventListener('click', () => updateStatus('depleted'));
    document.getElementById('wmsBatExpiredBtn')?.addEventListener('click', () => updateStatus('expired'));

    document.getElementById('wmsBatCreateForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form).entries());
        data.warehouse_id = Number(data.warehouse_id);
        data.product_id = Number(data.product_id);
        data.quantity = Number(data.quantity || 0);
        data.unit_cost = Number(data.unit_cost || 0);
        try {
            const res = await AdminAPI.createWmsBatch(data);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            closeModal('wmsBatCreateModal');
            await load();
        } catch (err) {
            showError(err.message || t('error'));
        }
    });

    document.addEventListener('wms:refresh', load);
    load();
});
