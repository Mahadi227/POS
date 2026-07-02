/**
 * Warehouse stock adjustments — manual corrections log + create form
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whAdjTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const readOnly = !!window.WH_PAGE?.readOnly;

    const TYPE_KEYS = {
        adjustment: 'wms_mov_adjustment',
        manual: 'wms_mov_manual',
        damaged: 'wms_mov_damaged',
        expired: 'wms_mov_expired',
        lost: 'wms_mov_lost',
    };

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        searchTimer: null,
        products: [],
    };

    const els = {
        loading: document.getElementById('whAdjLoading'),
        empty: document.getElementById('whAdjEmpty'),
        warehouse: document.getElementById('whAdjWarehouse'),
        search: document.getElementById('whAdjSearch'),
        type: document.getElementById('whAdjType'),
        dateFrom: document.getElementById('whAdjDateFrom'),
        dateTo: document.getElementById('whAdjDateTo'),
        refresh: document.getElementById('whAdjRefreshBtn'),
        exportBtn: document.getElementById('whAdjExportBtn'),
        newBtn: document.getElementById('whAdjNewBtn'),
        heroMeta: document.getElementById('whAdjHeroMeta'),
        statTotal: document.getElementById('whAdjStatTotal'),
        statIn: document.getElementById('whAdjStatIn'),
        statOut: document.getElementById('whAdjStatOut'),
        statNet: document.getElementById('whAdjStatNet'),
        statValue: document.getElementById('whAdjStatValue'),
        pagination: document.getElementById('whAdjPagination'),
        prev: document.getElementById('whAdjPrev'),
        next: document.getElementById('whAdjNext'),
        pageMeta: document.getElementById('whAdjPageMeta'),
        modal: document.getElementById('whAdjModal'),
        modalClose: document.getElementById('whAdjModalClose'),
        form: document.getElementById('whAdjForm'),
        formCancel: document.getElementById('whAdjFormCancel'),
        formError: document.getElementById('whAdjFormError'),
        formWarehouse: document.getElementById('whAdjFormWarehouse'),
        productPicker: document.getElementById('whAdjProductPicker'),
        productInput: document.getElementById('whAdjProductInput'),
        productId: document.getElementById('whAdjProductId'),
        onHand: document.getElementById('whAdjOnHand'),
        metricOnHand: document.getElementById('whAdjMetricOnHand'),
        metricBalance: document.getElementById('whAdjMetricBalance'),
        metricBalanceWrap: document.getElementById('whAdjMetricBalanceWrap'),
        direction: document.getElementById('whAdjDirection'),
        qtyAbs: document.getElementById('whAdjQtyAbs'),
    };

    let onHandQty = null;

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function typeBadge(type) {
        const cls = ['damaged', 'expired', 'lost'].includes(type) ? 'off' : (type === 'manual' ? 'warn' : 'idle');
        return `<span class="cr-badge cr-badge--${cls}">${esc(typeLabel(type))}</span>`;
    }

    function qtyCell(qty) {
        const n = Number(qty || 0);
        const cls = n > 0 ? 'wh-adj-qty--in' : (n < 0 ? 'wh-adj-qty--out' : '');
        const prefix = n > 0 ? '+' : '';
        return `<span class="wh-adj-qty ${cls}">${prefix}${n.toLocaleString()}</span>`;
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString(window.WH_CONFIG?.locale || 'fr-FR', {
                day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
        } catch {
            return iso;
        }
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-adj-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statIn) els.statIn.textContent = Number(s.stock_in ?? 0).toLocaleString();
        if (els.statOut) els.statOut.textContent = Number(s.stock_out ?? 0).toLocaleString();
        const net = Number(s.net_qty ?? 0);
        if (els.statNet) {
            els.statNet.textContent = `${net >= 0 ? '+' : ''}${net.toLocaleString()}`;
            els.statNet.classList.toggle('wh-adj-stat__value--neg', net < 0);
        }
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        setStatsLoading(false);
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
        const type = els.type?.value?.trim();
        if (type && type !== 'all') params.type = type;
        const from = els.dateFrom?.value?.trim();
        if (from) params.from = from;
        const to = els.dateTo?.value?.trim();
        if (to) params.to = to;
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
        const whCol = showWarehouseCol()
            ? `<th>${esc(t('wh_adj_col_warehouse'))}</th>` : '';
        tableWrap.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wh-adj-table"><thead><tr>
            <th>${esc(t('wh_adj_col_date'))}</th>
            <th>${esc(t('wh_adj_col_product'))}</th>
            ${whCol}
            <th>${esc(t('wh_adj_col_type'))}</th>
            <th>${esc(t('wh_adj_col_qty'))}</th>
            <th>${esc(t('wh_adj_col_balance'))}</th>
            <th>${esc(t('wh_adj_col_notes'))}</th>
            <th>${esc(t('wh_adj_col_user'))}</th>
        </tr></thead><tbody>${items.map((r) => {
            const whCell = showWarehouseCol()
                ? `<td>${esc(r.warehouse_name || '—')}</td>` : '';
            return `<tr>
            <td class="wh-adj-date">${esc(formatDate(r.created_at))}</td>
            <td><strong>${esc(r.product_name)}</strong><br><code class="wms-sku">${esc(r.sku || '—')}</code></td>
            ${whCell}
            <td>${typeBadge(r.movement_type)}</td>
            <td>${qtyCell(r.quantity)}</td>
            <td>${Number(r.balance_after ?? 0).toLocaleString()}</td>
            <td class="wh-adj-notes">${esc(r.notes || '—')}</td>
            <td>${esc(r.created_by_name || '—')}</td>
        </tr>`;
        }).join('')}</tbody></table></div>`;
    }

    function renderPagination() {
        const totalPages = Math.max(1, Math.ceil(state.total / state.limit));
        const show = state.total > state.limit;
        if (els.pagination) els.pagination.hidden = !show;
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= totalPages;
        if (els.pageMeta) {
            const from = state.total ? (state.page - 1) * state.limit + 1 : 0;
            const to = Math.min(state.page * state.limit, state.total);
            els.pageMeta.textContent = `${from}–${to} / ${state.total} ${t('records')}`;
        }
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getWmsAdjustments(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            renderStats(state.summary);
            renderTable(state.items);
            renderPagination();
            const whName = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
            if (els.heroMeta) els.heroMeta.textContent = `${whName} · ${state.total} ${t('records')}`;
            updateLastUpdated();
        } catch (err) {
            showError(err.message || t('load_error'));
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    async function exportAll() {
        try {
            const res = await AdminAPI.getWmsAdjustments(buildParams(true));
            const items = res.data || [];
            if (!items.length) return;
            const rows = [
                [t('wh_adj_col_date'), t('wh_adj_col_product'), 'SKU', t('wh_adj_col_warehouse'),
                    t('wh_adj_col_type'), t('wh_adj_col_qty'), t('wh_adj_col_balance'),
                    t('wh_adj_col_notes'), t('wh_adj_col_user')],
                ...items.map((r) => [
                    formatDate(r.created_at), r.product_name, r.sku, r.warehouse_name,
                    typeLabel(r.movement_type), r.quantity, r.balance_after, r.notes, r.created_by_name,
                ]),
            ];
            exportCsv(`stock-adjustments-${new Date().toISOString().slice(0, 10)}.csv`, rows);
        } catch (err) {
            showError(err.message || t('load_error'));
        }
    }

    function toast(msg, type = 'success') {
        const el = document.getElementById('whAdjToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `wh-grn-toast show${type === 'error' ? ' wh-grn-toast--error' : ''}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
    }

    function resetAdjSwipeStyles() {
        const panel = els.modal?.querySelector('.wh-grn-modal');
        if (!panel) return;
        panel.classList.remove('is-dragging');
        panel.style.transition = '';
        panel.style.transform = '';
        if (els.modal) {
            els.modal.style.transition = '';
            els.modal.style.opacity = '';
        }
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

    function productLabel(p) {
        const sku = p.sku ? ` · ${p.sku}` : '';
        const name = p.product_name || p.name || '';
        return `${name}${sku}`;
    }

    function findProductMatch(query) {
        const q = query.trim().toLowerCase();
        if (!q) return null;
        return state.products.find((p) =>
            String(p.product_name || p.name || '').toLowerCase() === q
            || String(p.sku || '').toLowerCase() === q
        ) || null;
    }

    function filterProducts(query) {
        const q = query.trim().toLowerCase();
        if (!q) return state.products.slice(0, 40);
        return state.products.filter((p) =>
            `${p.product_name || p.name || ''} ${p.sku || ''}`.toLowerCase().includes(q)
        ).slice(0, 40);
    }

    function resolveProductPicker() {
        const input = els.productInput;
        const hidden = els.productId;
        if (!input || !hidden) return true;
        const q = input.value.trim();
        if (!q) {
            hidden.value = '';
            updateOnHand();
            return true;
        }
        const match = findProductMatch(q)
            || filterProducts(q).find((p) => productLabel(p).toLowerCase() === q.toLowerCase());
        if (match) {
            hidden.value = String(match.product_id || match.id);
            input.value = productLabel(match);
            updateOnHand();
            return true;
        }
        hidden.value = '';
        updateOnHand();
        return false;
    }

    function renderProductDropdown(query) {
        const dropdown = els.productPicker?.querySelector('.wh-grn-product-dropdown');
        const input = els.productInput;
        if (!dropdown || !input) return;
        const matches = filterProducts(query.trim());
        dropdown.innerHTML = matches.length
            ? matches.map((p) =>
                `<button type="button" class="wh-grn-product-option" data-id="${p.product_id || p.id}">
                    <span class="wh-grn-product-option__name">${esc(p.product_name || p.name)}</span>
                    ${p.sku ? `<span class="wh-grn-product-option__sku">${esc(p.sku)}</span>` : ''}
                    <span class="wh-grn-product-option__sku">${Number(p.quantity || 0).toLocaleString()}</span>
                </button>`
            ).join('')
            : `<p class="wh-grn-product-empty">${esc(t('wh_adj_select_product'))}</p>`;
        dropdown.hidden = false;
    }

    function bindProductPicker() {
        const picker = els.productPicker;
        const input = els.productInput;
        const dropdown = picker?.querySelector('.wh-grn-product-dropdown');
        if (!picker || !input || picker.dataset.adjPickerBound) return;
        picker.dataset.adjPickerBound = '1';

        const closeDropdown = () => {
            if (dropdown) dropdown.hidden = true;
        };

        input.addEventListener('focus', () => renderProductDropdown(input.value));
        input.addEventListener('input', () => {
            renderProductDropdown(input.value);
            resolveProductPicker();
            updatePreviewBalance();
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
            const product = state.products.find((p) => String(p.product_id || p.id) === String(opt.dataset.id));
            if (product) input.value = productLabel(product);
            resolveProductPicker();
            updatePreviewBalance();
            closeDropdown();
        });
    }

    function updatePreviewBalance() {
        const qtyAbs = Math.abs(Number(els.qtyAbs?.value) || 0);
        const direction = els.direction?.value === 'out' ? -1 : 1;
        if (els.metricOnHand) {
            els.metricOnHand.textContent = onHandQty === null ? '—' : Number(onHandQty).toLocaleString();
        }
        if (els.metricBalance) {
            if (onHandQty === null || !qtyAbs) {
                els.metricBalance.textContent = '—';
                els.metricBalanceWrap?.classList.remove('wh-grn-metric--in', 'wh-grn-metric--out');
            } else {
                const next = onHandQty + (qtyAbs * direction);
                els.metricBalance.textContent = Number(next).toLocaleString();
                els.metricBalanceWrap?.classList.toggle('wh-grn-metric--in', next > onHandQty);
                els.metricBalanceWrap?.classList.toggle('wh-grn-metric--out', next < onHandQty);
            }
        }
    }

    function bindAdjSwipeDismiss() {
        const overlay = els.modal;
        const panel = overlay?.querySelector('.wh-grn-modal');
        if (!overlay || !panel || panel.dataset.adjSwipeBound) return;
        panel.dataset.adjSwipeBound = '1';

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
            setTimeout(resetAdjSwipeStyles, 240);
        }

        function animateClose() {
            panel.style.transition = 'transform 0.22s cubic-bezier(0.4, 0, 0.2, 1)';
            panel.style.transform = 'translateY(100%)';
            overlay.style.transition = 'opacity 0.22s ease';
            overlay.style.opacity = '0';
            setTimeout(() => closeModal(), 220);
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

    function openModal() {
        if (!els.modal) return;
        els.form?.reset();
        clearFormError();
        onHandQty = null;
        if (els.productInput) els.productInput.value = '';
        if (els.productId) els.productId.value = '';
        if (els.onHand) els.onHand.hidden = true;
        document.getElementById('whAdjMetaWrap')?.removeAttribute('open');
        updatePreviewBalance();
        const wh = els.warehouse?.value || String(window.WH_PAGE?.warehouseId || '');
        if (wh && els.formWarehouse) els.formWarehouse.value = wh;
        loadProductsForForm();
        els.modal.classList.add('is-open');
        els.modal.setAttribute('aria-hidden', 'false');
        els.formWarehouse?.focus();
    }

    function closeModal() {
        if (!els.modal) return;
        resetAdjSwipeStyles();
        clearFormError();
        els.modal.classList.remove('is-open');
        els.modal.setAttribute('aria-hidden', 'true');
    }

    async function loadProductsForForm() {
        const wh = els.formWarehouse?.value?.trim();
        if (!wh) {
            state.products = [];
            if (els.productInput) els.productInput.value = '';
            if (els.productId) els.productId.value = '';
            onHandQty = null;
            updateOnHand();
            return;
        }
        try {
            const res = await AdminAPI.getWmsInventory(wh, '', 'all');
            state.products = res.data || [];
            resolveProductPicker();
        } catch {
            state.products = [];
        }
    }

    async function updateOnHand() {
        const wh = els.formWarehouse?.value?.trim();
        const pid = els.productId?.value;
        if (!wh || !pid) {
            onHandQty = null;
            if (els.onHand) els.onHand.hidden = true;
            updatePreviewBalance();
            return;
        }
        try {
            const res = await AdminAPI.getWmsInventoryItem(wh, pid);
            onHandQty = Number(res.data?.quantity ?? 0);
            if (els.onHand) {
                els.onHand.textContent = `${t('wh_adj_on_hand')}: ${onHandQty.toLocaleString()}`;
                els.onHand.hidden = false;
            }
        } catch {
            onHandQty = null;
            if (els.onHand) els.onHand.hidden = true;
        }
        updatePreviewBalance();
    }

    async function submitAdjustment(ev) {
        ev.preventDefault();
        if (readOnly || !els.form) return;
        clearFormError();
        if (!els.form.reportValidity()) return;
        if (!resolveProductPicker()) {
            notifyFormError(t('wh_adj_select_product'));
            return;
        }

        const fd = new FormData(els.form);
        const wh = Number(fd.get('warehouse_id'));
        const productId = Number(fd.get('product_id'));
        const qtyAbs = Math.abs(Number(fd.get('quantity_abs') || 0));
        const direction = fd.get('direction') === 'out' ? -1 : 1;

        if (!wh || !productId || !qtyAbs) {
            notifyFormError(t('error'));
            return;
        }

        const payload = {
            warehouse_id: wh,
            product_id: productId,
            quantity: qtyAbs * direction,
            movement_type: String(fd.get('movement_type') || 'adjustment'),
            notes: String(fd.get('notes') || '').trim(),
        };
        try {
            const res = await AdminAPI.createWmsAdjustment(payload);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            closeModal();
            toast(t('wh_adj_success'));
            if (els.warehouse && wh) els.warehouse.value = String(wh);
            await load(true);
            hideError();
        } catch (err) {
            notifyFormError(err.message || t('error'));
        }
    }

    els.warehouse?.addEventListener('change', () => load(true));
    els.type?.addEventListener('change', () => load(true));
    els.dateFrom?.addEventListener('change', () => load(true));
    els.dateTo?.addEventListener('change', () => load(true));
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => load(true), 320);
    });
    els.refresh?.addEventListener('click', () => load());
    els.exportBtn?.addEventListener('click', exportAll);
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        if (state.page < Math.ceil(state.total / state.limit)) { state.page += 1; load(); }
    });
    els.newBtn?.addEventListener('click', openModal);
    els.modalClose?.addEventListener('click', closeModal);
    els.formCancel?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });
    els.form?.addEventListener('submit', submitAdjustment);
    els.formWarehouse?.addEventListener('change', () => {
        if (els.productInput) els.productInput.value = '';
        if (els.productId) els.productId.value = '';
        loadProductsForForm();
    });
    els.direction?.addEventListener('change', updatePreviewBalance);
    els.qtyAbs?.addEventListener('input', updatePreviewBalance);
    bindProductPicker();
    bindAdjSwipeDismiss();
    document.addEventListener('wh:refresh', () => load());

    loadWarehouseOptions(els.warehouse).then(() => {
        if (els.formWarehouse) {
            return loadWarehouseOptions(els.formWarehouse);
        }
        return null;
    }).then(() => {
        const defaultWh = String(window.WH_PAGE?.warehouseId || '');
        if (defaultWh && els.warehouse) els.warehouse.value = defaultWh;
        load();
    });
});
