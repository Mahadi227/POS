/**
 * Branch transfers — store-to-store stock movements (branch_to_branch)
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whBtrTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WarehouseUI;
    const canCreate = !!window.WH_PAGE?.canTransfer && !window.WH_PAGE?.readOnly;
    const canManage = !!window.WH_PAGE?.canManage && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
        requested: 'wms_status_requested',
        approved: 'wms_status_approved',
        picking: 'wms_status_picking',
        in_transit: 'wms_status_in_transit',
        received: 'wms_status_received',
        completed: 'wms_status_completed',
        rejected: 'wms_status_rejected',
        cancelled: 'wms_status_cancelled',
    };
    const STATUS_ORDER = ['requested', 'approved', 'picking', 'in_transit', 'received', 'completed', 'rejected', 'cancelled'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        stores: [],
        products: [],
        lineIndex: 0,
        searchTimer: null,
    };

    const els = {
        search: document.getElementById('whBtrSearch'),
        store: document.getElementById('whBtrStore'),
        status: document.getElementById('whBtrStatus'),
        refresh: document.getElementById('whBtrRefreshBtn'),
        exportBtn: document.getElementById('whBtrExportBtn'),
        newBtn: document.getElementById('whBtrNewBtn'),
        heroMeta: document.getElementById('whBtrHeroMeta'),
        breakdownPanel: document.getElementById('whBtrBreakdownPanel'),
        statusChips: document.getElementById('whBtrStatusChips'),
        statTotal: document.getElementById('whBtrStatTotal'),
        statRequested: document.getElementById('whBtrStatRequested'),
        statProgress: document.getElementById('whBtrStatProgress'),
        statCompleted: document.getElementById('whBtrStatCompleted'),
        loading: document.getElementById('whBtrLoading'),
        empty: document.getElementById('whBtrEmpty'),
        pagination: document.getElementById('whBtrPagination'),
        prev: document.getElementById('whBtrPrev'),
        next: document.getElementById('whBtrNext'),
        pageMeta: document.getElementById('whBtrPageMeta'),
        createModal: document.getElementById('whBtrCreateModal'),
        createClose: document.getElementById('whBtrCreateClose'),
        createCancel: document.getElementById('whBtrCreateCancel'),
        createForm: document.getElementById('whBtrCreateForm'),
        fromStore: document.getElementById('whBtrFromStore'),
        toStore: document.getElementById('whBtrToStore'),
        addLine: document.getElementById('whBtrAddLine'),
        productFilter: document.getElementById('whBtrProductFilter'),
        lineItems: document.getElementById('whBtrLineItems'),
        linesEmpty: document.getElementById('whBtrLinesEmpty'),
        lineCount: document.getElementById('whBtrLineCount'),
        estTotal: document.getElementById('whBtrEstTotal'),
        formError: document.getElementById('whBtrFormError'),
        detailModal: document.getElementById('whBtrDetailModal'),
        detailClose: document.getElementById('whBtrDetailClose'),
        detailTitle: document.getElementById('whBtrDetailTitle'),
        detailSubtitle: document.getElementById('whBtrDetailSubtitle'),
        detailBody: document.getElementById('whBtrDetailBody'),
        toast: document.getElementById('whBtrToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-btr-toast show${type === 'error' ? ' wh-btr-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'completed' ? 'ok' : (status === 'rejected' || status === 'cancelled' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function storeLabel(row, dir) {
        return dir === 'from' ? (row.from_store_name || '—') : (row.to_store_name || '—');
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return AdminAPI.formatDate(iso, { dateStyle: 'short', timeStyle: 'short' });
        } catch {
            return iso;
        }
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-btr-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statRequested) els.statRequested.textContent = String(s.requested ?? 0);
        if (els.statProgress) els.statProgress.textContent = String(s.in_progress ?? 0);
        if (els.statCompleted) els.statCompleted.textContent = String(s.completed ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_btr_hero_meta', s.requested ?? 0, s.in_progress ?? 0);
        }
        setStatsLoading(false);
    }

    function chipStatusFor(status) {
        if (status === 'requested') return 'btr_pending';
        return status;
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const list = (items || []).filter((r) => STATUS_ORDER.includes(r.status) && Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const sorted = [...list].sort((a, b) => {
            const ai = STATUS_ORDER.indexOf(a.status);
            const bi = STATUS_ORDER.indexOf(b.status);
            return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
        });
        const activeStatus = els.status?.value || 'btr_active';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const chipStatus = chipStatusFor(r.status);
            const isActive = activeStatus === r.status || activeStatus === chipStatus;
            return `<button type="button" class="wh-btr-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(chipStatus)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-btr-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.status) els.status.value = btn.dataset.status || 'btr_active';
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            transfer_type: 'branch_to_branch',
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
        };
        const store = els.store?.value?.trim();
        if (store) params.store_id = store;
        const status = els.status?.value?.trim();
        if (status) params.status = status;
        const q = els.search?.value?.trim();
        if (q) params.q = q;
        return params;
    }

    function renderTable(items) {
        if (!items.length) {
            tableWrap.innerHTML = '';
            tableWrap.hidden = true;
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        tableWrap.hidden = false;
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-btr-table">
<thead><tr>
    <th>${esc(t('wms_col_transfer'))}</th>
    <th>${esc(t('wms_col_from'))}</th>
    <th>${esc(t('wms_col_to'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r) => {
    const canApproveRow = canManage && r.status === 'requested';
    const canCompleteRow = canManage && r.status === 'approved';
    const canRejectRow = canManage && r.status === 'requested';
    return `<tr>
        <td><strong>${esc(r.transfer_number)}</strong></td>
        <td>${esc(storeLabel(r, 'from'))}</td>
        <td>${esc(storeLabel(r, 'to'))}</td>
        <td>${Number(r.total_items || 0)}</td>
        <td>${esc(money(r.total_value))}</td>
        <td>${esc(formatDate(r.created_at))}</td>
        <td>${statusBadge(r.status)}</td>
        <td class="wh-btr-row-actions">
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-btr-view="${r.id}">${esc(t('wms_view_details'))}</button>
            ${canApproveRow ? `<button type="button" class="wh-btn wh-btn--sm" data-btr-approve="${r.id}">${esc(t('wms_approve'))}</button>` : ''}
            ${canCompleteRow ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--primary" data-btr-complete="${r.id}">${esc(t('wms_complete'))}</button>` : ''}
            ${canRejectRow ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--warn" data-btr-reject="${r.id}">${esc(t('wms_reject'))}</button>` : ''}
        </td>
    </tr>`;
}).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-btr-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.btrView)));
        });
        tableWrap.querySelectorAll('[data-btr-approve]').forEach((btn) => {
            btn.addEventListener('click', () => approveTransfer(Number(btn.dataset.btrApprove)));
        });
        tableWrap.querySelectorAll('[data-btr-complete]').forEach((btn) => {
            btn.addEventListener('click', () => completeTransfer(Number(btn.dataset.btrComplete)));
        });
        tableWrap.querySelectorAll('[data-btr-reject]').forEach((btn) => {
            btn.addEventListener('click', () => rejectTransfer(Number(btn.dataset.btrReject)));
        });
    }

    function renderPagination() {
        if (!els.pagination) return;
        const pages = Math.max(1, Math.ceil(state.total / state.limit));
        els.pagination.hidden = state.total <= state.limit;
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= pages;
        if (els.pageMeta) {
            const from = state.total ? (state.page - 1) * state.limit + 1 : 0;
            const to = Math.min(state.page * state.limit, state.total);
            els.pageMeta.textContent = `${from}–${to} / ${state.total} ${t('records')}`;
        }
    }

    async function load() {
        hideError();
        if (els.loading) els.loading.hidden = false;
        if (els.empty) els.empty.hidden = true;
        setStatsLoading(true);

        try {
            const res = await AdminAPI.getWmsTransfers(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = res.total ?? state.items.length;
            state.summary = res.summary || null;
            state.breakdown = res.breakdown || [];
            renderStats(state.summary);
            renderBreakdown(state.breakdown);
            renderTable(state.items);
            renderPagination();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            renderTable([]);
            if (els.breakdownPanel) els.breakdownPanel.hidden = true;
        } finally {
            if (els.loading) els.loading.hidden = true;
            setStatsLoading(false);
        }
    }

    function buildExportRows(items) {
        return [
            [t('wms_col_transfer'), t('wms_col_from'), t('wms_col_to'), t('wms_col_items'), t('wms_col_value'), t('col_status')],
            ...items.map((r) => [r.transfer_number, storeLabel(r, 'from'), storeLabel(r, 'to'), r.total_items, r.total_value, r.status]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsTransfers(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`branch-transfers-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    function resetBtrSwipeStyles() {
        const panel = els.createModal?.querySelector('.wh-grn-modal');
        if (!panel) return;
        panel.classList.remove('is-dragging');
        panel.style.transition = '';
        panel.style.transform = '';
        if (els.createModal) {
            els.createModal.style.transition = '';
            els.createModal.style.opacity = '';
        }
    }

    function openModal(el) {
        el?.classList.add('is-open');
        el?.setAttribute('aria-hidden', 'false');
    }

    function closeModal(el) {
        if (el === els.createModal) {
            resetBtrSwipeStyles();
            clearFormError();
        }
        el?.classList.remove('is-open');
        el?.setAttribute('aria-hidden', 'true');
    }

    function clearFormError() {
        if (els.formError) {
            els.formError.hidden = true;
            els.formError.textContent = '';
        }
    }

    function notifyFormError(msg) {
        clearFormError();
        if (els.formError) {
            els.formError.textContent = msg;
            els.formError.hidden = false;
        }
        toast(msg, 'error');
    }

    async function approveTransfer(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_approve_trf'))) return;
        const res = await AdminAPI.approveWmsTransfer(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_btr_toast_approved'));
        if (fromDetail) closeModal(els.detailModal);
        await load();
    }

    async function completeTransfer(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_complete_trf'))) return;
        const res = await AdminAPI.completeWmsTransfer(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_btr_toast_completed'));
        if (fromDetail) closeModal(els.detailModal);
        await load();
    }

    async function rejectTransfer(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_reject_trf'))) return;
        const res = await AdminAPI.rejectWmsTransfer(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_btr_toast_rejected'));
        if (fromDetail) closeModal(els.detailModal);
        await load();
    }

    async function openDetail(id) {
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        openModal(els.detailModal);
        try {
            const res = await AdminAPI.getWmsTransfer(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.detailTitle) els.detailTitle.textContent = `${t('wms_transfer_details')} — ${r.transfer_number}`;
            if (els.detailSubtitle) {
                els.detailSubtitle.textContent = `${t('wms_type_branch')} · ${storeLabel(r, 'from')} → ${storeLabel(r, 'to')}`;
            }
            const items = r.items || [];
            const canApproveRow = canManage && r.status === 'requested';
            const canCompleteRow = canManage && r.status === 'approved';
            const canRejectRow = canManage && r.status === 'requested';
            els.detailBody.innerHTML = `
                <dl class="wh-btr-detail-grid">
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_from'))}</dt><dd>${esc(storeLabel(r, 'from'))}</dd></div>
                    <div><dt>${esc(t('wms_col_to'))}</dt><dd>${esc(storeLabel(r, 'to'))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.total_value))}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at))}</dd></div>
                    ${r.requested_by_name ? `<div><dt>${esc(t('wms_col_requested_by'))}</dt><dd>${esc(r.requested_by_name)}</dd></div>` : ''}
                </dl>
                ${r.reason ? `<p class="wh-btr-detail-notes"><strong>${esc(t('wms_col_reason'))}:</strong> ${esc(r.reason)}</p>` : ''}
                <div class="wh-btr-lines-wrap">
                    <table class="modern-table wh-table wh-btr-lines-table">
                        <thead><tr>
                            <th>${esc(t('wms_col_product'))}</th>
                            <th>${esc(t('wms_col_sku'))}</th>
                            <th>${esc(t('wms_col_qty'))}</th>
                            <th>${esc(t('wms_unit_cost'))}</th>
                        </tr></thead>
                        <tbody>${items.map((i) => `<tr>
                            <td>${esc(i.product_name)}</td>
                            <td>${esc(i.sku || '—')}</td>
                            <td><strong>${i.quantity_requested}</strong></td>
                            <td>${esc(money(i.unit_cost))}</td>
                        </tr>`).join('')}</tbody>
                    </table>
                </div>
                ${canApproveRow || canCompleteRow || canRejectRow ? `<div class="wh-btr-detail-actions">
                    ${canApproveRow ? `<button type="button" class="wh-btn" id="whBtrApproveBtn">${esc(t('wms_approve'))}</button>` : ''}
                    ${canCompleteRow ? `<button type="button" class="wh-btn wh-btn--primary" id="whBtrCompleteBtn">${esc(t('wms_complete'))}</button>` : ''}
                    ${canRejectRow ? `<button type="button" class="wh-btn wh-btn--warn" id="whBtrRejectBtn">${esc(t('wms_reject'))}</button>` : ''}
                </div>` : ''}`;
            document.getElementById('whBtrApproveBtn')?.addEventListener('click', () => approveTransfer(r.id, true));
            document.getElementById('whBtrCompleteBtn')?.addEventListener('click', () => completeTransfer(r.id, true));
            document.getElementById('whBtrRejectBtn')?.addEventListener('click', () => rejectTransfer(r.id, true));
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-btr-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function loadStores() {
        if (!state.stores.length) {
            const res = await AdminAPI.listStores();
            state.stores = res.status === 'success' ? (res.data || []) : [];
        }
        const fill = (sel, includeAll) => {
            if (!sel) return;
            const cur = sel.value;
            const opts = includeAll ? `<option value="">${esc(t('wms_all_stores'))}</option>` : '';
            sel.innerHTML = opts + state.stores.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
            if (cur) sel.value = cur;
        };
        fill(els.store, true);
        fill(els.fromStore, false);
        fill(els.toStore, false);
        if (els.fromStore && !els.fromStore.value && window.WH_PAGE?.storeId) {
            els.fromStore.value = String(window.WH_PAGE.storeId);
        }
    }

    async function loadProducts() {
        if (state.products.length) return;
        const res = await AdminAPI.getInventoryProducts();
        state.products = res.status === 'success' ? (res.data || []) : [];
    }

    function productLabel(p) {
        const sku = p.sku ? ` · ${p.sku}` : '';
        return `${p.name || ''}${sku}`;
    }

    function findProductMatch(query) {
        const q = query.trim().toLowerCase();
        if (!q) return null;
        return state.products.find((item) =>
            String(item.name || '').toLowerCase() === q
            || String(item.sku || '').toLowerCase() === q
            || String(item.barcode || '').toLowerCase() === q
        ) || null;
    }

    function filterProducts(query) {
        const q = query.trim().toLowerCase();
        if (!q) return state.products.slice(0, 40);
        return state.products.filter((item) =>
            `${item.name || ''} ${item.sku || ''} ${item.barcode || ''}`.toLowerCase().includes(q)
        ).slice(0, 40);
    }

    function productIdentityKey(productId) {
        const id = parseInt(productId, 10);
        return id > 0 ? `id:${id}` : null;
    }

    function readRowProductIdentity(row) {
        if (!row) return { productId: '', key: null };
        const idx = row.dataset.lineIdx;
        const productId = row.querySelector(`[name="product_id_${idx}"]`)?.value?.trim() || '';
        return { productId, key: productIdentityKey(productId) };
    }

    function getUsedProductKeys(excludeRow = null) {
        const keys = new Set();
        els.lineItems?.querySelectorAll('.wh-grn-table__row').forEach((row) => {
            if (row === excludeRow) return;
            const { key } = readRowProductIdentity(row);
            if (key) keys.add(key);
        });
        return keys;
    }

    function isDuplicateProduct(row, productId) {
        const key = productIdentityKey(productId);
        if (!key) return false;
        return getUsedProductKeys(row).has(key);
    }

    function saveValidSelection(picker) {
        if (!picker) return;
        const input = picker.querySelector('.wh-grn-product-input');
        const hidden = picker.querySelector('input[type="hidden"]');
        picker._lastValid = {
            inputValue: input?.value || '',
            productId: hidden?.value || '',
        };
    }

    function rejectDuplicateSelection(picker) {
        notifyFormError(t('wms_product_duplicate'));
        picker?.classList.add('is-duplicate');
        const sel = picker?._lastValid || { inputValue: '', productId: '' };
        const input = picker?.querySelector('.wh-grn-product-input');
        const hidden = picker?.querySelector('input[type="hidden"]');
        if (input) input.value = sel.inputValue;
        if (hidden) hidden.value = sel.productId;
        requestAnimationFrame(() => {
            input?.focus();
            input?.select();
        });
        return false;
    }

    function productPickerHtml(idx, data = {}) {
        const selected = data.product_id
            ? state.products.find((item) => String(item.id) === String(data.product_id))
            : null;
        const initialValue = selected ? productLabel(selected) : '';
        return `
            <div class="wh-grn-product-picker" data-picker-idx="${idx}">
                <div class="wh-grn-product-input-wrap">
                    <input type="text" class="wh-grn-product-input" value="${esc(initialValue)}" placeholder="${esc(t('wms_transfer_product_search'))}" autocomplete="off" required>
                    <input type="hidden" name="product_id_${idx}" value="${selected ? esc(String(selected.id)) : ''}">
                </div>
                <div class="wh-grn-product-dropdown" hidden></div>
            </div>`;
    }

    function resolveProductPicker(picker, options = {}) {
        const checkDuplicate = options.checkDuplicate !== false;
        if (!picker) return true;
        const input = picker.querySelector('.wh-grn-product-input');
        const hidden = picker.querySelector('input[type="hidden"]');
        const row = picker.closest('.wh-grn-table__row');
        const q = input?.value.trim() || '';
        if (!q) {
            if (hidden) hidden.value = '';
            picker.classList.remove('is-duplicate');
            saveValidSelection(picker);
            return true;
        }
        const match = findProductMatch(q) || filterProducts(q).find((item) => productLabel(item).toLowerCase() === q.toLowerCase());
        if (match) {
            if (hidden) hidden.value = String(match.id);
            if (input) input.value = productLabel(match);
            const idx = row?.dataset.lineIdx;
            const costInput = row?.querySelector(`[name="cost_${idx}"]`);
            if (costInput && !costInput.value) {
                costInput.value = match.cost_price || match.cost || match.price || '';
            }
            if (checkDuplicate && isDuplicateProduct(row, match.id)) {
                return rejectDuplicateSelection(picker);
            }
            picker.classList.remove('is-duplicate');
            saveValidSelection(picker);
            return true;
        }
        if (hidden) hidden.value = '';
        picker.classList.remove('is-duplicate');
        saveValidSelection(picker);
        return false;
    }

    function renderProductDropdown(picker, query) {
        const dropdown = picker.querySelector('.wh-grn-product-dropdown');
        const input = picker.querySelector('.wh-grn-product-input');
        if (!dropdown || !input) return;
        const mainRow = picker.closest('.wh-grn-table__row');
        const usedKeys = getUsedProductKeys(mainRow);
        const q = query.trim();
        const toolbarFilter = els.productFilter?.value?.trim().toLowerCase() || '';
        const matches = filterProducts(q).filter((item) => {
            if (usedKeys.has(`id:${item.id}`)) return false;
            if (!toolbarFilter) return true;
            return `${item.name || ''} ${item.sku || ''}`.toLowerCase().includes(toolbarFilter);
        });
        dropdown.innerHTML = matches.length
            ? matches.map((item) =>
                `<button type="button" class="wh-grn-product-option" data-id="${item.id}" data-cost="${item.cost_price || item.cost || item.price || 0}">
                    <span class="wh-grn-product-option__name">${esc(item.name)}</span>
                    ${item.sku ? `<span class="wh-grn-product-option__sku">${esc(item.sku)}</span>` : ''}
                </button>`
            ).join('')
            : `<p class="wh-grn-product-empty">${esc(t('wms_select_product'))}</p>`;
        dropdown.hidden = false;
    }

    function bindProductPicker(picker, idx, mainRow) {
        const input = picker.querySelector('.wh-grn-product-input');
        const dropdown = picker.querySelector('.wh-grn-product-dropdown');
        if (!input) return;

        const closeDropdown = () => {
            if (dropdown) dropdown.hidden = true;
        };

        input.addEventListener('focus', () => renderProductDropdown(picker, input.value));
        input.addEventListener('input', () => {
            picker.classList.remove('is-duplicate');
            renderProductDropdown(picker, input.value);
            resolveProductPicker(picker);
            updateLineSummary();
        });
        input.addEventListener('blur', () => {
            setTimeout(() => {
                closeDropdown();
                resolveProductPicker(picker);
            }, 150);
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDropdown();
        });

        dropdown?.addEventListener('mousedown', (e) => {
            const opt = e.target.closest('.wh-grn-product-option');
            if (!opt) return;
            e.preventDefault();
            const product = state.products.find((item) => String(item.id) === String(opt.dataset.id));
            if (product) {
                input.value = productLabel(product);
                const costInput = mainRow.querySelector(`[name="cost_${idx}"]`);
                if (costInput && !costInput.value && opt.dataset.cost) {
                    costInput.value = opt.dataset.cost;
                }
            }
            if (!resolveProductPicker(picker)) return;
            updateLineSummary();
            closeDropdown();
        });
        saveValidSelection(picker);
    }

    function refreshProductPickers() {
        els.lineItems?.querySelectorAll('.wh-grn-product-picker').forEach((picker) => {
            resolveProductPicker(picker, { checkDuplicate: false });
        });
    }

    function syncBtrLayout(rowsCount) {
        const modal = els.createModal?.querySelector('.wh-grn-modal');
        const titleEl = document.getElementById('whBtrLinesTitle');
        const baseTitle = t('wms_transfer_section_lines');
        if (titleEl) {
            titleEl.textContent = rowsCount > 0 ? `${baseTitle} (${rowsCount})` : baseTitle;
        }
        if (modal) {
            modal.classList.toggle('wh-grn-modal--many-lines', rowsCount >= 8);
        }
    }

    function updateLineSummary() {
        const rows = els.lineItems?.querySelectorAll('.wh-grn-table__row') || [];
        let total = 0;
        rows.forEach((row) => {
            const idx = row.dataset.lineIdx;
            const qty = parseFloat(row.querySelector(`[name="qty_${idx}"]`)?.value) || 0;
            const cost = parseFloat(row.querySelector(`[name="cost_${idx}"]`)?.value) || 0;
            total += qty * cost;
            const subEl = row.querySelector('.wh-grn-table__subtotal');
            if (subEl) subEl.textContent = money(qty * cost);
        });
        if (els.lineCount) els.lineCount.textContent = String(rows.length);
        if (els.estTotal) els.estTotal.textContent = money(total);
        if (els.linesEmpty) els.linesEmpty.hidden = rows.length > 0;
        const table = els.lineItems?.closest('.wh-grn-table');
        if (table) table.hidden = rows.length === 0;
        rows.forEach((row, i) => {
            const num = row.querySelector('.wh-grn-table__num');
            if (num) num.textContent = String(i + 1);
            row.querySelectorAll('[data-remove-line]').forEach((btn) => {
                btn.disabled = rows.length <= 1;
            });
        });
        syncBtrLayout(rows.length);
    }

    function bindLineInputs(mainRow, idx) {
        mainRow.querySelectorAll('input:not(.wh-grn-product-input)').forEach((el) => {
            el.addEventListener('input', updateLineSummary);
            el.addEventListener('change', updateLineSummary);
        });
        const picker = mainRow.querySelector('.wh-grn-product-picker');
        bindProductPicker(picker, idx, mainRow);
        mainRow.querySelector('[data-remove-line]')?.addEventListener('click', () => {
            if ((els.lineItems?.querySelectorAll('.wh-grn-table__row').length || 0) <= 1) return;
            mainRow.remove();
            updateLineSummary();
        });
    }

    function addLineRow(data = {}) {
        if (!els.lineItems) return;
        const idx = state.lineIndex++;
        const lineNo = els.lineItems.querySelectorAll('.wh-grn-table__row').length + 1;

        const mainRow = document.createElement('tr');
        mainRow.className = 'wh-grn-table__row wh-grn-table__row--enter';
        mainRow.dataset.lineIdx = String(idx);
        mainRow.innerHTML = `
            <td class="wh-grn-td--num" data-label="#">
                <span class="wh-grn-table__num">${lineNo}</span>
            </td>
            <td class="wh-grn-td--product" data-label="${esc(t('wms_col_product'))}">
                ${productPickerHtml(idx, data)}
            </td>
            <td class="wh-grn-td--qty" data-label="${esc(t('wms_qty_short'))}">
                <input type="number" name="qty_${idx}" min="1" value="${data.quantity || 1}" required inputmode="numeric">
            </td>
            <td class="wh-grn-td--cost" data-label="${esc(t('wms_unit_cost'))}">
                <input type="number" name="cost_${idx}" min="0" step="0.01" value="${data.unit_cost || ''}" placeholder="0" required inputmode="decimal">
            </td>
            <td class="wh-grn-td--sub" data-label="${esc(t('wms_line_subtotal'))}">
                <span class="wh-grn-table__subtotal">${money(0)}</span>
            </td>
            <td class="wh-grn-td--act">
                <div class="wh-grn-table__act">
                    <button type="button" class="wh-grn-table__btn wh-grn-table__btn--remove" data-remove-line aria-label="${esc(t('wms_remove_line'))}">
                        <span class="material-icons-round">delete_outline</span>
                    </button>
                </div>
            </td>`;

        els.lineItems.appendChild(mainRow);
        bindLineInputs(mainRow, idx);
        updateLineSummary();

        const scrollEl = document.getElementById('whBtrLinesScroll');
        requestAnimationFrame(() => {
            mainRow.classList.remove('wh-grn-table__row--enter');
            if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight;
            mainRow.querySelector('.wh-grn-product-input')?.focus();
        });
    }

    function collectFormItems() {
        const items = [];
        let valid = true;
        els.lineItems?.querySelectorAll('.wh-grn-table__row').forEach((row) => {
            const picker = row.querySelector('.wh-grn-product-picker');
            if (picker && !resolveProductPicker(picker, { checkDuplicate: false })) valid = false;
            const idx = row.dataset.lineIdx;
            const productId = row.querySelector(`[name="product_id_${idx}"]`)?.value;
            const qty = parseInt(row.querySelector(`[name="qty_${idx}"]`)?.value, 10) || 0;
            const cost = parseFloat(row.querySelector(`[name="cost_${idx}"]`)?.value);
            if (productId && qty > 0) {
                items.push({
                    product_id: Number(productId),
                    quantity: qty,
                    unit_cost: Number.isFinite(cost) ? cost : 0,
                });
            }
        });
        return { items, valid };
    }

    function bindBtrSwipeDismiss() {
        const overlay = els.createModal;
        const panel = overlay?.querySelector('.wh-grn-modal');
        if (!overlay || !panel || panel.dataset.btrSwipeBound) return;
        panel.dataset.btrSwipeBound = '1';

        const SWIPE_CLOSE_PX = 72;
        const SWIPE_VELOCITY = 0.4;
        const scrollEl = document.getElementById('whBtrLinesScroll');
        const swipeZone = panel.querySelector('.wh-grn-modal__swipe-zone');
        const handle = panel.querySelector('.wh-grn-modal__handle');

        let startY = 0;
        let startX = 0;
        let currentY = 0;
        let startTime = 0;
        let dragging = false;
        let canSwipe = false;

        function isTouchSheet() {
            return window.matchMedia('(max-width: 767px)').matches;
        }

        function snapBack() {
            panel.style.transition = 'transform 0.24s cubic-bezier(0.4, 0, 0.2, 1)';
            panel.style.transform = '';
            overlay.style.transition = 'opacity 0.24s ease';
            overlay.style.opacity = '';
            setTimeout(resetBtrSwipeStyles, 240);
        }

        function animateClose() {
            panel.style.transition = 'transform 0.22s cubic-bezier(0.4, 0, 0.2, 1)';
            panel.style.transform = 'translateY(100%)';
            overlay.style.transition = 'opacity 0.22s ease';
            overlay.style.opacity = '0';
            setTimeout(() => closeModal(overlay), 220);
        }

        function onTouchStart(e) {
            if (!isTouchSheet() || !overlay.classList.contains('is-open')) return;
            const touch = e.touches[0];
            const target = e.target;
            const onHandle = handle?.contains(target);
            const onHeader = swipeZone?.contains(target);
            const scrollAtTop = !scrollEl || scrollEl.scrollTop <= 0;
            const isInteractive = target.closest('input, select, textarea, button, a, option');

            if (isInteractive && !onHandle) return;
            if (!onHandle && !onHeader && !scrollAtTop) return;

            startY = touch.clientY;
            startX = touch.clientX;
            currentY = 0;
            startTime = Date.now();
            dragging = true;
            canSwipe = true;
            panel.classList.add('is-dragging');
        }

        function onTouchMove(e) {
            if (!dragging || !canSwipe) return;
            const touch = e.touches[0];
            const deltaY = touch.clientY - startY;
            const deltaX = touch.clientX - startX;
            if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaY) < 10) return;
            if (deltaY < 0) {
                currentY = 0;
                panel.style.transform = '';
                return;
            }
            e.preventDefault();
            currentY = deltaY;
            panel.style.transition = 'none';
            panel.style.transform = `translateY(${deltaY}px)`;
            overlay.style.transition = 'none';
            overlay.style.opacity = String(Math.max(0.4, 1 - deltaY / 320));
        }

        function onTouchEnd() {
            if (!dragging) return;
            dragging = false;
            panel.classList.remove('is-dragging');
            const elapsed = Date.now() - startTime;
            const velocity = currentY / Math.max(elapsed, 1);
            if (currentY > SWIPE_CLOSE_PX || velocity > SWIPE_VELOCITY) {
                animateClose();
            } else {
                snapBack();
            }
        }

        panel.addEventListener('touchstart', onTouchStart, { passive: true });
        panel.addEventListener('touchmove', onTouchMove, { passive: false });
        panel.addEventListener('touchend', onTouchEnd);
        panel.addEventListener('touchcancel', onTouchEnd);
    }

    async function openCreateModal() {
        if (!canCreate) return;
        await Promise.all([loadProducts(), loadStores()]);
        els.createForm?.reset();
        if (els.productFilter) els.productFilter.value = '';
        if (els.lineItems) els.lineItems.innerHTML = '';
        state.lineIndex = 0;
        clearFormError();
        document.getElementById('whBtrMetaWrap')?.removeAttribute('open');
        if (els.fromStore && window.WH_PAGE?.storeId) els.fromStore.value = String(window.WH_PAGE.storeId);
        addLineRow();
        updateLineSummary();
        openModal(els.createModal);
        els.fromStore?.focus();
    }

    async function submitCreate(e) {
        e.preventDefault();
        clearFormError();
        if (!els.createForm?.reportValidity()) return;

        const fromId = els.fromStore?.value ? Number(els.fromStore.value) : null;
        const toId = els.toStore?.value ? Number(els.toStore.value) : null;
        if (!fromId || !toId) {
            notifyFormError(t('wms_select_store'));
            return;
        }
        if (fromId === toId) {
            notifyFormError(t('error'));
            return;
        }

        const { items, valid } = collectFormItems();
        if (!valid) {
            notifyFormError(t('wms_select_product'));
            return;
        }
        if (!items.length) {
            notifyFormError(t('wms_add_line'));
            return;
        }
        const seen = new Set();
        for (const row of els.lineItems?.querySelectorAll('.wh-grn-table__row') || []) {
            const { key } = readRowProductIdentity(row);
            if (key && seen.has(key)) {
                notifyFormError(t('wms_product_duplicate'));
                return;
            }
            if (key) seen.add(key);
        }

        const payload = {
            transfer_type: 'branch_to_branch',
            from_store_id: fromId,
            to_store_id: toId,
            reason: els.createForm.reason?.value?.trim() || null,
            status: 'requested',
            items,
        };
        const res = await AdminAPI.createWmsTransfer(payload);
        if (res.status !== 'success') {
            notifyFormError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_btr_toast_created'));
        closeModal(els.createModal);
        state.page = 1;
        await load();
        if (res.data?.id) openDetail(res.data.id);
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.exportBtn?.addEventListener('click', exportData);
    els.store?.addEventListener('change', () => { state.page = 1; load(); });
    els.status?.addEventListener('change', () => { state.page = 1; load(); });
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => { state.page = 1; load(); }, 350);
    });
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        const pages = Math.ceil(state.total / state.limit);
        if (state.page < pages) { state.page += 1; load(); }
    });
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.detailModal?.addEventListener('click', (e) => {
        if (e.target === els.detailModal) closeModal(els.detailModal);
    });

    if (canCreate) {
        els.newBtn?.addEventListener('click', openCreateModal);
        els.addLine?.addEventListener('click', () => loadProducts().then(addLineRow));
        document.querySelector('[data-trigger-add-line]')?.addEventListener('click', () => loadProducts().then(addLineRow));
        els.productFilter?.addEventListener('input', refreshProductPickers);
        els.createForm?.addEventListener('submit', submitCreate);
        els.createClose?.addEventListener('click', () => closeModal(els.createModal));
        els.createCancel?.addEventListener('click', () => closeModal(els.createModal));
        els.createModal?.addEventListener('click', (e) => {
            if (e.target === els.createModal) closeModal(els.createModal);
        });
        bindBtrSwipeDismiss();
    }

    document.addEventListener('wh:refresh', load);

    loadStores().then(load);
});
