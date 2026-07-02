/**
 * Warehouse goods receipts (GRN) — receive stock into warehouse inventory
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whGrnTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canReceive = !!window.WH_PAGE?.canReceive && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
        pending: 'wms_status_pending',
        inspecting: 'wms_status_inspecting',
        accepted: 'wms_status_accepted',
        completed: 'wms_status_completed',
        rejected: 'wms_status_rejected',
    };
    const STATUS_ORDER = ['pending', 'inspecting', 'accepted', 'completed', 'rejected'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        warehouses: [],
        products: [],
        searchTimer: null,
        lineIndex: 0,
        submitting: false,
    };

    const els = {
        search: document.getElementById('whGrnSearch'),
        warehouse: document.getElementById('whGrnWarehouse'),
        status: document.getElementById('whGrnStatus'),
        refresh: document.getElementById('whGrnRefreshBtn'),
        exportBtn: document.getElementById('whGrnExportBtn'),
        newBtn: document.getElementById('whGrnNewBtn'),
        heroMeta: document.getElementById('whGrnHeroMeta'),
        breakdownPanel: document.getElementById('whGrnBreakdownPanel'),
        statusChips: document.getElementById('whGrnStatusChips'),
        statTotal: document.getElementById('whGrnStatTotal'),
        statPending: document.getElementById('whGrnStatPending'),
        statCompleted: document.getElementById('whGrnStatCompleted'),
        statValue: document.getElementById('whGrnStatValue'),
        loading: document.getElementById('whGrnLoading'),
        empty: document.getElementById('whGrnEmpty'),
        pagination: document.getElementById('whGrnPagination'),
        prev: document.getElementById('whGrnPrev'),
        next: document.getElementById('whGrnNext'),
        pageMeta: document.getElementById('whGrnPageMeta'),
        createModal: document.getElementById('whGrnCreateModal'),
        createClose: document.getElementById('whGrnCreateClose'),
        createCancel: document.getElementById('whGrnCreateCancel'),
        createForm: document.getElementById('whGrnCreateForm'),
        formWarehouse: document.getElementById('whGrnFormWarehouse'),
        addLine: document.getElementById('whGrnAddLine'),
        productFilter: document.getElementById('whGrnProductFilter'),
        lineItems: document.getElementById('whGrnLineItems'),
        linesEmpty: document.getElementById('whGrnLinesEmpty'),
        lineCount: document.getElementById('whGrnLineCount'),
        estTotal: document.getElementById('whGrnEstTotal'),
        formError: document.getElementById('whGrnFormError'),
        detailModal: document.getElementById('whGrnDetailModal'),
        detailClose: document.getElementById('whGrnDetailClose'),
        detailTitle: document.getElementById('whGrnDetailTitle'),
        detailBody: document.getElementById('whGrnDetailBody'),
        toast: document.getElementById('whGrnToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-grn-toast show${type === 'error' ? ' wh-grn-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function clearFormError() {
        if (els.formError) {
            els.formError.hidden = true;
            els.formError.textContent = '';
        }
        hideError();
    }

    function notifyFormError(msg) {
        const text = msg || t('error');
        if (els.formError) {
            els.formError.textContent = text;
            els.formError.hidden = false;
        }
        showError(text);
        toast(text, 'error');
    }

    function ensureFormWarehouse() {
        if (!els.formWarehouse) return '';
        const options = [...els.formWarehouse.options].filter((o) => o.value);
        const preferred = String(window.WH_PAGE?.warehouseId || els.formWarehouse.value || '');
        if (preferred && options.some((o) => o.value === preferred)) {
            els.formWarehouse.value = preferred;
            return preferred;
        }
        if (options.length === 1) {
            els.formWarehouse.value = options[0].value;
            return options[0].value;
        }
        return els.formWarehouse.value?.trim() || '';
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = {
            completed: 'ok',
            rejected: 'off',
            accepted: 'ok',
            inspecting: 'warn',
            pending: 'warn',
        }[status] || 'warn';
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
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
        document.querySelectorAll('.wh-grn-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary, overview) {
        const s = overview || summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statPending) els.statPending.textContent = String(s.pending ?? 0);
        if (els.statCompleted) els.statCompleted.textContent = String(s.completed ?? 0);
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        if (els.heroMeta) {
            const scope = els.warehouse?.value
                ? els.warehouse.options[els.warehouse.selectedIndex]?.text
                : (window.WH_PAGE?.warehouseName || '');
            const scopeText = scope ? `${scope} · ` : '';
            els.heroMeta.textContent = `${scopeText}${t('wh_grn_hero_meta', s.pending ?? 0, s.completed ?? 0)}`;
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
            return `<button type="button" class="wh-grn-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-grn-status-chip').forEach((btn) => {
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-grn-list-table">
<thead><tr>
    <th class="wh-grn-col--grn">${esc(t('wms_col_grn'))}</th>
    <th class="wh-grn-col--wh">${esc(t('wms_nav_warehouses'))}</th>
    <th class="wh-grn-col--supplier">${esc(t('wms_col_supplier'))}</th>
    <th class="wh-grn-col--items">${esc(t('wms_col_items'))}</th>
    <th class="wh-grn-col--value">${esc(t('wms_col_value'))}</th>
    <th class="wh-grn-col--date">${esc(t('col_date'))}</th>
    <th class="wh-grn-col--status">${esc(t('col_status'))}</th>
    <th class="wh-grn-col--actions" aria-label="${esc(t('wms_view_details'))}"></th>
</tr></thead>
<tbody>${items.map((r) => {
    const canComplete = canReceive && r.status !== 'completed' && r.status !== 'rejected';
    return `<tr class="wh-grn-list-row">
        <td class="wh-grn-col--grn"><strong>${esc(r.grn_number)}</strong></td>
        <td class="wh-grn-col--wh">${esc(r.warehouse_name || '—')}</td>
        <td class="wh-grn-col--supplier">${esc(r.supplier_name || '—')}</td>
        <td class="wh-grn-col--items">${Number(r.total_items || 0)}</td>
        <td class="wh-grn-col--value">${esc(money(r.total_value))}</td>
        <td class="wh-grn-col--date">${esc(formatDate(r.received_at))}</td>
        <td class="wh-grn-col--status">${statusBadge(r.status)}</td>
        <td class="wh-grn-col--actions wh-grn-row-actions">
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-grn-view="${r.id}">${esc(t('wms_view_details'))}</button>
            ${canComplete ? `<button type="button" class="wh-btn wh-btn--sm" data-grn-complete="${r.id}">${esc(t('wms_complete'))}</button>` : ''}
        </td>
    </tr>`;
}).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-grn-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.grnView)));
        });
        tableWrap.querySelectorAll('[data-grn-complete]').forEach((btn) => {
            btn.addEventListener('click', () => completeReceipt(Number(btn.dataset.grnComplete)));
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
            const res = await AdminAPI.getWmsReceipts(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = res.total ?? state.items.length;
            state.summary = res.summary || null;
            state.breakdown = res.breakdown || [];
            renderStats(state.summary, res.summary_overview);
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
            [t('wms_col_grn'), t('wms_nav_warehouses'), t('wms_col_supplier'), t('wms_col_items'), t('wms_col_value'), t('col_date'), t('col_status')],
            ...items.map((r) => [r.grn_number, r.warehouse_name, r.supplier_name, r.total_items, r.total_value, r.received_at, r.status]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsReceipts(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`goods-receipts-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    function openModal(el) {
        if (!el) return;
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
    }

    function resetGrnSwipeStyles(overlay, panel) {
        if (!panel) return;
        panel.classList.remove('is-dragging');
        panel.style.transition = '';
        panel.style.transform = '';
        if (overlay) {
            overlay.style.transition = '';
            overlay.style.opacity = '';
        }
    }

    function closeModal(el) {
        if (!el) return;
        const panel = el.querySelector('.wh-grn-modal');
        resetGrnSwipeStyles(el, panel);
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
    }

    function bindGrnSwipeDismiss(overlay, panel) {
        if (!overlay || !panel) return;

        const SWIPE_CLOSE_PX = 72;
        const SWIPE_VELOCITY = 0.4;
        const scrollEl = document.getElementById('whGrnLinesScroll');
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
            setTimeout(() => resetGrnSwipeStyles(overlay, panel), 240);
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
            const isInteractive = target.closest('input, select, textarea, button, a, .wh-grn-product-dropdown');

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
                panel.style.transform = `translateY(${deltaY * 0.2}px)`;
                return;
            }

            currentY = deltaY;
            e.preventDefault();
            panel.style.transform = `translateY(${deltaY}px)`;
            overlay.style.opacity = String(Math.max(0.15, 1 - deltaY / 280));
        }

        function onTouchEnd() {
            if (!dragging || !canSwipe) return;

            const elapsed = Math.max(1, Date.now() - startTime);
            const velocity = currentY / elapsed;

            if (currentY >= SWIPE_CLOSE_PX || (currentY > 28 && velocity > SWIPE_VELOCITY)) {
                animateClose();
            } else {
                snapBack();
            }

            dragging = false;
            canSwipe = false;
            panel.classList.remove('is-dragging');
        }

        panel.addEventListener('touchstart', onTouchStart, { passive: true });
        panel.addEventListener('touchmove', onTouchMove, { passive: false });
        panel.addEventListener('touchend', onTouchEnd);
        panel.addEventListener('touchcancel', onTouchEnd);
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

    function normalizeProductName(name) {
        return String(name || '').trim().toLowerCase();
    }

    function productIdentityKey(productId, productName) {
        const id = parseInt(productId, 10);
        if (id > 0) return `id:${id}`;
        const name = normalizeProductName(productName);
        if (!name) return null;
        const match = findProductMatch(name);
        if (match) return `id:${match.id}`;
        return `name:${name}`;
    }

    function readRowProductIdentity(row) {
        if (!row) return { productId: '', productName: '', key: null };
        const idx = row.dataset.lineIdx;
        const productId = row.querySelector(`[name="product_id_${idx}"]`)?.value?.trim() || '';
        const productName = row.dataset.productName?.trim() || '';
        const inputVal = row.querySelector('.wh-grn-product-input')?.value?.trim() || '';
        const key = productIdentityKey(productId, productName || inputVal);
        return { productId, productName: productName || (!productId ? inputVal : ''), key };
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

    function isDuplicateProduct(row, productId, productName) {
        const key = productIdentityKey(productId, productName);
        if (!key) return false;
        const used = getUsedProductKeys(row);
        return used.has(key);
    }

    function saveValidSelection(picker, row) {
        if (!picker) return;
        const input = picker.querySelector('.wh-grn-product-input');
        const hidden = picker.querySelector('input[type="hidden"]');
        picker._lastValid = {
            inputValue: input?.value || '',
            productId: hidden?.value || '',
            productName: row?.dataset.productName || '',
        };
    }

    function rejectDuplicateSelection(picker, row) {
        toast(t('wms_product_duplicate'), 'error');
        picker?.classList.add('is-duplicate');
        const sel = picker?._lastValid || { inputValue: '', productId: '', productName: '' };
        const input = picker?.querySelector('.wh-grn-product-input');
        const hidden = picker?.querySelector('input[type="hidden"]');
        const badge = picker?.querySelector('.wh-grn-product-new');
        if (input) input.value = sel.inputValue;
        if (hidden) hidden.value = sel.productId;
        if (row) row.dataset.productName = sel.productName;
        if (badge) badge.hidden = !sel.productName || !!sel.productId;
        requestAnimationFrame(() => {
            input?.focus();
            input?.select();
        });
        return false;
    }

    function productPickerHtml(idx, data = {}, filter = '') {
        const selected = data.product_id
            ? state.products.find((p) => String(p.id) === String(data.product_id))
            : null;
        const initialValue = selected ? productLabel(selected) : (data.product_name || '');
        return `
            <div class="wh-grn-product-picker" data-picker-idx="${idx}">
                <div class="wh-grn-product-input-wrap">
                    <input type="text" class="wh-grn-product-input" value="${esc(initialValue)}" placeholder="${esc(t('wms_product_search_placeholder'))}" autocomplete="off" required>
                    <input type="hidden" name="product_id_${idx}" value="${selected ? esc(String(selected.id)) : ''}">
                    <span class="wh-grn-product-new" hidden title="${esc(t('wms_product_create_hint'))}">
                        <span class="wh-grn-product-new__badge">${esc(t('wms_product_new_badge'))}</span>
                        <span class="wh-grn-product-new__hint">${esc(t('wms_product_create_hint'))}</span>
                    </span>
                </div>
                <div class="wh-grn-product-dropdown" hidden></div>
            </div>`;
    }

    function resolveProductPicker(picker, options = {}) {
        const checkDuplicate = options.checkDuplicate !== false;
        if (!picker) return true;
        const input = picker.querySelector('.wh-grn-product-input');
        const hidden = picker.querySelector('input[type="hidden"]');
        const badge = picker.querySelector('.wh-grn-product-new');
        const row = picker.closest('.wh-grn-table__row');
        const q = input?.value.trim() || '';
        if (!q) {
            if (hidden) hidden.value = '';
            if (badge) badge.hidden = true;
            if (row) row.dataset.productName = '';
            picker.classList.remove('is-duplicate');
            saveValidSelection(picker, row);
            return true;
        }
        const match = findProductMatch(q);
        if (match) {
            if (hidden) hidden.value = String(match.id);
            if (badge) badge.hidden = true;
            if (row) row.dataset.productName = '';
            if (input && !input.value.includes('·') && match.sku) {
                input.value = productLabel(match);
            }
            if (checkDuplicate && isDuplicateProduct(row, match.id, '')) {
                return rejectDuplicateSelection(picker, row);
            }
            picker.classList.remove('is-duplicate');
            saveValidSelection(picker, row);
            return true;
        }
        const partial = filterProducts(q);
        const exactPartial = partial.find((p) => productLabel(p).toLowerCase() === q.toLowerCase());
        if (exactPartial) {
            if (hidden) hidden.value = String(exactPartial.id);
            if (badge) badge.hidden = true;
            if (row) row.dataset.productName = '';
            if (input) input.value = productLabel(exactPartial);
            if (checkDuplicate && isDuplicateProduct(row, exactPartial.id, '')) {
                return rejectDuplicateSelection(picker, row);
            }
            picker.classList.remove('is-duplicate');
            saveValidSelection(picker, row);
            return true;
        }
        if (hidden) hidden.value = '';
        if (badge) badge.hidden = false;
        if (row) row.dataset.productName = q;
        if (checkDuplicate && isDuplicateProduct(row, '', q)) {
            return rejectDuplicateSelection(picker, row);
        }
        picker.classList.remove('is-duplicate');
        saveValidSelection(picker, row);
        return true;
    }

    function renderProductDropdown(picker, query) {
        const dropdown = picker.querySelector('.wh-grn-product-dropdown');
        const input = picker.querySelector('.wh-grn-product-input');
        if (!dropdown || !input) return;
        const mainRow = picker.closest('.wh-grn-table__row');
        const usedKeys = getUsedProductKeys(mainRow);
        const q = query.trim();
        const matches = filterProducts(q).filter((p) => !usedKeys.has(`id:${p.id}`));
        let html = matches.map((p) =>
            `<button type="button" class="wh-grn-product-option" data-id="${p.id}" data-cost="${p.cost_price || p.cost || p.price || 0}">
                <span class="wh-grn-product-option__name">${esc(p.name)}</span>
                ${p.sku ? `<span class="wh-grn-product-option__sku">${esc(p.sku)}</span>` : ''}
            </button>`
        ).join('');
        const nameKey = q.length >= 2 ? productIdentityKey('', q) : null;
        if (q.length >= 2 && !findProductMatch(q) && nameKey && !usedKeys.has(nameKey)) {
            html += `<button type="button" class="wh-grn-product-option wh-grn-product-option--new" data-new-name="${esc(q)}">
                <span class="material-icons-round">add_circle_outline</span>
                <span>${esc(t('wms_product_new_badge'))}: ${esc(q)}</span>
            </button>`;
        }
        dropdown.innerHTML = html || `<p class="wh-grn-product-empty">${esc(t('wms_select_product'))}</p>`;
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
            if (!opt || opt.classList.contains('wh-grn-product-option--used')) return;
            e.preventDefault();
            if (opt.dataset.newName) {
                input.value = opt.dataset.newName;
            } else {
                const product = state.products.find((p) => String(p.id) === String(opt.dataset.id));
                if (product) {
                    input.value = productLabel(product);
                    const costInput = mainRow.querySelector(`[name="cost_${idx}"]`);
                    if (costInput && !costInput.value && opt.dataset.cost) {
                        costInput.value = opt.dataset.cost;
                    }
                }
            }
            if (!resolveProductPicker(picker)) return;
            updateLineSummary();
            closeDropdown();
        });
    }

    function refreshProductSelects() {
        const filter = els.productFilter?.value || '';
        els.lineItems?.querySelectorAll('.wh-grn-product-picker').forEach((picker) => {
            const input = picker.querySelector('.wh-grn-product-input');
            const hidden = picker.querySelector('input[type="hidden"]');
            if (!input) return;
            const curId = hidden?.value || '';
            if (curId) {
                const p = state.products.find((pr) => String(pr.id) === String(curId));
                if (p && filter) {
                    const hay = `${p.name || ''} ${p.sku || ''}`.toLowerCase();
                    if (!hay.includes(filter.trim().toLowerCase())) return;
                }
            } else if (filter && input.value && !input.value.toLowerCase().includes(filter.trim().toLowerCase())) {
                return;
            }
            resolveProductPicker(picker);
        });
    }

    function getTrackingRow(mainRow) {
        const next = mainRow.nextElementSibling;
        return next?.classList.contains('wh-grn-table__tracking-row') ? next : null;
    }

    function getTrackingValues(mainRow, idx) {
        const trackingRow = getTrackingRow(mainRow);
        if (!trackingRow) return { batch: '', expiry: '' };
        return {
            batch: trackingRow.querySelector(`[name="batch_${idx}"]`)?.value?.trim() || '',
            expiry: trackingRow.querySelector(`[name="expiry_${idx}"]`)?.value?.trim() || '',
        };
    }

    function updateTrackingUI(mainRow, idx) {
        const trackingRow = getTrackingRow(mainRow);
        const btn = mainRow.querySelector('[data-toggle-tracking]');
        const dot = mainRow.querySelector('.wh-grn-track-dot');
        const closeBtn = trackingRow?.querySelector('[data-close-tracking]');
        if (!trackingRow || !btn) return;
        const { batch, expiry } = getTrackingValues(mainRow, idx);
        const hasData = !!(batch || expiry);
        const isOpen = trackingRow.classList.contains('is-open');
        btn.classList.toggle('is-open', isOpen);
        btn.classList.toggle('has-data', hasData);
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        btn.title = hasData && !isOpen
            ? `${t('wms_line_tracking')} — ${t('wms_tracking_has_data')}`
            : t('wms_line_tracking');
        if (dot) dot.hidden = !hasData || isOpen;
        if (closeBtn) {
            closeBtn.classList.toggle('is-active', isOpen);
            closeBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            closeBtn.disabled = !isOpen;
        }
        trackingRow.classList.toggle('has-data', hasData);
    }

    function setTrackingOpen(mainRow, open) {
        const trackingRow = getTrackingRow(mainRow);
        const idx = mainRow.dataset.lineIdx;
        if (!trackingRow) return;
        if (!open && trackingRow.classList.contains('is-open')) {
            trackingRow.classList.add('is-closing');
            trackingRow.classList.remove('is-open');
            updateTrackingUI(mainRow, idx);
            clearTimeout(trackingRow._closeTimer);
            trackingRow._closeTimer = setTimeout(() => {
                trackingRow.classList.remove('is-closing');
                updateTrackingUI(mainRow, idx);
            }, 200);
            return;
        }
        trackingRow.classList.remove('is-closing');
        clearTimeout(trackingRow._closeTimer);
        trackingRow.classList.toggle('is-open', open);
        updateTrackingUI(mainRow, idx);
        if (open) {
            requestAnimationFrame(() => {
                trackingRow.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                trackingRow.querySelector('input')?.focus();
            });
        }
    }

    function toggleTracking(mainRow) {
        const trackingRow = getTrackingRow(mainRow);
        if (!trackingRow) return;
        setTrackingOpen(mainRow, !trackingRow.classList.contains('is-open'));
    }

    function syncGrnLayout(rowsCount) {
        const modal = els.createModal?.querySelector('.wh-grn-modal');
        const titleEl = document.getElementById('whGrnLinesTitle');
        const baseTitle = t('wms_grn_section_lines');
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
            const lineTotal = qty * cost;
            total += lineTotal;
            const subEl = row.querySelector('.wh-grn-table__subtotal');
            if (subEl) subEl.textContent = money(lineTotal);
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
        syncGrnLayout(rows.length);
    }

    function bindLineInputs(mainRow, idx, trackingRow = null) {
        mainRow.querySelectorAll('input:not(.wh-grn-product-input), select').forEach((el) => {
            el.addEventListener('input', updateLineSummary);
            el.addEventListener('change', updateLineSummary);
        });
        const picker = mainRow.querySelector('.wh-grn-product-picker');
        bindProductPicker(picker, idx, mainRow);
        const trackRow = trackingRow || getTrackingRow(mainRow);
        if (trackRow) {
            trackRow.querySelectorAll('input').forEach((el) => {
                el.addEventListener('input', () => {
                    updateTrackingUI(mainRow, idx);
                    updateLineSummary();
                });
                el.addEventListener('change', () => {
                    updateTrackingUI(mainRow, idx);
                    updateLineSummary();
                });
            });
            updateTrackingUI(mainRow, idx);
        }
        mainRow.querySelector('[data-remove-line]')?.addEventListener('click', () => {
            if ((els.lineItems?.querySelectorAll('.wh-grn-table__row').length || 0) <= 1) return;
            trackRow?.remove();
            mainRow.remove();
            updateLineSummary();
        });
    }

    function handleLineItemsClick(e) {
        const toggleBtn = e.target.closest('[data-toggle-tracking]');
        if (toggleBtn) {
            const mainRow = toggleBtn.closest('.wh-grn-table__row');
            if (!mainRow || !els.lineItems?.contains(mainRow)) return;
            e.preventDefault();
            e.stopPropagation();
            toggleTracking(mainRow);
            return;
        }
        const closeBtn = e.target.closest('[data-close-tracking]');
        if (!closeBtn) return;
        e.preventDefault();
        e.stopPropagation();
        const trackingRow = closeBtn.closest('.wh-grn-table__tracking-row');
        const mainRow = trackingRow?.previousElementSibling;
        if (!mainRow?.classList.contains('wh-grn-table__row')) return;
        setTrackingOpen(mainRow, false);
        mainRow.querySelector('[data-toggle-tracking]')?.focus({ preventScroll: true });
    }

    function addLineRow(data = {}) {
        if (!els.lineItems) return;
        const idx = state.lineIndex++;
        const filter = els.productFilter?.value || '';
        const lineNo = els.lineItems.querySelectorAll('.wh-grn-table__row').length + 1;
        const hasTracking = !!(data.batch_number || data.expiry_date);

        const mainRow = document.createElement('tr');
        mainRow.className = 'wh-grn-table__row wh-grn-table__row--enter';
        mainRow.dataset.lineIdx = String(idx);
        mainRow.innerHTML = `
            <td class="wh-grn-td--num" data-label="#">
                <span class="wh-grn-table__num">${lineNo}</span>
            </td>
            <td class="wh-grn-td--product" data-label="${esc(t('wms_col_product'))}">
                ${productPickerHtml(idx, data, filter)}
            </td>
            <td class="wh-grn-td--qty" data-label="${esc(t('wms_qty_short'))}">
                <input type="number" name="qty_${idx}" min="1" value="${data.quantity_received || 1}" required inputmode="numeric">
            </td>
            <td class="wh-grn-td--cost" data-label="${esc(t('wms_unit_cost'))}">
                <input type="number" name="cost_${idx}" min="0" step="0.01" value="${data.unit_cost || ''}" placeholder="0" required inputmode="decimal">
            </td>
            <td class="wh-grn-td--sub" data-label="${esc(t('wms_line_subtotal'))}">
                <span class="wh-grn-table__subtotal">${money(0)}</span>
            </td>
            <td class="wh-grn-td--act">
                <div class="wh-grn-table__act">
                    <button type="button" class="wh-grn-table__btn wh-grn-table__btn--track${hasTracking ? ' is-open has-data' : ''}" data-toggle-tracking aria-expanded="${hasTracking ? 'true' : 'false'}" title="${esc(t('wms_line_tracking'))}">
                        <span class="material-icons-round">inventory_2</span>
                        <span class="wh-grn-track-dot" ${hasTracking ? '' : 'hidden'} aria-hidden="true"></span>
                    </button>
                    <button type="button" class="wh-grn-table__btn wh-grn-table__btn--remove" data-remove-line aria-label="${esc(t('wms_remove_line'))}">
                        <span class="material-icons-round">delete_outline</span>
                    </button>
                </div>
            </td>`;

        const trackingRow = document.createElement('tr');
        trackingRow.className = `wh-grn-table__tracking-row${hasTracking ? ' is-open' : ''}`;
        trackingRow.dataset.trackingFor = String(idx);
        trackingRow.innerHTML = `
            <td colspan="6">
                <div class="wh-grn-tracking-panel">
                    <div class="wh-grn-tracking-panel__head">
                        <span class="wh-grn-tracking-panel__title">
                            <span class="material-icons-round">inventory_2</span>
                            ${esc(t('wms_line_tracking'))}
                        </span>
                        <button type="button" class="wh-grn-tracking-panel__close" data-close-tracking aria-label="${esc(t('wms_tracking_close'))}">
                            <span class="material-icons-round">expand_less</span>
                            <span>${esc(t('wms_tracking_close'))}</span>
                        </button>
                    </div>
                    <div class="wh-grn-tracking">
                        <label class="wh-grn-field">
                            <span>${esc(t('wms_batch_optional'))}</span>
                            <input type="text" name="batch_${idx}" value="${esc(data.batch_number || '')}" placeholder="—" autocomplete="off">
                        </label>
                        <label class="wh-grn-field">
                            <span>${esc(t('wms_expiry_optional'))}</span>
                            <input type="date" name="expiry_${idx}" value="${esc(data.expiry_date || '')}">
                        </label>
                    </div>
                </div>
            </td>`;

        els.lineItems.appendChild(mainRow);
        els.lineItems.appendChild(trackingRow);
        bindLineInputs(mainRow, idx, trackingRow);
        saveValidSelection(mainRow.querySelector('.wh-grn-product-picker'), mainRow);
        updateLineSummary();

        const scrollEl = document.getElementById('whGrnLinesScroll');
        requestAnimationFrame(() => {
            mainRow.classList.remove('wh-grn-table__row--enter');
            if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight;
            mainRow.querySelector('.wh-grn-product-input')?.focus();
        });
    }

    async function openCreateModal() {
        await loadProducts();
        els.createForm?.reset();
        if (els.productFilter) els.productFilter.value = '';
        if (els.lineItems) els.lineItems.innerHTML = '';
        state.lineIndex = 0;
        clearFormError();
        document.getElementById('whGrnMetaWrap')?.removeAttribute('open');
        ensureFormWarehouse();
        addLineRow();
        updateLineSummary();
        openModal(els.createModal);
        els.formWarehouse?.focus();
    }

    function readLineItem(row) {
        const idx = row.dataset.lineIdx;
        resolveProductPicker(row.querySelector('.wh-grn-product-picker'), { checkDuplicate: false });
        const productId = row.querySelector(`[name="product_id_${idx}"]`)?.value?.trim() || '';
        const inputName = row.querySelector('.wh-grn-product-input')?.value?.trim() || '';
        const productName = row.dataset.productName?.trim() || (!productId ? inputName : '');
        const qty = parseInt(row.querySelector(`[name="qty_${idx}"]`)?.value, 10);
        const cost = parseFloat(row.querySelector(`[name="cost_${idx}"]`)?.value);
        const trackingRow = getTrackingRow(row);
        const batch = trackingRow?.querySelector(`[name="batch_${idx}"]`)?.value?.trim();
        const expiry = trackingRow?.querySelector(`[name="expiry_${idx}"]`)?.value;
        return { idx, productId, productName, qty, cost, batch, expiry };
    }

    async function submitCreate(e) {
        e.preventDefault();
        if (state.submitting) return;
        clearFormError();
        const form = e.target;
        const wh = ensureFormWarehouse();
        if (!wh) {
            notifyFormError(t('wms_select_warehouse'));
            els.formWarehouse?.focus();
            return;
        }
        const items = [];
        const seenKeys = new Set();
        let hasDuplicate = false;
        let missingProduct = false;
        els.lineItems?.querySelectorAll('.wh-grn-table__row').forEach((row) => {
            const { idx, productId, productName, qty, cost, batch, expiry } = readLineItem(row);
            if ((!productId && !productName) || !qty || qty < 1) {
                if (!productId && !productName) missingProduct = true;
                return;
            }
            const key = productIdentityKey(productId, productName);
            if (key) {
                if (seenKeys.has(key)) {
                    hasDuplicate = true;
                    row.querySelector('.wh-grn-product-picker')?.classList.add('is-duplicate');
                    return;
                }
                seenKeys.add(key);
            }
            const item = {
                quantity_received: qty,
                unit_cost: Number.isFinite(cost) ? cost : 0,
                batch_number: batch || null,
                expiry_date: expiry || null,
            };
            if (productId) {
                item.product_id = Number(productId);
            } else {
                item.product_name = productName;
            }
            items.push(item);
        });
        if (hasDuplicate) {
            notifyFormError(t('wms_product_duplicate'));
            return;
        }
        if (!items.length) {
            notifyFormError(missingProduct ? t('wms_select_product') : t('wms_qty_received'));
            return;
        }
        const supplier = form.supplier_name?.value?.trim();
        let notes = form.notes?.value?.trim() || null;
        if (supplier) {
            const prefix = `${t('wms_col_supplier')}: ${supplier}`;
            notes = notes ? `${prefix}\n${notes}` : prefix;
        }
        const submitBtn = form.querySelector('[type="submit"]');
        state.submitting = true;
        if (submitBtn) submitBtn.disabled = true;
        try {
            const payload = {
                warehouse_id: Number(wh),
                notes,
                status: 'pending',
                items,
            };
            if (supplier) payload.supplier_name = supplier;
            const res = await AdminAPI.createWmsReceipt(payload);
            if (res.status !== 'success') {
                const msg = /duplicate product/i.test(res.message || '')
                    ? t('wms_product_duplicate')
                    : (res.message || t('error'));
                notifyFormError(msg);
                return;
            }
            closeModal(els.createModal);
            clearFormError();
            state.products = [];
            toast(t('wh_grn_toast_created'));
            state.page = 1;
            await load();
            if (res.data?.id) openDetail(res.data.id);
        } catch (err) {
            notifyFormError(err.message || t('error'));
        } finally {
            state.submitting = false;
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    async function openDetail(id) {
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        openModal(els.detailModal);
        try {
            const res = await AdminAPI.getWmsReceipt(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.detailTitle) els.detailTitle.textContent = `${t('wms_receipt_details')} — ${r.grn_number}`;
            const items = r.items || [];
            const poLink = r.purchase_order_id
                ? `<div><dt>${esc(t('wms_po_col_number'))}</dt><dd><a href="purchase_orders.php?po=${r.purchase_order_id}" class="wh-link">${esc(r.po_number || ('PO#' + r.purchase_order_id))}</a></dd></div>`
                : '';
            const inspectBadge = r.inspection_status && r.inspection_status !== 'pending'
                ? `<span class="cr-badge cr-badge--${r.inspection_status === 'failed' ? 'off' : 'ok'}">${esc(r.inspection_status)}</span>`
                : '';
            els.detailBody.innerHTML = `
                <dl class="wh-grn-detail-grid">
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_supplier'))}</dt><dd>${esc(r.supplier_name || '—')}</dd></div>
                    ${poLink}
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)} ${inspectBadge}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.total_value))}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.received_at))}</dd></div>
                    <div><dt>${esc(t('wms_col_received_by'))}</dt><dd>${esc(r.received_by_name || '—')}</dd></div>
                </dl>
                ${r.notes ? `<p class="wh-grn-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(r.notes)}</p>` : ''}
                <div class="wh-grn-detail-table-wrap"><table class="modern-table wh-table"><thead><tr>
                    <th>${esc(t('wms_col_product'))}</th><th>${esc(t('wms_col_sku'))}</th><th>${esc(t('wms_qty_received'))}</th>
                    <th>${esc(t('wms_unit_cost'))}</th><th>${esc(t('wms_line_subtotal'))}</th><th>${esc(t('wms_batch_optional'))}</th><th>${esc(t('wms_expiry_optional'))}</th>
                </tr></thead><tbody>${items.map((i) => `<tr>
                    <td>${esc(i.product_name)}</td><td>${esc(i.sku || '—')}</td><td>${i.quantity_received}</td>
                    <td>${esc(money(i.unit_cost))}</td><td>${esc(money((i.quantity_received || 0) * (i.unit_cost || 0)))}</td>
                    <td>${esc(i.batch_number || '—')}</td><td>${esc(i.expiry_date || '—')}</td>
                </tr>`).join('')}</tbody></table></div>
                ${renderDetailActions(r)}`;
            bindDetailActions(r);
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-grn-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    function renderDetailActions(r) {
        if (!canReceive || r.status === 'completed' || r.status === 'rejected') return '';
        const actions = [];
        if (r.status === 'pending') {
            actions.push(`<button type="button" class="wh-btn wh-btn--ghost" data-grn-action="inspect" data-id="${r.id}">${esc(t('wms_status_inspecting'))}</button>`);
        }
        if (r.status === 'inspecting' || r.status === 'accepted') {
            actions.push(`<button type="button" class="wh-btn wh-btn--ghost" data-grn-action="accept" data-id="${r.id}">${esc(t('wms_status_accepted'))}</button>`);
        }
        if (['pending', 'inspecting', 'accepted'].includes(r.status)) {
            actions.push(`<button type="button" class="wh-btn wh-btn--ghost" data-grn-action="reject" data-id="${r.id}">${esc(t('wms_status_rejected'))}</button>`);
        }
        actions.push(`<button type="button" class="wh-btn wh-btn--primary" data-grn-action="complete" data-id="${r.id}">${esc(t('wms_complete'))}</button>`);
        if (!actions.length) return '';
        return `<div class="wh-grn-detail-actions">${actions.join('')}</div>`;
    }

    function bindDetailActions(r) {
        els.detailBody?.querySelectorAll('[data-grn-action]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const id = Number(btn.dataset.id);
                const action = btn.dataset.grnAction;
                try {
                    if (action === 'complete') await completeReceipt(id, true);
                    else if (action === 'inspect') {
                        const res = await AdminAPI.inspectWmsReceipt(id);
                        if (res.status !== 'success') throw new Error(res.message);
                        toast(t('wms_status_inspecting'));
                        await openDetail(id);
                        await load();
                    } else if (action === 'accept') {
                        const res = await AdminAPI.acceptWmsReceipt(id);
                        if (res.status !== 'success') throw new Error(res.message);
                        toast(t('wms_status_accepted'));
                        await openDetail(id);
                        await load();
                    } else if (action === 'reject') {
                        if (!window.confirm(t('wms_status_rejected') + '?')) return;
                        const res = await AdminAPI.rejectWmsReceipt(id);
                        if (res.status !== 'success') throw new Error(res.message);
                        toast(t('wms_status_rejected'));
                        closeModal(els.detailModal);
                        await load();
                    }
                } catch (err) {
                    showError(err.message || t('error'));
                }
            });
        });
    }

    async function completeReceipt(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_complete'))) return;
        const res = await AdminAPI.completeWmsReceipt(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        if (fromDetail) closeModal(els.detailModal);
        toast(t('wh_grn_toast_completed'));
        state.page = 1;
        await load();
    }

    async function initWarehouses() {
        const res = await AdminAPI.getWmsWarehouses({ limit: 200 });
        state.warehouses = res.status === 'success' ? (res.data || []) : [];
        await loadWarehouseOptions(els.warehouse);
        if (els.formWarehouse) {
            const cur = String(window.WH_PAGE?.warehouseId || '');
            const placeholder = `<option value="">${esc(t('wms_select_warehouse'))}</option>`;
            els.formWarehouse.innerHTML = placeholder + state.warehouses.map((w) =>
                `<option value="${w.id}">${esc(w.name)}</option>`
            ).join('');
            if (cur && state.warehouses.some((w) => String(w.id) === cur)) {
                els.formWarehouse.value = cur;
            }
        }
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.status?.addEventListener('change', () => { state.page = 1; load(); });
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => { state.page = 1; load(); }, 350);
    });
    els.exportBtn?.addEventListener('click', exportData);
    els.newBtn?.addEventListener('click', openCreateModal);
    els.addLine?.addEventListener('click', () => loadProducts().then(addLineRow));
    document.querySelector('[data-trigger-add-line]')?.addEventListener('click', () => loadProducts().then(addLineRow));
    els.productFilter?.addEventListener('input', refreshProductSelects);
    els.lineItems?.addEventListener('click', handleLineItemsClick);
    els.createForm?.addEventListener('submit', submitCreate);
    els.createClose?.addEventListener('click', () => closeModal(els.createModal));
    els.createCancel?.addEventListener('click', () => closeModal(els.createModal));
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.createModal?.addEventListener('click', (e) => { if (e.target === els.createModal) closeModal(els.createModal); });
    bindGrnSwipeDismiss(els.createModal, els.createModal?.querySelector('.wh-grn-modal'));
    els.detailModal?.addEventListener('click', (e) => { if (e.target === els.detailModal) closeModal(els.detailModal); });
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        const pages = Math.ceil(state.total / state.limit);
        if (state.page < pages) { state.page += 1; load(); }
    });

    document.addEventListener('wh:refresh', load);
    document.addEventListener('store-switched', () => { state.page = 1; load(); });

    initWarehouses().then(async () => {
        await load();
        const grnId = parseInt(new URLSearchParams(window.location.search).get('grn') || '0', 10);
        if (grnId > 0) openDetail(grnId);
    });
});
