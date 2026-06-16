/**
 * Admin damaged products — ledger audit (movement type: damaged)
 */
(() => {
    const CFG = window.INVENTORY_CONFIG || {};
    const i18n = window.INVENTORY_I18N || {};
    const locale = CFG.locale || (CFG.lang === 'fr' ? 'fr-FR' : 'en-US');
    const PAGE_SIZE = 20;

    const $ = (id) => document.getElementById(id);
    let allRows = [];
    let currentPage = 1;
    let activePeriod = 'all';
    let debounceTimer = null;
    let lastFetchAt = null;
    let refreshTimer = null;

    function scheduleRefresh() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(() => loadDamaged(), 400);
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
            product: t('col_product'),
            sku: t('col_sku'),
            quantity: t('col_quantity'),
            value: t('col_value'),
            user: t('col_user'),
            store: t('col_store'),
            notes: t('col_notes'),
            actions: t('col_actions'),
        };
    }

    function toast(msg, type = 'success') {
        const el = $('invToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast show ${type === 'error' ? 'error' : ''}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.dp-stat').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function showError(msg) {
        const banner = $('damagedError');
        if (!banner) return;
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        banner.classList.add('is-visible');
    }

    function hideError() {
        $('damagedError')?.classList.remove('is-visible');
    }

    function updateDateHeader() {
        const header = $('damagedDate');
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

        document.querySelectorAll('.dp-chips .inv-chip').forEach((chip) => {
            chip.classList.toggle('active', chip.dataset.period === period);
        });

        if (period === 'all') {
            if ($('damagedDateFrom')) $('damagedDateFrom').value = '';
            if ($('damagedDateTo')) $('damagedDateTo').value = '';
            return;
        }

        const from = new Date(today);
        if (period === 'week') from.setDate(from.getDate() - 6);
        if (period === 'month') from.setDate(from.getDate() - 29);

        if ($('damagedDateFrom')) $('damagedDateFrom').value = toDateInput(from);
        if ($('damagedDateTo')) $('damagedDateTo').value = toDateInput(today);
    }

    function buildQuery() {
        const query = { type: 'damaged' };
        const search = $('damagedSearch')?.value.trim();
        const storeId = $('damagedStore')?.value;
        const dateFrom = $('damagedDateFrom')?.value;
        const dateTo = $('damagedDateTo')?.value;

        if (search) query.q = search;
        if (storeId) query.store_id = storeId;
        if (dateFrom) query.date_from = dateFrom;
        if (dateTo) query.date_to = dateTo;
        return query;
    }

    function normalizeRow(row) {
        const qty = parseInt(row.stock_out, 10) || 0;
        const value = parseFloat(row.stock_out_value ?? row.out_value ?? 0) || 0;
        return {
            id: row.id,
            logId: row.reference_id ?? row.trace_id ?? null,
            productId: row.product_id ?? null,
            date: row.movement_date,
            product_name: row.product_name || '—',
            sku: row.sku || row.barcode || '—',
            quantity: qty,
            value,
            user_name: row.user_name || '—',
            store_name: row.store_name || '—',
            notes: row.notes || '—',
        };
    }

    function updateStats(rows) {
        setStatsLoading(false);
        const units = rows.reduce((s, r) => s + r.quantity, 0);
        const value = rows.reduce((s, r) => s + r.value, 0);
        const uniqueProducts = new Set(rows.map((r) => r.product_name)).size;

        if ($('stat-entries')) $('stat-entries').textContent = String(rows.length);
        if ($('stat-units')) $('stat-units').textContent = units.toLocaleString(locale);
        if ($('stat-value')) $('stat-value').textContent = AdminAPI.formatCurrency(value);
        if ($('stat-unique')) $('stat-unique').textContent = String(uniqueProducts);
    }

    function historyLink(row) {
        if (!row.logId && !row.productId) return '—';
        const params = new URLSearchParams();
        if (row.logId) params.set('highlight_log', row.logId);
        if (row.productId) params.set('highlight_product', row.productId);
        params.set('type', 'damaged');
        const href = `inventory_history.php?${params.toString()}`;
        return `<a href="${escapeAttr(href)}" class="inv-btn inv-btn-outline dp-history-link" title="${escapeAttr(t('view_history'))}">
            <span class="material-icons-round" style="font-size:16px;">history</span>
        </a>`;
    }

    function renderRows(rows) {
        const body = $('damagedProductsBody');
        if (!body) return;

        updateStats(rows);

        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="9" class="ad-empty-row">${t('no_damaged')}</td></tr>`;
            if ($('tableSummary')) $('tableSummary').textContent = t('no_damaged');
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
            $('tableSummary').textContent = t('damaged_table_summary', rows.length, currentPage, totalPages);
        }
        if ($('pageInfo')) $('pageInfo').textContent = `${currentPage} / ${totalPages}`;
        if ($('pagePrev')) $('pagePrev').disabled = currentPage <= 1;
        if ($('pageNext')) $('pageNext').disabled = currentPage >= totalPages;

        body.innerHTML = pageItems.map((row) => `
            <tr>
                <td data-label="${escapeAttr(lbl.date)}">${escapeHtml(AdminAPI.formatDate(row.date))}</td>
                <td data-label="${escapeAttr(lbl.product)}"><strong>${escapeHtml(row.product_name)}</strong></td>
                <td data-label="${escapeAttr(lbl.sku)}">${escapeHtml(row.sku)}</td>
                <td class="dp-qty-out" data-label="${escapeAttr(lbl.quantity)}">${escapeHtml(String(row.quantity))}</td>
                <td class="dp-value-lost" data-label="${escapeAttr(lbl.value)}">${escapeHtml(AdminAPI.formatCurrency(row.value))}</td>
                <td data-label="${escapeAttr(lbl.user)}">${escapeHtml(row.user_name)}</td>
                <td data-label="${escapeAttr(lbl.store)}">${escapeHtml(row.store_name)}</td>
                <td class="ih-notes" data-label="${escapeAttr(lbl.notes)}">${escapeHtml(row.notes)}</td>
                <td class="dp-actions-cell" data-label="${escapeAttr(lbl.actions)}">${historyLink(row)}</td>
            </tr>
        `).join('');
    }

    async function loadDamaged() {
        const body = $('damagedProductsBody');
        const btn = $('refreshDamaged');
        setStatsLoading(true);
        btn?.classList.add('spinning');
        if (body) {
            body.innerHTML = `<tr><td colspan="9" class="ad-empty-row">${t('loading_damaged')}</td></tr>`;
        }

        try {
            const result = await AdminAPI.getInventoryLedger(buildQuery());
            if (result.status !== 'success') {
                showError(result.message || t('load_error'));
                allRows = [];
            } else {
                hideError();
                allRows = (result.data || [])
                    .map(normalizeRow)
                    .filter((r) => r.quantity > 0 || r.value > 0);
            }
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
            setStatsLoading(false);
        } finally {
            btn?.classList.remove('spinning');
        }
    }

    async function populateStores() {
        const select = $('damagedStore');
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
        if ($('damagedSearch')) $('damagedSearch').value = '';
        if ($('damagedStore')) $('damagedStore').value = CFG.storeId ? String(CFG.storeId) : '';
        applyPeriodChip('all');
        loadDamaged();
    }

    function bindEvents() {
        $('refreshDamaged')?.addEventListener('click', loadDamaged);

        document.addEventListener('store-switched', () => {
            if ($('damagedStore') && CFG.storeId) {
                $('damagedStore').value = String(CFG.storeId);
            }
            loadDamaged();
        });

        window.addEventListener('storage', (e) => {
            if (e.key === 'pos-inventory-damaged') scheduleRefresh();
        });
        window.addEventListener('inventory-damaged', scheduleRefresh);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) loadDamaged();
        });

        $('applyDamagedFilters')?.addEventListener('click', () => {
            currentPage = 1;
            loadDamaged();
        });
        $('clearDamagedFilters')?.addEventListener('click', resetFilters);

        $('damagedSearch')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                loadDamaged();
            }, 350);
        });

        ['damagedStore', 'damagedDateFrom', 'damagedDateTo'].forEach((id) => {
            $(id)?.addEventListener('change', () => {
                document.querySelectorAll('.dp-chips .inv-chip').forEach((c) => c.classList.remove('active'));
            });
        });

        document.querySelectorAll('.dp-chips .inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                applyPeriodChip(chip.dataset.period || 'all');
                currentPage = 1;
                loadDamaged();
            });
        });

        $('pagePrev')?.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage -= 1;
                renderRows(allRows);
            }
        });
        $('pageNext')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(allRows.length / PAGE_SIZE));
            if (currentPage < totalPages) {
                currentPage += 1;
                renderRows(allRows);
            }
        });
    }

    async function init() {
        updateDateHeader();
        bindEvents();
        await populateStores();
        applyPeriodChip('all');
        await loadDamaged();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
