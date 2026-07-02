/**
 * Warehouse purchase orders — create, approve, receive via GRN
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whPoTableWrap');
    if (!tableWrap) return;

    const cfg = window.WH_PO_CONFIG || {};
    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;

    const STATUS_KEYS = {
        draft: 'wms_po_status_draft',
        pending: 'wms_po_status_pending',
        approved: 'wms_po_status_approved',
        partial: 'wms_po_status_partial',
        received: 'wms_po_status_received',
        cancelled: 'wms_po_status_cancelled',
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
        createStatus: 'pending',
    };

    const els = {
        search: document.getElementById('whPoSearch'),
        warehouse: document.getElementById('whPoWarehouse'),
        status: document.getElementById('whPoStatus'),
        refresh: document.getElementById('whPoRefreshBtn'),
        exportBtn: document.getElementById('whPoExportBtn'),
        newBtn: document.getElementById('whPoNewBtn'),
        heroMeta: document.getElementById('whPoHeroMeta'),
        breakdownPanel: document.getElementById('whPoBreakdownPanel'),
        statusChips: document.getElementById('whPoStatusChips'),
        statTotal: document.getElementById('whPoStatTotal'),
        statOpen: document.getElementById('whPoStatOpen'),
        statReceived: document.getElementById('whPoStatReceived'),
        statValue: document.getElementById('whPoStatValue'),
        loading: document.getElementById('whPoLoading'),
        empty: document.getElementById('whPoEmpty'),
        pagination: document.getElementById('whPoPagination'),
        prev: document.getElementById('whPoPrev'),
        next: document.getElementById('whPoNext'),
        pageMeta: document.getElementById('whPoPageMeta'),
        createModal: document.getElementById('whPoCreateModal'),
        createClose: document.getElementById('whPoCreateClose'),
        createCancel: document.getElementById('whPoCreateCancel'),
        createForm: document.getElementById('whPoCreateForm'),
        saveDraft: document.getElementById('whPoSaveDraft'),
        formWarehouse: document.getElementById('whPoFormWarehouse'),
        addLine: document.getElementById('whPoAddLine'),
        productFilter: document.getElementById('whPoProductFilter'),
        lineItems: document.getElementById('whPoLineItems'),
        linesEmpty: document.getElementById('whPoLinesEmpty'),
        lineCount: document.getElementById('whPoLineCount'),
        estTotal: document.getElementById('whPoEstTotal'),
        formError: document.getElementById('whPoFormError'),
        detailModal: document.getElementById('whPoDetailModal'),
        detailClose: document.getElementById('whPoDetailClose'),
        detailTitle: document.getElementById('whPoDetailTitle'),
        detailSubtitle: document.getElementById('whPoDetailSubtitle'),
        detailBody: document.getElementById('whPoDetailBody'),
        detailActions: document.getElementById('whPoDetailActions'),
        toast: document.getElementById('whPoToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-po-toast show${type === 'error' ? ' wh-po-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function statusLabel(s) {
        return t(STATUS_KEYS[s] || s) || s || '—';
    }

    function statusBadge(s) {
        const cls = s === 'received' ? 'ok' : (s === 'cancelled' ? 'off' : (s === 'approved' ? 'idle' : 'warn'));
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(s))}</span>`;
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
        document.querySelectorAll('.wh-po-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statOpen) els.statOpen.textContent = String(s.open ?? 0);
        if (els.statReceived) els.statReceived.textContent = String(s.received ?? 0);
        if (els.statValue) els.statValue.textContent = money(s.total_amount ?? 0);
        setStatsLoading(false);
    }

    function renderBreakdown(breakdown) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const items = breakdown || [];
        els.breakdownPanel.hidden = !items.length;
        const active = els.status?.value || 'all';
        els.statusChips.innerHTML = items.map((b) => {
            const st = b.status || '';
            const isActive = active === st ? ' is-active' : '';
            return `<button type="button" class="wh-po-status-chip${isActive}" data-status="${esc(st)}">
                <span>${esc(statusLabel(st))}</span><strong>${Number(b.count ?? 0).toLocaleString()}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-po-status-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                const st = chip.dataset.status || 'all';
                if (els.status) els.status.value = els.status.value === st ? 'all' : st;
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
        const st = els.status?.value?.trim();
        if (st && st !== 'all') params.status = st;
        return params;
    }

    function showWarehouseCol() {
        return !els.warehouse?.value?.trim();
    }

    function renderTable(items) {
        if (!items.length) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        const whCol = showWarehouseCol() ? `<th>${esc(t('wms_nav_warehouses'))}</th>` : '';
        tableWrap.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wh-po-table"><thead><tr>
            <th>${esc(t('wms_col_po'))}</th>
            <th>${esc(t('wms_col_supplier'))}</th>
            ${whCol}
            <th>${esc(t('wms_col_items'))}</th>
            <th>${esc(t('wms_po_qty_ordered'))}</th>
            <th>${esc(t('wms_po_qty_received'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('wms_po_expected_date'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((r) => {
            const whCell = showWarehouseCol() ? `<td>${esc(r.warehouse_name || '—')}</td>` : '';
            return `<tr class="wh-po-row" data-id="${esc(r.id)}">
            <td><strong>${esc(r.po_number)}</strong></td>
            <td>${esc(r.supplier_name || '—')}</td>
            ${whCell}
            <td>${Number(r.line_count ?? 0).toLocaleString()}</td>
            <td>${Number(r.total_qty_ordered ?? 0).toLocaleString()}</td>
            <td>${Number(r.total_qty_received ?? 0).toLocaleString()}</td>
            <td>${esc(money(r.total_amount))}</td>
            <td class="wh-po-date">${esc(r.expected_date || '—')}</td>
            <td>${statusBadge(r.status)}</td>
            <td><button type="button" class="wh-btn wh-btn--ghost wh-btn--sm wh-po-view" data-id="${esc(r.id)}" title="${esc(t('wms_view_details'))}">
                <span class="material-icons-round">visibility</span></button></td>
        </tr>`;
        }).join('')}</tbody></table></div>`;

        tableWrap.querySelectorAll('.wh-po-row, .wh-po-view').forEach((el) => {
            el.addEventListener('click', (ev) => {
                if (ev.target.closest('button') === null && !ev.currentTarget.classList.contains('wh-po-view')) {
                    if (!ev.currentTarget.classList.contains('wh-po-row')) return;
                }
                const id = ev.target.closest('[data-id]')?.dataset?.id || ev.currentTarget.dataset?.id;
                if (id) openDetail(Number(id));
            });
        });
    }

    function renderPagination() {
        const pages = Math.max(1, Math.ceil(state.total / state.limit));
        const show = state.total > state.limit;
        if (els.pagination) els.pagination.hidden = !show;
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= pages;
        if (els.pageMeta) {
            const from = state.total ? (state.page - 1) * state.limit + 1 : 0;
            const to = Math.min(state.page * state.limit, state.total);
            els.pageMeta.textContent = `${from}–${to} / ${state.total} ${t('records')}`;
        }
        if (els.heroMeta) {
            const wh = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
            els.heroMeta.textContent = `${wh} · ${state.total} ${t('records')}`;
        }
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getWmsPurchaseOrders(buildParams());
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
            if (els.breakdownPanel) els.breakdownPanel.hidden = true;
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    function resetPoSwipeStyles() {
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
        if (!el) return;
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
    }

    function closeModal(el) {
        if (!el) return;
        if (el === els.createModal) {
            resetPoSwipeStyles();
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

    function bindPoSwipeDismiss() {
        const overlay = els.createModal;
        const panel = overlay?.querySelector('.wh-grn-modal');
        if (!overlay || !panel || panel.dataset.poSwipeBound) return;
        panel.dataset.poSwipeBound = '1';

        const SWIPE_CLOSE_PX = 72;
        const SWIPE_VELOCITY = 0.4;
        const scrollEl = document.getElementById('whPoLinesScroll');
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
            setTimeout(resetPoSwipeStyles, 240);
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

    async function openDetail(id) {
        if (!id) return;
        if (els.detailBody) els.detailBody.innerHTML = `<p class="cr-empty">${esc(t('loading'))}</p>`;
        if (els.detailActions) els.detailActions.hidden = true;
        openModal(els.detailModal);
        try {
            const res = await AdminAPI.getWmsPurchaseOrder(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const po = res.data;
            if (els.detailTitle) els.detailTitle.textContent = po.po_number || t('wms_po_details');
            if (els.detailSubtitle) {
                els.detailSubtitle.textContent = [po.supplier_name, po.warehouse_name, statusLabel(po.status)].filter(Boolean).join(' · ');
            }
            const items = po.items || [];
            if (els.detailBody) {
                els.detailBody.innerHTML = `
                    <dl class="wh-po-detail-grid">
                        <div><dt>${esc(t('wms_col_supplier'))}</dt><dd>${esc(po.supplier_name || '—')}</dd></div>
                        <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(po.warehouse_name || '—')}</dd></div>
                        <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(po.status)}</dd></div>
                        <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(po.total_amount))}</dd></div>
                        <div><dt>${esc(t('wms_po_expected_date'))}</dt><dd>${esc(po.expected_date || '—')}</dd></div>
                        <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(po.created_at))}</dd></div>
                    </dl>
                    ${po.notes ? `<p class="wh-po-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(po.notes)}</p>` : ''}
                    <div class="cr-table-wrap"><table class="modern-table"><thead><tr>
                        <th>${esc(t('wms_col_product'))}</th>
                        <th>${esc(t('wms_po_qty_ordered'))}</th>
                        <th>${esc(t('wms_po_qty_received'))}</th>
                        <th>${esc(t('wms_unit_cost'))}</th>
                        <th>${esc(t('wms_line_subtotal'))}</th>
                    </tr></thead><tbody>${items.map((it) => `<tr>
                        <td><strong>${esc(it.product_name)}</strong><br><code class="wms-sku">${esc(it.sku || '—')}</code></td>
                        <td>${Number(it.quantity_ordered ?? 0).toLocaleString()}</td>
                        <td>${Number(it.quantity_received ?? 0).toLocaleString()}</td>
                        <td>${esc(money(it.unit_cost))}</td>
                        <td>${esc(money(it.line_total))}</td>
                    </tr>`).join('')}</tbody></table></div>`;
            }
            renderDetailActions(po);
        } catch (err) {
            if (els.detailBody) els.detailBody.innerHTML = `<p class="cr-empty">${esc(err.message || t('load_error'))}</p>`;
        }
    }

    function renderDetailActions(po) {
        if (!els.detailActions || !po) return;
        const actions = [];
        if (cfg.canPo && po.status === 'draft') {
            actions.push(`<button type="button" class="wh-btn wh-btn--primary wh-po-action" data-action="submit" data-id="${po.id}">${esc(t('wms_po_submit'))}</button>`);
        }
        if (cfg.canApprove && po.status === 'pending') {
            actions.push(`<button type="button" class="wh-btn wh-btn--primary wh-po-action" data-action="approve" data-id="${po.id}">${esc(t('wms_po_approve'))}</button>`);
        }
        if (cfg.canPo && ['approved', 'partial'].includes(po.status)) {
            actions.push(`<button type="button" class="wh-btn wh-btn--primary wh-po-action" data-action="receive" data-id="${po.id}">${esc(t('wms_po_receive'))}</button>`);
        }
        if (cfg.canPo && !['received', 'cancelled'].includes(po.status)) {
            actions.push(`<button type="button" class="wh-btn wh-btn--ghost wh-po-action" data-action="cancel" data-id="${po.id}">${esc(t('wms_po_cancel'))}</button>`);
        }
        if (!actions.length) {
            els.detailActions.hidden = true;
            return;
        }
        els.detailActions.innerHTML = actions.join('');
        els.detailActions.hidden = false;
        els.detailActions.querySelectorAll('.wh-po-action').forEach((btn) => {
            btn.addEventListener('click', () => runAction(btn.dataset.action, Number(btn.dataset.id)));
        });
    }

    async function runAction(action, id) {
        if (!id || state.submitting) return;
        state.submitting = true;
        try {
            let res;
            if (action === 'submit') res = await AdminAPI.submitWmsPurchaseOrder(id);
            else if (action === 'approve') res = await AdminAPI.approveWmsPurchaseOrder(id);
            else if (action === 'cancel') res = await AdminAPI.cancelWmsPurchaseOrder(id);
            else if (action === 'receive') res = await AdminAPI.receiveWmsPurchaseOrder(id);
            else return;
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            const msgs = {
                submit: t('wms_po_toast_submitted'),
                approve: t('wms_po_toast_approved'),
                cancel: t('wms_po_toast_cancelled'),
                receive: t('wms_po_toast_grn'),
            };
            toast(msgs[action] || t('save'));
            closeModal(els.detailModal);
            if (action === 'receive' && res.data?.id) {
                window.location.href = `goods_receipts.php?grn=${res.data.id}`;
                return;
            }
            load();
        } catch (err) {
            toast(err.message || t('error'), 'error');
        } finally {
            state.submitting = false;
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
                    <input type="text" class="wh-grn-product-input" value="${esc(initialValue)}" placeholder="${esc(t('wms_po_product_search'))}" autocomplete="off" required>
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

    function syncPoLayout(rowsCount) {
        const modal = els.createModal?.querySelector('.wh-grn-modal');
        const titleEl = document.getElementById('whPoLinesTitle');
        const baseTitle = t('wms_po_section_lines');
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
        syncPoLayout(rows.length);
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
                <input type="number" name="qty_${idx}" min="1" value="${data.quantity_ordered || 1}" required inputmode="numeric">
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

        const scrollEl = document.getElementById('whPoLinesScroll');
        requestAnimationFrame(() => {
            mainRow.classList.remove('wh-grn-table__row--enter');
            if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight;
            mainRow.querySelector('.wh-grn-product-input')?.focus();
        });
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
                items.push({ product_id: Number(productId), quantity_ordered: qty, unit_cost: cost });
            }
        });
        return { items, valid };
    }

    async function submitCreate(status) {
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
        const payload = {
            warehouse_id: Number(fd.get('warehouse_id')),
            supplier_name: fd.get('supplier_name'),
            expected_date: fd.get('expected_date') || null,
            notes: fd.get('notes') || null,
            status,
            items,
        };
        try {
            const res = await AdminAPI.createWmsPurchaseOrder(payload);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            toast(status === 'draft' ? t('wms_po_toast_created') : t('wms_po_toast_submitted'));
            closeModal(els.createModal);
            els.createForm.reset();
            if (els.lineItems) els.lineItems.innerHTML = '';
            state.lineIndex = 0;
            load(true);
        } catch (err) {
            notifyFormError(err.message || t('error'));
        } finally {
            state.submitting = false;
        }
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsPurchaseOrders(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : [];
            if (!items.length) return;
            exportCsv(`purchase-orders-${new Date().toISOString().slice(0, 10)}.csv`, [
                [t('wms_col_po'), t('wms_col_supplier'), t('wms_nav_warehouses'), t('wms_col_items'),
                    t('wms_po_qty_ordered'), t('wms_po_qty_received'), t('wms_col_value'), t('wms_po_expected_date'), t('col_status')],
                ...items.map((r) => [
                    r.po_number, r.supplier_name, r.warehouse_name, r.line_count,
                    r.total_qty_ordered, r.total_qty_received, r.total_amount, r.expected_date, r.status,
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
    els.refresh?.addEventListener('click', () => load());
    els.exportBtn?.addEventListener('click', exportData);
    els.prev?.addEventListener('click', () => { state.page -= 1; load(); });
    els.next?.addEventListener('click', () => { state.page += 1; load(); });
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.createClose?.addEventListener('click', () => closeModal(els.createModal));
    els.createCancel?.addEventListener('click', () => closeModal(els.createModal));
    els.newBtn?.addEventListener('click', async () => {
        await loadProducts();
        els.createForm?.reset();
        if (els.productFilter) els.productFilter.value = '';
        if (els.lineItems) els.lineItems.innerHTML = '';
        state.lineIndex = 0;
        clearFormError();
        document.getElementById('whPoMetaWrap')?.removeAttribute('open');
        ensureFormWarehouse();
        addLineRow();
        updateLineSummary();
        openModal(els.createModal);
        els.formWarehouse?.focus();
    });
    els.addLine?.addEventListener('click', () => loadProducts().then(addLineRow));
    document.querySelector('[data-trigger-add-line]')?.addEventListener('click', () => loadProducts().then(addLineRow));
    els.productFilter?.addEventListener('input', refreshProductPickers);
    els.createForm?.addEventListener('submit', (ev) => {
        ev.preventDefault();
        submitCreate('pending');
    });
    els.saveDraft?.addEventListener('click', () => submitCreate('draft'));
    els.createModal?.addEventListener('click', (e) => {
        if (e.target === els.createModal) closeModal(els.createModal);
    });
    bindPoSwipeDismiss();
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
