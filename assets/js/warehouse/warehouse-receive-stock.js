/**

 * Warehouse receive stock — quick GRN entry with barcode scan, PO prefill, and optional posting

 */

document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('whRcvForm');

    const recentList = document.getElementById('whRcvRecentList');

    if (!form && !recentList) return;



    const {

        t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated,

    } = WarehouseUI;

    const canReceive = !!window.WH_PAGE?.canReceive && !window.WH_PAGE?.readOnly;



    const STATUS_KEYS = {

        pending: 'wms_status_pending',

        inspecting: 'wms_status_inspecting',

        accepted: 'wms_status_accepted',

        completed: 'wms_status_completed',

        rejected: 'wms_status_rejected',

    };



    const state = {

        products: [],

        locations: [],

        lineIndex: 0,

        submitting: false,

        poContext: null,

        recentLoading: false,

    };



    const els = {

        form,

        warehouse: document.getElementById('whRcvWarehouse'),

        scan: document.getElementById('whRcvScan'),

        productFilter: document.getElementById('whRcvProductFilter'),

        addLine: document.getElementById('whRcvAddLine'),

        lineItems: document.getElementById('whRcvLineItems'),

        linesEmpty: document.getElementById('whRcvLinesEmpty'),

        lineCount: document.getElementById('whRcvLineCount'),

        estTotal: document.getElementById('whRcvEstTotal'),

        mode: document.getElementById('whRcvMode'),

        submit: document.getElementById('whRcvSubmit'),

        submitLabel: document.querySelector('.wh-rcv-submit-label'),

        reset: document.getElementById('whRcvReset'),

        heroMeta: document.getElementById('whRcvHeroMeta'),

        statToday: document.getElementById('whRcvStatToday'),

        statItems: document.getElementById('whRcvStatItems'),

        statValue: document.getElementById('whRcvStatValue'),

        recentList,

        recentSkeleton: document.getElementById('whRcvRecentSkeleton'),

        refreshRecent: document.getElementById('whRcvRefreshRecent'),

        toast: document.getElementById('whRcvToast'),

        formError: document.getElementById('whRcvFormError'),

        poBanner: document.getElementById('whRcvPoBanner'),

        poBannerText: document.getElementById('whRcvPoBannerText'),

        poBannerClose: document.getElementById('whRcvPoBannerClose'),

    };



    function todayIso() {

        return new Date().toISOString().slice(0, 10);

    }



    function toast(msg, type = 'success') {

        if (!els.toast) return;

        els.toast.textContent = msg;

        els.toast.className = `wh-rcv-toast show${type === 'error' ? ' wh-rcv-toast--error' : ''}`;

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



    function setStatsLoading(loading) {

        document.querySelectorAll('.wh-rcv-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));

    }



    function setRecentLoading(loading) {

        state.recentLoading = loading;

        els.recentSkeleton?.classList.toggle('is-visible', loading);

        if (els.refreshRecent) els.refreshRecent.disabled = loading;

    }



    function formatTime(iso) {

        if (!iso) return '—';

        try {

            return AdminAPI.formatDate(iso, { dateStyle: 'short', timeStyle: 'short' });

        } catch {

            return iso;

        }

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



    function updateModeLabel() {

        const post = els.mode?.querySelector('[value="post"]')?.checked;

        if (els.submitLabel) {

            els.submitLabel.textContent = post ? t('wh_rcv_submit_post') : t('wh_rcv_submit_pending');

        }

    }



    function setSubmitLoading(loading) {

        if (!els.submit) return;

        els.submit.disabled = loading || state.submitting;

        els.submit.classList.toggle('is-loading', loading);

        if (els.submitLabel && loading) {

            els.submitLabel.textContent = t('wh_rcv_submitting');

        } else {

            updateModeLabel();

        }

    }



    function renderTodayStats(summary) {

        const s = summary || {};

        const count = Number(s.completed ?? s.total ?? 0);

        const items = Number(s.total_items ?? 0);

        const value = Number(s.total_value ?? 0);

        if (els.statToday) els.statToday.textContent = String(count);

        if (els.statItems) els.statItems.textContent = String(items);

        if (els.statValue) els.statValue.textContent = money(value);

        if (els.heroMeta) {

            const wh = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');

            els.heroMeta.textContent = `${wh} · ${t('wh_rcv_hero_meta', count, items)}`;

        }

        setStatsLoading(false);

    }



    function renderRecent(receipts) {

        if (!els.recentList) return;

        const list = (receipts || []).slice(0, 10);

        const skeleton = els.recentSkeleton;

        if (!list.length) {

            els.recentList.innerHTML = skeleton ? '' : '';

            if (skeleton) els.recentList.appendChild(skeleton);

            els.recentList.insertAdjacentHTML('beforeend', `<p class="wh-rcv-recent-empty">${esc(t('wh_rcv_recent_empty'))}</p>`);

            return;

        }

        els.recentList.innerHTML = skeleton ? '' : '';

        if (skeleton) els.recentList.appendChild(skeleton);

        els.recentList.insertAdjacentHTML('beforeend', `<ul class="wh-rcv-recent-list">${list.map((r) => {

            const href = `goods_receipts.php?grn=${encodeURIComponent(r.id)}`;

            return `<li class="wh-rcv-recent-item">

                <a href="${href}" class="wh-rcv-recent-item__link">

                    <div class="wh-rcv-recent-item__head">

                        <strong>${esc(r.grn_number)}</strong>

                        ${statusBadge(r.status)}

                    </div>

                    <span class="wh-rcv-recent-item__wh">${esc(r.warehouse_name || '—')}</span>

                    <span class="wh-rcv-recent-item__meta">${esc(money(r.total_value))} · ${Number(r.total_items || 0)} ${esc(t('wms_col_items'))}</span>

                    <time datetime="${esc(r.received_at || '')}">${esc(formatTime(r.received_at))}</time>

                </a>

            </li>`;

        }).join('')}</ul>`);

    }



    async function loadRecent() {

        setStatsLoading(true);

        setRecentLoading(true);

        try {

            const wh = els.warehouse?.value || window.WH_PAGE?.warehouseId || '';

            const today = todayIso();

            const base = {};

            if (wh) base.warehouse_id = wh;



            const [statsRes, listRes] = await Promise.all([

                AdminAPI.getWmsReceipts({

                    ...base,

                    scope: 'history',

                    date_from: today,

                    date_to: today,

                    limit: 1,

                    offset: 0,

                }),

                AdminAPI.getWmsReceipts({ ...base, limit: 12, offset: 0 }),

            ]);



            if (statsRes.status !== 'success' && listRes.status !== 'success') {

                throw new Error(statsRes.message || listRes.message || t('load_error'));

            }

            setMigrationHint(statsRes.module_ready !== false || listRes.module_ready !== false);

            renderTodayStats(statsRes.summary || {});

            renderRecent(listRes.data || []);

            updateLastUpdated();

        } catch (e) {

            renderTodayStats({});

            if (els.recentList) {

                const skeleton = els.recentSkeleton;

                els.recentList.innerHTML = skeleton ? '' : '';

                if (skeleton) els.recentList.appendChild(skeleton);

                els.recentList.insertAdjacentHTML('beforeend', `<p class="wh-rcv-recent-empty">${esc(e.message || t('load_error'))}</p>`);

            }

        } finally {

            setRecentLoading(false);

        }

    }



    async function loadProducts() {

        if (state.products.length) return;

        const res = await AdminAPI.getInventoryProducts();

        state.products = res.status === 'success' ? (res.data || []) : [];

    }



    async function loadLocations(warehouseId) {

        if (!warehouseId) {

            state.locations = [];

            return;

        }

        const res = await AdminAPI.getWmsLocations({ warehouse_id: warehouseId, status: 'active', limit: 200 });

        state.locations = res.status === 'success' ? (res.data || []) : [];

    }



    function productOptions(selectedId = '', filter = '') {

        const q = filter.trim().toLowerCase();

        const list = q

            ? state.products.filter((p) => `${p.name || ''} ${p.sku || ''} ${p.barcode || ''}`.toLowerCase().includes(q))

            : state.products;

        const opts = list.map((p) =>

            `<option value="${p.id}" data-cost="${p.cost_price || p.cost || p.price || 0}" data-sku="${esc(p.sku || '')}" data-barcode="${esc(p.barcode || '')}" ${String(p.id) === String(selectedId) ? 'selected' : ''}>${esc(p.name)}${p.sku ? ` · ${esc(p.sku)}` : ''}</option>`

        ).join('');

        return `<option value="">${esc(t('wms_select_product'))}</option>` + opts;

    }



    function locationOptions(selectedId = '') {

        const opts = state.locations.map((loc) =>

            `<option value="${loc.id}" ${String(loc.id) === String(selectedId) ? 'selected' : ''}>${esc(loc.location_code || loc.zone || loc.id)}</option>`

        ).join('');

        return `<option value="">${esc(t('wh_rcv_location_optional'))}</option>` + opts;

    }



    function findProductByCode(code) {

        const q = code.trim().toLowerCase();

        if (!q) return null;

        return state.products.find((p) =>

            String(p.sku || '').toLowerCase() === q

            || String(p.barcode || '').toLowerCase() === q

        ) || null;

    }



    function refreshProductSelects() {

        const filter = els.productFilter?.value || '';

        els.lineItems?.querySelectorAll('.wms-grn-line').forEach((row) => {

            const idx = row.dataset.lineIdx;

            const sel = row.querySelector(`[name="product_id_${idx}"]`);

            if (!sel) return;

            const cur = sel.value;

            sel.innerHTML = productOptions(cur, filter);

            if (cur) sel.value = cur;

        });

    }



    function refreshLocationSelects() {

        els.lineItems?.querySelectorAll('.wms-grn-line').forEach((row) => {

            const idx = row.dataset.lineIdx;

            const sel = row.querySelector(`[name="location_id_${idx}"]`);

            if (!sel) return;

            const cur = sel.value;

            sel.innerHTML = locationOptions(cur);

            if (cur) sel.value = cur;

        });

    }



    function updateLineSummary() {

        const rows = els.lineItems?.querySelectorAll('.wms-grn-line') || [];

        let total = 0;

        rows.forEach((row) => {

            const idx = row.dataset.lineIdx;

            const qty = parseFloat(row.querySelector(`[name="qty_${idx}"]`)?.value) || 0;

            const cost = parseFloat(row.querySelector(`[name="cost_${idx}"]`)?.value) || 0;

            const lineTotal = qty * cost;

            total += lineTotal;

            const subEl = row.querySelector('.wms-grn-line__subtotal-val');

            if (subEl) subEl.textContent = money(lineTotal);

        });

        if (els.lineCount) els.lineCount.textContent = t('wms_grn_lines_count', rows.length);

        if (els.estTotal) els.estTotal.textContent = money(total);

        if (els.linesEmpty) els.linesEmpty.hidden = rows.length > 0;

        rows.forEach((row, i) => {

            const num = row.querySelector('.wms-grn-line__num');

            if (num) num.textContent = String(i + 1);

            row.querySelectorAll('.wms-grn-line__remove').forEach((btn) => {

                btn.disabled = rows.length <= 1;

            });

        });

    }



    function bindLineInputs(row, idx) {

        row.querySelectorAll('input, select').forEach((el) => {

            el.addEventListener('input', updateLineSummary);

            el.addEventListener('change', updateLineSummary);

        });

        const sel = row.querySelector(`select[name="product_id_${idx}"]`);

        sel?.addEventListener('change', () => {

            const opt = sel.selectedOptions[0];

            const costInput = row.querySelector(`[name="cost_${idx}"]`);

            if (opt?.dataset.cost && costInput && !costInput.value) {

                costInput.value = opt.dataset.cost;

                updateLineSummary();

            }

        });

        row.querySelector('[data-toggle-tracking]')?.addEventListener('click', () => {

            const panel = row.querySelector('.wms-grn-line__tracking');

            const btn = row.querySelector('[data-toggle-tracking]');

            if (!panel || !btn) return;

            const open = panel.hidden;

            panel.hidden = !open;

            btn.setAttribute('aria-expanded', open ? 'true' : 'false');

            btn.classList.toggle('is-open', open);

        });

        row.querySelector('[data-remove-line]')?.addEventListener('click', () => {

            if ((els.lineItems?.querySelectorAll('.wms-grn-line').length || 0) <= 1) return;

            row.remove();

            updateLineSummary();

        });

    }



    function addLineRow(data = {}, focusQty = false) {

        if (!els.lineItems) return null;

        const idx = state.lineIndex++;

        const filter = els.productFilter?.value || '';

        const row = document.createElement('article');

        row.className = 'wms-grn-line';

        row.dataset.lineIdx = String(idx);

        const lineNo = els.lineItems.querySelectorAll('.wms-grn-line').length + 1;

        const hasTracking = !!(data.batch_number || data.expiry_date || data.location_id);

        row.innerHTML = `

            <header class="wms-grn-line__head">

                <span class="wms-grn-line__num">${lineNo}</span>

                <button type="button" class="wms-grn-line__remove" data-remove-line aria-label="${esc(t('wms_remove_line'))}">

                    <span class="material-icons-round">delete_outline</span>

                </button>

            </header>

            <label class="wms-grn-line__field wms-grn-line__field--product">

                <span>${esc(t('wms_col_product'))}</span>

                <select name="product_id_${idx}" required>${productOptions(data.product_id, filter)}</select>

            </label>

            <div class="wms-grn-line__metrics">

                <label class="wms-grn-line__field">

                    <span>${esc(t('wms_qty_short'))}</span>

                    <input type="number" name="qty_${idx}" min="1" value="${data.quantity_received || 1}" required>

                </label>

                <label class="wms-grn-line__field">

                    <span>${esc(t('wms_unit_cost'))}</span>

                    <input type="number" name="cost_${idx}" min="0" step="0.01" value="${data.unit_cost || ''}" placeholder="0" required>

                </label>

                <div class="wms-grn-line__subtotal">

                    <span>${esc(t('wms_line_subtotal'))}</span>

                    <strong class="wms-grn-line__subtotal-val">${money(0)}</strong>

                </div>

            </div>

            <button type="button" class="wms-grn-line__tracking-toggle" data-toggle-tracking aria-expanded="${hasTracking ? 'true' : 'false'}">

                <span class="material-icons-round">inventory_2</span>

                <span class="wms-grn-line__toggle-text">${esc(t('wms_line_tracking'))}</span>

                <span class="material-icons-round wms-grn-line__chevron">expand_more</span>

            </button>

            <div class="wms-grn-line__tracking wh-rcv-tracking" ${hasTracking ? '' : 'hidden'}>

                <label class="wms-grn-line__field">

                    <span>${esc(t('wms_nav_locations'))}</span>

                    <select name="location_id_${idx}">${locationOptions(data.location_id)}</select>

                </label>

                <label class="wms-grn-line__field">

                    <span>${esc(t('wms_batch_optional'))}</span>

                    <input type="text" name="batch_${idx}" value="${esc(data.batch_number || '')}" placeholder="—">

                </label>

                <label class="wms-grn-line__field">

                    <span>${esc(t('wms_expiry_optional'))}</span>

                    <input type="date" name="expiry_${idx}" value="${esc(data.expiry_date || '')}">

                </label>

            </div>`;

        if (hasTracking) row.querySelector('[data-toggle-tracking]')?.classList.add('is-open');

        if (data.product_id) {

            const sel = row.querySelector(`select[name="product_id_${idx}"]`);

            if (sel) sel.value = String(data.product_id);

        }

        bindLineInputs(row, idx);

        els.lineItems.appendChild(row);

        updateLineSummary();

        if (focusQty) row.querySelector(`[name="qty_${idx}"]`)?.focus();

        return row;

    }



    function showPoBanner(po) {

        if (!els.poBanner || !els.poBannerText || !po) return;

        const label = po.po_number || `#${po.id}`;

        els.poBannerText.textContent = t('wh_rcv_from_po', label);

        els.poBanner.hidden = false;

    }



    function hidePoBanner() {

        state.poContext = null;

        if (els.poBanner) els.poBanner.hidden = true;

    }



    function resetForm(keepPo = false) {

        els.form?.reset();

        if (els.lineItems) els.lineItems.innerHTML = '';

        state.lineIndex = 0;

        if (els.productFilter) els.productFilter.value = '';

        const wh = String(window.WH_PAGE?.warehouseId || '');

        if (wh && els.warehouse) els.warehouse.value = wh;

        if (!keepPo) hidePoBanner();

        addLineRow();

        updateModeLabel();

        clearFormError();

        WarehouseReceiveScan?.focusWedge();

    }



    async function handleScan(code) {

        const trimmed = code.trim();

        if (!trimmed) return;

        await loadProducts();

        const product = findProductByCode(trimmed);

        if (!product) {

            toast(t('wh_rcv_product_not_found'), 'error');

            return false;

        }

        const existing = [...(els.lineItems?.querySelectorAll('.wms-grn-line') || [])].find((row) => {

            const idx = row.dataset.lineIdx;

            return row.querySelector(`[name="product_id_${idx}"]`)?.value === String(product.id);

        });

        if (existing) {

            const idx = existing.dataset.lineIdx;

            const qtyInput = existing.querySelector(`[name="qty_${idx}"]`);

            if (qtyInput) qtyInput.value = String((parseInt(qtyInput.value, 10) || 0) + 1);

            updateLineSummary();

        } else {

            const emptyRow = [...(els.lineItems?.querySelectorAll('.wms-grn-line') || [])].find((row) => {

                const idx = row.dataset.lineIdx;

                return !row.querySelector(`[name="product_id_${idx}"]`)?.value;

            });

            if (emptyRow) {

                const idx = emptyRow.dataset.lineIdx;

                const sel = emptyRow.querySelector(`[name="product_id_${idx}"]`);

                if (sel) {

                    sel.innerHTML = productOptions(product.id);

                    sel.value = String(product.id);

                    sel.dispatchEvent(new Event('change'));

                }

                emptyRow.querySelector(`[name="qty_${idx}"]`)?.focus();

                updateLineSummary();

            } else {

                addLineRow({

                    product_id: product.id,

                    unit_cost: product.cost_price || product.cost || product.price || 0,

                    quantity_received: 1,

                }, true);

            }

        }

        return true;

    }



    function collectItems() {

        const items = [];

        els.lineItems?.querySelectorAll('.wms-grn-line').forEach((row) => {

            const idx = row.dataset.lineIdx;

            const productId = row.querySelector(`[name="product_id_${idx}"]`)?.value;

            const qty = parseInt(row.querySelector(`[name="qty_${idx}"]`)?.value, 10);

            const cost = parseFloat(row.querySelector(`[name="cost_${idx}"]`)?.value);

            const batch = row.querySelector(`[name="batch_${idx}"]`)?.value?.trim();

            const expiry = row.querySelector(`[name="expiry_${idx}"]`)?.value;

            const locationId = row.querySelector(`[name="location_id_${idx}"]`)?.value;

            if (!productId || !qty) return;

            items.push({

                product_id: Number(productId),

                quantity_received: qty,

                unit_cost: Number.isFinite(cost) ? cost : 0,

                batch_number: batch || null,

                expiry_date: expiry || null,

                location_id: locationId ? Number(locationId) : null,

            });

        });

        return items;

    }



    async function submitForm(e) {

        e.preventDefault();

        if (!canReceive || state.submitting) return;

        const formEl = e.target;

        const wh = els.warehouse?.value;

        if (!wh) {

            notifyFormError(t('wh_rcv_select_warehouse'));

            return;

        }

        const items = collectItems();

        if (!items.length) {

            notifyFormError(t('wms_select_product'));

            return;

        }

        const postNow = els.mode?.querySelector('[value="post"]')?.checked;

        if (postNow && !window.confirm(t('wms_confirm_complete'))) return;



        const supplier = formEl.querySelector('[name="supplier_name"]')?.value?.trim();

        let notes = formEl.querySelector('[name="notes"]')?.value?.trim() || null;

        if (supplier) {

            const prefix = `${t('wms_col_supplier')}: ${supplier}`;

            notes = notes ? `${prefix}\n${notes}` : prefix;

        }

        if (state.poContext?.po_number) {

            const poNote = `${t('wms_po_col_number')}: ${state.poContext.po_number}`;

            notes = notes ? `${poNote}\n${notes}` : poNote;

        }



        state.submitting = true;

        setSubmitLoading(true);

        clearFormError();

        try {

            const payload = {

                warehouse_id: Number(wh),

                notes,

                status: 'pending',

                items,

            };

            if (state.poContext?.id) payload.purchase_order_id = state.poContext.id;



            const res = await AdminAPI.createWmsReceipt(payload);

            if (res.status !== 'success') {

                notifyFormError(res.message || t('error'));

                return;

            }

            const receiptId = res.data?.id;

            const grnNumber = res.data?.grn_number || '';



            if (postNow && receiptId) {

                const done = await AdminAPI.completeWmsReceipt(receiptId);

                if (done.status !== 'success') {

                    notifyFormError(done.message || t('error'));

                    toast(t('wh_rcv_toast_saved'));

                    return;

                }

                toast(`${t('wh_rcv_toast_posted')}${grnNumber ? ` (${grnNumber})` : ''}`);

            } else {

                toast(`${t('wh_rcv_toast_saved')}${grnNumber ? ` (${grnNumber})` : ''}`);

            }



            resetForm();

            await loadRecent();

        } finally {

            state.submitting = false;

            setSubmitLoading(false);

        }

    }



    async function initWarehouses() {
        if (!els.warehouse) return;
        const res = await AdminAPI.getWmsWarehouses({ limit: 200 });
        const items = res.status === 'success' ? (res.data || []) : [];
        const cur = String(window.WH_PAGE?.warehouseId || els.warehouse.value || '');
        els.warehouse.innerHTML = `<option value="">${esc(t('wh_rcv_select_warehouse'))}</option>`
            + items.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
        if (cur && items.some((w) => String(w.id) === cur)) {
            els.warehouse.value = cur;
        }
    }

    async function loadFromPurchaseOrder(poId) {

        const res = await AdminAPI.getWmsPurchaseOrder(poId);

        if (res.status !== 'success' || !res.data) {

            throw new Error(res.message || t('wh_rcv_po_error'));

        }

        const po = res.data;

        if (!['approved', 'partial'].includes(po.status)) {

            throw new Error(t('wh_rcv_po_error'));

        }



        await loadProducts();

        if (els.warehouse && po.warehouse_id) {

            els.warehouse.value = String(po.warehouse_id);

            await loadLocations(po.warehouse_id);

        }



        const supplierInput = els.form?.querySelector('[name="supplier_name"]');

        if (supplierInput && po.supplier_name) supplierInput.value = po.supplier_name;



        const notesInput = els.form?.querySelector('[name="notes"]');

        if (notesInput && po.notes) notesInput.value = po.notes;



        if (els.lineItems) els.lineItems.innerHTML = '';

        state.lineIndex = 0;



        let linesAdded = 0;

        for (const line of po.items || []) {

            const remaining = Number(line.quantity_ordered || 0) - Number(line.quantity_received || 0);

            if (remaining <= 0) continue;

            addLineRow({

                product_id: line.product_id,

                quantity_received: remaining,

                unit_cost: line.unit_cost || 0,

            });

            linesAdded += 1;

        }

        if (!linesAdded) throw new Error(t('wh_rcv_po_error'));



        state.poContext = { id: po.id, po_number: po.po_number };

        showPoBanner(po);

        toast(t('wh_rcv_po_loaded'));

        updateLineSummary();

    }



    async function onWarehouseChange() {

        const wh = els.warehouse?.value;

        await loadLocations(wh);

        refreshLocationSelects();

        loadRecent();

    }



    function initFromQuery() {

        const params = new URLSearchParams(window.location.search);

        const po = params.get('po');

        const q = params.get('q');

        if (po && canReceive) {

            loadFromPurchaseOrder(po).catch((err) => notifyFormError(err.message));

        } else if (q && canReceive) {

            loadProducts().then(() => handleScan(q));

        }

    }



    if (canReceive && form) {

        els.mode?.querySelectorAll('input[name="receive_mode"]').forEach((el) => {

            el.addEventListener('change', updateModeLabel);

        });

        els.warehouse?.addEventListener('change', onWarehouseChange);

        els.addLine?.addEventListener('click', () => loadProducts().then(() => addLineRow()));

        els.productFilter?.addEventListener('input', refreshProductSelects);



        WarehouseReceiveScan.init({

            t,

            enabled: true,

            onScan: (code) => handleScan(code),

        });



        els.reset?.addEventListener('click', () => resetForm());

        els.poBannerClose?.addEventListener('click', hidePoBanner);

        form.addEventListener('submit', submitForm);



        initWarehouses()

            .then(loadProducts)

            .then(() => loadLocations(els.warehouse?.value))

            .then(() => {

                addLineRow();

                updateModeLabel();

                initFromQuery();

                if (!new URLSearchParams(window.location.search).get('q')) {

                    WarehouseReceiveScan.focusWedge();

                }

            })

            .catch((err) => notifyFormError(err.message || t('load_error')));

    } else if (els.warehouse) {

        initWarehouses().catch(() => {});

    }



    els.refreshRecent?.addEventListener('click', loadRecent);

    document.addEventListener('wh:refresh', loadRecent);

    document.addEventListener('store-switched', () => {

        if (canReceive) {

            initWarehouses().then(onWarehouseChange);

        } else {

            loadRecent();

        }

    });



    loadRecent();

});


