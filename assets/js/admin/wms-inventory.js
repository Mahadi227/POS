document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsInvRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WmsUI;

    let allItems = [];
    let warehouses = [];
    let lastSummary = null;

    const STOCK_KEYS = {
        ok: 'wms_stock_ok',
        low: 'wms_stock_low',
        out: 'wms_stock_out',
        alert: 'wms_stock_alert',
    };

    function stockLabel(status) {
        return t(STOCK_KEYS[status] || status) || status || '—';
    }

    function stockBadge(status) {
        const cls = status === 'ok' ? 'ok' : (status === 'out' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(stockLabel(status))}</span>`;
    }

    function qtyCell(qty, reorder) {
        const n = Number(qty || 0);
        const low = reorder != null && n > 0 && n <= Number(reorder);
        const out = n === 0;
        const cls = out ? 'wms-qty--out' : (low ? 'wms-qty--low' : '');
        return `<span class="wms-qty ${cls}">${n}</span>`;
    }

    function setStats(summary) {
        const s = summary || { sku_count: 0, total_units: 0, total_value: 0, low_stock: 0, out_of_stock: 0 };
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsInvSkus', String(s.sku_count ?? 0));
        set('wmsInvUnits', Number(s.total_units ?? 0).toLocaleString());
        set('wmsInvValue', money(s.total_value));
        set('wmsInvLow', String((s.low_stock ?? 0) + (s.out_of_stock ?? 0)));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function applyClientFilter(items) {
        const q = (document.getElementById('wmsInvSearch')?.value || '').trim().toLowerCase();
        if (!q) return items;
        return items.filter((r) => {
            const hay = [r.product_name, r.sku, r.barcode, r.location_code, r.batch_number].join(' ').toLowerCase();
            return hay.includes(q);
        });
    }

    function renderTable(items) {
        const list = applyClientFilter(items);
        if (!list.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wms-inv-table"><thead><tr>
            <th>${esc(t('wms_col_product'))}</th>
            <th>SKU</th>
            <th>${esc(t('wms_col_qty'))}</th>
            <th>${esc(t('wms_col_available'))}</th>
            <th>${esc(t('wms_col_reserved'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('wms_col_location'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${list.map((r) => `<tr>
            <td><strong>${esc(r.product_name)}</strong></td>
            <td><code class="wms-sku">${esc(r.sku || '—')}</code></td>
            <td>${qtyCell(r.quantity, r.reorder_level)}</td>
            <td>${Number(r.available_qty ?? (r.quantity - r.reserved_qty))}</td>
            <td>${Number(r.reserved_qty || 0)}</td>
            <td>${esc(money(r.stock_value))}</td>
            <td>${esc(r.location_code || '—')}</td>
            <td>${stockBadge(r.stock_status)}</td>
            <td class="cr-actions">
                <button type="button" class="cr-btn cr-btn--ghost" data-inv-view="${r.product_id}">${esc(t('wms_view_details'))}</button>
            </td>
        </tr>`).join('')}</tbody></table></div>`;

        root.querySelectorAll('[data-inv-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.invView)));
        });
    }

    function buildExportRows(items) {
        return [
            [t('wms_col_product'), 'SKU', t('wms_col_qty'), t('wms_col_available'), t('wms_col_reserved'),
                t('wms_col_unit_cost'), t('wms_col_value'), t('wms_col_reorder'), t('wms_col_location'),
                t('wms_col_batch'), t('col_status')],
            ...items.map((r) => [
                r.product_name,
                r.sku,
                r.quantity,
                r.available_qty ?? (r.quantity - r.reserved_qty),
                r.reserved_qty,
                r.unit_cost,
                r.stock_value,
                r.reorder_level,
                r.location_code || '',
                r.batch_number || '',
                r.stock_status,
            ]),
        ];
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses();
        warehouses = res.status === 'success' ? (res.data || []) : [];
        const sel = document.getElementById('wmsInvWarehouse');
        if (!sel) return;
        const cur = sel.value;
        sel.innerHTML = `<option value="">${esc(t('wms_select_warehouse'))}</option>` +
            warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
        if (cur) sel.value = cur;
        else if (warehouses.length === 1) sel.value = String(warehouses[0].id);
    }

    async function load() {
        hideError();
        const wh = document.getElementById('wmsInvWarehouse')?.value;
        if (!wh) {
            allItems = [];
            lastSummary = null;
            setStats(null);
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_select_warehouse'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.add('is-loading'));
        try {
            const filter = document.getElementById('wmsInvFilter')?.value || 'all';
            const q = document.getElementById('wmsInvSearch')?.value?.trim();
            const res = await AdminAPI.getWmsInventory(wh, q, filter);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allItems = res.data || [];
            lastSummary = res.summary || null;
            setStats(lastSummary);
            renderTable(allItems);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
            document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
        }
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

    async function openDetail(productId) {
        const wh = document.getElementById('wmsInvWarehouse')?.value;
        if (!wh) return;
        const body = document.getElementById('wmsInvDetailBody');
        if (!body) return;
        body.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        openModal('wmsInvDetailModal');
        try {
            const res = await AdminAPI.getWmsInventoryItem(wh, productId);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            document.getElementById('wmsInvDetailTitle').textContent = r.product_name || t('wms_inventory_details');
            const sub = document.getElementById('wmsInvDetailSubtitle');
            if (sub) sub.textContent = [r.sku, r.warehouse_name].filter(Boolean).join(' · ');
            const movements = r.movements || [];
            body.innerHTML = `
                <dl class="wms-detail-grid wms-inv-detail-grid">
                    <div><dt>${esc(t('col_status'))}</dt><dd>${stockBadge(r.stock_status)}</dd></div>
                    <div><dt>${esc(t('wms_col_qty'))}</dt><dd>${qtyCell(r.quantity, r.reorder_level)} / ${esc(t('wms_col_reorder'))}: ${r.reorder_level}</dd></div>
                    <div><dt>${esc(t('wms_col_available'))}</dt><dd>${Number(r.available_qty ?? 0)}</dd></div>
                    <div><dt>${esc(t('wms_col_reserved'))}</dt><dd>${Number(r.reserved_qty || 0)}</dd></div>
                    <div><dt>${esc(t('wms_col_unit_cost'))}</dt><dd>${esc(money(r.unit_cost))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.stock_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_location'))}</dt><dd>${esc(r.location_code || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_batch'))}</dt><dd>${esc(r.batch_number || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_damaged'))}</dt><dd>${Number(r.damaged_qty || 0)}</dd></div>
                    <div><dt>${esc(t('wms_col_expired'))}</dt><dd>${Number(r.expired_qty || 0)}</dd></div>
                    <div><dt>${esc(t('wms_col_last_movement'))}</dt><dd>${esc(r.last_movement_at ? AdminAPI.formatDate(r.last_movement_at, { dateStyle: 'short', timeStyle: 'short' }) : '—')}</dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                </dl>
                <h4 class="wms-inv-movements-title"><span class="material-icons-round">swap_horiz</span>${esc(t('wms_recent_movements'))}</h4>
                ${movements.length ? `<div class="cr-table-wrap"><table class="modern-table"><thead><tr>
                    <th>${esc(t('col_date'))}</th><th>Type</th><th>${esc(t('wms_col_qty'))}</th><th>Balance</th>
                </tr></thead><tbody>${movements.map((m) => `<tr>
                    <td>${esc(AdminAPI.formatDate(m.created_at, { dateStyle: 'short', timeStyle: 'short' }))}</td>
                    <td>${esc(m.movement_type)}</td>
                    <td>${m.quantity}</td>
                    <td>${m.balance_after}</td>
                </tr>`).join('')}</tbody></table></div>` : `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`}`;
        } catch (e) {
            body.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    document.getElementById('wmsInvRefresh')?.addEventListener('click', load);
    document.getElementById('wmsInvWarehouse')?.addEventListener('change', load);
    document.getElementById('wmsInvFilter')?.addEventListener('change', load);
    document.getElementById('wmsInvSearch')?.addEventListener('input', () => renderTable(allItems));
    document.getElementById('wmsInvExport')?.addEventListener('click', () => {
        if (!allItems.length) return;
        exportCsv('warehouse-inventory.csv', buildExportRows(applyClientFilter(allItems)));
    });
    document.getElementById('wmsInvDetailClose')?.addEventListener('click', () => closeModal('wmsInvDetailModal'));
    document.getElementById('wmsInvDetailModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'wmsInvDetailModal') closeModal('wmsInvDetailModal');
    });

    document.addEventListener('wms:refresh', load);
    loadWarehouses().then(load);
});
