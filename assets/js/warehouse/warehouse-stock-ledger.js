/**
 * Warehouse stock ledger — full WMS movement history with filters, breakdown, and detail modal
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whLedgerTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;

    const TYPE_KEYS = {
        purchase: 'wms_mov_purchase',
        sale: 'wms_mov_sale',
        transfer_in: 'wms_mov_transfer_in',
        transfer_out: 'wms_mov_transfer_out',
        return_in: 'wms_mov_return_in',
        return_out: 'wms_mov_return_out',
        adjustment: 'wms_mov_adjustment',
        damaged: 'wms_mov_damaged',
        expired: 'wms_mov_expired',
        lost: 'wms_mov_lost',
        manual: 'wms_mov_manual',
        dispatch_out: 'wms_mov_dispatch_out',
        receipt_in: 'wms_mov_receipt_in',
    };

    const IN_TYPES = ['receipt_in', 'transfer_in', 'purchase', 'return_in', 'adjustment', 'manual'];
    const OUT_TYPES = ['dispatch_out', 'transfer_out', 'sale', 'return_out', 'damaged', 'expired', 'lost'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        searchTimer: null,
    };

    const els = {
        loading: document.getElementById('whLedgerLoading'),
        empty: document.getElementById('whLedgerEmpty'),
        warehouse: document.getElementById('whLedgerWarehouse'),
        search: document.getElementById('whLedgerSearch'),
        type: document.getElementById('whLedgerType'),
        dateFrom: document.getElementById('whLedgerDateFrom'),
        dateTo: document.getElementById('whLedgerDateTo'),
        refresh: document.getElementById('whLedgerRefreshBtn'),
        exportBtn: document.getElementById('whLedgerExportBtn'),
        heroMeta: document.getElementById('whLedgerHeroMeta'),
        statTotal: document.getElementById('whLedgerStatTotal'),
        statIn: document.getElementById('whLedgerStatIn'),
        statOut: document.getElementById('whLedgerStatOut'),
        statNet: document.getElementById('whLedgerStatNet'),
        statValue: document.getElementById('whLedgerStatValue'),
        breakdownPanel: document.getElementById('whLedgerBreakdownPanel'),
        typeChips: document.getElementById('whLedgerTypeChips'),
        pagination: document.getElementById('whLedgerPagination'),
        prev: document.getElementById('whLedgerPrev'),
        next: document.getElementById('whLedgerNext'),
        pageMeta: document.getElementById('whLedgerPageMeta'),
        modal: document.getElementById('whLedgerDetailModal'),
        modalClose: document.getElementById('whLedgerDetailClose'),
        modalTitle: document.getElementById('whLedgerDetailTitle'),
        modalSubtitle: document.getElementById('whLedgerDetailSubtitle'),
        modalBody: document.getElementById('whLedgerDetailBody'),
    };

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function typeBadge(type) {
        let cls = 'idle';
        if (IN_TYPES.includes(type)) cls = 'ok';
        else if (OUT_TYPES.includes(type)) cls = 'off';
        else if (['damaged', 'expired', 'lost'].includes(type)) cls = 'off';
        else if (type === 'manual') cls = 'warn';
        return `<span class="cr-badge cr-badge--${cls}">${esc(typeLabel(type))}</span>`;
    }

    function qtyCell(qty) {
        const n = Number(qty || 0);
        const cls = n > 0 ? 'wh-ledger-qty--in' : (n < 0 ? 'wh-ledger-qty--out' : '');
        const prefix = n > 0 ? '+' : '';
        return `<span class="wh-ledger-qty ${cls}">${prefix}${n.toLocaleString()}</span>`;
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString(window.WH_CONFIG?.locale || 'fr-FR', {
                day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
        } catch {
            return iso;
        }
    }

    function formatRef(row) {
        const parts = [];
        if (row.reference_type) parts.push(row.reference_type);
        if (row.reference_id) parts.push(`#${row.reference_id}`);
        return parts.length ? parts.join(' ') : '—';
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-ledger-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statIn) els.statIn.textContent = Number(s.stock_in ?? 0).toLocaleString();
        if (els.statOut) els.statOut.textContent = Number(s.stock_out ?? 0).toLocaleString();
        const net = Number(s.net_qty ?? 0);
        if (els.statNet) {
            els.statNet.textContent = `${net >= 0 ? '+' : ''}${net.toLocaleString()}`;
            els.statNet.classList.toggle('wh-ledger-stat__value--neg', net < 0);
        }
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        setStatsLoading(false);
    }

    function renderBreakdown(breakdown) {
        if (!els.breakdownPanel || !els.typeChips) return;
        const items = breakdown || [];
        if (!items.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        els.breakdownPanel.hidden = false;
        const activeType = els.type?.value || 'all';
        els.typeChips.innerHTML = items.map((b) => {
            const type = b.movement_type || 'unknown';
            const active = activeType === type ? ' is-active' : '';
            return `<button type="button" class="wh-ledger-type-chip${active}" data-type="${esc(type)}">
                <span>${esc(typeLabel(type))}</span>
                <strong>${Number(b.movement_count ?? 0).toLocaleString()}</strong>
            </button>`;
        }).join('');
        els.typeChips.querySelectorAll('.wh-ledger-type-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                const type = chip.dataset.type || 'all';
                if (els.type) {
                    els.type.value = els.type.value === type ? 'all' : type;
                }
                load(true);
            });
        });
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
        const type = els.type?.value?.trim();
        if (type && type !== 'all') params.type = type;
        const from = els.dateFrom?.value?.trim();
        if (from) params.from = from;
        const to = els.dateTo?.value?.trim();
        if (to) params.to = to;
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
        const whCol = showWarehouseCol()
            ? `<th>${esc(t('wh_ledger_col_warehouse'))}</th>` : '';
        tableWrap.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wh-ledger-table"><thead><tr>
            <th>${esc(t('wh_ledger_col_date'))}</th>
            <th>${esc(t('wh_ledger_col_product'))}</th>
            ${whCol}
            <th>${esc(t('wh_ledger_col_type'))}</th>
            <th>${esc(t('wh_ledger_col_qty'))}</th>
            <th>${esc(t('wh_ledger_col_balance'))}</th>
            <th>${esc(t('wh_ledger_col_value'))}</th>
            <th>${esc(t('wh_ledger_col_reference'))}</th>
            <th>${esc(t('wh_ledger_col_user'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((r) => {
            const whCell = showWarehouseCol()
                ? `<td>${esc(r.warehouse_name || '—')}</td>` : '';
            return `<tr data-id="${esc(r.id)}" class="wh-ledger-row">
            <td class="wh-ledger-date">${esc(formatDate(r.created_at))}</td>
            <td><strong>${esc(r.product_name)}</strong><br><code class="wms-sku">${esc(r.sku || '—')}</code></td>
            ${whCell}
            <td>${typeBadge(r.movement_type)}</td>
            <td>${qtyCell(r.quantity)}</td>
            <td>${Number(r.balance_after ?? 0).toLocaleString()}</td>
            <td>${esc(money(r.stock_value))}</td>
            <td class="wh-ledger-ref">${esc(formatRef(r))}</td>
            <td>${esc(r.created_by_name || '—')}</td>
            <td><button type="button" class="wh-btn wh-btn--ghost wh-btn--sm wh-ledger-view" data-id="${esc(r.id)}" title="${esc(t('wms_view_details'))}">
                <span class="material-icons-round">visibility</span></button></td>
        </tr>`;
        }).join('')}</tbody></table></div>`;

        tableWrap.querySelectorAll('.wh-ledger-row').forEach((row) => {
            row.addEventListener('click', (ev) => {
                if (ev.target.closest('button')) return;
                const item = state.items.find((i) => String(i.id) === String(row.dataset.id));
                if (item) openDetail(item);
            });
        });
        tableWrap.querySelectorAll('.wh-ledger-view').forEach((btn) => {
            btn.addEventListener('click', (ev) => {
                ev.stopPropagation();
                const item = state.items.find((i) => String(i.id) === String(btn.dataset.id));
                if (item) openDetail(item);
            });
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

    function openDetail(row) {
        if (els.modalTitle) els.modalTitle.textContent = row.product_name || t('wh_ledger_details');
        if (els.modalSubtitle) {
            els.modalSubtitle.textContent = [row.sku, typeLabel(row.movement_type), row.warehouse_name].filter(Boolean).join(' · ');
        }
        if (els.modalBody) {
            els.modalBody.innerHTML = `
                <dl class="wh-ledger-detail-grid">
                    <div><dt>${esc(t('wh_ledger_col_date'))}</dt><dd>${esc(formatDate(row.created_at))}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_type'))}</dt><dd>${typeBadge(row.movement_type)}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_qty'))}</dt><dd>${qtyCell(row.quantity)}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_balance'))}</dt><dd>${Number(row.balance_after ?? 0).toLocaleString()}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_value'))}</dt><dd>${esc(money(row.stock_value))}</dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(row.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_reference'))}</dt><dd>${esc(formatRef(row))}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_user'))}</dt><dd>${esc(row.created_by_name || '—')}</dd></div>
                </dl>
                ${row.notes ? `<p class="wh-ledger-detail-notes"><strong>${esc(t('wh_ledger_col_notes'))}:</strong> ${esc(row.notes)}</p>` : ''}
                <div class="wh-ledger-detail-links">
                    ${row.sku ? `<a class="wh-scan-link" href="barcode_scanner.php?q=${encodeURIComponent(row.sku)}">${esc(t('wh_ledger_link_scanner'))}</a>` : ''}
                    <a class="wh-scan-link" href="warehouse_inventory.php">${esc(t('wh_ledger_link_inventory'))}</a>
                </div>`;
        }
        openModal();
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const params = buildParams();
            const wh = params.warehouse_id || null;
            delete params.warehouse_id;
            const res = await AdminAPI.getWmsMovements(wh, params);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            state.breakdown = res.breakdown || [];
            renderStats(state.summary);
            renderBreakdown(state.breakdown);
            renderTable(state.items);
            renderPagination();
            const whName = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
            if (els.heroMeta) els.heroMeta.textContent = `${whName} · ${state.total} ${t('records')}`;
            updateLastUpdated();
        } catch (err) {
            showError(err.message || t('load_error'));
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
            if (els.breakdownPanel) els.breakdownPanel.hidden = true;
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    async function exportAll() {
        try {
            const params = buildParams(true);
            const wh = params.warehouse_id || null;
            delete params.warehouse_id;
            const res = await AdminAPI.getWmsMovements(wh, params);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            const rows = res.data || [];
            if (!rows.length) return;
            const headers = [
                t('wh_ledger_col_date'), t('wh_ledger_col_product'), 'SKU',
                t('wh_ledger_col_warehouse'), t('wh_ledger_col_type'),
                t('wh_ledger_col_qty'), t('wh_ledger_col_balance'), t('wh_ledger_col_value'),
                t('wh_ledger_col_reference'), t('wh_ledger_col_notes'), t('wh_ledger_col_user'),
            ];
            exportCsv('warehouse-stock-ledger.csv', [
                headers,
                ...rows.map((r) => [
                    formatDate(r.created_at), r.product_name, r.sku,
                    r.warehouse_name, typeLabel(r.movement_type),
                    r.quantity, r.balance_after, r.stock_value,
                    formatRef(r), r.notes || '', r.created_by_name || '',
                ]),
            ]);
        } catch (err) {
            showError(err.message || t('load_error'));
        }
    }

    function initFromQuery() {
        const q = new URLSearchParams(window.location.search).get('q');
        if (q && els.search) {
            els.search.value = q;
        }
    }

    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => load(true), 320);
    });
    els.warehouse?.addEventListener('change', () => load(true));
    els.type?.addEventListener('change', () => load(true));
    els.dateFrom?.addEventListener('change', () => load(true));
    els.dateTo?.addEventListener('change', () => load(true));
    els.refresh?.addEventListener('click', () => load());
    els.exportBtn?.addEventListener('click', exportAll);
    els.prev?.addEventListener('click', () => { state.page -= 1; load(); });
    els.next?.addEventListener('click', () => { state.page += 1; load(); });
    els.modalClose?.addEventListener('click', closeModal);
    els.modal?.querySelector('[data-close-modal]')?.addEventListener('click', closeModal);
    document.addEventListener('wh:refresh', () => load());
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });

    loadWarehouseOptions(els.warehouse).then(() => {
        const whId = String(window.WH_PAGE?.warehouseId || '');
        if (whId && els.warehouse) els.warehouse.value = whId;
        initFromQuery();
        load(true);
    }).catch((err) => showError(err.message || t('load_error')));
});
