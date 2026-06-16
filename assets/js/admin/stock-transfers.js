/**
 * Admin stock transfers — list, create, accept/reject, i18n
 */
(() => {
    const CFG = window.INVENTORY_CONFIG || {};
    const i18n = window.INVENTORY_I18N || {};
    const locale = CFG.locale || (CFG.lang === 'fr' ? 'fr-FR' : 'en-US');
    const PAGE_SIZE = 20;

    const STATUS_KEYS = {
        pending: 'status_pending',
        accepted: 'status_accepted',
        rejected: 'status_rejected',
    };

    const $ = (id) => document.getElementById(id);
    let allRows = [];
    let stores = [];
    let currentPage = 1;
    let activeStatus = '';
    let debounceTimer = null;
    let productDebounce = null;
    let lastFetchAt = null;
    let selectedProduct = null;
    let productResults = [];

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
            fromStore: t('col_from_store'),
            toStore: t('col_to_store'),
            status: t('col_status'),
            actions: t('actions'),
        };
    }

    function statusLabel(status) {
        const key = STATUS_KEYS[status];
        return key ? t(key) : (status || '—');
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
        document.querySelectorAll('.ih-stat').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function showError(msg) {
        const banner = $('transfersError');
        if (!banner) return;
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        banner.classList.add('is-visible');
    }

    function hideError() {
        $('transfersError')?.classList.remove('is-visible');
    }

    function updateDateHeader() {
        const header = $('transfersDate');
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

    function buildQuery() {
        const query = {};
        const search = $('transfersSearch')?.value.trim();
        const fromStore = $('transfersFromStore')?.value;
        const toStore = $('transfersToStore')?.value;

        if (search) query.q = search;
        if (fromStore) query.from_store = fromStore;
        if (toStore) query.to_store = toStore;
        if (activeStatus) query.status = activeStatus;
        return query;
    }

    function statusBadge(status) {
        const cls = {
            accepted: 'success',
            pending: 'warning',
            rejected: 'pending',
        }[status] || 'pending';
        return `<span class="status-badge ${cls}">${escapeHtml(statusLabel(status))}</span>`;
    }

    function renderActionButtons(row) {
        if (row.status !== 'pending' || !row.can_accept) {
            return '—';
        }
        return `
            <div class="st-row-actions">
                <button type="button" class="inv-btn inv-btn-primary st-accept-btn" data-id="${row.id}">
                    ${escapeHtml(t('accept_transfer'))}
                </button>
                <button type="button" class="inv-btn inv-btn-outline st-reject-btn" data-id="${row.id}">
                    ${escapeHtml(t('reject_transfer'))}
                </button>
            </div>
        `;
    }

    function renderRows(rows) {
        const body = $('stockTransfersBody');
        if (!body) return;

        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="8" class="ad-empty-row">${t('no_transfers')}</td></tr>`;
            if ($('tableSummary')) $('tableSummary').textContent = t('no_transfers');
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
            $('tableSummary').textContent = t('transfers_table_summary', rows.length, currentPage, totalPages);
        }
        if ($('pageInfo')) $('pageInfo').textContent = `${currentPage} / ${totalPages}`;
        if ($('pagePrev')) $('pagePrev').disabled = currentPage <= 1;
        if ($('pageNext')) $('pageNext').disabled = currentPage >= totalPages;

        body.innerHTML = pageItems.map((row) => `
            <tr>
                <td data-label="${escapeAttr(lbl.date)}">${escapeHtml(AdminAPI.formatDate(row.created_at))}</td>
                <td data-label="${escapeAttr(lbl.product)}"><strong>${escapeHtml(row.product_name)}</strong></td>
                <td data-label="${escapeAttr(lbl.sku)}">${escapeHtml(row.sku || '—')}</td>
                <td data-label="${escapeAttr(lbl.quantity)}" style="font-weight:600;">${escapeHtml(String(row.quantity))}</td>
                <td data-label="${escapeAttr(lbl.fromStore)}">${escapeHtml(row.from_store_name || '—')}</td>
                <td data-label="${escapeAttr(lbl.toStore)}">${escapeHtml(row.to_store_name || '—')}</td>
                <td data-label="${escapeAttr(lbl.status)}">${statusBadge(row.status)}</td>
                <td class="st-actions-cell" data-label="${escapeAttr(lbl.actions)}">${renderActionButtons(row)}</td>
            </tr>
        `).join('');

        body.querySelectorAll('.st-accept-btn').forEach((btn) => {
            btn.addEventListener('click', () => handleAccept(btn));
        });
        body.querySelectorAll('.st-reject-btn').forEach((btn) => {
            btn.addEventListener('click', () => handleReject(btn));
        });
    }

    async function loadStats() {
        try {
            const result = await AdminAPI.getTransferStats();
            if (result.status !== 'success') return;
            const stats = result.data || {};
            setStatsLoading(false);
            if ($('stat-pending')) $('stat-pending').textContent = String(stats.pending ?? 0);
            if ($('stat-accepted')) $('stat-accepted').textContent = String(stats.accepted ?? 0);
            if ($('stat-rejected')) $('stat-rejected').textContent = String(stats.rejected ?? 0);
            if ($('stat-pending-units')) {
                $('stat-pending-units').textContent = (stats.pending_units ?? 0).toLocaleString(locale);
            }
        } catch (e) {
            console.error(e);
            setStatsLoading(false);
        }
    }

    async function loadTransfers() {
        const body = $('stockTransfersBody');
        const btn = $('refreshTransfersBtn');
        setStatsLoading(true);
        btn?.classList.add('spinning');
        if (body) {
            body.innerHTML = `<tr><td colspan="8" class="ad-empty-row">${t('loading_transfers')}</td></tr>`;
        }

        try {
            const result = await AdminAPI.listTransfers(buildQuery());
            if (result.status !== 'success') {
                showError(result.message || t('load_error'));
                allRows = [];
            } else {
                hideError();
                allRows = result.data || [];
            }
            lastFetchAt = new Date();
            updateLastUpdated();
            renderRows(allRows);
            await loadStats();
        } catch (e) {
            console.error(e);
            showError(t('connection_error'));
            if (body) {
                body.innerHTML = `<tr><td colspan="8" class="ad-empty-row">${t('load_error')}</td></tr>`;
            }
            setStatsLoading(false);
        } finally {
            btn?.classList.remove('spinning');
        }
    }

    function fillStoreSelects() {
        const filterFrom = $('transfersFromStore');
        const filterTo = $('transfersToStore');
        const modalFrom = $('transferFromStore');
        const modalTo = $('transferToStore');

        [filterFrom, filterTo, modalFrom, modalTo].forEach((sel) => {
            if (!sel) return;
            const isFilter = sel === filterFrom || sel === filterTo;
            const placeholder = sel === filterFrom || sel === modalFrom
                ? t('all_from_stores')
                : (isFilter ? t('all_to_stores') : '');
            const current = sel.value;
            sel.innerHTML = isFilter
                ? `<option value="">${escapeHtml(placeholder)}</option>`
                : '';
            stores.forEach((store) => {
                const opt = document.createElement('option');
                opt.value = String(store.id);
                opt.textContent = store.name || t('store_fallback', store.id);
                sel.appendChild(opt);
            });
            if (current && [...sel.options].some((o) => o.value === current)) {
                sel.value = current;
            }
        });

        if (modalFrom && CFG.storeId) {
            modalFrom.value = String(CFG.storeId);
        }
    }

    async function loadStores() {
        try {
            const result = await AdminAPI.listStores();
            if (result.status === 'success') {
                stores = result.data || [];
                fillStoreSelects();
            }
        } catch (e) {
            console.error(e);
            toast(t('stores_load_error'), 'error');
        }
    }

    function applyStatusChip(status) {
        activeStatus = status;
        document.querySelectorAll('.st-chips .inv-chip').forEach((chip) => {
            chip.classList.toggle('active', chip.dataset.status === status);
        });
        currentPage = 1;
        loadTransfers();
    }

    function clearFilters() {
        if ($('transfersSearch')) $('transfersSearch').value = '';
        if ($('transfersFromStore')) $('transfersFromStore').value = '';
        if ($('transfersToStore')) $('transfersToStore').value = '';
        activeStatus = '';
        document.querySelectorAll('.st-chips .inv-chip').forEach((chip) => {
            chip.classList.toggle('active', chip.dataset.status === '');
        });
        currentPage = 1;
        loadTransfers();
    }

    function openCreateModal() {
        selectedProduct = null;
        productResults = [];
        if ($('transferProductId')) $('transferProductId').value = '';
        if ($('transferProductSearch')) $('transferProductSearch').value = '';
        if ($('transferQuantity')) $('transferQuantity').value = '';
        if ($('transferStockHint')) $('transferStockHint').textContent = '';
        fillStoreSelects();
        renderProductList();
        searchProducts();
        $('createTransferOverlay')?.classList.add('active');
    }

    function closeCreateModal() {
        $('createTransferOverlay')?.classList.remove('active');
    }

    function renderProductList() {
        const list = $('transferProductList');
        if (!list) return;

        const fromId = $('transferFromStore')?.value;
        if (!fromId) {
            list.innerHTML = `<div class="st-empty-products">${t('select_from_store_first')}</div>`;
            return;
        }

        if (!productResults.length) {
            list.innerHTML = `<div class="st-empty-products">${t('loading')}</div>`;
            return;
        }

        list.innerHTML = productResults.map((p) => {
            const selected = selectedProduct && selectedProduct.id === p.id;
            return `
                <div class="st-product-item${selected ? ' is-selected' : ''}" data-id="${p.id}" data-stock="${p.stock_quantity}">
                    <div>
                        <strong>${escapeHtml(p.name)}</strong>
                        <div class="st-product-meta">${escapeHtml(p.sku || '—')} · ${t('available_stock', p.stock_quantity)}</div>
                    </div>
                </div>
            `;
        }).join('');

        list.querySelectorAll('.st-product-item').forEach((item) => {
            item.addEventListener('click', () => {
                const id = parseInt(item.dataset.id, 10);
                selectedProduct = productResults.find((p) => p.id === id) || null;
                if ($('transferProductId')) {
                    $('transferProductId').value = selectedProduct ? String(selectedProduct.id) : '';
                }
                updateStockHint();
                renderProductList();
            });
        });
    }

    function updateStockHint() {
        const hint = $('transferStockHint');
        if (!hint) return;
        if (!selectedProduct) {
            hint.textContent = '';
            return;
        }
        hint.textContent = t('available_stock', selectedProduct.stock_quantity);
    }

    async function searchProducts() {
        const fromId = $('transferFromStore')?.value;
        const q = $('transferProductSearch')?.value.trim() || '';
        const list = $('transferProductList');

        if (!fromId) {
            if (list) list.innerHTML = `<div class="st-empty-products">${t('select_from_store_first')}</div>`;
            return;
        }

        try {
            const result = await AdminAPI.getTransferProducts(fromId, q);
            productResults = result.status === 'success' ? (result.data || []) : [];
            if (!productResults.length) {
                if (list) list.innerHTML = `<div class="st-empty-products">${t('no_products_found')}</div>`;
                return;
            }
            renderProductList();
        } catch (e) {
            console.error(e);
            if (list) list.innerHTML = `<div class="st-empty-products">${t('load_error')}</div>`;
        }
    }

    async function submitCreateTransfer(e) {
        e.preventDefault();

        const fromId = parseInt($('transferFromStore')?.value, 10);
        const toId = parseInt($('transferToStore')?.value, 10);
        const productId = parseInt($('transferProductId')?.value, 10);
        const qty = parseInt($('transferQuantity')?.value, 10);

        if (!fromId || !toId) {
            toast(t('stores_must_differ'), 'error');
            return;
        }
        if (fromId === toId) {
            toast(t('stores_must_differ'), 'error');
            return;
        }
        if (!productId) {
            toast(t('select_product_required'), 'error');
            return;
        }
        if (!qty || qty <= 0) {
            toast(t('quantity_required'), 'error');
            return;
        }
        if (selectedProduct && qty > selectedProduct.stock_quantity) {
            toast(t('insufficient_stock'), 'error');
            return;
        }

        const btn = $('submitCreateTransfer');
        btn?.setAttribute('disabled', 'disabled');

        try {
            const result = await AdminAPI.createTransfer({
                from_store_id: fromId,
                to_store_id: toId,
                product_id: productId,
                quantity: qty,
            });
            if (result.status === 'success') {
                toast(result.message || t('transfer_created'));
                closeCreateModal();
                currentPage = 1;
                await loadTransfers();
            } else {
                const msg = result.message || t('error');
                toast(msg.includes('insuffisant') || msg.includes('Insufficient') ? t('insufficient_stock') : msg, 'error');
            }
        } catch (err) {
            console.error(err);
            toast(t('connection_error'), 'error');
        } finally {
            btn?.removeAttribute('disabled');
        }
    }

    async function handleAccept(btn) {
        const id = parseInt(btn.dataset.id, 10);
        const row = allRows.find((r) => r.id === id);
        if (row && !window.confirm(t('confirm_accept', row.quantity, row.product_name, row.to_store_name))) {
            return;
        }

        btn.disabled = true;
        try {
            const result = await AdminAPI.updateTransfer(id, { action: 'accept' });
            if (result.status === 'success') {
                toast(result.message || t('transfer_accepted'));
                await loadTransfers();
            } else {
                toast(result.message || t('error'), 'error');
                btn.disabled = false;
            }
        } catch (e) {
            console.error(e);
            toast(t('connection_error'), 'error');
            btn.disabled = false;
        }
    }

    async function handleReject(btn) {
        const id = parseInt(btn.dataset.id, 10);
        if (!window.confirm(t('confirm_reject'))) return;

        btn.disabled = true;
        try {
            const result = await AdminAPI.updateTransfer(id, { action: 'reject' });
            if (result.status === 'success') {
                toast(result.message || t('transfer_rejected'));
                await loadTransfers();
            } else {
                toast(result.message || t('error'), 'error');
                btn.disabled = false;
            }
        } catch (e) {
            console.error(e);
            toast(t('connection_error'), 'error');
            btn.disabled = false;
        }
    }

    function bindEvents() {
        $('refreshTransfersBtn')?.addEventListener('click', loadTransfers);

        document.addEventListener('store-switched', () => loadTransfers());

        $('applyTransfersFilters')?.addEventListener('click', () => {
            currentPage = 1;
            loadTransfers();
        });
        $('clearTransfersFilters')?.addEventListener('click', clearFilters);

        $('transfersSearch')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                currentPage = 1;
                loadTransfers();
            }, 350);
        });

        document.querySelectorAll('.st-chips .inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => applyStatusChip(chip.dataset.status || ''));
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

        $('newTransferBtn')?.addEventListener('click', openCreateModal);
        $('closeCreateTransfer')?.addEventListener('click', closeCreateModal);
        $('cancelCreateTransfer')?.addEventListener('click', closeCreateModal);
        $('createTransferOverlay')?.addEventListener('click', (e) => {
            if (e.target === $('createTransferOverlay')) closeCreateModal();
        });
        $('createTransferForm')?.addEventListener('submit', submitCreateTransfer);

        $('transferFromStore')?.addEventListener('change', () => {
            selectedProduct = null;
            if ($('transferProductId')) $('transferProductId').value = '';
            if ($('transferStockHint')) $('transferStockHint').textContent = '';
            searchProducts();
        });

        $('transferProductSearch')?.addEventListener('input', () => {
            clearTimeout(productDebounce);
            productDebounce = setTimeout(searchProducts, 300);
        });
    }

    async function init() {
        updateDateHeader();
        bindEvents();
        await loadStores();
        await loadTransfers();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
