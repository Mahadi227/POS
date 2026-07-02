/**
 * Warehouse dispatch orders — outbound shipments to stores or other warehouses
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whDspTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canDispatch = !!window.WH_PAGE?.canDispatch && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
        draft: 'wms_status_draft',
        picking: 'wms_status_picking',
        packed: 'wms_status_packed',
        dispatched: 'wms_status_dispatched',
        in_transit: 'wms_status_in_transit',
        delivered: 'wms_status_delivered',
        cancelled: 'wms_status_cancelled',
    };
    const STATUS_ORDER = ['draft', 'picking', 'packed', 'dispatched', 'in_transit', 'delivered', 'cancelled'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        warehouses: [],
        stores: [],
        products: [],
        searchTimer: null,
        lineIndex: 0,
        submitting: false,
    };

    const els = {
        search: document.getElementById('whDspSearch'),
        warehouse: document.getElementById('whDspWarehouse'),
        status: document.getElementById('whDspStatus'),
        refresh: document.getElementById('whDspRefreshBtn'),
        exportBtn: document.getElementById('whDspExportBtn'),
        newBtn: document.getElementById('whDspNewBtn'),
        heroMeta: document.getElementById('whDspHeroMeta'),
        breakdownPanel: document.getElementById('whDspBreakdownPanel'),
        statusChips: document.getElementById('whDspStatusChips'),
        statTotal: document.getElementById('whDspStatTotal'),
        statDraft: document.getElementById('whDspStatDraft'),
        statOutgoing: document.getElementById('whDspStatOutgoing'),
        statDelivered: document.getElementById('whDspStatDelivered'),
        loading: document.getElementById('whDspLoading'),
        empty: document.getElementById('whDspEmpty'),
        pagination: document.getElementById('whDspPagination'),
        prev: document.getElementById('whDspPrev'),
        next: document.getElementById('whDspNext'),
        pageMeta: document.getElementById('whDspPageMeta'),
        createModal: document.getElementById('whDspCreateModal'),
        createClose: document.getElementById('whDspCreateClose'),
        createCancel: document.getElementById('whDspCreateCancel'),
        createForm: document.getElementById('whDspCreateForm'),
        formError: document.getElementById('whDspFormError'),
        formWarehouse: document.getElementById('whDspFormWarehouse'),
        destType: document.getElementById('whDspDestType'),
        storeField: document.getElementById('whDspStoreField'),
        whDestField: document.getElementById('whDspWhDestField'),
        formStore: document.getElementById('whDspFormStore'),
        formWhDest: document.getElementById('whDspFormWhDest'),
        addLine: document.getElementById('whDspAddLine'),
        productFilter: document.getElementById('whDspProductFilter'),
        lineItems: document.getElementById('whDspLineItems'),
        linesEmpty: document.getElementById('whDspLinesEmpty'),
        lineCount: document.getElementById('whDspLineCount'),
        estTotal: document.getElementById('whDspEstTotal'),
        detailModal: document.getElementById('whDspDetailModal'),
        detailClose: document.getElementById('whDspDetailClose'),
        detailTitle: document.getElementById('whDspDetailTitle'),
        detailSubtitle: document.getElementById('whDspDetailSubtitle'),
        detailBody: document.getElementById('whDspDetailBody'),
        toast: document.getElementById('whDspToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-dsp-toast show${type === 'error' ? ' wh-dsp-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'delivered' ? 'ok' : (status === 'cancelled' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function destinationLabel(row) {
        if (row.to_store_name) return `${t('wms_dest_store')}: ${row.to_store_name}`;
        if (row.to_warehouse_name) return `${t('wms_dest_warehouse')}: ${row.to_warehouse_name}`;
        return '—';
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
        document.querySelectorAll('.wh-dsp-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statDraft) els.statDraft.textContent = String(s.draft ?? 0);
        if (els.statOutgoing) els.statOutgoing.textContent = String(s.outgoing ?? 0);
        if (els.statDelivered) els.statDelivered.textContent = String(s.delivered ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_dsp_hero_meta', s.draft ?? 0, s.outgoing ?? 0);
        }
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const list = (items || []).filter((r) => Number(r.count) > 0);
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
            return `<button type="button" class="wh-dsp-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-dsp-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                const status = btn.dataset.status || 'all';
                if (els.status) els.status.value = status;
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-dsp-table">
<thead><tr>
    <th>${esc(t('wms_col_dispatch'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_destination'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th>${esc(t('wms_col_driver'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((d) => {
    const canShip = canDispatch && ['draft', 'picking', 'packed'].includes(d.status);
    return `<tr>
        <td><strong>${esc(d.dispatch_number)}</strong></td>
        <td>${esc(d.from_warehouse_name || '—')}</td>
        <td>${esc(d.to_store_name || d.to_warehouse_name || '—')}</td>
        <td>${Number(d.total_items || 0)}</td>
        <td>${esc(money(d.total_value))}</td>
        <td>${esc(d.driver_name || '—')}</td>
        <td>${esc(formatDate(d.created_at))}</td>
        <td>${statusBadge(d.status)}</td>
        <td class="wh-dsp-row-actions">
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-dsp-view="${d.id}">${esc(t('wms_view_details'))}</button>
            ${canShip ? `<button type="button" class="wh-btn wh-btn--sm" data-dsp-ship="${d.id}">${esc(t('wms_dispatch_btn'))}</button>` : ''}
        </td>
    </tr>`;
}).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-dsp-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.dspView)));
        });
        tableWrap.querySelectorAll('[data-dsp-ship]').forEach((btn) => {
            btn.addEventListener('click', () => shipDispatch(Number(btn.dataset.dspShip)));
        });
    }

    function renderPagination() {
        if (!els.pagination) return;
        const pages = Math.max(1, Math.ceil(state.total / state.limit));
        const show = state.total > state.limit;
        els.pagination.hidden = !show;
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
            const res = await AdminAPI.getWmsDispatches(buildParams());
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
            [t('wms_col_dispatch'), t('wms_nav_warehouses'), t('wms_col_destination'), t('wms_col_items'), t('wms_col_value'), t('wms_col_driver'), t('col_date'), t('col_status')],
            ...items.map((d) => [
                d.dispatch_number,
                d.from_warehouse_name,
                d.to_store_name || d.to_warehouse_name,
                d.total_items,
                d.total_value,
                d.driver_name,
                d.created_at,
                d.status,
            ]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsDispatches(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`dispatch-orders-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    function openModal(el) {
        if (!el) return;
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
    }

    function resetDspSwipeStyles() {
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

    function closeModal(el) {
        if (!el) return;
        if (el === els.createModal) {
            resetDspSwipeStyles();
            clearFormError();
        }
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
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

    function ensureFormDestination() {
        const type = els.destType?.value || 'store';
        if (type === 'store') {
            if (els.formStore?.value) return true;
            notifyFormError(t('wms_select_store'));
            els.formStore?.focus();
            return false;
        }
        if (els.formWhDest?.value) return true;
        notifyFormError(t('wms_select_warehouse'));
        els.formWhDest?.focus();
        return false;
    }

    async function loadStores() {
        if (state.stores.length) return;
        const res = await AdminAPI.listStores();
        state.stores = res.status === 'success' ? (res.data || []) : [];
        if (!els.formStore) return;
        els.formStore.innerHTML = `<option value="">${esc(t('wms_select_store'))}</option>` +
            state.stores.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
    }

    async function loadProducts() {
        if (state.products.length) return;
        const res = await AdminAPI.getInventoryProducts();
        state.products = res.status === 'success' ? (res.data || []) : [];
    }

    function toggleDestFields() {
        const type = els.destType?.value || 'store';
        if (els.storeField) els.storeField.hidden = type !== 'store';
        if (els.whDestField) els.whDestField.hidden = type !== 'warehouse';
        if (els.formStore) els.formStore.required = type === 'store';
        if (els.formWhDest) els.formWhDest.required = type === 'warehouse';
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
                    <input type="text" class="wh-grn-product-input" value="${esc(initialValue)}" placeholder="${esc(t('wms_dispatch_product_search'))}" autocomplete="off" required>
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
                `<button type="button" class="wh-grn-product-option" data-id="${p.id}" data-cost="${p.cost_price || p.cost || p.price || 0}">
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

    function syncDspLayout(rowsCount) {
        const modal = els.createModal?.querySelector('.wh-grn-modal');
        const titleEl = document.getElementById('whDspLinesTitle');
        const baseTitle = t('wms_dispatch_section_lines');
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
        syncDspLayout(rows.length);
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

        const scrollEl = document.getElementById('whDspLinesScroll');
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
            const cost = parseFloat(row.querySelector(`[name="cost_${idx}"]`)?.value) || 0;
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

    function bindDspSwipeDismiss() {
        const overlay = els.createModal;
        const panel = overlay?.querySelector('.wh-grn-modal');
        if (!overlay || !panel || panel.dataset.dspSwipeBound) return;
        panel.dataset.dspSwipeBound = '1';

        const SWIPE_CLOSE_PX = 72;
        const SWIPE_VELOCITY = 0.4;
        const scrollEl = document.getElementById('whDspLinesScroll');
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
            setTimeout(resetDspSwipeStyles, 240);
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
        await Promise.all([loadProducts(), loadStores()]);
        els.createForm?.reset();
        if (els.productFilter) els.productFilter.value = '';
        if (els.lineItems) els.lineItems.innerHTML = '';
        state.lineIndex = 0;
        clearFormError();
        document.getElementById('whDspMetaWrap')?.removeAttribute('open');
        toggleDestFields();
        ensureFormWarehouse();
        addLineRow();
        updateLineSummary();
        openModal(els.createModal);
        els.formWarehouse?.focus();
    }

    async function submitCreate(e) {
        e.preventDefault();
        if (state.submitting || !els.createForm) return;
        clearFormError();
        if (!ensureFormWarehouse() || !ensureFormDestination()) return;
        if (!els.createForm.reportValidity()) return;

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
        const destType = form.dest_type?.value;
        const payload = {
            from_warehouse_id: Number(form.from_warehouse_id?.value),
            to_store_id: destType === 'store' ? Number(form.to_store_id?.value) || null : null,
            to_warehouse_id: destType === 'warehouse' ? Number(form.to_warehouse_id?.value) || null : null,
            driver_name: form.driver_name?.value?.trim() || null,
            vehicle_number: form.vehicle_number?.value?.trim() || null,
            delivery_date: form.delivery_date?.value || null,
            notes: form.notes?.value?.trim() || null,
            status: 'draft',
            items,
        };

        state.submitting = true;
        try {
            const res = await AdminAPI.createWmsDispatch(payload);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            closeModal(els.createModal);
            hideError();
            toast(t('wh_dsp_toast_created'));
            state.page = 1;
            await load();
            if (res.data?.id) openDetail(res.data.id);
        } catch (err) {
            notifyFormError(err.message || t('error'));
        } finally {
            state.submitting = false;
        }
    }

    async function openDetail(id) {
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        openModal(els.detailModal);
        try {
            const res = await AdminAPI.getWmsDispatch(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const d = res.data;
            if (els.detailTitle) els.detailTitle.textContent = `${t('wms_dispatch_details')} — ${d.dispatch_number}`;
            if (els.detailSubtitle) els.detailSubtitle.textContent = destinationLabel(d);
            const items = d.items || [];
            const canShip = canDispatch && ['draft', 'picking', 'packed'].includes(d.status);
            els.detailBody.innerHTML = `
                <dl class="wh-dsp-detail-grid">
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(d.from_warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_destination'))}</dt><dd>${esc(d.to_store_name || d.to_warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(d.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(d.total_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_driver'))}</dt><dd>${esc(d.driver_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_vehicle'))}</dt><dd>${esc(d.vehicle_number || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_delivery_date'))}</dt><dd>${esc(d.delivery_date || '—')}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(d.created_at))}</dd></div>
                </dl>
                ${d.notes ? `<p class="wh-dsp-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(d.notes)}</p>` : ''}
                <div class="wh-dsp-detail-table-wrap">
                    <table class="modern-table wh-table">
                        <thead><tr>
                            <th>${esc(t('wms_col_product'))}</th>
                            <th>${esc(t('wms_col_sku'))}</th>
                            <th>${esc(t('wms_col_qty'))}</th>
                            <th>${esc(t('wms_unit_cost'))}</th>
                        </tr></thead>
                        <tbody>${items.map((i) => `<tr>
                            <td>${esc(i.product_name)}</td>
                            <td>${esc(i.sku || '—')}</td>
                            <td>${i.quantity}</td>
                            <td>${esc(money(i.unit_cost))}</td>
                        </tr>`).join('')}</tbody>
                    </table>
                </div>
                ${canShip ? `<div class="wh-dsp-detail-actions">
                    <button type="button" class="wh-btn wh-btn--primary" id="whDspDetailShip" data-id="${d.id}">${esc(t('wms_dispatch_btn'))}</button>
                </div>` : ''}`;
            document.getElementById('whDspDetailShip')?.addEventListener('click', () => {
                shipDispatch(Number(d.id), true);
            });
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-dsp-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function shipDispatch(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_dispatch'))) return;
        const res = await AdminAPI.dispatchWmsOut(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_dsp_toast_dispatched'));
        if (fromDetail) closeModal(els.detailModal);
        await load();
    }

    els.refresh?.addEventListener('click', load);
    els.exportBtn?.addEventListener('click', exportData);
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

    els.newBtn?.addEventListener('click', openCreateModal);
    els.addLine?.addEventListener('click', () => loadProducts().then(addLineRow));
    document.querySelector('[data-trigger-add-line]')?.addEventListener('click', () => loadProducts().then(addLineRow));
    els.productFilter?.addEventListener('input', refreshProductPickers);
    els.destType?.addEventListener('change', toggleDestFields);
    els.createForm?.addEventListener('submit', submitCreate);
    bindDspSwipeDismiss();
    els.createClose?.addEventListener('click', () => closeModal(els.createModal));
    els.createCancel?.addEventListener('click', () => closeModal(els.createModal));
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.createModal?.addEventListener('click', (e) => {
        if (e.target === els.createModal) closeModal(els.createModal);
    });
    els.detailModal?.addEventListener('click', (e) => {
        if (e.target === els.detailModal) closeModal(els.detailModal);
    });

    document.addEventListener('wh:refresh', load);

    async function initWarehouses() {
        const res = await AdminAPI.getWmsWarehouses({ limit: 200 });
        state.warehouses = res.status === 'success' ? (res.data || []) : [];
        await loadWarehouseOptions(els.warehouse);
        const cur = String(window.WH_PAGE?.warehouseId || '');
        if (els.formWarehouse) {
            els.formWarehouse.innerHTML = state.warehouses.map((w) =>
                `<option value="${w.id}">${esc(w.name)}</option>`
            ).join('');
            if (cur && state.warehouses.some((w) => String(w.id) === cur)) {
                els.formWarehouse.value = cur;
            }
        }
        if (els.formWhDest) {
            els.formWhDest.innerHTML = `<option value="">${esc(t('wms_select_warehouse'))}</option>` +
                state.warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
        }
    }

    initWarehouses().then(load);
});
