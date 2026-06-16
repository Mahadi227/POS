/**
 * Admin stock movements — ledger + transfers, filters, pagination, i18n
 */
(() => {
    const CFG = window.INVENTORY_CONFIG || {};
    const i18n = window.INVENTORY_I18N || {};
    const locale = CFG.locale || (CFG.lang === 'fr' ? 'fr-FR' : 'en-US');
    const PAGE_SIZE = 20;

    const MOVEMENT_KEYS = {
        purchase: 'mov_purchase',
        sale: 'mov_sale',
        return: 'mov_return',
        transfer_in: 'mov_transfer_in',
        transfer_out: 'mov_transfer_out',
        adjustment: 'mov_adjustment',
        damaged: 'mov_damaged',
        expired: 'mov_expired',
        manual_edit: 'mov_manual_edit',
        transfer: 'type_transfer',
    };

    const STATUS_KEYS = {
        completed: 'status_completed',
        pending: 'status_pending',
        accepted: 'status_accepted',
        rejected: 'status_rejected',
    };

    const $ = (id) => document.getElementById(id);
    let allRows = [];
    let currentPage = 1;
    let debounceTimer = null;
    let lastFetchAt = null;

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
            product: t('col_product'),
            type: t('col_type'),
            quantity: t('col_quantity'),
            fromStore: t('col_from_store'),
            toStore: t('col_to_store'),
            user: t('col_user'),
            status: t('col_status'),
            notes: t('col_notes'),
        };
    }

    function movementLabel(type) {
        const key = MOVEMENT_KEYS[type];
        return key ? t(key) : (type || '—');
    }

    function statusLabel(status) {
        const key = STATUS_KEYS[status];
        return key ? t(key) : (status || '—');
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.ih-stat').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function showError(msg) {
        const banner = $('movementsError');
        if (!banner) return;
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        banner.classList.add('is-visible');
    }

    function hideError() {
        $('movementsError')?.classList.remove('is-visible');
    }

    function updateDateHeader() {
        const header = $('movementsDate');
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
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        document.querySelectorAll('.ih-chips .inv-chip').forEach((chip) => {
            chip.classList.toggle('active', chip.dataset.period === period);
        });

        if (period === 'all') {
            if ($('movementsDateFrom')) $('movementsDateFrom').value = '';
            if ($('movementsDateTo')) $('movementsDateTo').value = '';
            return;
        }

        const from = new Date(today);
        if (period === 'week') from.setDate(from.getDate() - 6);
        if (period === 'month') from.setDate(from.getDate() - 29);

        if ($('movementsDateFrom')) $('movementsDateFrom').value = toDateInput(from);
        if ($('movementsDateTo')) $('movementsDateTo').value = toDateInput(today);
    }

    function buildQuery() {
        const query = {};
        const search = $('movementsSearch')?.value.trim();
        const type = $('movementsType')?.value;
        const storeId = $('movementsStore')?.value;
        const status = $('movementsStatus')?.value;
        const dateFrom = $('movementsDateFrom')?.value;
        const dateTo = $('movementsDateTo')?.value;

        if (search) query.q = search;
        if (storeId) query.store_id = storeId;
        if (dateFrom) query.date_from = dateFrom;
        if (dateTo) query.date_to = dateTo;
        if (type && type !== 'transfer') query.type = type;
        if (status && status !== 'completed') query.status = status;
        return { query, typeFilter: type, statusFilter: status };
    }

    function normalizeLedgerRow(row) {
        const stockIn = parseInt(row.stock_in, 10) || 0;
        const stockOut = parseInt(row.stock_out, 10) || 0;
        const type = row.movement_type || 'adjustment';
        const store = row.store_name || '—';
        let fromStore = '—';
        let toStore = '—';

        if (type === 'transfer_in' || stockIn > 0) {
            toStore = store;
        }
        if (type === 'transfer_out' || type === 'sale' || stockOut > 0) {
            fromStore = store;
        }
        if (type === 'transfer_in') fromStore = '—';
        if (type === 'transfer_out') toStore = '—';

        return {
            id: `l-${row.id}`,
            sortDate: new Date(row.movement_date || 0).getTime(),
            date: row.movement_date,
            product_name: row.product_name,
            type,
            quantity: stockIn || stockOut,
            stock_in: stockIn,
            stock_out: stockOut,
            from_store: fromStore,
            to_store: toStore,
            user_name: row.user_name || '—',
            status: 'completed',
            notes: row.notes || '—',
        };
    }

    function normalizeTransferRow(row) {
        return {
            id: `sm-${row.id}`,
            sortDate: new Date(row.created_at || 0).getTime(),
            date: row.created_at,
            product_name: row.product_name,
            type: 'transfer',
            quantity: parseInt(row.quantity, 10) || 0,
            stock_in: 0,
            stock_out: 0,
            from_store: row.from_store || '—',
            to_store: row.to_store || '—',
            user_name: '—',
            status: row.status || 'pending',
            notes: '—',
        };
    }

    async function fetchAllMovements() {
        const { query, typeFilter, statusFilter } = buildQuery();
        const rows = [];

        const wantLedger = !typeFilter || typeFilter !== 'transfer';
        const wantTransfers = !typeFilter || typeFilter === 'transfer' || typeFilter === 'transfer_in' || typeFilter === 'transfer_out';

        if (wantLedger && (!statusFilter || statusFilter === 'completed')) {
            try {
                const ledgerQuery = { ...query };
                if (typeFilter === 'transfer_in' || typeFilter === 'transfer_out') {
                    ledgerQuery.type = typeFilter;
                }
                const result = await AdminAPI.getInventoryLedger(ledgerQuery);
                if (result.status === 'success') {
                    (result.data || []).forEach((row) => rows.push(normalizeLedgerRow(row)));
                }
            } catch (e) {
                console.error(e);
            }
        }

        if (wantTransfers && statusFilter !== 'completed') {
            try {
                const transferQuery = { ...query };
                if (statusFilter) transferQuery.status = statusFilter;
                const result = await AdminAPI.getInventoryMovements(transferQuery);
                if (result.status === 'success') {
                    (result.data || []).forEach((row) => rows.push(normalizeTransferRow(row)));
                }
            } catch (e) {
                console.error(e);
            }
        }

        if (!rows.length && wantLedger) {
            hideError();
        }

        rows.sort((a, b) => b.sortDate - a.sortDate);
        return rows;
    }

    function updateStats(rows) {
        setStatsLoading(false);
        const totalIn = rows.reduce((s, r) => s + (r.stock_in || 0), 0);
        const totalOut = rows.reduce((s, r) => s + (r.stock_out || 0), 0);
        const pending = rows.filter((r) => r.status === 'pending').length;

        if ($('stat-movements')) $('stat-movements').textContent = String(rows.length);
        if ($('stat-total-in')) $('stat-total-in').textContent = totalIn.toLocaleString(locale);
        if ($('stat-total-out')) $('stat-total-out').textContent = totalOut.toLocaleString(locale);
        if ($('stat-pending')) $('stat-pending').textContent = String(pending);
    }

    function statusBadge(status) {
        const cls = {
            completed: 'success',
            accepted: 'success',
            pending: 'warning',
            rejected: 'pending',
        }[status] || 'pending';
        return `<span class="status-badge ${cls}">${escapeHtml(statusLabel(status))}</span>`;
    }

    function renderRows(rows) {
        const body = $('stockMovementsBody');
        if (!body) return;

        updateStats(rows);

        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="9" class="ad-empty-row">${t('no_movements')}</td></tr>`;
            if ($('tableSummary')) $('tableSummary').textContent = t('no_movements');
            if ($('pageInfo')) $('pageInfo').textContent = '1 / 1';
            if ($('pagePrev')) $('pagePrev').disabled = true;
            if ($('pageNext')) $('pageNext').disabled = true;
            return;
        }

        const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * PAGE_SIZE;
        const pageItems = rows.slice(start, start + PAGE_SIZE);
        const lbl = columnLabels();

        if ($('tableSummary')) {
            $('tableSummary').textContent = t('movements_table_summary', rows.length, currentPage, totalPages);
        }
        if ($('pageInfo')) $('pageInfo').textContent = `${currentPage} / ${totalPages}`;
        if ($('pagePrev')) $('pagePrev').disabled = currentPage <= 1;
        if ($('pageNext')) $('pageNext').disabled = currentPage >= totalPages;

        body.innerHTML = pageItems.map((row) => `
            <tr>
                <td data-label="${escapeAttr(lbl.date)}">${escapeHtml(AdminAPI.formatDate(row.date))}</td>
                <td data-label="${escapeAttr(lbl.product)}"><strong>${escapeHtml(row.product_name)}</strong></td>
                <td data-label="${escapeAttr(lbl.type)}"><span class="ih-type-badge ih-type--${escapeHtml(row.type)}">${escapeHtml(movementLabel(row.type))}</span></td>
                <td data-label="${escapeAttr(lbl.quantity)}" style="font-weight:600;">${escapeHtml(row.quantity)}</td>
                <td data-label="${escapeAttr(lbl.fromStore)}">${escapeHtml(row.from_store)}</td>
                <td data-label="${escapeAttr(lbl.toStore)}">${escapeHtml(row.to_store)}</td>
                <td data-label="${escapeAttr(lbl.user)}">${escapeHtml(row.user_name)}</td>
                <td data-label="${escapeAttr(lbl.status)}">${statusBadge(row.status)}</td>
                <td class="ih-notes" data-label="${escapeAttr(lbl.notes)}">${escapeHtml(row.notes)}</td>
            </tr>
        `).join('');
    }

    async function loadMovements() {
        const body = $('stockMovementsBody');
        const btn = $('refreshMovementsBtn');
        setStatsLoading(true);
        btn?.classList.add('spinning');
        if (body) {
            body.innerHTML = `<tr><td colspan="9" class="ad-empty-row">${t('loading_movements')}</td></tr>`;
        }

        try {
            allRows = await fetchAllMovements();
            hideError();
            lastFetchAt = new Date();
            updateLastUpdated();
            currentPage = 1;
            renderRows(allRows);
        } catch (e) {
            console.error(e);
            showError(t('connection_error'));
            if (body) {
                body.innerHTML = `<tr><td colspan="9" class="ad-empty-row">${t('load_error')}</td></tr>`;
            }
        }

        btn?.classList.remove('spinning');
    }

    async function populateStores() {
        const select = $('movementsStore');
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
        }
    }

    function resetFilters() {
        if ($('movementsSearch')) $('movementsSearch').value = '';
        if ($('movementsType')) $('movementsType').value = '';
        if ($('movementsStatus')) $('movementsStatus').value = '';
        if ($('movementsStore')) $('movementsStore').value = CFG.storeId ? String(CFG.storeId) : '';
        applyPeriodChip('all');
        loadMovements();
    }

    function attachEvents() {
        $('refreshMovementsBtn')?.addEventListener('click', loadMovements);

        document.addEventListener('store-switched', () => {
            if ($('movementsStore') && CFG.storeId) {
                $('movementsStore').value = String(CFG.storeId);
            }
            loadMovements();
        });

        $('applyMovementsFilters')?.addEventListener('click', () => {
            currentPage = 1;
            loadMovements();
        });
        $('clearMovementsFilters')?.addEventListener('click', resetFilters);

        $('movementsSearch')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                loadMovements();
            }, 350);
        });

        ['movementsType', 'movementsStore', 'movementsStatus', 'movementsDateFrom', 'movementsDateTo'].forEach((id) => {
            $(id)?.addEventListener('change', () => {
                document.querySelectorAll('.ih-chips .inv-chip').forEach((c) => c.classList.remove('active'));
            });
        });

        document.querySelectorAll('.ih-chips .inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                applyPeriodChip(chip.dataset.period || 'all');
                currentPage = 1;
                loadMovements();
            });
        });

        $('pagePrev')?.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderRows(allRows);
            }
        });
        $('pageNext')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(allRows.length / PAGE_SIZE));
            if (currentPage < totalPages) {
                currentPage++;
                renderRows(allRows);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        updateDateHeader();
        attachEvents();
        await populateStores();
        loadMovements();
    });
})();
