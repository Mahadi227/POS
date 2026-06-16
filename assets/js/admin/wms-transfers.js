document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsTrfRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = WmsUI;
    const canManage = !!window.ADMIN_PAGE?.canManage;

    let allTransfers = [];
    let warehouses = [];
    let stores = [];
    let products = [];
    let lineIndex = 0;

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

    const TYPE_KEYS = {
        warehouse_to_warehouse: 'wms_type_wh_wh',
        warehouse_to_store: 'wms_type_wh_store',
        store_to_warehouse: 'wms_type_store_wh',
        branch_to_branch: 'wms_type_branch',
    };

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function statusBadge(status) {
        const cls = status === 'completed' ? 'ok' : (status === 'rejected' || status === 'cancelled' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function endpointLabel(row, dir) {
        if (dir === 'from') {
            return row.from_warehouse_name || row.from_store_name || '—';
        }
        return row.to_warehouse_name || row.to_store_name || '—';
    }

    function setStats(summary) {
        const s = summary || {};
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsTrfTotal', String(s.total ?? 0));
        set('wmsTrfRequested', String(s.requested ?? 0));
        set('wmsTrfProgress', String(s.in_progress ?? 0));
        set('wmsTrfCompleted', String(s.completed ?? 0));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function applyClientFilter(items) {
        const q = (document.getElementById('wmsTrfSearch')?.value || '').trim().toLowerCase();
        if (!q) return items;
        return items.filter((r) => {
            const hay = [r.transfer_number, r.transfer_type, r.from_warehouse_name, r.to_warehouse_name,
                r.from_store_name, r.to_store_name, r.reason, r.status].join(' ').toLowerCase();
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
            <th>${esc(t('wms_col_transfer'))}</th>
            <th>${esc(t('wms_col_type'))}</th>
            <th>${esc(t('wms_col_from'))}</th>
            <th>${esc(t('wms_col_to'))}</th>
            <th>${esc(t('wms_col_items'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('col_date'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${list.map((r) => {
            const canApprove = canManage && r.status === 'requested';
            const canComplete = canManage && r.status === 'approved';
            const canReject = canManage && r.status === 'requested';
            return `<tr>
                <td><strong>${esc(r.transfer_number)}</strong></td>
                <td>${esc(typeLabel(r.transfer_type))}</td>
                <td>${esc(endpointLabel(r, 'from'))}</td>
                <td>${esc(endpointLabel(r, 'to'))}</td>
                <td>${Number(r.total_items || 0)}</td>
                <td>${esc(money(r.total_value))}</td>
                <td>${esc(AdminAPI.formatDate(r.created_at, { dateStyle: 'short', timeStyle: 'short' }))}</td>
                <td>${statusBadge(r.status)}</td>
                <td class="cr-actions">
                    <button type="button" class="cr-btn cr-btn--ghost" data-trf-view="${r.id}">${esc(t('wms_view_details'))}</button>
                    ${canApprove ? `<button type="button" class="cr-btn" data-trf-approve="${r.id}">${esc(t('wms_approve'))}</button>` : ''}
                    ${canComplete ? `<button type="button" class="cr-btn" data-trf-complete="${r.id}">${esc(t('wms_complete'))}</button>` : ''}
                    ${canReject ? `<button type="button" class="cr-btn cr-btn--warn" data-trf-reject="${r.id}">${esc(t('wms_reject'))}</button>` : ''}
                </td>
            </tr>`;
        }).join('')}</tbody></table></div>`;

        root.querySelectorAll('[data-trf-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.trfView)));
        });
        root.querySelectorAll('[data-trf-approve]').forEach((btn) => {
            btn.addEventListener('click', () => approveTransfer(Number(btn.dataset.trfApprove)));
        });
        root.querySelectorAll('[data-trf-complete]').forEach((btn) => {
            btn.addEventListener('click', () => completeTransfer(Number(btn.dataset.trfComplete)));
        });
        root.querySelectorAll('[data-trf-reject]').forEach((btn) => {
            btn.addEventListener('click', () => rejectTransfer(Number(btn.dataset.trfReject)));
        });
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses();
        warehouses = res.status === 'success' ? (res.data || []) : [];
        const fill = (sel, placeholder) => {
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = `<option value="">${esc(placeholder || t('wms_select_warehouse'))}</option>` +
                warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
            if (cur) sel.value = cur;
        };
        fill(document.getElementById('wmsTrfWarehouse'), t('wms_all_warehouses'));
        fill(document.getElementById('wmsTrfFromWh'));
        fill(document.getElementById('wmsTrfToWh'));
    }

    async function loadStores() {
        if (!stores.length) {
            const res = await AdminAPI.listStores();
            stores = res.status === 'success' ? (res.data || []) : [];
        }
        const fill = (sel) => {
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = `<option value="">${esc(t('wms_select_store'))}</option>` +
                stores.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
            if (cur) sel.value = cur;
        };
        fill(document.getElementById('wmsTrfFromStore'));
        fill(document.getElementById('wmsTrfToStore'));
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
            const wh = document.getElementById('wmsTrfWarehouse')?.value;
            const status = document.getElementById('wmsTrfStatus')?.value;
            const q = document.getElementById('wmsTrfSearch')?.value?.trim();
            const res = await AdminAPI.getWmsTransfers(wh, status, q);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allTransfers = res.data || [];
            setStats(res.summary);
            renderTable(allTransfers);
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

    function toggleTypeFields() {
        const type = document.getElementById('wmsTrfType')?.value || 'warehouse_to_warehouse';
        const setField = (fieldId, selectId, visible, required) => {
            const field = document.getElementById(fieldId);
            const sel = document.getElementById(selectId);
            if (field) field.hidden = !visible;
            if (sel) {
                sel.required = !!required && visible;
                if (!visible) sel.value = '';
            }
        };
        setField('wmsTrfFromWhField', 'wmsTrfFromWh', ['warehouse_to_warehouse', 'warehouse_to_store'].includes(type), true);
        setField('wmsTrfToWhField', 'wmsTrfToWh', ['warehouse_to_warehouse', 'store_to_warehouse'].includes(type), true);
        setField('wmsTrfFromStoreField', 'wmsTrfFromStore', ['store_to_warehouse', 'branch_to_branch'].includes(type), true);
        setField('wmsTrfToStoreField', 'wmsTrfToStore', ['warehouse_to_store', 'branch_to_branch'].includes(type), true);
    }

    function productOptions(selectedId = '', filter = '') {
        const q = filter.trim().toLowerCase();
        const list = q ? products.filter((p) => `${p.name || ''} ${p.sku || ''}`.toLowerCase().includes(q)) : products;
        return `<option value="">${esc(t('wms_select_product'))}</option>` +
            list.map((p) => `<option value="${p.id}" data-cost="${p.cost_price || p.price || 0}" ${String(p.id) === String(selectedId) ? 'selected' : ''}>${esc(p.name)}${p.sku ? ` · ${esc(p.sku)}` : ''}</option>`).join('');
    }

    function refreshProductSelects() {
        const filter = document.getElementById('wmsTrfProductFilter')?.value || '';
        document.querySelectorAll('#wmsTrfLineItems .wms-grn-line--dispatch').forEach((row) => {
            const idx = row.dataset.lineIdx;
            const sel = row.querySelector(`[name="product_id_${idx}"]`);
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = productOptions(cur, filter);
            if (cur) sel.value = cur;
        });
    }

    function updateLineSummary() {
        const rows = document.querySelectorAll('#wmsTrfLineItems .wms-grn-line--dispatch');
        let total = 0;
        rows.forEach((row) => {
            const idx = row.dataset.lineIdx;
            const qty = parseFloat(row.querySelector(`[name="qty_${idx}"]`)?.value) || 0;
            const cost = parseFloat(row.querySelector(`[name="cost_${idx}"]`)?.value) || 0;
            total += qty * cost;
            const subEl = row.querySelector('.wms-grn-line__subtotal-val');
            if (subEl) subEl.textContent = money(qty * cost);
        });
        const countEl = document.getElementById('wmsTrfLineCount');
        const totalEl = document.getElementById('wmsTrfEstTotal');
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
            if (document.querySelectorAll('#wmsTrfLineItems .wms-grn-line--dispatch').length <= 1) return;
            row.remove();
            updateLineSummary();
        });
    }

    function addLineRow(data = {}) {
        const container = document.getElementById('wmsTrfLineItems');
        if (!container) return;
        const idx = lineIndex++;
        const filter = document.getElementById('wmsTrfProductFilter')?.value || '';
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
        document.getElementById('wmsTrfCreateForm')?.reset();
        document.getElementById('wmsTrfProductFilter').value = '';
        document.getElementById('wmsTrfLineItems').innerHTML = '';
        lineIndex = 0;
        toggleTypeFields();
        addLineRow();
        openModal('wmsTrfCreateModal');
    }

    async function submitCreate(e) {
        e.preventDefault();
        const form = e.target;
        const type = form.transfer_type?.value;
        const items = [];
        document.querySelectorAll('#wmsTrfLineItems .wms-grn-line--dispatch').forEach((row) => {
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
            transfer_type: type,
            from_warehouse_id: form.from_warehouse_id?.value ? Number(form.from_warehouse_id.value) : null,
            to_warehouse_id: form.to_warehouse_id?.value ? Number(form.to_warehouse_id.value) : null,
            from_store_id: form.from_store_id?.value ? Number(form.from_store_id.value) : null,
            to_store_id: form.to_store_id?.value ? Number(form.to_store_id.value) : null,
            reason: form.reason?.value?.trim() || null,
            status: 'requested',
            items,
        };
        const res = await AdminAPI.createWmsTransfer(payload);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        closeModal('wmsTrfCreateModal');
        hideError();
        await load();
        const newId = res.data?.id;
        if (newId) openDetail(newId);
    }

    async function openDetail(id) {
        const body = document.getElementById('wmsTrfDetailBody');
        if (!body) return;
        body.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        openModal('wmsTrfDetailModal');
        try {
            const res = await AdminAPI.getWmsTransfer(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            document.getElementById('wmsTrfDetailTitle').textContent = `${t('wms_transfer_details')} — ${r.transfer_number}`;
            const sub = document.getElementById('wmsTrfDetailSubtitle');
            if (sub) sub.textContent = `${typeLabel(r.transfer_type)} · ${endpointLabel(r, 'from')} → ${endpointLabel(r, 'to')}`;
            const items = r.items || [];
            const canApprove = canManage && r.status === 'requested';
            const canComplete = canManage && r.status === 'approved';
            const canReject = canManage && r.status === 'requested';
            body.innerHTML = `
                <dl class="wms-detail-grid">
                    <div><dt>${esc(t('wms_col_type'))}</dt><dd>${esc(typeLabel(r.transfer_type))}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_from'))}</dt><dd>${esc(endpointLabel(r, 'from'))}</dd></div>
                    <div><dt>${esc(t('wms_col_to'))}</dt><dd>${esc(endpointLabel(r, 'to'))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.total_value))}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(AdminAPI.formatDate(r.created_at))}</dd></div>
                </dl>
                ${r.reason ? `<p class="cr-muted"><strong>${esc(t('wms_col_reason'))}:</strong> ${esc(r.reason)}</p>` : ''}
                <div class="cr-table-wrap"><table class="modern-table"><thead><tr>
                    <th>${esc(t('wms_col_product'))}</th><th>SKU</th><th>${esc(t('wms_col_qty'))}</th>
                    <th>${esc(t('wms_unit_cost'))}</th><th>Sent</th><th>Received</th>
                </tr></thead><tbody>${items.map((i) => `<tr>
                    <td>${esc(i.product_name)}</td><td>${esc(i.sku || '—')}</td><td>${i.quantity_requested}</td>
                    <td>${esc(money(i.unit_cost))}</td><td>${i.quantity_sent || 0}</td><td>${i.quantity_received || 0}</td>
                </tr>`).join('')}</tbody></table></div>
                ${canApprove || canComplete || canReject ? `<div class="cr-form-actions" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
                    ${canApprove ? `<button type="button" class="cr-btn" id="wmsTrfDetailApprove">${esc(t('wms_approve'))}</button>` : ''}
                    ${canComplete ? `<button type="button" class="cr-btn" id="wmsTrfDetailComplete">${esc(t('wms_complete'))}</button>` : ''}
                    ${canReject ? `<button type="button" class="cr-btn cr-btn--warn" id="wmsTrfDetailReject">${esc(t('wms_reject'))}</button>` : ''}
                </div>` : ''}`;
            document.getElementById('wmsTrfDetailApprove')?.addEventListener('click', () => approveTransfer(r.id, true));
            document.getElementById('wmsTrfDetailComplete')?.addEventListener('click', () => completeTransfer(r.id, true));
            document.getElementById('wmsTrfDetailReject')?.addEventListener('click', () => rejectTransfer(r.id, true));
        } catch (e) {
            body.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function approveTransfer(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_approve_trf'))) return;
        const res = await AdminAPI.approveWmsTransfer(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        if (fromDetail) closeModal('wmsTrfDetailModal');
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
        if (fromDetail) closeModal('wmsTrfDetailModal');
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
        if (fromDetail) closeModal('wmsTrfDetailModal');
        await load();
    }

    document.getElementById('wmsTrfRefresh')?.addEventListener('click', load);
    document.getElementById('wmsTrfWarehouse')?.addEventListener('change', load);
    document.getElementById('wmsTrfStatus')?.addEventListener('change', load);
    document.getElementById('wmsTrfSearch')?.addEventListener('input', () => renderTable(allTransfers));

    document.getElementById('wmsTrfNewBtn')?.addEventListener('click', openCreateModal);
    document.getElementById('wmsTrfAddLine')?.addEventListener('click', () => loadProducts().then(addLineRow));
    document.getElementById('wmsTrfProductFilter')?.addEventListener('input', refreshProductSelects);
    document.getElementById('wmsTrfType')?.addEventListener('change', toggleTypeFields);
    document.getElementById('wmsTrfCreateForm')?.addEventListener('submit', submitCreate);
    ['wmsTrfCreateClose', 'wmsTrfCreateCancel'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', () => closeModal('wmsTrfCreateModal'));
    });
    document.getElementById('wmsTrfDetailClose')?.addEventListener('click', () => closeModal('wmsTrfDetailModal'));
    document.getElementById('wmsTrfCreateModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'wmsTrfCreateModal') closeModal('wmsTrfCreateModal');
    });
    document.getElementById('wmsTrfDetailModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'wmsTrfDetailModal') closeModal('wmsTrfDetailModal');
    });

    document.addEventListener('wms:refresh', load);
    loadWarehouses().then(load);
});
