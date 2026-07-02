/**
 * Warehouse inventory history — store-level movement ledger with traceability
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whIhTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, updateLastUpdated, exportCsv } = WarehouseUI;

    const PAGE_SIZE = 50;
    const MOVEMENT_KEYS = {
        purchase: 'mov_purchase',
        sale: 'mov_sale',
        return: 'mov_return',
        transfer_in: 'mov_transfer_in',
        transfer_out: 'mov_transfer_out',
        transfer: 'mov_transfer_in',
        adjustment: 'mov_adjustment',
        damaged: 'mov_damaged',
        expired: 'mov_expired',
        manual_edit: 'mov_manual_edit',
    };
    const TRANSFER_TYPES = ['transfer_in', 'transfer_out', 'transfer'];
    const IN_TYPES = ['purchase', 'return', 'transfer_in', 'adjustment', 'manual_edit'];
    const OUT_TYPES = ['sale', 'transfer_out', 'damaged', 'expired'];

    const state = {
        allRows: [],
        filteredRows: [],
        page: 1,
        quickFilter: 'all',
        searchTimer: null,
        dataSource: 'inventory_ledger',
        highlightLogId: null,
        highlightProductId: null,
    };

    const els = {
        loading: document.getElementById('whIhLoading'),
        empty: document.getElementById('whIhEmpty'),
        search: document.getElementById('whIhSearch'),
        type: document.getElementById('whIhType'),
        dateFrom: document.getElementById('whIhDateFrom'),
        dateTo: document.getElementById('whIhDateTo'),
        refresh: document.getElementById('whIhRefreshBtn'),
        exportBtn: document.getElementById('whIhExportBtn'),
        heroMeta: document.getElementById('whIhHeroMeta'),
        statEntries: document.getElementById('whIhStatEntries'),
        statIn: document.getElementById('whIhStatIn'),
        statOut: document.getElementById('whIhStatOut'),
        statProfit: document.getElementById('whIhStatProfit'),
        sourceNotice: document.getElementById('whIhSourceNotice'),
        breakdownPanel: document.getElementById('whIhBreakdownPanel'),
        quickChips: document.getElementById('whIhQuickChips'),
        typeChips: document.getElementById('whIhTypeChips'),
        pagination: document.getElementById('whIhPagination'),
        prev: document.getElementById('whIhPrev'),
        next: document.getElementById('whIhNext'),
        pageMeta: document.getElementById('whIhPageMeta'),
        modal: document.getElementById('whIhDetailModal'),
        modalClose: document.getElementById('whIhDetailClose'),
        modalTitle: document.getElementById('whIhDetailTitle'),
        modalSubtitle: document.getElementById('whIhDetailSubtitle'),
        modalBody: document.getElementById('whIhDetailBody'),
    };

    function movementLabel(type) {
        return t(MOVEMENT_KEYS[type] || type) || type || '—';
    }

    function typeBadge(type) {
        let cls = 'idle';
        if (IN_TYPES.includes(type)) cls = 'ok';
        else if (OUT_TYPES.includes(type)) cls = 'off';
        else if (type === 'manual_edit') cls = 'warn';
        return `<span class="cr-badge cr-badge--${cls}">${esc(movementLabel(type))}</span>`;
    }

    function normalizeRow(row) {
        const stockIn = parseInt(row.stock_in, 10) || 0;
        const stockOut = parseInt(row.stock_out, 10) || 0;
        let opening = parseInt(row.opening_stock, 10);
        if (Number.isNaN(opening)) opening = 0;
        let current = parseInt(row.current_stock, 10);
        if (Number.isNaN(current)) current = Math.max(0, opening + stockIn - stockOut);
        const cost = parseFloat(row.purchase_price ?? row.cost ?? 0) || 0;
        const price = parseFloat(row.selling_price ?? row.price ?? 0) || 0;
        const profit = Number.isFinite(parseFloat(row.estimated_profit))
            ? parseFloat(row.estimated_profit)
            : estimateProfit(row.movement_type, stockIn, stockOut, cost, price);
        return {
            ...row,
            opening_stock: opening,
            stock_in: stockIn,
            stock_out: stockOut,
            current_stock: current,
            cost_price: cost,
            sale_price: price,
            estimated_profit: profit,
            movement_type: row.movement_type || 'adjustment',
        };
    }

    function estimateProfit(type, stockIn, stockOut, cost, price) {
        switch (type) {
            case 'sale': return stockOut * (price - cost);
            case 'return': return stockIn * (price - cost);
            case 'damaged':
            case 'expired':
            case 'transfer_out': return -stockOut * cost;
            default:
                if (stockOut > 0 && stockIn === 0) return -stockOut * cost;
                return 0;
        }
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

    function qtyCell(n, kind) {
        const v = Number(n || 0);
        if (!v) return '—';
        const cls = kind === 'in' ? 'wh-ih-qty--in' : (kind === 'out' ? 'wh-ih-qty--out' : '');
        const prefix = kind === 'in' ? '+' : (kind === 'out' ? '-' : '');
        return `<span class="wh-ih-qty ${cls}">${prefix}${v.toLocaleString()}</span>`;
    }

    function profitCell(n) {
        const v = Number(n || 0);
        const cls = v > 0 ? 'wh-ih-profit--pos' : (v < 0 ? 'wh-ih-profit--neg' : '');
        const prefix = v > 0 ? '+' : '';
        return `<span class="wh-ih-profit ${cls}">${prefix}${money(v)}</span>`;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-ih-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(rows) {
        const totalIn = rows.reduce((s, r) => s + (r.stock_in || 0), 0);
        const totalOut = rows.reduce((s, r) => s + (r.stock_out || 0), 0);
        const profit = rows.reduce((s, r) => s + (r.estimated_profit || 0), 0);
        if (els.statEntries) els.statEntries.textContent = String(rows.length);
        if (els.statIn) els.statIn.textContent = totalIn.toLocaleString();
        if (els.statOut) els.statOut.textContent = totalOut.toLocaleString();
        if (els.statProfit) els.statProfit.textContent = money(profit);
        setStatsLoading(false);
    }

    function applyQuickFilter(rows) {
        const qf = state.quickFilter;
        if (qf === 'all') return rows;
        if (qf === 'transfer') return rows.filter((r) => TRANSFER_TYPES.includes(r.movement_type));
        if (qf === 'adjustments') return rows.filter((r) => ['adjustment', 'manual_edit'].includes(r.movement_type));
        return rows.filter((r) => r.movement_type === qf);
    }

    function buildBreakdown(rows) {
        const map = {};
        rows.forEach((r) => {
            const type = r.movement_type || 'unknown';
            if (!map[type]) map[type] = { movement_type: type, movement_count: 0 };
            map[type].movement_count += 1;
        });
        return Object.values(map).sort((a, b) => b.movement_count - a.movement_count);
    }

    function renderBreakdown(rows) {
        if (!els.breakdownPanel) return;
        const breakdown = buildBreakdown(rows);
        els.breakdownPanel.hidden = !breakdown.length;

        if (els.typeChips) {
            const activeType = els.type?.value || '';
            els.typeChips.innerHTML = breakdown.map((b) => {
                const type = b.movement_type;
                const active = activeType === type ? ' is-active' : '';
                return `<button type="button" class="wh-ih-type-chip${active}" data-type="${esc(type)}">
                    <span>${esc(movementLabel(type))}</span>
                    <strong>${Number(b.movement_count).toLocaleString()}</strong>
                </button>`;
            }).join('');
            els.typeChips.querySelectorAll('.wh-ih-type-chip').forEach((chip) => {
                chip.addEventListener('click', () => {
                    const type = chip.dataset.type || '';
                    if (els.type) els.type.value = els.type.value === type ? '' : type;
                    state.quickFilter = 'all';
                    syncQuickChips();
                    load();
                });
            });
        }
    }

    function syncQuickChips() {
        els.quickChips?.querySelectorAll('.wh-ih-quick-chip').forEach((chip) => {
            chip.classList.toggle('active', chip.dataset.quick === state.quickFilter);
        });
    }

    function buildQuery() {
        const query = {};
        const q = els.search?.value?.trim();
        if (q) query.q = q;
        const type = els.type?.value?.trim();
        if (type) query.type = type;
        const from = els.dateFrom?.value?.trim();
        if (from) query.date_from = from;
        const to = els.dateTo?.value?.trim();
        if (to) query.date_to = to;
        const page = window.WH_PAGE || {};
        if (!page.isGlobalView && page.storeId) query.store_id = page.storeId;
        return query;
    }

    function applyFilters() {
        state.filteredRows = applyQuickFilter(state.allRows);
        state.page = 1;
        renderPage();
    }

    function pageRows() {
        const start = (state.page - 1) * PAGE_SIZE;
        return state.filteredRows.slice(start, start + PAGE_SIZE);
    }

    function isRowHighlighted(row) {
        if (!state.highlightLogId && !state.highlightProductId) return false;
        if (state.highlightLogId) {
            if (String(row.id) === String(state.highlightLogId)) return true;
            if (String(row.reference_id || '') === String(state.highlightLogId)) return true;
        }
        if (state.highlightProductId && String(row.product_id || '') === String(state.highlightProductId)) {
            return ['adjustment', 'manual_edit'].includes(row.movement_type);
        }
        return false;
    }

    function renderTable(items) {
        if (!items.length) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        tableWrap.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wh-ih-table"><thead><tr>
            <th>${esc(t('wh_ih_col_date'))}</th>
            <th>${esc(t('col_type'))}</th>
            <th>${esc(t('col_product'))}</th>
            <th>${esc(t('col_store'))}</th>
            <th>${esc(t('col_stock_in'))}</th>
            <th>${esc(t('col_stock_out'))}</th>
            <th>${esc(t('wh_ih_col_opening'))}</th>
            <th>${esc(t('wh_ih_col_balance'))}</th>
            <th>${esc(t('col_estimated_profit'))}</th>
            <th>${esc(t('col_user'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((r) => {
            const hl = isRowHighlighted(r) ? ' wh-ih-row--highlight' : '';
            return `<tr class="wh-ih-row${hl}" data-id="${esc(r.id)}">
            <td class="wh-ih-date">${esc(formatDate(r.movement_date))}</td>
            <td>${typeBadge(r.movement_type)}</td>
            <td><strong>${esc(r.product_name)}</strong><br><code class="wms-sku">${esc(r.sku || r.barcode || '—')}</code></td>
            <td>${esc(r.store_name || '—')}</td>
            <td>${qtyCell(r.stock_in, 'in')}</td>
            <td>${qtyCell(r.stock_out, 'out')}</td>
            <td>${Number(r.opening_stock ?? 0).toLocaleString()}</td>
            <td>${Number(r.current_stock ?? 0).toLocaleString()}</td>
            <td>${profitCell(r.estimated_profit)}</td>
            <td>${esc(r.user_name || '—')}</td>
            <td><button type="button" class="wh-btn wh-btn--ghost wh-btn--sm wh-ih-view" data-id="${esc(r.id)}" title="${esc(t('wms_view_details'))}">
                <span class="material-icons-round">visibility</span></button>            </td>
        </tr>`;
        }).join('')}</tbody></table></div>`;

        tableWrap.querySelectorAll('.wh-ih-row').forEach((row) => {
            row.addEventListener('click', (ev) => {
                if (ev.target.closest('button')) return;
                const item = state.allRows.find((i) => String(i.id) === String(row.dataset.id));
                if (item) openDetail(item);
            });
        });
        tableWrap.querySelectorAll('.wh-ih-view').forEach((btn) => {
            btn.addEventListener('click', (ev) => {
                ev.stopPropagation();
                const item = state.allRows.find((i) => String(i.id) === String(btn.dataset.id));
                if (item) openDetail(item);
            });
        });
    }

    function renderPagination() {
        const total = state.filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));
        const show = total > PAGE_SIZE;
        if (els.pagination) els.pagination.hidden = !show;
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= totalPages;
        if (els.pageMeta) {
            const from = total ? (state.page - 1) * PAGE_SIZE + 1 : 0;
            const to = Math.min(state.page * PAGE_SIZE, total);
            els.pageMeta.textContent = `${from}–${to} / ${total} ${t('records')}`;
        }
        if (els.heroMeta) {
            const filterLabel = state.quickFilter === 'all' ? t('wh_ih_filter_all')
                : (state.quickFilter === 'adjustments' ? t('wh_ih_filter_adjustments')
                    : (state.quickFilter === 'transfer' ? t('wh_ih_filter_transfers')
                        : movementLabel(state.quickFilter)));
            els.heroMeta.textContent = `${total} ${t('records')} · ${filterLabel}`;
        }
    }

    function renderPage() {
        renderStats(state.filteredRows);
        renderBreakdown(state.filteredRows);
        renderTable(pageRows());
        renderPagination();
        scrollToHighlight();
    }

    function scrollToHighlight() {
        if (!state.highlightLogId && !state.highlightProductId) return;
        const row = tableWrap.querySelector('.wh-ih-row--highlight');
        if (row) {
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
        if (els.modalTitle) els.modalTitle.textContent = row.product_name || t('wh_ih_details');
        if (els.modalSubtitle) {
            els.modalSubtitle.textContent = [movementLabel(row.movement_type), row.store_name, formatDate(row.movement_date)].filter(Boolean).join(' · ');
        }
        if (els.modalBody) {
            els.modalBody.innerHTML = `
                <div class="wh-ih-flow">
                    <div class="wh-ih-flow__node"><span>${esc(t('opening_stock'))}</span><strong>${Number(row.opening_stock ?? 0).toLocaleString()}</strong></div>
                    <span class="wh-ih-flow__arrow material-icons-round">arrow_forward</span>
                    <div class="wh-ih-flow__node wh-ih-flow__node--in"><span>${esc(t('col_stock_in'))}</span><strong>+${Number(row.stock_in ?? 0).toLocaleString()}</strong></div>
                    <div class="wh-ih-flow__node wh-ih-flow__node--out"><span>${esc(t('col_stock_out'))}</span><strong>-${Number(row.stock_out ?? 0).toLocaleString()}</strong></div>
                    <span class="wh-ih-flow__arrow material-icons-round">arrow_forward</span>
                    <div class="wh-ih-flow__node wh-ih-flow__node--bal"><span>${esc(t('col_current_stock'))}</span><strong>${Number(row.current_stock ?? 0).toLocaleString()}</strong></div>
                </div>
                <dl class="wh-ih-detail-grid">
                    <div><dt>${esc(t('col_sku_barcode'))}</dt><dd><code>${esc(row.sku || row.barcode || '—')}</code></dd></div>
                    <div><dt>${esc(t('col_type'))}</dt><dd>${typeBadge(row.movement_type)}</dd></div>
                    <div><dt>${esc(t('col_store'))}</dt><dd>${esc(row.store_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_user'))}</dt><dd>${esc(row.user_name || '—')}</dd></div>
                    <div><dt>${esc(t('cost_price'))}</dt><dd>${esc(money(row.cost_price))}</dd></div>
                    <div><dt>${esc(t('sale_price_label'))}</dt><dd>${esc(money(row.sale_price))}</dd></div>
                    <div><dt>${esc(t('col_estimated_profit'))}</dt><dd>${profitCell(row.estimated_profit)}</dd></div>
                </dl>
                ${row.notes ? `<p class="wh-ih-detail-notes"><strong>${esc(t('col_notes'))}:</strong> ${esc(row.notes)}</p>` : ''}
                <div class="wh-ih-detail-links">
                    ${row.sku ? `<a class="wh-scan-link" href="barcode_scanner.php?q=${encodeURIComponent(row.sku)}">${esc(t('wh_ih_link_products'))}</a>` : ''}
                    <a class="wh-scan-link" href="stock_ledger.php">${esc(t('wh_ih_link_ledger'))}</a>
                </div>`;
        }
        openModal();
    }

    async function load() {
        hideError();
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getInventoryLedger(buildQuery());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            state.dataSource = res.source || 'inventory_ledger';
            if (els.sourceNotice) {
                els.sourceNotice.hidden = state.dataSource !== 'inventory_logs';
            }
            state.allRows = (res.data || []).map(normalizeRow);
            applyFilters();
            updateLastUpdated();
            if (state.highlightLogId) {
                const hit = state.filteredRows.find((r) => isRowHighlighted(r));
                if (hit) openDetail(hit);
            }
        } catch (err) {
            showError(err.message || t('load_error'));
            state.allRows = [];
            state.filteredRows = [];
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
            if (els.breakdownPanel) els.breakdownPanel.hidden = true;
            setStatsLoading(false);
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    function exportAll() {
        const rows = state.filteredRows;
        if (!rows.length) return;
        exportCsv('warehouse-inventory-history.csv', [
            [t('wh_ih_col_date'), t('col_type'), t('col_product'), 'SKU', t('col_store'),
                t('col_stock_in'), t('col_stock_out'), t('wh_ih_col_opening'), t('wh_ih_col_balance'),
                t('col_estimated_profit'), t('col_user'), t('col_notes')],
            ...rows.map((r) => [
                formatDate(r.movement_date), movementLabel(r.movement_type), r.product_name, r.sku,
                r.store_name, r.stock_in, r.stock_out, r.opening_stock, r.current_stock,
                r.estimated_profit, r.user_name || '', r.notes || '',
            ]),
        ]);
    }

    function initFromQuery() {
        const params = new URLSearchParams(window.location.search);
        const q = params.get('q');
        if (q && els.search) els.search.value = q;
        state.highlightLogId = params.get('highlight_log');
        state.highlightProductId = params.get('highlight_product');
    }

    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(load, 320);
    });
    els.type?.addEventListener('change', () => {
        state.quickFilter = 'all';
        syncQuickChips();
        load();
    });
    els.dateFrom?.addEventListener('change', load);
    els.dateTo?.addEventListener('change', load);
    els.refresh?.addEventListener('click', load);
    els.exportBtn?.addEventListener('click', exportAll);
    els.prev?.addEventListener('click', () => { state.page -= 1; renderPage(); });
    els.next?.addEventListener('click', () => { state.page += 1; renderPage(); });
    els.modalClose?.addEventListener('click', closeModal);
    els.modal?.querySelector('[data-close-modal]')?.addEventListener('click', closeModal);
    els.quickChips?.querySelectorAll('.wh-ih-quick-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            state.quickFilter = chip.dataset.quick || 'all';
            syncQuickChips();
            if (els.type) els.type.value = '';
            applyFilters();
        });
    });
    document.addEventListener('wh:refresh', load);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });

    initFromQuery();
    load();
});
