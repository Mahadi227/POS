/**
 * Warehouse inventory count (stock count / audits)
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whScTableWrap');
    if (!tableWrap) return;

    const cfg = window.WH_SC_CONFIG || {};
    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;

    const STATUS_KEYS = {
        draft: 'wms_status_draft',
        in_progress: 'wms_status_in_progress',
        pending_approval: 'wms_status_pending_approval',
        approved: 'wms_status_approved',
        rejected: 'wms_status_rejected',
    };

    const TYPE_KEYS = {
        cycle_count: 'wms_type_cycle_count',
        physical_count: 'wms_type_physical_count',
        spot_check: 'wms_type_spot_check',
    };

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        products: [],
        lineIndex: 0,
        searchTimer: null,
        submitting: false,
        detailId: null,
    };

    const els = {
        search: document.getElementById('whScSearch'),
        warehouse: document.getElementById('whScWarehouse'),
        type: document.getElementById('whScType'),
        status: document.getElementById('whScStatus'),
        refresh: document.getElementById('whScRefreshBtn'),
        exportBtn: document.getElementById('whScExportBtn'),
        newBtn: document.getElementById('whScNewBtn'),
        heroMeta: document.getElementById('whScHeroMeta'),
        breakdownPanel: document.getElementById('whScBreakdownPanel'),
        statusChips: document.getElementById('whScStatusChips'),
        statTotal: document.getElementById('whScStatTotal'),
        statOpen: document.getElementById('whScStatOpen'),
        statVariance: document.getElementById('whScStatVariance'),
        statCompleted: document.getElementById('whScStatCompleted'),
        loading: document.getElementById('whScLoading'),
        empty: document.getElementById('whScEmpty'),
        pagination: document.getElementById('whScPagination'),
        prev: document.getElementById('whScPrev'),
        next: document.getElementById('whScNext'),
        pageMeta: document.getElementById('whScPageMeta'),
        createModal: document.getElementById('whScCreateModal'),
        createClose: document.getElementById('whScCreateClose'),
        createCancel: document.getElementById('whScCreateCancel'),
        createForm: document.getElementById('whScCreateForm'),
        formError: document.getElementById('whScFormError'),
        formWarehouse: document.getElementById('whScFormWarehouse'),
        addLine: document.getElementById('whScAddLine'),
        productFilter: document.getElementById('whScProductFilter'),
        lineItems: document.getElementById('whScLineItems'),
        linesEmpty: document.getElementById('whScLinesEmpty'),
        lineCount: document.getElementById('whScLineCount'),
        totalCounted: document.getElementById('whScTotalCounted'),
        detailModal: document.getElementById('whScDetailModal'),
        detailClose: document.getElementById('whScDetailClose'),
        detailTitle: document.getElementById('whScDetailTitle'),
        detailSubtitle: document.getElementById('whScDetailSubtitle'),
        detailBody: document.getElementById('whScDetailBody'),
        detailActions: document.getElementById('whScDetailActions'),
        toast: document.getElementById('whScToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-sc-toast show${type === 'error' ? ' wh-sc-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function auditRef(id) {
        return `AUD-${String(id).padStart(5, '0')}`;
    }

    function statusLabel(s) {
        return t(STATUS_KEYS[s] || s) || s || '—';
    }

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function statusBadge(s) {
        const cls = s === 'approved' ? 'ok' : (s === 'rejected' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(s))}</span>`;
    }

    function varianceCell(val) {
        const n = Number(val || 0);
        const cls = n > 0 ? 'wh-sc-var--pos' : (n < 0 ? 'wh-sc-var--neg' : '');
        const sign = n > 0 ? '+' : '';
        return `<span class="wh-sc-variance ${cls}">${sign}${esc(money(n))}</span>`;
    }

    function qtyVarianceCell(n) {
        const v = Number(n || 0);
        if (v === 0) return '0';
        const cls = v > 0 ? 'wh-sc-var--pos' : 'wh-sc-var--neg';
        return `<span class="wh-sc-variance ${cls}">${v > 0 ? '+' : ''}${v.toLocaleString()}</span>`;
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return AdminAPI.formatDate(iso, { dateStyle: 'short', timeStyle: 'short' });
        } catch {
            return iso;
        }
    }

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function resetScSwipeStyles() {
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

    function closeModal(modal) {
        if (!modal) return;
        if (modal === els.createModal) {
            resetScSwipeStyles();
            clearFormError();
        }
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
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

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-sc-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statOpen) els.statOpen.textContent = String(s.open ?? 0);
        if (els.statVariance) els.statVariance.textContent = String(s.with_variance ?? 0);
        if (els.statCompleted) els.statCompleted.textContent = String(s.completed ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_sc_hero_meta', s.open ?? 0, s.with_variance ?? 0);
        }
        setStatsLoading(false);
    }

    function renderBreakdown(breakdown) {
        if (!els.statusChips || !els.breakdownPanel) return;
        const rows = breakdown || [];
        if (!rows.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        els.breakdownPanel.hidden = false;
        const cur = els.status?.value || 'all';
        els.statusChips.innerHTML = rows.map(({ status, count }) => {
            const isActive = cur === status ? ' is-active' : '';
            return `<button type="button" class="wh-sc-status-chip${isActive}" data-status="${esc(status)}">
                ${esc(statusLabel(status))} <strong>${count}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-sc-status-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                if (els.status) els.status.value = chip.dataset.status || 'all';
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
        const status = els.status?.value?.trim();
        if (status && status !== 'all') params.status = status;
        const auditType = els.type?.value?.trim();
        if (auditType && auditType !== 'all') params.audit_type = auditType;
        return params;
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

    function renderTable(items) {
        if (!items.length) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        tableWrap.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wh-sc-table"><thead><tr>
            <th>#</th>
            <th>${esc(t('wms_nav_warehouses'))}</th>
            <th>${esc(t('wms_col_audit_type'))}</th>
            <th>${esc(t('wms_col_items'))}</th>
            <th>${esc(t('wms_col_expected'))}</th>
            <th>${esc(t('wms_col_counted_value'))}</th>
            <th>${esc(t('wms_col_variance'))}</th>
            <th>${esc(t('col_date'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((r) => `<tr class="wh-sc-row" data-id="${esc(r.id)}">
            <td><strong>${esc(auditRef(r.id))}</strong></td>
            <td>${esc(r.warehouse_name || '—')}</td>
            <td>${esc(typeLabel(r.audit_type))}</td>
            <td>${Number(r.total_items || 0).toLocaleString()}</td>
            <td>${esc(money(r.expected_value))}</td>
            <td>${esc(money(r.counted_value))}</td>
            <td>${varianceCell(r.variance_value)}</td>
            <td class="wh-sc-date">${esc(formatDate(r.created_at))}</td>
            <td>${statusBadge(r.status)}</td>
            <td><button type="button" class="wh-btn wh-btn--ghost wh-btn--sm wh-sc-view" data-id="${esc(r.id)}" title="${esc(t('wms_view_details'))}">
                <span class="material-icons-round">visibility</span></button></td>
        </tr>`).join('')}</tbody></table></div>`;

        tableWrap.querySelectorAll('.wh-sc-row, .wh-sc-view').forEach((el) => {
            el.addEventListener('click', (ev) => {
                if (ev.target.closest('button') === null && !ev.currentTarget.classList.contains('wh-sc-view')) {
                    if (!ev.currentTarget.classList.contains('wh-sc-row')) return;
                }
                const id = Number(ev.currentTarget.dataset.id || ev.currentTarget.closest('[data-id]')?.dataset.id);
                if (id) openDetail(id);
            });
        });
    }

    async function load(resetPage = false) {
        hideError();
        if (resetPage) state.page = 1;
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getWmsAudits(buildParams());
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
            updateLastUpdated();
        } catch (err) {
            showError(err.message || t('load_error'));
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
            setStatsLoading(false);
        } finally {
            if (els.loading) els.loading.hidden = true;
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
                    <input type="text" class="wh-grn-product-input" value="${esc(initialValue)}" placeholder="${esc(t('wms_audit_product_search'))}" autocomplete="off" required>
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

    function bindProductPicker(picker) {
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

    function syncScLayout(rowsCount) {
        const modal = els.createModal?.querySelector('.wh-grn-modal');
        const titleEl = document.getElementById('whScLinesTitle');
        const baseTitle = t('wms_audit_section_lines');
        if (titleEl) {
            titleEl.textContent = rowsCount > 0 ? `${baseTitle} (${rowsCount})` : baseTitle;
        }
        if (modal) {
            modal.classList.toggle('wh-grn-modal--many-lines', rowsCount >= 8);
        }
    }

    function updateLineSummary() {
        const rows = els.lineItems?.querySelectorAll('.wh-grn-table__row') || [];
        let totalCounted = 0;
        rows.forEach((row) => {
            const idx = row.dataset.lineIdx;
            totalCounted += parseInt(row.querySelector(`[name="counted_qty_${idx}"]`)?.value, 10) || 0;
        });
        if (els.lineCount) els.lineCount.textContent = String(rows.length);
        if (els.totalCounted) els.totalCounted.textContent = String(totalCounted);
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
        syncScLayout(rows.length);
    }

    function bindLineInputs(mainRow, idx) {
        mainRow.querySelectorAll('input:not(.wh-grn-product-input)').forEach((el) => {
            el.addEventListener('input', updateLineSummary);
            el.addEventListener('change', updateLineSummary);
        });
        const picker = mainRow.querySelector('.wh-grn-product-picker');
        bindProductPicker(picker);
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
            <td class="wh-grn-td--qty" data-label="${esc(t('wms_col_counted_qty'))}">
                <input type="number" name="counted_qty_${idx}" min="0" step="1" value="${data.counted_qty ?? 0}" required inputmode="numeric">
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

        const scrollEl = document.getElementById('whScLinesScroll');
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
            const countedQty = Number(row.querySelector(`[name="counted_qty_${idx}"]`)?.value);
            if (productId && Number.isFinite(countedQty) && countedQty >= 0) {
                items.push({ product_id: Number(productId), counted_qty: countedQty });
            }
        });
        return { items, valid };
    }

    function bindScSwipeDismiss() {
        const overlay = els.createModal;
        const panel = overlay?.querySelector('.wh-grn-modal');
        if (!overlay || !panel || panel.dataset.scSwipeBound) return;
        panel.dataset.scSwipeBound = '1';

        const SWIPE_CLOSE_PX = 72;
        const SWIPE_VELOCITY = 0.4;
        const scrollEl = document.getElementById('whScLinesScroll');
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
            setTimeout(resetScSwipeStyles, 240);
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
        await loadProducts();
        els.createForm?.reset();
        if (els.productFilter) els.productFilter.value = '';
        if (els.lineItems) els.lineItems.innerHTML = '';
        state.lineIndex = 0;
        clearFormError();
        document.getElementById('whScMetaWrap')?.removeAttribute('open');
        ensureFormWarehouse();
        addLineRow();
        updateLineSummary();
        openModal(els.createModal);
        els.formWarehouse?.focus();
    }

    async function openDetail(id) {
        state.detailId = id;
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        if (els.detailActions) els.detailActions.hidden = true;
        openModal(els.detailModal);
        try {
            const res = await AdminAPI.getWmsAudit(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.detailTitle) els.detailTitle.textContent = auditRef(r.id);
            if (els.detailSubtitle) {
                els.detailSubtitle.textContent = [typeLabel(r.audit_type), r.warehouse_name, statusLabel(r.status)].filter(Boolean).join(' · ');
            }
            const items = r.items || [];
            els.detailBody.innerHTML = `
                <dl class="wh-sc-detail-grid">
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_audit_type'))}</dt><dd>${esc(typeLabel(r.audit_type))}</dd></div>
                    <div><dt>${esc(t('wms_col_expected'))}</dt><dd>${esc(money(r.expected_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_counted_value'))}</dt><dd>${esc(money(r.counted_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_variance'))}</dt><dd>${varianceCell(r.variance_value)}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at))}</dd></div>
                </dl>
                ${r.notes ? `<p class="wh-sc-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(r.notes)}</p>` : ''}
                ${items.length ? `<div class="cr-table-wrap wh-sc-detail-table"><table class="modern-table"><thead><tr>
                    <th>${esc(t('wms_col_product'))}</th>
                    <th>${esc(t('wms_col_system_qty'))}</th>
                    <th>${esc(t('wms_col_counted_qty'))}</th>
                    <th>${esc(t('wms_col_variance'))}</th>
                </tr></thead><tbody>${items.map((it) => `<tr>
                    <td>${esc(it.product_name)} <code class="wms-sku">${esc(it.sku || '')}</code></td>
                    <td>${Number(it.system_qty || 0).toLocaleString()}</td>
                    <td>${Number(it.counted_qty || 0).toLocaleString()}</td>
                    <td>${qtyVarianceCell(it.variance_qty)}</td>
                </tr>`).join('')}</tbody></table></div>` : ''}`;
            renderDetailActions(r);
        } catch (err) {
            els.detailBody.innerHTML = `<p class="wh-sc-empty-inline">${esc(err.message || t('load_error'))}</p>`;
        }
    }

    function renderDetailActions(row) {
        if (!els.detailActions) return;
        const actions = [];
        const isDraft = row.status === 'draft' || row.status === 'in_progress';
        const isPending = row.status === 'pending_approval';
        if (cfg.canCount && isDraft) {
            actions.push(`<button type="button" class="wh-btn wh-btn--primary wh-sc-action" data-action="submit" data-id="${row.id}">${esc(t('wms_submit_audit'))}</button>`);
        }
        if (cfg.canApprove && isPending) {
            actions.push(`<button type="button" class="wh-btn wh-btn--primary wh-sc-action" data-action="approve" data-id="${row.id}">${esc(t('wms_approve'))}</button>`);
            actions.push(`<button type="button" class="wh-btn wh-btn--ghost wh-sc-action" data-action="reject" data-id="${row.id}">${esc(t('wms_reject'))}</button>`);
        }
        if (!actions.length) {
            els.detailActions.hidden = true;
            return;
        }
        els.detailActions.innerHTML = `<div class="wms-grn-modal__actions">${actions.join('')}</div>`;
        els.detailActions.hidden = false;
        els.detailActions.querySelectorAll('.wh-sc-action').forEach((btn) => {
            btn.addEventListener('click', () => runAction(btn.dataset.action, Number(btn.dataset.id)));
        });
    }

    async function runAction(action, id) {
        const confirmKeys = {
            submit: 'wms_confirm_submit_audit',
            approve: 'wms_confirm_approve_audit',
            reject: 'wms_confirm_reject_audit',
        };
        if (confirmKeys[action] && !window.confirm(t(confirmKeys[action]))) return;
        const fns = {
            submit: AdminAPI.submitWmsAudit,
            approve: AdminAPI.approveWmsAudit,
            reject: AdminAPI.rejectWmsAudit,
        };
        try {
            const res = await fns[action](id);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            toast(t(`wh_sc_toast_${action}`) || t('save'));
            closeModal(els.detailModal);
            load();
        } catch (err) {
            toast(err.message || t('error'), 'error');
        }
    }

    async function submitCreate(ev) {
        ev.preventDefault();
        if (state.submitting || !els.createForm) return;
        clearFormError();
        if (!ensureFormWarehouse()) return;
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

        state.submitting = true;
        const fd = new FormData(els.createForm);
        try {
            const res = await AdminAPI.createWmsAudit({
                warehouse_id: Number(fd.get('warehouse_id')),
                audit_type: fd.get('audit_type'),
                notes: fd.get('notes') || null,
                items,
            });
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            toast(t('wh_sc_toast_created'));
            closeModal(els.createModal);
            if (els.warehouse && fd.get('warehouse_id')) {
                els.warehouse.value = String(fd.get('warehouse_id'));
            }
            await load(true);
            hideError();
        } catch (err) {
            notifyFormError(err.message || t('error'));
        } finally {
            state.submitting = false;
        }
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsAudits(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : [];
            if (!items.length) return;
            exportCsv(`inventory-count-${new Date().toISOString().slice(0, 10)}.csv`, [
                ['#', t('wms_nav_warehouses'), t('wms_col_audit_type'), t('wms_col_items'),
                    t('wms_col_expected'), t('wms_col_counted_value'), t('wms_col_variance'), t('col_date'), t('col_status')],
                ...items.map((r) => [
                    auditRef(r.id), r.warehouse_name, r.audit_type, r.total_items,
                    r.expected_value, r.counted_value, r.variance_value, r.created_at, r.status,
                ]),
            ]);
        } catch (err) {
            showError(err.message || t('load_error'));
        }
    }

    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => load(true), 320);
    });
    els.warehouse?.addEventListener('change', () => load(true));
    els.status?.addEventListener('change', () => load(true));
    els.type?.addEventListener('change', () => load(true));
    els.refresh?.addEventListener('click', () => load());
    els.exportBtn?.addEventListener('click', exportData);
    els.prev?.addEventListener('click', () => { state.page -= 1; load(); });
    els.next?.addEventListener('click', () => { state.page += 1; load(); });
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.createClose?.addEventListener('click', () => closeModal(els.createModal));
    els.createCancel?.addEventListener('click', () => closeModal(els.createModal));
    els.createModal?.addEventListener('click', (e) => { if (e.target === els.createModal) closeModal(els.createModal); });
    els.newBtn?.addEventListener('click', openCreateModal);
    els.addLine?.addEventListener('click', () => loadProducts().then(addLineRow));
    document.querySelector('[data-trigger-add-line]')?.addEventListener('click', () => loadProducts().then(addLineRow));
    els.productFilter?.addEventListener('input', refreshProductPickers);
    els.createForm?.addEventListener('submit', submitCreate);
    bindScSwipeDismiss();
    document.addEventListener('wh:refresh', () => load());

    loadWarehouseOptions(els.warehouse).then(() => {
        const whId = String(window.WH_PAGE?.warehouseId || '');
        if (whId && els.warehouse) els.warehouse.value = whId;
        return loadWarehouseOptions(els.formWarehouse);
    }).then(() => {
        if (window.WH_PAGE?.warehouseId && els.formWarehouse) {
            els.formWarehouse.value = String(window.WH_PAGE.warehouseId);
        }
        load(true);
    }).catch((err) => showError(err.message || t('load_error')));
});
