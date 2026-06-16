/**
 * Admin inventory history — dynamic ledger, filters, export, traceability
 */
(() => {
    const CFG = window.INVENTORY_CONFIG || {};
    const i18n = window.INVENTORY_I18N || {};
    const locale = CFG.locale || (CFG.lang === 'fr' ? 'fr-FR' : 'en-US');
    const PAGE_SIZE = 20;
    const VIEW_KEY = 'ih_view_mode';

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

    const $ = (id) => document.getElementById(id);
    let currentRows = [];
    let currentPage = 1;
    let activePeriod = 'all';
    let debounceTimer = null;
    let lastFetchAt = null;
    let adjustHighlights = [];
    let jumpToHighlightOnce = false;
    let dataSource = 'inventory_ledger';
    let viewMode = localStorage.getItem(VIEW_KEY) || 'compact';
    let quickTypeFilter = 'all';

    const ADJUST_HIGHLIGHT_KEY = 'pos_inventory_adjust_highlights';
    const ADJUST_HIGHLIGHT_TTL_MS = 24 * 60 * 60 * 1000;

    function loadAdjustHighlights() {
        const params = new URLSearchParams(window.location.search);
        const urlLogId = params.get('highlight_log');
        const urlProductId = params.get('highlight_product');
        let items = [];

        try {
            items = JSON.parse(sessionStorage.getItem(ADJUST_HIGHLIGHT_KEY) || '[]');
        } catch {
            items = [];
        }

        const now = Date.now();
        items = items.filter((item) => now - (item.at || 0) < ADJUST_HIGHLIGHT_TTL_MS);

        if (urlLogId) {
            items.unshift({ logId: urlLogId, productId: urlProductId || null, at: now });
        }

        const seen = new Set();
        items = items.filter((item) => {
            const key = `${item.logId || ''}-${item.ledgerId || ''}-${item.productId || ''}-${item.at || ''}`;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });

        sessionStorage.setItem(ADJUST_HIGHLIGHT_KEY, JSON.stringify(items.slice(0, 30)));
        return items;
    }

    function clearAdjustHighlights() {
        adjustHighlights = [];
        sessionStorage.removeItem(ADJUST_HIGHLIGHT_KEY);
        const url = new URL(window.location.href);
        url.searchParams.delete('highlight_log');
        url.searchParams.delete('highlight_product');
        window.history.replaceState({}, '', url.pathname + url.search);
        updateAdjustHighlightBanner();
        renderRows(currentRows);
    }

    function isRowHighlighted(row) {
        if (!adjustHighlights.length) return false;

        return adjustHighlights.some((h) => {
            if (h.logId) {
                if (String(row.reference_id || '') === String(h.logId)) return true;
                if (String(row.trace_id || '') === String(h.logId)) return true;
                if (row.notes && String(row.notes).includes(`#${h.logId}`)) return true;
            }
            if (h.ledgerId && String(row.id || '') === String(h.ledgerId)) return true;
            if (h.productId && String(row.product_id || '') === String(h.productId)) {
                const types = ['adjustment', 'manual_edit'];
                if (types.includes(row.movement_type)) {
                    const rowTime = new Date(row.movement_date || 0).getTime();
                    if (!h.at || Math.abs(rowTime - h.at) < 15 * 60 * 1000) return true;
                }
            }
            return false;
        });
    }

    function updateAdjustHighlightBanner() {
        const banner = $('adjustHighlightBanner');
        const text = $('adjustHighlightText');
        if (!banner || !text) return;

        const count = currentRows.filter((row) => isRowHighlighted(row)).length;
        if (!adjustHighlights.length || count === 0) {
            banner.hidden = true;
            return;
        }

        banner.hidden = false;
        text.textContent = t('adjust_highlight_banner', count);
    }

    function scrollToFirstHighlight() {
        const row = document.querySelector('#inventoryHistoryBody tr.ih-row-adjust-highlight');
        row?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function escapeAttr(s) {
        return String(s ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function columnLabels() {
        return {
            date: t('col_date'),
            type: t('col_type'),
            product: t('col_product'),
            sku: t('col_sku_barcode'),
            opening: t('col_opening_stock'),
            stockIn: t('col_stock_in'),
            stockOut: t('col_stock_out'),
            current: t('col_current_stock'),
            openingValue: t('col_opening_value'),
            outValue: t('col_out_value'),
            currentValue: t('col_current_value'),
            profit: t('col_estimated_profit'),
            user: t('col_user'),
            store: t('col_store'),
            notes: t('col_notes'),
            actions: t('col_actions'),
        };
    }

    function movementLabel(type) {
        const key = MOVEMENT_KEYS[type];
        return key ? t(key) : (type || '—');
    }

    function normalizeRow(row) {
        const stockIn = parseInt(row.stock_in, 10) || 0;
        const stockOut = parseInt(row.stock_out, 10) || 0;
        let openingStock = parseInt(row.opening_stock, 10);
        if (Number.isNaN(openingStock)) openingStock = 0;
        let currentStock = parseInt(row.current_stock, 10);
        if (Number.isNaN(currentStock)) {
            currentStock = openingStock + stockIn - stockOut;
        }

        return {
            ...row,
            opening_stock: openingStock,
            stock_in: stockIn,
            stock_out: stockOut,
            current_stock: currentStock,
            cost_price: row.purchase_price ?? row.cost_price ?? 0,
            sale_price: row.selling_price ?? row.sale_price ?? 0,
            opening_value: row.opening_stock_value ?? row.opening_value ?? 0,
            trace_id: row.reference_id ?? row.trace_id ?? null,
        };
    }

    function formatQty(value) {
        const n = parseInt(value, 10);
        return Number.isNaN(n) ? '0' : n.toLocaleString(locale);
    }

    function formatDate(value) {
        if (!value) return '—';
        return AdminAPI.formatDate(value, { dateStyle: 'short', timeStyle: 'short' });
    }

    function colSpan() {
        return viewMode === 'compact' ? 11 : 16;
    }

    function applyViewMode(mode) {
        viewMode = mode === 'detailed' ? 'detailed' : 'compact';
        localStorage.setItem(VIEW_KEY, viewMode);
        document.body.classList.toggle('view-compact', viewMode === 'compact');
        document.querySelectorAll('.ih-view-toggle button').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.view === viewMode);
        });
    }

    function showSourceNotice() {
        const notice = $('dataSourceNotice');
        if (notice) notice.hidden = true;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.ih-stat').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function showError(msg) {
        const banner = $('historyError');
        if (!banner) return;
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        banner.classList.add('is-visible');
    }

    function hideError() {
        $('historyError')?.classList.remove('is-visible');
    }

    function toast(msg, type = 'success') {
        const el = $('invToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast show ${type === 'error' ? 'error' : ''}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
    }

    function updateDateHeader() {
        const header = $('historyDate');
        if (!header) return;
        header.textContent = new Date().toLocaleDateString(locale, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }

    function updateLastUpdated() {
        const el = $('lastUpdated');
        if (!el || !lastFetchAt) return;
        const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        el.textContent = t('last_updated', time);
    }

    function toDateInput(d) {
        return d.toISOString().slice(0, 10);
    }

    function applyPeriodChip(period) {
        activePeriod = period;
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        document.querySelectorAll('.ih-chips[data-chip-group="period"] .inv-chip').forEach((chip) => {
            chip.classList.toggle('active', chip.dataset.period === period);
        });

        if (period === 'all') {
            if ($('historyDateFrom')) $('historyDateFrom').value = '';
            if ($('historyDateTo')) $('historyDateTo').value = '';
            return;
        }

        const from = new Date(today);
        if (period === 'week') from.setDate(from.getDate() - 6);
        if (period === 'month') from.setDate(from.getDate() - 29);

        if ($('historyDateFrom')) $('historyDateFrom').value = toDateInput(from);
        if ($('historyDateTo')) $('historyDateTo').value = toDateInput(today);
    }

    function applyQuickType(type) {
        quickTypeFilter = type || 'all';
        document.querySelectorAll('.ih-type-chips .inv-chip').forEach((chip) => {
            chip.classList.toggle('active', chip.dataset.typeFilter === quickTypeFilter);
        });

        const select = $('historyMovementType');
        if (!select) return;

        if (quickTypeFilter === 'all' || quickTypeFilter === 'transfer') {
            select.value = '';
        } else if (quickTypeFilter === 'adjustments') {
            select.value = 'adjustment';
        } else {
            select.value = quickTypeFilter;
        }
    }

    function filterRowsByQuickType(rows) {
        if (quickTypeFilter === 'all') return rows;
        if (quickTypeFilter === 'transfer') {
            return rows.filter((r) => TRANSFER_TYPES.includes(r.movement_type));
        }
        if (quickTypeFilter === 'adjustments') {
            return rows.filter((r) => ['adjustment', 'manual_edit'].includes(r.movement_type));
        }
        return rows.filter((r) => r.movement_type === quickTypeFilter);
    }

    function buildQuery() {
        const query = {};
        const search = $('historySearch')?.value.trim();
        const movementType = $('historyMovementType')?.value;
        const storeId = $('historyStore')?.value;
        const dateFrom = $('historyDateFrom')?.value;
        const dateTo = $('historyDateTo')?.value;

        if (search) query.q = search;
        if (movementType) query.type = movementType;
        if (storeId) query.store_id = storeId;
        if (dateFrom) query.date_from = dateFrom;
        if (dateTo) query.date_to = dateTo;
        return query;
    }

    async function fetchLedger() {
        try {
            const result = await AdminAPI.getInventoryLedger(buildQuery());
            if (result.status === 'success') {
                hideError();
                dataSource = result.source || 'inventory_ledger';
                showSourceNotice();
                return (result.data || []).map(normalizeRow);
            }
            showError(result.message || t('load_error'));
            return [];
        } catch (error) {
            console.error(error);
            showError(t('connection_error'));
            return [];
        }
    }

    function updateSummaryStats(rows) {
        setStatsLoading(false);
        const totalIn = rows.reduce((s, r) => s + (parseInt(r.stock_in, 10) || 0), 0);
        const totalOut = rows.reduce((s, r) => s + (parseInt(r.stock_out, 10) || 0), 0);
        const profit = rows.reduce((s, r) => s + (parseFloat(r.estimated_profit) || 0), 0);

        if ($('stat-entries')) $('stat-entries').textContent = String(rows.length);
        if ($('stat-total-in')) $('stat-total-in').textContent = totalIn.toLocaleString(locale);
        if ($('stat-total-out')) $('stat-total-out').textContent = totalOut.toLocaleString(locale);
        if ($('stat-profit')) $('stat-profit').textContent = AdminAPI.formatCurrency(profit);

        if ($('historyTotalEntries')) $('historyTotalEntries').textContent = t('entries_count', rows.length);
        if ($('historyTraceCount')) {
            $('historyTraceCount').textContent = t('trace_count', getTraceCount(rows));
        }
    }

    function getTraceCount(rows) {
        return rows.filter((row) => row.trace_id || row.notes).length;
    }

    function renderNoData() {
        const body = $('inventoryHistoryBody');
        const span = colSpan();
        if (body) {
            body.innerHTML = `
                <tr><td colspan="${span}" class="ad-empty-row">
                    <div class="ih-empty-state">
                        <span class="material-icons-round">history_toggle_off</span>
                        <p>${t('no_history')}</p>
                    </div>
                </td></tr>`;
        }
        updateSummaryStats([]);
        if ($('tableSummary')) $('tableSummary').textContent = t('no_history');
        if ($('pageInfo')) $('pageInfo').textContent = '1 / 1';
        if ($('pagePrev')) $('pagePrev').disabled = true;
        if ($('pageNext')) $('pageNext').disabled = true;
    }

    function traceSection(titleKey, fields) {
        const items = fields
            .map(([labelKey, value]) => `<dt>${escapeHtml(t(labelKey))}</dt><dd>${value}</dd>`)
            .join('');
        return `<section class="ih-trace-section"><h4>${escapeHtml(t(titleKey))}</h4><dl>${items}</dl></section>`;
    }

    function openTraceabilityModal(record) {
        const modalOverlay = $('traceabilityModalOverlay');
        const modalContent = $('traceabilityModalContent');
        if (!modalOverlay || !modalContent) return;

        modalContent.innerHTML = `
            <div class="ih-trace-grid">
                ${traceSection('trace_section_product', [
                    ['col_product', escapeHtml(record.product_name || '—')],
                    ['col_sku_barcode', escapeHtml(record.sku || record.barcode || '—')],
                    ['col_store', escapeHtml(record.store_name || CFG.storeName || '—')],
                    ['col_user', escapeHtml(record.user_name || '—')],
                ])}
                ${traceSection('trace_section_stock', [
                    ['modal_date', escapeHtml(formatDate(record.movement_date))],
                    ['col_type', escapeHtml(movementLabel(record.movement_type))],
                    ['col_opening_stock', escapeHtml(String(record.opening_stock ?? '—'))],
                    ['qty_in', escapeHtml(String(record.stock_in ?? 0))],
                    ['qty_out', escapeHtml(String(record.stock_out ?? 0))],
                    ['stock_after', escapeHtml(String(record.current_stock ?? '—'))],
                ])}
                ${traceSection('trace_section_financial', [
                    ['cost_price', escapeHtml(AdminAPI.formatCurrency(record.cost_price))],
                    ['sale_price_label', escapeHtml(AdminAPI.formatCurrency(record.sale_price))],
                    ['col_opening_value', escapeHtml(AdminAPI.formatCurrency(record.opening_value))],
                    ['col_out_value', escapeHtml(AdminAPI.formatCurrency(record.stock_out_value))],
                    ['col_current_value', escapeHtml(AdminAPI.formatCurrency(record.current_stock_value))],
                    ['col_estimated_profit', escapeHtml(AdminAPI.formatCurrency(record.estimated_profit))],
                ])}
                <section class="ih-trace-section full-width">
                    <h4>${escapeHtml(t('trace_section_audit'))}</h4>
                    <dl>
                        <dt>${escapeHtml(t('trace_ref'))}</dt>
                        <dd>${escapeHtml(record.trace_id || '—')}</dd>
                        <dt>${escapeHtml(t('col_notes'))}</dt>
                        <dd>${escapeHtml(record.notes || t('no_notes'))}</dd>
                    </dl>
                </section>
            </div>`;
        modalOverlay.classList.add('active');
    }

    function closeTraceabilityModal() {
        $('traceabilityModalOverlay')?.classList.remove('active');
    }

    function exportHistoryCsv() {
        if (!currentRows.length) {
            toast(t('no_history'), 'error');
            return;
        }

        const headers = [
            t('modal_date'), t('col_type'), t('col_product'), t('col_sku_barcode'),
            t('col_opening_stock'), t('col_stock_in'), t('col_stock_out'), t('col_current_stock'),
            t('col_opening_value'), t('col_out_value'), t('col_current_value'), t('col_estimated_profit'),
            t('col_user'), t('col_store'), t('trace_ref'), t('col_notes'),
        ];

        const csvEscape = (val) => {
            const str = String(val ?? '');
            if (/[",\n\r]/.test(str)) return `"${str.replace(/"/g, '""')}"`;
            return str;
        };

        const lines = [headers.map(csvEscape).join(',')];
        currentRows.forEach((row) => {
            lines.push([
                formatDate(row.movement_date),
                movementLabel(row.movement_type),
                row.product_name,
                row.sku || row.barcode || '',
                row.opening_stock,
                row.stock_in,
                row.stock_out,
                row.current_stock,
                row.opening_value,
                row.stock_out_value,
                row.current_stock_value,
                row.estimated_profit,
                row.user_name || '',
                row.store_name || '',
                row.trace_id || '',
                row.notes || '',
            ].map(csvEscape).join(','));
        });

        const blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const stamp = new Date().toISOString().slice(0, 10);
        link.href = url;
        link.download = `inventory-history-${stamp}.csv`;
        link.click();
        URL.revokeObjectURL(url);
        toast(t('export_success'));
    }

    async function populateStores() {
        const select = $('historyStore');
        if (!select || typeof AdminAPI?.listStores !== 'function') return;

        try {
            const response = await AdminAPI.listStores();
            const stores = Array.isArray(response.data) ? response.data : [];
            stores.forEach((store) => {
                const option = document.createElement('option');
                option.value = store.id;
                option.textContent = store.name || t('store_fallback', store.id);
                select.appendChild(option);
            });
            if (CFG.storeId) select.value = String(CFG.storeId);
        } catch (error) {
            console.error(error);
            toast(t('stores_load_error'), 'error');
        }
    }

    function renderRows(rows) {
        const body = $('inventoryHistoryBody');
        if (!body) return;

        updateSummaryStats(rows);

        if (!rows.length) {
            renderNoData();
            return;
        }

        const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * PAGE_SIZE;
        const pageItems = rows.slice(start, start + PAGE_SIZE);
        const compact = viewMode === 'compact';
        const lbl = columnLabels();

        if ($('tableSummary')) {
            $('tableSummary').textContent = t('history_table_summary', rows.length, currentPage, totalPages);
        }
        if ($('pageInfo')) $('pageInfo').textContent = `${currentPage} / ${totalPages}`;
        if ($('pagePrev')) $('pagePrev').disabled = currentPage <= 1;
        if ($('pageNext')) $('pageNext').disabled = currentPage >= totalPages;

        body.innerHTML = pageItems.map((row, index) => {
            const globalIndex = start + index;
            const highlighted = isRowHighlighted(row);
            const adjustBadge = highlighted
                ? `<span class="ih-adjust-badge"><span class="material-icons-round" style="font-size:12px;">edit</span>${escapeHtml(t('adjust_highlight_badge'))}</span>`
                : '';
            const hasTrace = row.trace_id || row.notes;
            const traceAction = hasTrace
                ? `<button type="button" class="inv-btn inv-btn-outline ih-trace-btn" data-trace-index="${globalIndex}" aria-label="${escapeHtml(t('view_trace'))}"><span class="material-icons-round" style="font-size:16px;">visibility</span></button>`
                : '—';
            const financialCols = compact ? '' : `
                    <td class="ih-col-financial" data-label="${escapeAttr(lbl.openingValue)}">${escapeHtml(AdminAPI.formatCurrency(row.opening_value))}</td>
                    <td class="ih-col-financial" data-label="${escapeAttr(lbl.outValue)}">${escapeHtml(AdminAPI.formatCurrency(row.stock_out_value))}</td>
                    <td class="ih-col-financial" data-label="${escapeAttr(lbl.currentValue)}">${escapeHtml(AdminAPI.formatCurrency(row.current_stock_value))}</td>
                    <td class="ih-col-financial" data-label="${escapeAttr(lbl.profit)}">${escapeHtml(AdminAPI.formatCurrency(row.estimated_profit))}</td>`;
            const notesCol = compact ? '' : `<td class="ih-col-notes ih-notes" data-label="${escapeAttr(lbl.notes)}">${escapeHtml(row.notes || '—')}</td>`;

            return `
                <tr class="ih-row-clickable${highlighted ? ' ih-row-adjust-highlight' : ''}" data-row-index="${globalIndex}" tabindex="0" role="button">
                    <td data-label="${escapeAttr(lbl.date)}">${escapeHtml(formatDate(row.movement_date))}</td>
                    <td data-label="${escapeAttr(lbl.type)}"><span class="ih-type-badge ih-type--${escapeHtml(row.movement_type)}">${escapeHtml(movementLabel(row.movement_type))}</span>${adjustBadge}</td>
                    <td data-label="${escapeAttr(lbl.product)}">${escapeHtml(row.product_name)}</td>
                    <td data-label="${escapeAttr(lbl.sku)}">${escapeHtml(row.sku || row.barcode || '—')}</td>
                    <td class="ih-col-opening" data-label="${escapeAttr(lbl.opening)}">${formatQty(row.opening_stock)}</td>
                    <td class="ih-qty-in" data-label="${escapeAttr(lbl.stockIn)}">${formatQty(row.stock_in)}</td>
                    <td class="ih-qty-out" data-label="${escapeAttr(lbl.stockOut)}">${formatQty(row.stock_out)}</td>
                    <td data-label="${escapeAttr(lbl.current)}">${formatQty(row.current_stock)}</td>
                    ${financialCols}
                    <td data-label="${escapeAttr(lbl.user)}">${escapeHtml(row.user_name || '—')}</td>
                    <td data-label="${escapeAttr(lbl.store)}">${escapeHtml(row.store_name || '—')}</td>
                    ${notesCol}
                    <td class="ih-col-actions" data-label="${escapeAttr(lbl.actions)}">${traceAction}</td>
                </tr>`;
        }).join('');

        body.querySelectorAll('tr.ih-row-clickable').forEach((tr) => {
            const rowIndex = Number(tr.getAttribute('data-row-index'));
            if (Number.isNaN(rowIndex) || !currentRows[rowIndex]) return;

            tr.addEventListener('click', (event) => {
                if (event.target.closest('.ih-trace-btn')) return;
                openTraceabilityModal(currentRows[rowIndex]);
            });
            tr.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openTraceabilityModal(currentRows[rowIndex]);
                }
            });
        });

        body.querySelectorAll('.ih-trace-btn').forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.stopPropagation();
                const rowIndex = Number(btn.getAttribute('data-trace-index'));
                if (!Number.isNaN(rowIndex) && currentRows[rowIndex]) {
                    openTraceabilityModal(currentRows[rowIndex]);
                }
            });
        });

        updateAdjustHighlightBanner();
        if (pageItems.some((row) => isRowHighlighted(row))) {
            requestAnimationFrame(scrollToFirstHighlight);
        }
    }

    async function renderHistoryTable() {
        const body = $('inventoryHistoryBody');
        const btn = $('refreshHistoryBtn');
        setStatsLoading(true);
        btn?.classList.add('spinning');
        if (body) {
            body.innerHTML = `<tr><td colspan="${colSpan()}" class="ad-empty-row">${t('loading_history')}</td></tr>`;
        }

        const fetched = await fetchLedger();
        currentRows = filterRowsByQuickType(fetched);
        lastFetchAt = new Date();
        updateLastUpdated();

        if (jumpToHighlightOnce && adjustHighlights.length) {
            const idx = currentRows.findIndex((row) => isRowHighlighted(row));
            currentPage = idx >= 0 ? Math.floor(idx / PAGE_SIZE) + 1 : 1;
            jumpToHighlightOnce = false;
        } else {
            currentPage = 1;
        }
        renderRows(currentRows);
        btn?.classList.remove('spinning');
    }

    function resetFilters() {
        if ($('historySearch')) $('historySearch').value = '';
        if ($('historyMovementType')) $('historyMovementType').value = '';
        if ($('historyStore')) $('historyStore').value = CFG.storeId ? String(CFG.storeId) : '';
        applyPeriodChip('all');
        applyQuickType('all');
        renderHistoryTable();
    }

    function attachEvents() {
        $('refreshHistoryBtn')?.addEventListener('click', renderHistoryTable);

        document.addEventListener('store-switched', () => {
            if ($('historyStore') && CFG.storeId) {
                $('historyStore').value = String(CFG.storeId);
            }
            renderHistoryTable();
        });

        $('exportHistoryBtn')?.addEventListener('click', exportHistoryCsv);
        $('applyHistoryFilters')?.addEventListener('click', () => {
            currentPage = 1;
            renderHistoryTable();
        });
        $('clearHistoryFilters')?.addEventListener('click', resetFilters);
        $('clearAdjustHighlight')?.addEventListener('click', clearAdjustHighlights);
        $('closeTraceabilityModalBtn')?.addEventListener('click', closeTraceabilityModal);
        $('traceabilityModalOverlay')?.addEventListener('click', (event) => {
            if (event.target === event.currentTarget) closeTraceabilityModal();
        });

        document.querySelectorAll('.ih-view-toggle button').forEach((btn) => {
            btn.addEventListener('click', () => {
                applyViewMode(btn.dataset.view);
                renderRows(currentRows);
            });
        });

        document.querySelectorAll('.ih-type-chips .inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                applyQuickType(chip.dataset.typeFilter || 'all');
                currentPage = 1;
                renderHistoryTable();
            });
        });

        $('historySearch')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                renderHistoryTable();
            }, 350);
        });

        ['historyMovementType', 'historyStore', 'historyDateFrom', 'historyDateTo'].forEach((id) => {
            $(id)?.addEventListener('change', () => {
                document.querySelectorAll('.ih-chips[data-chip-group="period"] .inv-chip').forEach((c) => c.classList.remove('active'));
                activePeriod = '';
                const val = $('historyMovementType')?.value || '';
                if (val === 'adjustment' || val === 'manual_edit') {
                    applyQuickType('adjustments');
                } else if (TRANSFER_TYPES.includes(val)) {
                    applyQuickType('transfer');
                } else if (val) {
                    applyQuickType(val);
                } else {
                    applyQuickType('all');
                }
            });
        });

        document.querySelectorAll('.ih-chips[data-chip-group="period"] .inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                applyPeriodChip(chip.dataset.period || 'all');
                currentPage = 1;
                renderHistoryTable();
            });
        });

        $('pagePrev')?.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderRows(currentRows);
            }
        });
        $('pageNext')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(currentRows.length / PAGE_SIZE));
            if (currentPage < totalPages) {
                currentPage++;
                renderRows(currentRows);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeTraceabilityModal();
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        adjustHighlights = loadAdjustHighlights();
        jumpToHighlightOnce = adjustHighlights.length > 0;
        applyViewMode(viewMode);
        applyQuickType('all');
        updateDateHeader();
        attachEvents();
        await populateStores();
        await renderHistoryTable();
    });
})();
