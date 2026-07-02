/**
 * Warehouse inventory page v2 — per-warehouse SKU stock lines
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whInvTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;

    const STOCK_KEYS = {
        ok: 'wms_stock_ok',
        low: 'wms_stock_low',
        out: 'wms_stock_out',
        alert: 'wms_stock_alert',
    };

    const state = {
        items: [],
        summary: null,
        searchTimer: null,
    };

    const els = {
        loading: document.getElementById('whInvLoading'),
        empty: document.getElementById('whInvEmpty'),
        placeholder: document.getElementById('whInvPlaceholder'),
        warehouse: document.getElementById('whInvWarehouse'),
        search: document.getElementById('whInvSearch'),
        filter: document.getElementById('whInvFilter'),
        refresh: document.getElementById('whInvRefreshBtn'),
        exportBtn: document.getElementById('whInvExportBtn'),
        heroMeta: document.getElementById('whInvHeroMeta'),
        statSkus: document.getElementById('whInvStatSkus'),
        statUnits: document.getElementById('whInvStatUnits'),
        statValue: document.getElementById('whInvStatValue'),
        statLow: document.getElementById('whInvStatLow'),
        statOut: document.getElementById('whInvStatOut'),
        modal: document.getElementById('whInvDetailModal'),
        modalClose: document.getElementById('whInvDetailClose'),
        modalTitle: document.getElementById('whInvDetailTitle'),
        modalSubtitle: document.getElementById('whInvDetailSubtitle'),
        modalBody: document.getElementById('whInvDetailBody'),
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
        return `<span class="wms-qty ${cls}">${n.toLocaleString()}</span>`;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-inv-stat__value').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statSkus) els.statSkus.textContent = String(s.sku_count ?? 0);
        if (els.statUnits) els.statUnits.textContent = Number(s.total_units ?? 0).toLocaleString();
        if (els.statValue) els.statValue.textContent = money(s.total_value);
        if (els.statLow) els.statLow.textContent = String(s.low_stock ?? 0);
        if (els.statOut) els.statOut.textContent = String(s.out_of_stock ?? 0);
        setStatsLoading(false);
    }

    function warehouseLabel() {
        const opt = els.warehouse?.selectedOptions?.[0];
        return opt?.value ? opt.text : t('wms_select_warehouse');
    }

    function renderTable(items) {
        if (els.placeholder) els.placeholder.hidden = true;
        if (!items.length) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-inv-list-table"><thead><tr>
            <th class="wh-inv-col--product">${esc(t('wms_col_product'))}</th>
            <th class="wh-inv-col--sku">${esc(t('wms_col_sku'))}</th>
            <th class="wh-inv-col--qty">${esc(t('wms_col_qty'))}</th>
            <th class="wh-inv-col--qty">${esc(t('wms_col_available'))}</th>
            <th class="wh-inv-col--qty">${esc(t('wms_col_reserved'))}</th>
            <th class="wh-inv-col--value">${esc(t('wms_col_value'))}</th>
            <th class="wh-inv-col--loc">${esc(t('wms_col_location'))}</th>
            <th class="wh-inv-col--batch">${esc(t('wms_col_batch'))}</th>
            <th class="wh-inv-col--status">${esc(t('col_status'))}</th>
            <th class="wh-inv-col--actions" aria-label="${esc(t('wms_view_details'))}"></th>
        </tr></thead><tbody>${items.map((r) => `<tr class="wh-inv-list-row">
            <td class="wh-inv-col--product">
                <strong>${esc(r.product_name)}</strong>
                ${r.barcode ? `<span class="wh-inv-barcode">${esc(r.barcode)}</span>` : ''}
            </td>
            <td class="wh-inv-col--sku"><code class="wms-sku">${esc(r.sku || '—')}</code></td>
            <td class="wh-inv-col--qty">${qtyCell(r.quantity, r.reorder_level)}</td>
            <td class="wh-inv-col--qty">${Number(r.available_qty ?? (r.quantity - r.reserved_qty)).toLocaleString()}</td>
            <td class="wh-inv-col--qty">${Number(r.reserved_qty || 0).toLocaleString()}</td>
            <td class="wh-inv-col--value">${esc(money(r.stock_value))}</td>
            <td class="wh-inv-col--loc">${esc(r.location_code || '—')}</td>
            <td class="wh-inv-col--batch">${esc(r.batch_number || '—')}</td>
            <td class="wh-inv-col--status">${stockBadge(r.stock_status)}</td>
            <td class="wh-inv-col--actions wh-inv-row-actions">
                <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-inv-view="${r.product_id}">${esc(t('wms_view_details'))}</button>
            </td>
        </tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-inv-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.invView)));
        });
    }

    function showPlaceholder() {
        state.items = [];
        state.summary = null;
        renderStats(null);
        tableWrap.innerHTML = '';
        if (els.placeholder) {
            els.placeholder.hidden = false;
            tableWrap.appendChild(els.placeholder);
        }
        if (els.empty) els.empty.hidden = true;
        if (els.heroMeta) els.heroMeta.textContent = t('wms_select_warehouse');
    }

    async function load() {
        hideError();
        const wh = els.warehouse?.value?.trim();
        if (!wh) {
            showPlaceholder();
            return;
        }
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const filter = els.filter?.value || 'all';
            const q = els.search?.value?.trim();
            const res = await AdminAPI.getWmsInventory(wh, q, filter === 'all' ? undefined : filter);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.summary = res.summary || null;
            renderStats(state.summary);
            renderTable(state.items);
            if (els.heroMeta) {
                els.heroMeta.textContent = `${warehouseLabel()} · ${state.items.length} ${t('records')}`;
            }
            updateLastUpdated();
        } catch (err) {
            showError(err.message || t('load_error'));
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
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
                stockLabel(r.stock_status),
            ]),
        ];
    }

    function closeModal() {
        if (!els.modal) return;
        els.modal.classList.remove('is-open');
        els.modal.setAttribute('aria-hidden', 'true');
    }

    function openModal() {
        if (!els.modal) return;
        els.modal.classList.add('is-open');
        els.modal.setAttribute('aria-hidden', 'false');
    }

    async function openDetail(productId) {
        const wh = els.warehouse?.value?.trim();
        if (!wh || !productId) return;
        if (els.modalBody) els.modalBody.innerHTML = `<p class="cr-empty">${esc(t('loading'))}</p>`;
        openModal();
        try {
            const res = await AdminAPI.getWmsInventoryItem(wh, productId);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.modalTitle) els.modalTitle.textContent = r.product_name || t('wms_inventory_details');
            if (els.modalSubtitle) {
                els.modalSubtitle.textContent = [r.sku, r.warehouse_name].filter(Boolean).join(' · ');
            }
            const movements = r.movements || [];
            const links = [];
            if (r.sku) {
                links.push(`<a class="wh-inv-detail-link" href="products.php?q=${encodeURIComponent(r.sku)}">${esc(t('wh_inv_link_products'))}</a>`);
                links.push(`<a class="wh-inv-detail-link" href="stock_ledger.php?q=${encodeURIComponent(r.sku)}">${esc(t('wh_inv_link_ledger'))}</a>`);
            }
            if (r.barcode) {
                links.push(`<a class="wh-inv-detail-link" href="barcode_scanner.php?q=${encodeURIComponent(r.barcode)}">${esc(t('wh_inv_link_scanner'))}</a>`);
            }

            const movTable = movements.length
                ? `<div class="cr-table-wrap"><table class="modern-table"><thead><tr>
                    <th>${esc(t('col_date'))}</th><th>Type</th><th>${esc(t('wms_col_qty'))}</th><th>Balance</th>
                </tr></thead><tbody>${movements.map((m) => `<tr>
                    <td>${esc(AdminAPI.formatDate(m.created_at, { dateStyle: 'short', timeStyle: 'short' }))}</td>
                    <td>${esc(m.movement_type)}</td>
                    <td>${m.quantity}</td>
                    <td>${m.balance_after}</td>
                </tr>`).join('')}</tbody></table></div>`
                : `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;

            if (els.modalBody) {
                els.modalBody.innerHTML = `
                    <div class="wh-inv-detail-links">${links.join('')}</div>
                    <dl class="wms-detail-grid wms-inv-detail-grid">
                        <div><dt>${esc(t('col_status'))}</dt><dd>${stockBadge(r.stock_status)}</dd></div>
                        <div><dt>${esc(t('wms_col_qty'))}</dt><dd>${qtyCell(r.quantity, r.reorder_level)} / ${esc(t('wms_col_reorder'))}: ${r.reorder_level}</dd></div>
                        <div><dt>${esc(t('wms_col_available'))}</dt><dd>${Number(r.available_qty ?? 0).toLocaleString()}</dd></div>
                        <div><dt>${esc(t('wms_col_reserved'))}</dt><dd>${Number(r.reserved_qty || 0).toLocaleString()}</dd></div>
                        <div><dt>${esc(t('wms_col_unit_cost'))}</dt><dd>${esc(money(r.unit_cost))}</dd></div>
                        <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.stock_value))}</dd></div>
                        <div><dt>${esc(t('wms_col_location'))}</dt><dd>${esc(r.location_code || '—')}</dd></div>
                        <div><dt>${esc(t('wms_col_batch'))}</dt><dd>${esc(r.batch_number || '—')}</dd></div>
                        <div><dt>${esc(t('wms_col_damaged'))}</dt><dd>${Number(r.damaged_qty || 0).toLocaleString()}</dd></div>
                        <div><dt>${esc(t('wms_col_expired'))}</dt><dd>${Number(r.expired_qty || 0).toLocaleString()}</dd></div>
                        <div><dt>${esc(t('wms_col_last_movement'))}</dt><dd>${esc(r.last_movement_at ? AdminAPI.formatDate(r.last_movement_at, { dateStyle: 'short', timeStyle: 'short' }) : '—')}</dd></div>
                        <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    </dl>
                    <h4 class="wh-inv-detail-heading"><span class="material-icons-round">swap_horiz</span>${esc(t('wms_recent_movements'))}</h4>
                    ${movTable}`;
            }
        } catch (err) {
            if (els.modalBody) els.modalBody.innerHTML = `<p class="cr-empty">${esc(err.message || t('load_error'))}</p>`;
        }
    }

    els.warehouse?.addEventListener('change', load);
    els.filter?.addEventListener('change', load);
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(load, 320);
    });
    els.refresh?.addEventListener('click', load);
    els.exportBtn?.addEventListener('click', () => {
        if (!state.items.length) return;
        exportCsv(`warehouse-inventory-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(state.items));
    });
    els.modalClose?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
    document.addEventListener('wh:refresh', load);

    const urlQ = new URLSearchParams(window.location.search).get('q');
    if (urlQ && els.search) els.search.value = urlQ;

    loadWarehouseOptions(els.warehouse).then(() => {
        if (els.warehouse?.options[0]) {
            els.warehouse.options[0].textContent = t('wms_select_warehouse');
        }
        const urlWh = new URLSearchParams(window.location.search).get('warehouse_id');
        const defaultWh = urlWh || String(window.WH_PAGE?.warehouseId || '');
        if (defaultWh && els.warehouse) {
            els.warehouse.value = defaultWh;
        } else if (els.warehouse && els.warehouse.options.length === 2) {
            els.warehouse.selectedIndex = 1;
        }
        load();
    });
});
