/**
 * Warehouse stock levels v1 — on-hand vs reorder threshold monitoring
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whSlTableWrap');
    if (!tableWrap) return;

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;

    const LEVEL_KEYS = { ok: 'wh_sl_level_ok', low: 'wh_sl_level_low', out: 'wh_sl_level_out' };

    const state = { page: 1, limit: 50, total: 0, items: [], summary: null, searchTimer: null };

    const els = {
        loading: document.getElementById('whSlLoading'),
        empty: document.getElementById('whSlEmpty'),
        warehouse: document.getElementById('whSlWarehouse'),
        search: document.getElementById('whSlSearch'),
        filter: document.getElementById('whSlFilter'),
        refresh: document.getElementById('whSlRefreshBtn'),
        exportBtn: document.getElementById('whSlExportBtn'),
        heroMeta: document.getElementById('whSlHeroMeta'),
        statSkus: document.getElementById('whSlStatSkus'),
        statOk: document.getElementById('whSlStatOk'),
        statLow: document.getElementById('whSlStatLow'),
        statOut: document.getElementById('whSlStatOut'),
        statNeeds: document.getElementById('whSlStatNeeds'),
        statGap: document.getElementById('whSlStatGap'),
        pagination: document.getElementById('whSlPagination'),
        prev: document.getElementById('whSlPrev'),
        next: document.getElementById('whSlNext'),
        pageMeta: document.getElementById('whSlPageMeta'),
    };

    function levelLabel(status) {
        return t(LEVEL_KEYS[status] || status) || status || '—';
    }

    function levelBadge(status) {
        const cls = status === 'ok' ? 'ok' : (status === 'out' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(levelLabel(status))}</span>`;
    }

    function fillBar(pct, status) {
        const n = Math.min(100, Math.max(0, Number(pct) || 0));
        const cls = status === 'ok' ? 'ok' : (status === 'out' ? 'out' : 'low');
        return `<div class="wh-sl-fill" aria-hidden="true"><div class="wh-sl-fill__bar wh-sl-fill__bar--${cls}" style="width:${n}%"></div></div><span class="wh-sl-fill__pct">${n}%</span>`;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-sl-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statSkus) els.statSkus.textContent = String(s.sku_count ?? 0);
        if (els.statOk) els.statOk.textContent = String(s.ok_count ?? 0);
        if (els.statLow) els.statLow.textContent = String(s.low_count ?? 0);
        if (els.statOut) els.statOut.textContent = String(s.out_count ?? 0);
        if (els.statNeeds) els.statNeeds.textContent = String(s.needs_reorder ?? 0);
        if (els.statGap) els.statGap.textContent = Number(s.total_reorder_gap ?? 0).toLocaleString();
        setStatsLoading(false);
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
        return params;
    }

    function showWarehouseCol() {
        return !els.warehouse?.value?.trim();
    }

    function renderTable(items) {
        if (!items.length) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        const whColHead = showWarehouseCol()
            ? `<th class="wh-sl-col--wh">${esc(t('wh_sl_col_warehouse'))}</th>` : '';
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-sl-list-table"><thead><tr>
            <th class="wh-sl-col--product">${esc(t('wh_sl_col_product'))}</th>
            ${whColHead}
            <th class="wh-sl-col--qty">${esc(t('wh_sl_col_on_hand'))}</th>
            <th class="wh-sl-col--qty">${esc(t('wh_sl_col_available'))}</th>
            <th class="wh-sl-col--qty">${esc(t('wh_sl_col_reorder'))}</th>
            <th class="wh-sl-col--gap">${esc(t('wh_sl_col_gap'))}</th>
            <th class="wh-sl-col--fill">${esc(t('wh_sl_col_fill'))}</th>
            <th class="wh-sl-col--status">${esc(t('wh_sl_col_status'))}</th>
            <th class="wh-sl-col--actions" aria-label="${esc(t('wh_sl_link_inv'))}"></th>
        </tr></thead><tbody>${items.map((r) => {
            const whCell = showWarehouseCol()
                ? `<td class="wh-sl-col--wh">${esc(r.warehouse_name || '—')}</td>` : '';
            return `<tr class="wh-sl-list-row">
            <td class="wh-sl-col--product">
                <strong>${esc(r.product_name)}</strong>
                <code class="wms-sku">${esc(r.sku || '—')}</code>
            </td>
            ${whCell}
            <td class="wh-sl-col--qty">${Number(r.quantity || 0).toLocaleString()}</td>
            <td class="wh-sl-col--qty">${Number(r.available_qty ?? (r.quantity - r.reserved_qty)).toLocaleString()}</td>
            <td class="wh-sl-col--qty">${Number(r.reorder_level ?? 0).toLocaleString()}</td>
            <td class="wh-sl-col--gap${Number(r.reorder_gap) > 0 ? ' wh-sl-gap--warn' : ''}">${Number(r.reorder_gap || 0).toLocaleString()}</td>
            <td class="wh-sl-col--fill">${fillBar(r.fill_pct, r.level_status)}</td>
            <td class="wh-sl-col--status">${levelBadge(r.level_status)}</td>
            <td class="wh-sl-col--actions wh-sl-row-actions">
                <a class="wh-btn wh-btn--ghost wh-btn--sm" href="warehouse_inventory.php?warehouse_id=${r.warehouse_id}&amp;q=${encodeURIComponent(r.sku || '')}">${esc(t('wh_sl_link_inv'))}</a>
            </td>
        </tr>`;
        }).join('')}</tbody></table>`;
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
            const res = await AdminAPI.getWmsStockLevels(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            renderStats(state.summary);
            renderTable(state.items);
            renderPagination();
            const whName = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
            if (els.heroMeta) els.heroMeta.textContent = `${whName} · ${state.total} ${t('records')}`;
            updateLastUpdated();
        } catch (err) {
            showError(err.message || t('load_error'));
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    async function exportAll() {
        try {
            const res = await AdminAPI.getWmsStockLevels(buildParams(true));
            const items = res.data || [];
            if (!items.length) return;
            const rows = [
                [t('wh_sl_col_product'), t('wms_col_sku'), t('wh_sl_col_warehouse'), t('wh_sl_col_on_hand'),
                    t('wh_sl_col_available'), t('wh_sl_col_reorder'), t('wh_sl_col_gap'),
                    t('wh_sl_col_fill'), t('wh_sl_col_status')],
                ...items.map((r) => [
                    r.product_name, r.sku, r.warehouse_name, r.quantity, r.available_qty,
                    r.reorder_level, r.reorder_gap, `${r.fill_pct}%`, levelLabel(r.level_status),
                ]),
            ];
            exportCsv(`stock-levels-${new Date().toISOString().slice(0, 10)}.csv`, rows);
        } catch (err) {
            showError(err.message || t('load_error'));
        }
    }

    els.warehouse?.addEventListener('change', () => load(true));
    els.filter?.addEventListener('change', () => load(true));
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => load(true), 320);
    });
    els.refresh?.addEventListener('click', () => load());
    els.exportBtn?.addEventListener('click', exportAll);
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        if (state.page < Math.ceil(state.total / state.limit)) { state.page += 1; load(); }
    });
    document.addEventListener('wh:refresh', () => load());

    const urlQ = new URLSearchParams(window.location.search).get('q');
    if (urlQ && els.search) els.search.value = urlQ;

    loadWarehouseOptions(els.warehouse).then(() => {
        const defaultWh = String(window.WH_PAGE?.warehouseId || '');
        if (defaultWh && els.warehouse) els.warehouse.value = defaultWh;
        load();
    });
});
