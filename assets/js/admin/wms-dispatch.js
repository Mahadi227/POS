document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsDspRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = WmsUI;
    const canManage = !!window.ADMIN_PAGE?.canManage;

    let allDispatches = [];
    let warehouses = [];
    let stores = [];
    let products = [];
    let lineIndex = 0;

    const STATUS_KEYS = {
        draft: 'wms_status_draft',
        picking: 'wms_status_picking',
        packed: 'wms_status_packed',
        dispatched: 'wms_status_dispatched',
        in_transit: 'wms_status_in_transit',
        delivered: 'wms_status_delivered',
        cancelled: 'wms_status_cancelled',
    };

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

    function setStats(summary) {
        const s = summary || {};
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsDspTotal', String(s.total ?? 0));
        set('wmsDspDraft', String(s.draft ?? 0));
        set('wmsDspOutgoing', String(s.outgoing ?? 0));
        set('wmsDspDelivered', String(s.delivered ?? 0));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function applyClientFilter(items) {
        const q = (document.getElementById('wmsDspSearch')?.value || '').trim().toLowerCase();
        if (!q) return items;
        return items.filter((d) => {
            const hay = [d.dispatch_number, d.from_warehouse_name, d.to_store_name, d.to_warehouse_name,
                d.driver_name, d.vehicle_number, d.status].join(' ').toLowerCase();
            return hay.includes(q);
        });
    }

    function renderTable(items) {
        const list = applyClientFilter(items);
        if (!list.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table"><thead><tr>
            <th>${esc(t('wms_col_dispatch'))}</th>
            <th>${esc(t('wms_nav_warehouses'))}</th>
            <th>${esc(t('wms_col_destination'))}</th>
            <th>${esc(t('wms_col_items'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('wms_col_driver'))}</th>
            <th>${esc(t('col_date'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${list.map((d) => {
            const canShip = canManage && ['draft', 'picking', 'packed'].includes(d.status);
            return `<tr>
                <td><strong>${esc(d.dispatch_number)}</strong></td>
                <td>${esc(d.from_warehouse_name || '—')}</td>
                <td>${esc(d.to_store_name || d.to_warehouse_name || '—')}</td>
                <td>${Number(d.total_items || 0)}</td>
                <td>${esc(money(d.total_value))}</td>
                <td>${esc(d.driver_name || '—')}</td>
                <td>${esc(AdminAPI.formatDate(d.created_at, { dateStyle: 'short', timeStyle: 'short' }))}</td>
                <td>${statusBadge(d.status)}</td>
                <td class="cr-actions">
                    <button type="button" class="cr-btn cr-btn--ghost" data-dsp-view="${d.id}">${esc(t('wms_view_details'))}</button>
                    ${canShip ? `<button type="button" class="cr-btn" data-dsp-ship="${d.id}">${esc(t('wms_dispatch_btn'))}</button>` : ''}
                </td>
            </tr>`;
        }).join('')}</tbody></table></div>`;

        root.querySelectorAll('[data-dsp-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.dspView)));
        });
        root.querySelectorAll('[data-dsp-ship]').forEach((btn) => {
            btn.addEventListener('click', () => shipDispatch(Number(btn.dataset.dspShip)));
        });
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses();
        warehouses = res.status === 'success' ? (res.data || []) : [];
        const fill = (sel, includeAll, placeholder) => {
            if (!sel) return;
            const cur = sel.value;
            const opts = includeAll ? `<option value="">${esc(placeholder || t('wms_all_warehouses'))}</option>` : '';
            sel.innerHTML = opts + warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
            if (cur) sel.value = cur;
        };
        fill(document.getElementById('wmsDspWarehouse'), true);
        fill(document.getElementById('wmsDspFormWarehouse'), false, t('wms_select_warehouse'));
        fill(document.getElementById('wmsDspFormWhDest'), false, t('wms_select_warehouse'));
    }

    async function loadStores() {
        if (stores.length) return;
        const res = await AdminAPI.listStores();
        stores = res.status === 'success' ? (res.data || []) : [];
        const sel = document.getElementById('wmsDspFormStore');
        if (!sel) return;
        sel.innerHTML = `<option value="">${esc(t('wms_select_store'))}</option>` +
            stores.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
    }

    async function loadProducts() {
        if (products.length) return;
        const res = await AdminAPI.getInventoryProducts();
        products = res.status === 'success' ? (res.data || []) : [];
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.add('is-loading'));
        try {
            const wh = document.getElementById('wmsDspWarehouse')?.value;
            const status = document.getElementById('wmsDspStatus')?.value;
            const q = document.getElementById('wmsDspSearch')?.value?.trim();
            const res = await AdminAPI.getWmsDispatches(wh, status, q);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allDispatches = res.data || [];
            setStats(res.summary);
            renderTable(allDispatches);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
            document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
        }
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (el) { el.classList.add('is-open'); el.setAttribute('aria-hidden', 'false'); }
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) { el.classList.remove('is-open'); el.setAttribute('aria-hidden', 'true'); }
    }

    function toggleDestFields() {
        const type = document.getElementById('wmsDspDestType')?.value || 'store';
        const storeField = document.getElementById('wmsDspStoreField');
        const whField = document.getElementById('wmsDspWhDestField');
        const storeSel = document.getElementById('wmsDspFormStore');
        const whSel = document.getElementById('wmsDspFormWhDest');
        if (storeField) storeField.hidden = type !== 'store';
        if (whField) whField.hidden = type !== 'warehouse';
        if (storeSel) storeSel.required = type === 'store';
        if (whSel) whSel.required = type === 'warehouse';
    }

    function productOptions(selectedId = '', filter = '') {
        const q = filter.trim().toLowerCase();
        const list = q ? products.filter((p) => `${p.name || ''} ${p.sku || ''}`.toLowerCase().includes(q)) : products;
        return `<option value="">${esc(t('wms_select_product'))}</option>` +
            list.map((p) => `<option value="${p.id}" data-cost="${p.cost_price || p.price || 0}" ${String(p.id) === String(selectedId) ? 'selected' : ''}>${esc(p.name)}${p.sku ? ` · ${esc(p.sku)}` : ''}</option>`).join('');
    }

    function refreshProductSelects() {
        const filter = document.getElementById('wmsDspProductFilter')?.value || '';
        document.querySelectorAll('#wmsDspLineItems .wms-grn-line--dispatch').forEach((row) => {
            const idx = row.dataset.lineIdx;
            const sel = row.querySelector(`[name="product_id_${idx}"]`);
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = productOptions(cur, filter);
            if (cur) sel.value = cur;
        });
    }

    function updateLineSummary() {
        const rows = document.querySelectorAll('#wmsDspLineItems .wms-grn-line--dispatch');
        let total = 0;
        rows.forEach((row) => {
            const idx = row.dataset.lineIdx;
            const qty = parseFloat(row.querySelector(`[name="qty_${idx}"]`)?.value) || 0;
            const cost = parseFloat(row.querySelector(`[name="cost_${idx}"]`)?.value) || 0;
            total += qty * cost;
            const subEl = row.querySelector('.wms-grn-line__subtotal-val');
            if (subEl) subEl.textContent = money(qty * cost);
        });
        const countEl = document.getElementById('wmsDspLineCount');
        const totalEl = document.getElementById('wmsDspEstTotal');
        if (countEl) countEl.textContent = t('wms_grn_lines_count', rows.length);
        if (totalEl) totalEl.textContent = money(total);
        rows.forEach((row, i) => {
            const num = row.querySelector('.wms-grn-line__num');
            if (num) num.textContent = String(i + 1);
        });
        document.querySelectorAll('.wms-grn-line--dispatch .wms-grn-line__remove').forEach((btn) => {
            btn.disabled = rows.length <= 1;
        });
    }

    function bindLineInputs(row, idx) {
        row.querySelectorAll('input, select').forEach((el) => {
            el.addEventListener('input', updateLineSummary);
            el.addEventListener('change', updateLineSummary);
        });
        const sel = row.querySelector('select');
        sel?.addEventListener('change', () => {
            const opt = sel.selectedOptions[0];
            const costInput = row.querySelector(`[name="cost_${idx}"]`);
            if (opt?.dataset.cost && costInput && !costInput.value) {
                costInput.value = opt.dataset.cost;
                updateLineSummary();
            }
        });
        row.querySelector('[data-remove-line]')?.addEventListener('click', () => {
            if (document.querySelectorAll('#wmsDspLineItems .wms-grn-line--dispatch').length <= 1) return;
            row.remove();
            updateLineSummary();
        });
    }

    function addLineRow(data = {}) {
        const container = document.getElementById('wmsDspLineItems');
        if (!container) return;
        const idx = lineIndex++;
        const filter = document.getElementById('wmsDspProductFilter')?.value || '';
        const row = document.createElement('article');
        row.className = 'wms-grn-line wms-grn-line--dispatch';
        row.dataset.lineIdx = String(idx);
        const lineNo = container.querySelectorAll('.wms-grn-line--dispatch').length + 1;
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
                    <input type="number" name="qty_${idx}" min="1" value="${data.quantity || 1}" required>
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
        `;
        bindLineInputs(row, idx);
        container.appendChild(row);
        updateLineSummary();
    }

    async function openCreateModal() {
        await Promise.all([loadProducts(), loadStores(), loadWarehouses()]);
        document.getElementById('wmsDspCreateForm')?.reset();
        document.getElementById('wmsDspProductFilter').value = '';
        document.getElementById('wmsDspLineItems').innerHTML = '';
        lineIndex = 0;
        toggleDestFields();
        addLineRow();
        openModal('wmsDspCreateModal');
    }

    async function submitCreate(e) {
        e.preventDefault();
        const form = e.target;
        const destType = form.dest_type?.value;
        const items = [];
        document.querySelectorAll('#wmsDspLineItems .wms-grn-line--dispatch').forEach((row) => {
            const idx = row.dataset.lineIdx;
            const productId = row.querySelector(`[name="product_id_${idx}"]`)?.value;
            const qty = parseInt(row.querySelector(`[name="qty_${idx}"]`)?.value, 10);
            const cost = parseFloat(row.querySelector(`[name="cost_${idx}"]`)?.value);
            if (!productId || !qty) return;
            items.push({
                product_id: Number(productId),
                quantity: qty,
                unit_cost: Number.isFinite(cost) ? cost : 0,
            });
        });
        if (!items.length) {
            showError(t('wms_select_product'));
            return;
        }
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
        const res = await AdminAPI.createWmsDispatch(payload);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        closeModal('wmsDspCreateModal');
        hideError();
        await load();
        if (res.data?.id) openDetail(res.data.id);
    }

    async function openDetail(id) {
        const body = document.getElementById('wmsDspDetailBody');
        if (!body) return;
        body.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        openModal('wmsDspDetailModal');
        try {
            const res = await AdminAPI.getWmsDispatch(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const d = res.data;
            document.getElementById('wmsDspDetailTitle').textContent = `${t('wms_dispatch_details')} — ${d.dispatch_number}`;
            const sub = document.getElementById('wmsDspDetailSubtitle');
            if (sub) sub.textContent = destinationLabel(d);
            const items = d.items || [];
            const canShip = canManage && ['draft', 'picking', 'packed'].includes(d.status);
            body.innerHTML = `
                <dl class="wms-detail-grid">
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(d.from_warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_destination'))}</dt><dd>${esc(d.to_store_name || d.to_warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(d.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(d.total_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_driver'))}</dt><dd>${esc(d.driver_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_vehicle'))}</dt><dd>${esc(d.vehicle_number || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_delivery_date'))}</dt><dd>${esc(d.delivery_date || '—')}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(AdminAPI.formatDate(d.created_at))}</dd></div>
                </dl>
                ${d.notes ? `<p class="cr-muted"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(d.notes)}</p>` : ''}
                <div class="cr-table-wrap"><table class="modern-table"><thead><tr>
                    <th>${esc(t('wms_col_product'))}</th><th>SKU</th><th>${esc(t('wms_col_qty'))}</th>
                    <th>${esc(t('wms_unit_cost'))}</th>
                </tr></thead><tbody>${items.map((i) => `<tr>
                    <td>${esc(i.product_name)}</td><td>${esc(i.sku || '—')}</td><td>${i.quantity}</td>
                    <td>${esc(money(i.unit_cost))}</td>
                </tr>`).join('')}</tbody></table></div>
                ${canShip ? `<div class="cr-form-actions" style="margin-top:16px">
                    <button type="button" class="cr-btn" id="wmsDspDetailShip" data-id="${d.id}">${esc(t('wms_dispatch_btn'))}</button>
                </div>` : ''}`;
            document.getElementById('wmsDspDetailShip')?.addEventListener('click', () => {
                shipDispatch(Number(d.id), true);
            });
        } catch (e) {
            body.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
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
        if (fromDetail) closeModal('wmsDspDetailModal');
        await load();
    }

    document.getElementById('wmsDspRefresh')?.addEventListener('click', load);
    document.getElementById('wmsDspWarehouse')?.addEventListener('change', load);
    document.getElementById('wmsDspStatus')?.addEventListener('change', load);
    document.getElementById('wmsDspSearch')?.addEventListener('input', () => renderTable(allDispatches));

    document.getElementById('wmsDspNewBtn')?.addEventListener('click', openCreateModal);
    document.getElementById('wmsDspAddLine')?.addEventListener('click', () => loadProducts().then(addLineRow));
    document.getElementById('wmsDspProductFilter')?.addEventListener('input', refreshProductSelects);
    document.getElementById('wmsDspDestType')?.addEventListener('change', toggleDestFields);
    document.getElementById('wmsDspCreateForm')?.addEventListener('submit', submitCreate);
    ['wmsDspCreateClose', 'wmsDspCreateCancel'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', () => closeModal('wmsDspCreateModal'));
    });
    document.getElementById('wmsDspDetailClose')?.addEventListener('click', () => closeModal('wmsDspDetailModal'));
    document.getElementById('wmsDspCreateModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'wmsDspCreateModal') closeModal('wmsDspCreateModal');
    });
    document.getElementById('wmsDspDetailModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'wmsDspDetailModal') closeModal('wmsDspDetailModal');
    });

    document.addEventListener('wms:refresh', load);
    loadWarehouses().then(load);
});
