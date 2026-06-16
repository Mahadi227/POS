/**
 * Admin expired products — products with past expiry dates only
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
        refreshTimer = setTimeout(() => loadExpired(), 400);
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
            expiry: t('col_expiry'),
            product: t('col_product'),
            sku: t('col_sku'),
            quantity: t('col_quantity'),
            value: t('col_value'),
            status: t('col_category'),
            store: t('col_store'),
            notes: t('col_notes'),
            actions: t('col_actions'),
        };
    }

    function hasExpiryDate(row) {
        return Boolean(row.expiryDate && row.expiryDate !== '0000-00-00');
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.ep-stat').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function showError(msg) {
        const banner = $('expiredError');
        if (!banner) return;
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        banner.classList.add('is-visible');
    }

    function hideError() {
        $('expiredError')?.classList.remove('is-visible');
    }

    function updateDateHeader() {
        const header = $('expiredDate');
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

        document.querySelectorAll('.ep-chips .inv-chip').forEach((chip) => {
            chip.classList.toggle('active', chip.dataset.period === period);
        });

        if (period === 'all') {
            if ($('expiredDateFrom')) $('expiredDateFrom').value = '';
            if ($('expiredDateTo')) $('expiredDateTo').value = '';
            return;
        }

        if (period === 'today') {
            if ($('expiredDateFrom')) $('expiredDateFrom').value = toDateInput(today);
            if ($('expiredDateTo')) $('expiredDateTo').value = toDateInput(today);
            return;
        }

        const from = new Date(today);
        if (period === 'week') from.setDate(from.getDate() - 6);
        if (period === 'month') from.setDate(from.getDate() - 29);

        if ($('expiredDateFrom')) $('expiredDateFrom').value = toDateInput(from);
        if ($('expiredDateTo')) $('expiredDateTo').value = toDateInput(today);
    }

    function buildQuery() {
        const query = {};
        const search = $('expiredSearch')?.value.trim();
        const storeId = $('expiredStore')?.value;
        const dateFrom = $('expiredDateFrom')?.value;
        const dateTo = $('expiredDateTo')?.value;

        if (search) query.q = search;
        if (storeId) query.store_id = storeId;
        if (dateFrom) query.date_from = dateFrom;
        if (dateTo) query.date_to = dateTo;
        return query;
    }

    function formatExpiryBadge(row) {
        const days = row.daysUntilExpiry;
        if (days == null) return '';
        if (days > 0) {
            return `<span class="ep-badge ep-badge--info">${escapeHtml(t('days_until_expiry', String(days)))}</span>`;
        }
        if (days === 0) {
            return `<span class="ep-badge ep-badge--warn">${escapeHtml(t('expires_today'))}</span>`;
        }
        return `<span class="ep-badge ep-badge--danger">${escapeHtml(t('days_expired', String(Math.abs(days))))}</span>`;
    }

    function normalizeRow(row) {
        const qty = parseInt(row.stock_out ?? row.stock_quantity, 10) || 0;
        const value = parseFloat(row.stock_out_value ?? 0) || 0;
        const daysUntilExpiry = row.days_until_expiry != null ? parseInt(row.days_until_expiry, 10) : null;

        return {
            id: row.id,
            productId: row.product_id ?? null,
            expiryDate: row.expiry_date,
            product_name: row.product_name || '—',
            sku: row.sku || row.barcode || '—',
            quantity: qty,
            value,
            store_name: row.store_name || '—',
            category: row.category_name || row.notes || '—',
            notes: row.notes || '—',
            daysUntilExpiry,
            status: row.status || 'expired',
        };
    }

    function updateStats(rows) {
        setStatsLoading(false);
        const units = rows.reduce((s, r) => s + r.quantity, 0);
        const value = rows.reduce((s, r) => s + r.value, 0);
        const expiringSoon = rows.filter((r) => (r.daysUntilExpiry ?? -1) > 0 && (r.daysUntilExpiry ?? 999) <= 7).length;

        if ($('stat-products')) $('stat-products').textContent = String(rows.length);
        if ($('stat-units')) $('stat-units').textContent = units.toLocaleString(locale);
        if ($('stat-value')) $('stat-value').textContent = AdminAPI.formatCurrency(value);
        if ($('stat-expiring')) $('stat-expiring').textContent = String(expiringSoon);
    }

    function actionLinks(row) {
        if (!row.productId) return '—';
        const href = `inventory.php?edit=${encodeURIComponent(row.productId)}`;
        return `<a href="${escapeAttr(href)}" class="inv-btn inv-btn-outline ep-action-link" title="${escapeAttr(t('edit_product'))}">
            <span class="material-icons-round" style="font-size:16px;">edit</span>
        </a>`;
    }

    function renderRows(rows) {
        const body = $('expiredProductsBody');
        if (!body) return;

        updateStats(rows);

        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="9" class="ad-empty-row">${t('no_expired')}</td></tr>`;
            if ($('tableSummary')) $('tableSummary').textContent = t('no_expired');
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
            $('tableSummary').textContent = t('expired_table_summary', rows.length, currentPage, totalPages);
        }
        if ($('pageInfo')) $('pageInfo').textContent = `${currentPage} / ${totalPages}`;
        if ($('pagePrev')) $('pagePrev').disabled = currentPage <= 1;
        if ($('pageNext')) $('pageNext').disabled = currentPage >= totalPages;

        body.innerHTML = pageItems.map((row) => `
            <tr>
                <td data-label="${escapeAttr(lbl.expiry)}">
                    ${escapeHtml(AdminAPI.formatDate(row.expiryDate, { dateStyle: 'medium' }))}
                    <div class="ep-expiry-badge">${formatExpiryBadge(row)}</div>
                </td>
                <td data-label="${escapeAttr(lbl.product)}"><strong>${escapeHtml(row.product_name)}</strong></td>
                <td data-label="${escapeAttr(lbl.sku)}">${escapeHtml(row.sku)}</td>
                <td class="ep-qty" data-label="${escapeAttr(lbl.quantity)}">${escapeHtml(String(row.quantity))}</td>
                <td class="ep-value" data-label="${escapeAttr(lbl.value)}">${escapeHtml(AdminAPI.formatCurrency(row.value))}</td>
                <td data-label="${escapeAttr(lbl.status)}">${escapeHtml(row.category || '—')}</td>
                <td data-label="${escapeAttr(lbl.store)}">${escapeHtml(row.store_name)}</td>
                <td class="ih-notes" data-label="${escapeAttr(lbl.notes)}">${escapeHtml(row.notes)}</td>
                <td class="ep-actions-cell" data-label="${escapeAttr(lbl.actions)}">${actionLinks(row)}</td>
            </tr>
        `).join('');
    }

    async function loadExpired() {
        const body = $('expiredProductsBody');
        const btn = $('refreshExpired');
        setStatsLoading(true);
        btn?.classList.add('spinning');
        if (body) {
            body.innerHTML = `<tr><td colspan="9" class="ad-empty-row">${t('loading_expired')}</td></tr>`;
        }

        try {
            const result = await AdminAPI.getExpiredProducts(buildQuery());
            if (result.status !== 'success') {
                showError(result.message || t('load_error'));
                allRows = [];
            } else {
                hideError();
                allRows = (result.data || [])
                    .map(normalizeRow)
                    .filter((r) => hasExpiryDate(r) && (r.quantity > 0 || r.value > 0));
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
        const select = $('expiredStore');
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
        if ($('expiredSearch')) $('expiredSearch').value = '';
        if ($('expiredStore')) $('expiredStore').value = CFG.storeId ? String(CFG.storeId) : '';
        applyPeriodChip('all');
        loadExpired();
    }

    function bindEvents() {
        $('refreshExpired')?.addEventListener('click', loadExpired);

        document.addEventListener('store-switched', () => {
            if ($('expiredStore') && CFG.storeId) {
                $('expiredStore').value = String(CFG.storeId);
            }
            loadExpired();
        });

        window.addEventListener('storage', (e) => {
            if (e.key === 'pos-inventory-expired' || e.key === 'pos-inventory-updated') scheduleRefresh();
        });
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) loadExpired();
        });

        $('applyExpiredFilters')?.addEventListener('click', () => {
            currentPage = 1;
            loadExpired();
        });
        $('clearExpiredFilters')?.addEventListener('click', resetFilters);

        $('expiredSearch')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                loadExpired();
            }, 350);
        });

        ['expiredStore', 'expiredDateFrom', 'expiredDateTo'].forEach((id) => {
            $(id)?.addEventListener('change', () => {
                document.querySelectorAll('.ep-chips .inv-chip').forEach((c) => c.classList.remove('active'));
            });
        });

        document.querySelectorAll('.ep-chips .inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                applyPeriodChip(chip.dataset.period || 'all');
                currentPage = 1;
                loadExpired();
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
        await loadExpired();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
