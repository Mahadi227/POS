/**
 * Warehouse transfer requests — store stock requests with manager/warehouse approval
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whTrqTableWrap');
    if (!tableWrap) return;

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canManage = !!window.WH_PAGE?.canManage && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
        pending: 'wms_status_pending',
        manager_approved: 'wms_status_manager_approved',
        warehouse_approved: 'wms_status_warehouse_approved',
        dispatched: 'wms_status_dispatched',
        delivered: 'wms_status_delivered',
        rejected: 'wms_status_rejected',
        cancelled: 'wms_status_cancelled',
    };
    const PRIORITY_KEYS = {
        low: 'wms_priority_low',
        normal: 'wms_priority_normal',
        high: 'wms_priority_high',
        urgent: 'wms_priority_urgent',
    };
    const STATUS_ORDER = ['pending', 'manager_approved', 'warehouse_approved', 'dispatched', 'delivered', 'rejected', 'cancelled'];

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
        search: document.getElementById('whTrqSearch'),
        store: document.getElementById('whTrqStore'),
        warehouse: document.getElementById('whTrqWarehouse'),
        status: document.getElementById('whTrqStatus'),
        refresh: document.getElementById('whTrqRefreshBtn'),
        exportBtn: document.getElementById('whTrqExportBtn'),
        newBtn: document.getElementById('whTrqNewBtn'),
        heroMeta: document.getElementById('whTrqHeroMeta'),
        breakdownPanel: document.getElementById('whTrqBreakdownPanel'),
        statusChips: document.getElementById('whTrqStatusChips'),
        statTotal: document.getElementById('whTrqStatTotal'),
        statPending: document.getElementById('whTrqStatPending'),
        statApproved: document.getElementById('whTrqStatApproved'),
        statUrgent: document.getElementById('whTrqStatUrgent'),
        loading: document.getElementById('whTrqLoading'),
        empty: document.getElementById('whTrqEmpty'),
        pagination: document.getElementById('whTrqPagination'),
        prev: document.getElementById('whTrqPrev'),
        next: document.getElementById('whTrqNext'),
        pageMeta: document.getElementById('whTrqPageMeta'),
        createModal: document.getElementById('whTrqCreateModal'),
        createClose: document.getElementById('whTrqCreateClose'),
        createCancel: document.getElementById('whTrqCreateCancel'),
        createForm: document.getElementById('whTrqCreateForm'),
        formStore: document.getElementById('whTrqFormStore'),
        formWarehouse: document.getElementById('whTrqFormWarehouse'),
        addLine: document.getElementById('whTrqAddLine'),
        productFilter: document.getElementById('whTrqProductFilter'),
        lineItems: document.getElementById('whTrqLineItems'),
        linesEmpty: document.getElementById('whTrqLinesEmpty'),
        lineCount: document.getElementById('whTrqLineCount'),
        totalQty: document.getElementById('whTrqTotalQty'),
        formError: document.getElementById('whTrqFormError'),
        detailModal: document.getElementById('whTrqDetailModal'),
        detailClose: document.getElementById('whTrqDetailClose'),
        detailTitle: document.getElementById('whTrqDetailTitle'),
        detailSubtitle: document.getElementById('whTrqDetailSubtitle'),
        detailBody: document.getElementById('whTrqDetailBody'),
        toast: document.getElementById('whTrqToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-trq-toast show${type === 'error' ? ' wh-trq-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function priorityLabel(priority) {
        return t(PRIORITY_KEYS[priority] || priority) || priority || '—';
    }

    function statusBadge(status) {
        const cls = status === 'delivered' || status === 'warehouse_approved' ? 'ok'
            : (status === 'rejected' || status === 'cancelled' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function priorityBadge(priority) {
        const cls = priority === 'urgent' || priority === 'high' ? 'warn' : 'idle';
        return `<span class="cr-badge cr-badge--${cls}">${esc(priorityLabel(priority))}</span>`;
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
        document.querySelectorAll('.wh-trq-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statPending) els.statPending.textContent = String(s.pending ?? 0);
        if (els.statApproved) els.statApproved.textContent = String(s.approved ?? 0);
        if (els.statUrgent) els.statUrgent.textContent = String(s.urgent ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_trq_hero_meta', s.pending ?? 0, s.urgent ?? 0);
        }
        setStatsLoading(false);
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
        const activeStatus = els.status?.value || 'all';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const isActive = activeStatus === r.status;
            return `<button type="button" class="wh-trq-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-trq-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.status) els.status.value = btn.dataset.status || 'all';
                state.page = 1;
                load();
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
        const store = els.store?.value?.trim();
        if (store) params.store_id = store;
        const status = els.status?.value?.trim();
        if (status && status !== 'all') params.status = status;
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-trq-table">
<thead><tr>
    <th>${esc(t('wms_col_request'))}</th>
    <th>${esc(t('wms_col_store'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_priority'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_qty'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r) => {
    const canMgrApprove = canManage && r.status === 'pending';
    const canWhApprove = canManage && r.status === 'manager_approved';
    const canReject = canManage && ['pending', 'manager_approved'].includes(r.status);
    return `<tr>
        <td><strong>${esc(r.request_number)}</strong></td>
        <td>${esc(r.store_name || '—')}</td>
        <td>${esc(r.warehouse_name || '—')}</td>
        <td>${priorityBadge(r.priority)}</td>
        <td>${Number(r.total_items || 0)}</td>
        <td>${Number(r.total_qty || 0)}</td>
        <td>${esc(formatDate(r.created_at))}</td>
        <td>${statusBadge(r.status)}</td>
        <td class="wh-trq-row-actions">
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-trq-view="${r.id}">${esc(t('wms_view_details'))}</button>
            ${canMgrApprove ? `<button type="button" class="wh-btn wh-btn--sm" data-trq-approve-mgr="${r.id}">${esc(t('wms_approve'))}</button>` : ''}
            ${canWhApprove ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--primary" data-trq-approve-wh="${r.id}">${esc(t('wms_approve_warehouse'))}</button>` : ''}
            ${canReject ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--warn" data-trq-reject="${r.id}">${esc(t('wms_reject'))}</button>` : ''}
        </td>
    </tr>`;
}).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-trq-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.trqView)));
        });
        tableWrap.querySelectorAll('[data-trq-approve-mgr]').forEach((btn) => {
            btn.addEventListener('click', () => approveRequest(Number(btn.dataset.trqApproveMgr), 'manager'));
        });
        tableWrap.querySelectorAll('[data-trq-approve-wh]').forEach((btn) => {
            btn.addEventListener('click', () => approveRequest(Number(btn.dataset.trqApproveWh), 'warehouse'));
        });
        tableWrap.querySelectorAll('[data-trq-reject]').forEach((btn) => {
            btn.addEventListener('click', () => rejectRequest(Number(btn.dataset.trqReject)));
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
        fill(els.formStore, false);
        if (els.formStore && !els.formStore.value && window.WH_PAGE?.storeId) {
            els.formStore.value = String(window.WH_PAGE.storeId);
        }
    }

    async function loadProducts() {
        if (state.products.length) return;
        const res = await AdminAPI.getInventoryProducts();
        state.products = res.status === 'success' ? (res.data || []) : [];
    }

    async function loadFormWarehouses() {
        if (!els.formWarehouse) return;
        const res = await AdminAPI.getWmsWarehouses();
        const items = res.status === 'success' ? (res.data || []) : [];
        const cur = els.formWarehouse.value || String(window.WH_PAGE?.warehouseId || '');
        els.formWarehouse.innerHTML = `<option value="">${esc(t('wms_select_warehouse'))}</option>`
            + items.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
        if (cur) els.formWarehouse.value = cur;
    }

    async function load() {
        hideError();
        if (els.loading) els.loading.hidden = false;
        if (els.empty) els.empty.hidden = true;
        setStatsLoading(true);

        try {
            const res = await AdminAPI.getWmsRequests(buildParams());
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
            [t('wms_col_request'), t('wms_col_store'), t('wms_nav_warehouses'), t('wms_col_priority'), t('wms_col_items'), t('wms_col_qty'), t('col_status'), t('wms_col_requested_by')],
            ...items.map((r) => [r.request_number, r.store_name, r.warehouse_name, r.priority, r.total_items, r.total_qty, r.status, r.requested_by_name]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsRequests(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`transfer-requests-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    function resetTrqSwipeStyles() {
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
            resetTrqSwipeStyles();
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

    function ensureFormStore() {
        if (!els.formStore) return null;
        if (els.formStore.value) return Number(els.formStore.value);
        const storeId = String(window.WH_PAGE?.storeId || '');
        if (storeId && [...els.formStore.options].some((o) => o.value === storeId)) {
            els.formStore.value = storeId;
            return Number(storeId);
        }
        notifyFormError(t('wms_select_store'));
        els.formStore.focus();
        return null;
    }

    function ensureFormWarehouse() {
        if (!els.formWarehouse) return null;
        if (els.formWarehouse.value) return Number(els.formWarehouse.value);
        const whId = String(window.WH_PAGE?.warehouseId || '');
        if (whId && [...els.formWarehouse.options].some((o) => o.value === whId)) {
            els.formWarehouse.value = whId;
            return Number(whId);
        }
        notifyFormError(t('wms_select_warehouse'));
        els.formWarehouse.focus();
        return null;
    }

    function productLabel(p) {
        const sku = p.sku ? ` · ${p.sku}` : '';
        return `${p.name || ''}${sku}`;
    }

    function findProductMatch(query) {
        const q = query.trim().toLowerCase();
        if (!q) return null;
        return state.products.find((p) =>
            String(p.name || '').toLowerCase() === q
            || String(p.sku || '').toLowerCase() === q
            || String(p.barcode || '').toLowerCase() === q
        ) || null;
    }

    function filterProducts(query) {
        const q = query.trim().toLowerCase();
        if (!q) return state.products.slice(0, 40);
        return state.products.filter((p) =>
            `${p.name || ''} ${p.sku || ''} ${p.barcode || ''}`.toLowerCase().includes(q)
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
            ? state.products.find((p) => String(p.id) === String(data.product_id))
            : null;
        const initialValue = selected ? productLabel(selected) : '';
        return `
            <div class="wh-grn-product-picker" data-picker-idx="${idx}">
                <div class="wh-grn-product-input-wrap">
                    <input type="text" class="wh-grn-product-input" value="${esc(initialValue)}" placeholder="${esc(t('wms_request_product_search'))}" autocomplete="off" required>
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
        const match = findProductMatch(q) || filterProducts(q).find((p) => productLabel(p).toLowerCase() === q.toLowerCase());
        if (match) {
            if (hidden) hidden.value = String(match.id);
            if (input) input.value = productLabel(match);
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
        const matches = filterProducts(q).filter((p) => {
            if (usedKeys.has(`id:${p.id}`)) return false;
            if (!toolbarFilter) return true;
            return `${p.name || ''} ${p.sku || ''}`.toLowerCase().includes(toolbarFilter);
        });
        dropdown.innerHTML = matches.length
            ? matches.map((p) =>
                `<button type="button" class="wh-grn-product-option" data-id="${p.id}">
                    <span class="wh-grn-product-option__name">${esc(p.name)}</span>
                    ${p.sku ? `<span class="wh-grn-product-option__sku">${esc(p.sku)}</span>` : ''}
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
            const product = state.products.find((p) => String(p.id) === String(opt.dataset.id));
            if (product) input.value = productLabel(product);
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

    function syncTrqLayout(rowsCount) {
        const modal = els.createModal?.querySelector('.wh-grn-modal');
        const titleEl = document.getElementById('whTrqLinesTitle');
        const baseTitle = t('wms_request_section_lines');
        if (titleEl) {
            titleEl.textContent = rowsCount > 0 ? `${baseTitle} (${rowsCount})` : baseTitle;
        }
        if (modal) {
            modal.classList.toggle('wh-grn-modal--many-lines', rowsCount >= 8);
        }
    }

    function updateLineSummary() {
        const rows = els.lineItems?.querySelectorAll('.wh-grn-table__row') || [];
        let totalQty = 0;
        rows.forEach((row) => {
            const idx = row.dataset.lineIdx;
            totalQty += parseInt(row.querySelector(`[name="qty_${idx}"]`)?.value, 10) || 0;
        });
        if (els.lineCount) els.lineCount.textContent = String(rows.length);
        if (els.totalQty) els.totalQty.textContent = String(totalQty);
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
        syncTrqLayout(rows.length);
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

        const scrollEl = document.getElementById('whTrqLinesScroll');
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
            if (productId && qty > 0) {
                items.push({ product_id: Number(productId), quantity: qty });
            }
        });
        return { items, valid };
    }

    function bindTrqSwipeDismiss() {
        const overlay = els.createModal;
        const panel = overlay?.querySelector('.wh-grn-modal');
        if (!overlay || !panel || panel.dataset.trqSwipeBound) return;
        panel.dataset.trqSwipeBound = '1';

        const SWIPE_CLOSE_PX = 72;
        const SWIPE_VELOCITY = 0.4;
        const scrollEl = document.getElementById('whTrqLinesScroll');
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
            setTimeout(resetTrqSwipeStyles, 240);
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
        await Promise.all([loadProducts(), loadStores(), loadFormWarehouses()]);
        els.createForm?.reset();
        if (els.productFilter) els.productFilter.value = '';
        if (els.lineItems) els.lineItems.innerHTML = '';
        state.lineIndex = 0;
        clearFormError();
        document.getElementById('whTrqMetaWrap')?.removeAttribute('open');
        ensureFormStore();
        ensureFormWarehouse();
        addLineRow();
        updateLineSummary();
        openModal(els.createModal);
        els.formStore?.focus();
    }

    async function submitCreate(e) {
        e.preventDefault();
        clearFormError();
        if (!ensureFormStore() || !ensureFormWarehouse()) return;
        if (!els.createForm?.reportValidity()) return;

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

        const form = els.createForm;
        const payload = {
            store_id: Number(form.store_id?.value),
            warehouse_id: Number(form.warehouse_id?.value),
            priority: form.priority?.value || 'normal',
            notes: form.notes?.value?.trim() || null,
            status: 'pending',
            items,
        };
        const res = await AdminAPI.createWmsRequest(payload);
        if (res.status !== 'success') {
            notifyFormError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_trq_toast_created'));
        closeModal(els.createModal);
        state.page = 1;
        await load();
        if (res.data?.id) openDetail(res.data.id);
    }

    async function openDetail(id) {
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        openModal(els.detailModal);
        try {
            const res = await AdminAPI.getWmsRequest(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.detailTitle) els.detailTitle.textContent = `${t('wms_request_details')} — ${r.request_number}`;
            if (els.detailSubtitle) {
                els.detailSubtitle.textContent = `${r.store_name || '—'} → ${r.warehouse_name || '—'}`;
            }
            const items = r.items || [];
            const canMgrApprove = canManage && r.status === 'pending';
            const canWhApprove = canManage && r.status === 'manager_approved';
            const canReject = canManage && ['pending', 'manager_approved'].includes(r.status);
            els.detailBody.innerHTML = `
                <dl class="wh-trq-detail-grid">
                    <div><dt>${esc(t('wms_col_store'))}</dt><dd>${esc(r.store_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_priority'))}</dt><dd>${priorityBadge(r.priority)}</dd></div>
                    <div><dt>${esc(t('wms_col_requested_by'))}</dt><dd>${esc(r.requested_by_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at))}</dd></div>
                </dl>
                ${r.notes ? `<p class="wh-trq-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(r.notes)}</p>` : ''}
                <div class="wh-trq-lines-wrap">
                    <table class="modern-table wh-table wh-trq-lines-table">
                        <thead><tr>
                            <th>${esc(t('wms_col_product'))}</th>
                            <th>${esc(t('wms_col_sku'))}</th>
                            <th>${esc(t('wms_col_qty'))}</th>
                            <th>${esc(t('wh_trq_col_approved'))}</th>
                            <th>${esc(t('wh_trq_col_delivered'))}</th>
                        </tr></thead>
                        <tbody>${items.map((i) => `<tr>
                            <td>${esc(i.product_name)}</td>
                            <td>${esc(i.sku || '—')}</td>
                            <td><strong>${i.quantity_requested}</strong></td>
                            <td>${i.quantity_approved || 0}</td>
                            <td>${i.quantity_delivered || 0}</td>
                        </tr>`).join('')}</tbody>
                    </table>
                </div>
                ${canMgrApprove || canWhApprove || canReject ? `<div class="wh-trq-detail-actions">
                    ${canMgrApprove ? `<button type="button" class="wh-btn" id="whTrqDetailApproveMgr">${esc(t('wms_approve'))}</button>` : ''}
                    ${canWhApprove ? `<button type="button" class="wh-btn wh-btn--primary" id="whTrqDetailApproveWh">${esc(t('wms_approve_warehouse'))}</button>` : ''}
                    ${canReject ? `<button type="button" class="wh-btn wh-btn--warn" id="whTrqDetailReject">${esc(t('wms_reject'))}</button>` : ''}
                </div>` : ''}`;
            document.getElementById('whTrqDetailApproveMgr')?.addEventListener('click', () => approveRequest(r.id, 'manager', true));
            document.getElementById('whTrqDetailApproveWh')?.addEventListener('click', () => approveRequest(r.id, 'warehouse', true));
            document.getElementById('whTrqDetailReject')?.addEventListener('click', () => rejectRequest(r.id, true));
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-trq-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function approveRequest(id, role, fromDetail = false) {
        const msg = role === 'warehouse' ? t('wms_confirm_approve_wh') : t('wms_confirm_approve_mgr');
        if (!window.confirm(msg)) return;
        const res = await AdminAPI.approveWmsRequest(id, role);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_trq_toast_approved'));
        if (fromDetail) closeModal(els.detailModal);
        await load();
    }

    async function rejectRequest(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_reject'))) return;
        const res = await AdminAPI.rejectWmsRequest(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_trq_toast_rejected'));
        if (fromDetail) closeModal(els.detailModal);
        await load();
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.exportBtn?.addEventListener('click', exportData);
    els.newBtn?.addEventListener('click', openCreateModal);
    els.store?.addEventListener('change', () => { state.page = 1; load(); });
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
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
    els.addLine?.addEventListener('click', () => loadProducts().then(addLineRow));
    document.querySelector('[data-trigger-add-line]')?.addEventListener('click', () => loadProducts().then(addLineRow));
    els.productFilter?.addEventListener('input', refreshProductPickers);
    els.createForm?.addEventListener('submit', submitCreate);
    els.createClose?.addEventListener('click', () => closeModal(els.createModal));
    els.createCancel?.addEventListener('click', () => closeModal(els.createModal));
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.createModal?.addEventListener('click', (e) => { if (e.target === els.createModal) closeModal(els.createModal); });
    els.detailModal?.addEventListener('click', (e) => { if (e.target === els.detailModal) closeModal(els.detailModal); });
    bindTrqSwipeDismiss();

    document.addEventListener('wh:refresh', load);

    Promise.all([loadWarehouseOptions(els.warehouse), loadStores()]).then(load);
});
