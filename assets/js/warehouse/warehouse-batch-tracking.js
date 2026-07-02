/**
 * Batch tracking — list, create, and manage product batches
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whBatTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canManage = !!window.WH_PAGE?.canManage && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
        active: 'wms_status_active',
        expired: 'wms_status_expired',
        recalled: 'wms_status_recalled',
        depleted: 'wms_status_depleted',
        expiring_soon: 'wms_filter_expiring_soon',
    };
    const STATUS_ORDER = ['active', 'expiring_soon', 'expired', 'recalled', 'depleted'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        products: [],
        detailId: null,
        searchTimer: null,
    };

    const els = {
        search: document.getElementById('whBatSearch'),
        warehouse: document.getElementById('whBatWarehouse'),
        status: document.getElementById('whBatStatus'),
        refresh: document.getElementById('whBatRefreshBtn'),
        exportBtn: document.getElementById('whBatExportBtn'),
        newBtn: document.getElementById('whBatNewBtn'),
        heroMeta: document.getElementById('whBatHeroMeta'),
        breakdownPanel: document.getElementById('whBatBreakdownPanel'),
        statusChips: document.getElementById('whBatStatusChips'),
        statTotal: document.getElementById('whBatStatTotal'),
        statActive: document.getElementById('whBatStatActive'),
        statExpiring: document.getElementById('whBatStatExpiring'),
        statExpired: document.getElementById('whBatStatExpired'),
        loading: document.getElementById('whBatLoading'),
        empty: document.getElementById('whBatEmpty'),
        pagination: document.getElementById('whBatPagination'),
        prev: document.getElementById('whBatPrev'),
        next: document.getElementById('whBatNext'),
        pageMeta: document.getElementById('whBatPageMeta'),
        createModal: document.getElementById('whBatCreateModal'),
        createClose: document.getElementById('whBatCreateClose'),
        createCancel: document.getElementById('whBatCreateCancel'),
        createForm: document.getElementById('whBatCreateForm'),
        formError: document.getElementById('whBatFormError'),
        formWarehouse: document.getElementById('whBatFormWarehouse'),
        productPicker: document.getElementById('whBatProductPicker'),
        productInput: document.getElementById('whBatProductInput'),
        productId: document.getElementById('whBatProductId'),
        batchNumber: document.getElementById('whBatBatchNumber'),
        qty: document.getElementById('whBatQty'),
        unitCost: document.getElementById('whBatUnitCost'),
        metricQty: document.getElementById('whBatMetricQty'),
        metricValue: document.getElementById('whBatMetricValue'),
        detailModal: document.getElementById('whBatDetailModal'),
        detailClose: document.getElementById('whBatDetailClose'),
        detailTitle: document.getElementById('whBatDetailTitle'),
        detailSubtitle: document.getElementById('whBatDetailSubtitle'),
        detailBody: document.getElementById('whBatDetailBody'),
        detailActions: document.getElementById('whBatDetailActions'),
        recallBtn: document.getElementById('whBatRecallBtn'),
        depleteBtn: document.getElementById('whBatDepleteBtn'),
        expiredBtn: document.getElementById('whBatExpiredBtn'),
        toast: document.getElementById('whBatToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-bat-toast show${type === 'error' ? ' wh-bat-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'active' ? 'ok' : (status === 'recalled' || status === 'expired' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function formatDate(val, withTime = false) {
        if (!val) return '—';
        try {
            return AdminAPI.formatDate(val, withTime ? { dateStyle: 'short', timeStyle: 'short' } : { dateStyle: 'short' });
        } catch {
            return val;
        }
    }

    function expiryCell(row) {
        const exp = row.expiry_date;
        if (!exp) return '—';
        const days = row.days_to_expiry != null ? Number(row.days_to_expiry) : null;
        let cls = '';
        if (days != null) {
            if (days < 0) cls = 'wh-bat-expiry--past';
            else if (days <= 30) cls = 'wh-bat-expiry--soon';
        }
        const hint = days != null ? ` <small class="wh-bat-expiry-days">(${days}d)</small>` : '';
        return `<span class="wh-bat-expiry ${cls}">${esc(formatDate(exp))}${hint}</span>`;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-bat-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statActive) els.statActive.textContent = String(s.active ?? 0);
        if (els.statExpiring) els.statExpiring.textContent = String(s.expiring_soon ?? 0);
        if (els.statExpired) els.statExpired.textContent = String(s.expired ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_bat_hero_meta', s.active ?? 0, s.expiring_soon ?? 0);
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
        const activeStatus = els.status?.value || '';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const isActive = activeStatus === r.status;
            return `<button type="button" class="wh-bat-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-bat-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.status) els.status.value = btn.dataset.status || '';
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-bat-table">
<thead><tr>
    <th>${esc(t('wms_col_batch'))}</th>
    <th>${esc(t('wms_col_product'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_qty'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th>${esc(t('wms_col_mfg'))}</th>
    <th>${esc(t('wms_col_expiry'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r) => `<tr>
    <td><strong>${esc(r.batch_number)}</strong></td>
    <td>${esc(r.product_name)}<br><code class="wms-sku">${esc(r.sku || '')}</code></td>
    <td>${esc(r.warehouse_name || '—')}</td>
    <td>${Number(r.quantity || 0)}</td>
    <td>${esc(money(r.stock_value))}</td>
    <td>${esc(formatDate(r.manufacturing_date))}</td>
    <td>${expiryCell(r)}</td>
    <td>${statusBadge(r.status)}</td>
    <td class="wh-bat-row-actions">
        <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-bat-view="${r.id}">${esc(t('wms_view_details'))}</button>
    </td>
</tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-bat-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.batView)));
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
            const res = await AdminAPI.getWmsBatches(buildParams());
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
            [t('wms_col_batch'), t('wms_col_product'), t('wms_nav_warehouses'), t('wms_col_qty'), t('wms_col_value'), t('wms_col_mfg'), t('wms_col_expiry'), t('col_status')],
            ...items.map((r) => [
                r.batch_number,
                r.product_name,
                r.warehouse_name,
                r.quantity,
                r.stock_value,
                r.manufacturing_date,
                r.expiry_date,
                r.status,
            ]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsBatches(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`batch-tracking-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    function openModal(el) {
        el?.classList.add('is-open');
        el?.setAttribute('aria-hidden', 'false');
    }

    function resetBatSwipeStyles() {
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
        if (el === els.createModal) {
            resetBatSwipeStyles();
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

    function applyProductDefaults(product) {
        if (!product || !els.unitCost) return;
        const cost = product.cost_price ?? product.cost ?? product.price;
        if (cost != null && cost !== '' && (!els.unitCost.value || Number(els.unitCost.value) === 0)) {
            els.unitCost.value = String(cost);
        }
        updateBatchMetrics();
    }

    function resolveProductPicker() {
        const input = els.productInput;
        const hidden = els.productId;
        if (!input || !hidden) return true;
        const q = input.value.trim();
        if (!q) {
            hidden.value = '';
            updateBatchMetrics();
            return true;
        }
        const match = findProductMatch(q)
            || filterProducts(q).find((p) => productLabel(p).toLowerCase() === q.toLowerCase());
        if (match) {
            hidden.value = String(match.id);
            input.value = productLabel(match);
            applyProductDefaults(match);
            return true;
        }
        hidden.value = '';
        updateBatchMetrics();
        return false;
    }

    function renderProductDropdown(query) {
        const dropdown = els.productPicker?.querySelector('.wh-grn-product-dropdown');
        const input = els.productInput;
        if (!dropdown || !input) return;
        const matches = filterProducts(query.trim());
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

    function bindProductPicker() {
        const picker = els.productPicker;
        const input = els.productInput;
        const dropdown = picker?.querySelector('.wh-grn-product-dropdown');
        if (!picker || !input || picker.dataset.batPickerBound) return;
        picker.dataset.batPickerBound = '1';

        const closeDropdown = () => {
            if (dropdown) dropdown.hidden = true;
        };

        input.addEventListener('focus', () => renderProductDropdown(input.value));
        input.addEventListener('input', () => {
            renderProductDropdown(input.value);
            resolveProductPicker();
        });
        input.addEventListener('blur', () => {
            setTimeout(() => {
                closeDropdown();
                resolveProductPicker();
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
            resolveProductPicker();
            closeDropdown();
        });
    }

    function updateBatchMetrics() {
        const qty = Number(els.qty?.value) || 0;
        const cost = Number(els.unitCost?.value) || 0;
        if (els.metricQty) els.metricQty.textContent = qty.toLocaleString();
        if (els.metricValue) els.metricValue.textContent = money(qty * cost);
    }

    function bindBatSwipeDismiss() {
        const overlay = els.createModal;
        const panel = overlay?.querySelector('.wh-grn-modal');
        if (!overlay || !panel || panel.dataset.batSwipeBound) return;
        panel.dataset.batSwipeBound = '1';

        const SWIPE_CLOSE_PX = 72;
        const SWIPE_VELOCITY = 0.4;
        const swipeZone = panel.querySelector('.wh-grn-modal__swipe-zone');
        const handle = panel.querySelector('.wh-grn-modal__handle');

        let startY = 0;
        let startX = 0;
        let currentY = 0;
        let startTime = 0;
        let dragging = false;

        function isTouchSheet() {
            return window.matchMedia('(max-width: 767px)').matches;
        }

        function snapBack() {
            panel.style.transition = 'transform 0.24s cubic-bezier(0.4, 0, 0.2, 1)';
            panel.style.transform = '';
            overlay.style.transition = 'opacity 0.24s ease';
            overlay.style.opacity = '';
            setTimeout(resetBatSwipeStyles, 240);
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
            const isInteractive = target.closest('input, select, textarea, button, a, option');
            if (isInteractive && !onHandle) return;
            if (!onHandle && !onHeader) return;

            startY = touch.clientY;
            startX = touch.clientX;
            currentY = 0;
            startTime = Date.now();
            dragging = true;
            panel.classList.add('is-dragging');
        }

        function onTouchMove(e) {
            if (!dragging) return;
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

    function updateDetailActions(row) {
        if (!els.detailActions) return;
        const show = canManage && row && row.status === 'active';
        els.detailActions.hidden = !show;
    }

    async function openDetail(id) {
        state.detailId = id;
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        updateDetailActions(null);
        openModal(els.detailModal);
        try {
            const res = await AdminAPI.getWmsBatch(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.detailTitle) els.detailTitle.textContent = r.batch_number || t('wms_batch_details');
            if (els.detailSubtitle) {
                els.detailSubtitle.textContent = [r.product_name, r.sku, r.warehouse_name].filter(Boolean).join(' · ');
            }
            const days = r.days_to_expiry != null ? Number(r.days_to_expiry) : null;
            els.detailBody.innerHTML = `
                <dl class="wh-bat-detail-grid">
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_product'))}</dt><dd>${esc(r.product_name)} <code class="wms-sku">${esc(r.sku || '')}</code></dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_qty'))}</dt><dd>${Number(r.quantity || 0)}</dd></div>
                    <div><dt>${esc(t('wms_unit_cost'))}</dt><dd>${esc(money(r.unit_cost))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.stock_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_barcode'))}</dt><dd>${esc(r.barcode || r.product_barcode || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_serial'))}</dt><dd>${esc(r.serial_number || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_mfg'))}</dt><dd>${esc(formatDate(r.manufacturing_date))}</dd></div>
                    <div><dt>${esc(t('wms_col_expiry'))}</dt><dd>${expiryCell(r)}</dd></div>
                    <div><dt>${esc(t('wms_days_to_expiry'))}</dt><dd>${days != null ? `${days} ${t('wms_days_short') || 'd'}` : '—'}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at, true))}</dd></div>
                </dl>`;
            updateDetailActions(r);
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-bat-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function updateStatus(status) {
        if (!state.detailId) return;
        const confirmKeys = {
            recalled: 'wms_confirm_recall',
            depleted: 'wms_confirm_deplete',
            expired: 'wms_confirm_mark_expired',
        };
        if (!window.confirm(t(confirmKeys[status] || 'error'))) return;
        try {
            const res = await AdminAPI.updateWmsBatchStatus(state.detailId, status);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            closeModal(els.detailModal);
            const toastKeys = {
                recalled: 'wh_bat_toast_recalled',
                depleted: 'wh_bat_toast_depleted',
                expired: 'wh_bat_toast_expired',
            };
            toast(t(toastKeys[status] || 'save'));
            state.page = 1;
            await load();
        } catch (e) {
            toast(e.message || t('error'), 'error');
        }
    }

    async function openCreate() {
        await Promise.all([loadProducts(), loadFormWarehouses()]);
        els.createForm?.reset();
        clearFormError();
        if (els.productInput) els.productInput.value = '';
        if (els.productId) els.productId.value = '';
        document.getElementById('whBatMetaWrap')?.removeAttribute('open');
        updateBatchMetrics();
        ensureFormWarehouse();
        openModal(els.createModal);
        els.formWarehouse?.focus();
    }

    async function submitCreate(e) {
        e.preventDefault();
        clearFormError();
        if (!ensureFormWarehouse()) return;
        if (!els.createForm?.reportValidity()) return;
        if (!resolveProductPicker()) {
            notifyFormError(t('wms_select_product'));
            return;
        }

        const data = Object.fromEntries(new FormData(els.createForm).entries());
        data.warehouse_id = Number(data.warehouse_id);
        data.product_id = Number(data.product_id);
        data.quantity = Number(data.quantity || 0);
        data.unit_cost = Number(data.unit_cost || 0);

        if (!data.batch_number?.trim()) {
            notifyFormError(t('wms_col_batch'));
            els.batchNumber?.focus();
            return;
        }

        try {
            const res = await AdminAPI.createWmsBatch(data);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            closeModal(els.createModal);
            toast(t('wh_bat_toast_created'));
            state.page = 1;
            await load();
        } catch (err) {
            notifyFormError(err.message || t('error'));
        }
    }

    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => {
            state.page = 1;
            load();
        }, 350);
    });
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.status?.addEventListener('change', () => { state.page = 1; load(); });
    els.refresh?.addEventListener('click', load);
    els.exportBtn?.addEventListener('click', exportData);
    els.newBtn?.addEventListener('click', openCreate);
    els.createClose?.addEventListener('click', () => closeModal(els.createModal));
    els.createCancel?.addEventListener('click', () => closeModal(els.createModal));
    els.createModal?.addEventListener('click', (e) => {
        if (e.target === els.createModal) closeModal(els.createModal);
    });
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.recallBtn?.addEventListener('click', () => updateStatus('recalled'));
    els.depleteBtn?.addEventListener('click', () => updateStatus('depleted'));
    els.expiredBtn?.addEventListener('click', () => updateStatus('expired'));
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        const pages = Math.max(1, Math.ceil(state.total / state.limit));
        if (state.page < pages) { state.page += 1; load(); }
    });

    els.qty?.addEventListener('input', updateBatchMetrics);
    els.unitCost?.addEventListener('input', updateBatchMetrics);
    els.createForm?.addEventListener('submit', submitCreate);
    bindProductPicker();
    bindBatSwipeDismiss();

    document.addEventListener('wh:refresh', load);
    loadWarehouseOptions(els.warehouse).then(load);
});
