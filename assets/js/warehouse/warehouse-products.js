/**
 * Warehouse product catalog v1 — aggregated stock by product across warehouses
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whProdTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;

    const STOCK_KEYS = {
        ok: 'wms_stock_ok',
        low: 'wms_stock_low',
        out: 'wms_stock_out',
        alert: 'wms_stock_alert',
    };

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        searchTimer: null,
    };

    const els = {
        loading: document.getElementById('whProdLoading'),
        empty: document.getElementById('whProdEmpty'),
        warehouse: document.getElementById('whProdWarehouse'),
        search: document.getElementById('whProdSearch'),
        filter: document.getElementById('whProdFilter'),
        category: document.getElementById('whProdCategory'),
        refresh: document.getElementById('whProdRefreshBtn'),
        exportBtn: document.getElementById('whProdExportBtn'),
        heroMeta: document.getElementById('whProdHeroMeta'),
        statProducts: document.getElementById('whProdStatProducts'),
        statInStock: document.getElementById('whProdStatInStock'),
        statLow: document.getElementById('whProdStatLow'),
        statOut: document.getElementById('whProdStatOut'),
        statValue: document.getElementById('whProdStatValue'),
        pagination: document.getElementById('whProdPagination'),
        prev: document.getElementById('whProdPrev'),
        next: document.getElementById('whProdNext'),
        pageMeta: document.getElementById('whProdPageMeta'),
        modal: document.getElementById('whProdDetailModal'),
        modalClose: document.getElementById('whProdDetailClose'),
        modalTitle: document.getElementById('whProdDetailTitle'),
        modalSubtitle: document.getElementById('whProdDetailSubtitle'),
        modalBody: document.getElementById('whProdDetailBody'),
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

    function buildParams(forExport = false) {
        const params = {
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        const q = els.search?.value?.trim();
        if (q) params.q = q;
        const filter = els.filter?.value?.trim();
        if (filter && filter !== 'all') params.filter = filter;
        const cat = els.category?.value?.trim();
        if (cat) params.category_id = cat;
        return params;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-prod-stat__value').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statProducts) els.statProducts.textContent = String(s.product_count ?? 0);
        if (els.statInStock) els.statInStock.textContent = String(s.in_stock ?? 0);
        if (els.statLow) els.statLow.textContent = String(s.low_stock ?? 0);
        if (els.statOut) els.statOut.textContent = String(s.out_of_stock ?? 0);
        if (els.statValue) els.statValue.textContent = money(s.total_value);
        setStatsLoading(false);
    }

    function renderCategories(categories) {
        if (!els.category) return;
        const cur = els.category.value;
        const opts = `<option value="">${esc(t('wh_prod_filter_category'))}</option>`
            + (categories || []).map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
        els.category.innerHTML = opts;
        if (cur) els.category.value = cur;
    }

    function renderTable(items) {
        if (!items.length) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        const available = (r) => Number(r.total_qty || 0) - Number(r.reserved_qty || 0);
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-prod-list-table"><thead><tr>
            <th class="wh-prod-col--product">${esc(t('wh_prod_col_product'))}</th>
            <th class="wh-prod-col--sku">${esc(t('wms_col_sku'))}</th>
            <th class="wh-prod-col--cat">${esc(t('wh_prod_col_category'))}</th>
            <th class="wh-prod-col--qty">${esc(t('wh_prod_col_qty'))}</th>
            <th class="wh-prod-col--qty">${esc(t('wh_prod_col_available'))}</th>
            <th class="wh-prod-col--qty">${esc(t('wh_prod_col_reserved'))}</th>
            <th class="wh-prod-col--value">${esc(t('wh_prod_col_value'))}</th>
            <th class="wh-prod-col--wh">${esc(t('wh_prod_col_warehouses'))}</th>
            <th class="wh-prod-col--price">${esc(t('wh_prod_col_price'))}</th>
            <th class="wh-prod-col--status">${esc(t('wh_prod_col_status'))}</th>
            <th class="wh-prod-col--actions" aria-label="${esc(t('wh_prod_view_details'))}"></th>
        </tr></thead><tbody>${items.map((r) => `<tr class="wh-prod-list-row">
            <td class="wh-prod-col--product">
                <strong>${esc(r.name)}</strong>
                ${r.barcode ? `<span class="wh-prod-barcode">${esc(r.barcode)}</span>` : ''}
            </td>
            <td class="wh-prod-col--sku"><code class="wms-sku">${esc(r.sku || '—')}</code></td>
            <td class="wh-prod-col--cat">${esc(r.category_name || '—')}</td>
            <td class="wh-prod-col--qty">${qtyCell(r.total_qty, r.reorder_level)}</td>
            <td class="wh-prod-col--qty">${available(r).toLocaleString()}</td>
            <td class="wh-prod-col--qty">${Number(r.reserved_qty || 0).toLocaleString()}</td>
            <td class="wh-prod-col--value">${esc(money(r.stock_value))}</td>
            <td class="wh-prod-col--wh">${Number(r.warehouse_count || 0)}</td>
            <td class="wh-prod-col--price">${esc(money(r.price))}</td>
            <td class="wh-prod-col--status">${stockBadge(r.stock_status)}</td>
            <td class="wh-prod-col--actions wh-prod-row-actions">
                <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-prod-view="${r.id}">${esc(t('wh_prod_view_details'))}</button>
            </td>
        </tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-prod-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.prodView)));
        });
    }

    function renderPagination() {
        const totalPages = Math.max(1, Math.ceil(state.total / state.limit));
        const show = state.total > state.limit;
        if (els.pagination) els.pagination.hidden = !show;
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= totalPages;
        if (els.pageMeta) {
            const from = state.total ? (state.page - 1) * state.limit + 1 : 0;
            const to = Math.min(state.page * state.limit, state.total);
            els.pageMeta.textContent = `${from}–${to} / ${state.total} ${t('records')}`;
        }
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getWmsProducts(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            renderStats(state.summary);
            renderCategories(res.categories || []);
            renderTable(state.items);
            renderPagination();
            if (els.heroMeta) {
                const whName = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
                els.heroMeta.textContent = `${whName} · ${state.total} ${t('records')}`;
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
        if (!productId) return;
        if (els.modalBody) els.modalBody.innerHTML = `<p class="cr-empty">${esc(t('loading'))}</p>`;
        openModal();
        try {
            const params = {};
            const wh = els.warehouse?.value?.trim();
            if (wh) params.warehouse_id = wh;
            const res = await AdminAPI.getWmsProduct(productId, params);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const p = res.data;
            if (els.modalTitle) els.modalTitle.textContent = p.name || t('wh_prod_details');
            if (els.modalSubtitle) {
                els.modalSubtitle.textContent = [p.sku, p.category_name].filter(Boolean).join(' · ');
            }
            const totals = p.totals || {};
            const whRows = p.warehouses || [];
            const links = [];
            if (p.sku) links.push(`<a class="wh-prod-detail-link" href="stock_ledger.php?q=${encodeURIComponent(p.sku)}">${esc(t('wh_prod_link_ledger'))}</a>`);
            if (p.barcode) links.push(`<a class="wh-prod-detail-link" href="barcode_scanner.php?q=${encodeURIComponent(p.barcode)}">${esc(t('wh_prod_link_scanner'))}</a>`);
            links.push(`<a class="wh-prod-detail-link" href="warehouse_inventory.php">${esc(t('wh_prod_link_inv'))}</a>`);

            const whTable = whRows.length
                ? `<div class="cr-table-wrap"><table class="modern-table"><thead><tr>
                    <th>${esc(t('wms_nav_warehouses'))}</th>
                    <th>${esc(t('wms_col_qty'))}</th>
                    <th>${esc(t('wh_prod_col_available'))}</th>
                    <th>${esc(t('wh_prod_col_reserved'))}</th>
                    <th>${esc(t('wms_col_value'))}</th>
                    <th>${esc(t('wms_col_location'))}</th>
                    <th>${esc(t('col_status'))}</th>
                </tr></thead><tbody>${whRows.map((w) => `<tr>
                    <td><strong>${esc(w.warehouse_name)}</strong></td>
                    <td>${qtyCell(w.quantity, w.reorder_level)}</td>
                    <td>${Number(w.available_qty ?? (w.quantity - w.reserved_qty)).toLocaleString()}</td>
                    <td>${Number(w.reserved_qty || 0).toLocaleString()}</td>
                    <td>${esc(money(w.stock_value))}</td>
                    <td>${esc(w.location_code || '—')}</td>
                    <td>${stockBadge(w.stock_status)}</td>
                </tr>`).join('')}</tbody></table></div>`
                : `<p class="cr-empty">${esc(t('wh_prod_no_wh_stock'))}</p>`;

            if (els.modalBody) {
                els.modalBody.innerHTML = `
                    <div class="wh-prod-detail-summary">
                        <div><span>${esc(t('wh_prod_col_qty'))}</span><strong>${Number(totals.total_qty || 0).toLocaleString()}</strong></div>
                        <div><span>${esc(t('wh_prod_col_available'))}</span><strong>${(Number(totals.total_qty || 0) - Number(totals.reserved_qty || 0)).toLocaleString()}</strong></div>
                        <div><span>${esc(t('wh_prod_col_value'))}</span><strong>${esc(money(totals.stock_value))}</strong></div>
                        <div><span>${esc(t('wh_prod_col_warehouses'))}</span><strong>${Number(totals.warehouse_count || 0)}</strong></div>
                        <div><span>${esc(t('wh_prod_col_price'))}</span><strong>${esc(money(p.price))}</strong></div>
                    </div>
                    <div class="wh-prod-detail-links">${links.join('')}</div>
                    <h4 class="wh-prod-detail-heading">${esc(t('wh_prod_wh_breakdown'))}</h4>
                    ${whTable}`;
            }
        } catch (err) {
            if (els.modalBody) els.modalBody.innerHTML = `<p class="cr-empty">${esc(err.message || t('load_error'))}</p>`;
        }
    }

    async function exportAll() {
        try {
            const res = await AdminAPI.getWmsProducts(buildParams(true));
            const items = res.data || [];
            if (!items.length) return;
            const rows = [
                [t('wh_prod_col_product'), t('wms_col_sku'), t('wh_prod_col_category'), t('wh_prod_col_qty'),
                    t('wh_prod_col_available'), t('wh_prod_col_reserved'), t('wh_prod_col_value'),
                    t('wh_prod_col_warehouses'), t('wh_prod_col_price'), t('wh_prod_col_status')],
                ...items.map((r) => [
                    r.name, r.sku, r.category_name, r.total_qty,
                    Number(r.total_qty || 0) - Number(r.reserved_qty || 0),
                    r.reserved_qty, r.stock_value, r.warehouse_count, r.price, stockLabel(r.stock_status),
                ]),
            ];
            exportCsv(`warehouse-products-${new Date().toISOString().slice(0, 10)}.csv`, rows);
        } catch (err) {
            showError(err.message || t('load_error'));
        }
    }

    els.warehouse?.addEventListener('change', () => load(true));
    els.filter?.addEventListener('change', () => load(true));
    els.category?.addEventListener('change', () => load(true));
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => load(true), 320);
    });
    els.refresh?.addEventListener('click', () => load());
    els.exportBtn?.addEventListener('click', exportAll);
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        const totalPages = Math.ceil(state.total / state.limit);
        if (state.page < totalPages) { state.page += 1; load(); }
    });
    els.modalClose?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
    document.addEventListener('wh:refresh', () => load());

    const urlQ = new URLSearchParams(window.location.search).get('q');
    if (urlQ && els.search) els.search.value = urlQ;

    loadWarehouseOptions(els.warehouse).then(() => {
        const defaultWh = String(window.WH_PAGE?.warehouseId || '');
        if (defaultWh && els.warehouse && !els.warehouse.value) els.warehouse.value = defaultWh;
        load();
    });
});
