document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsReqRoot');
    if (!root) return;

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated } = WmsUI;
    const canManage = !!window.ADMIN_PAGE?.canManage;

    let allRequests = [];
    let warehouses = [];
    let stores = [];
    let products = [];
    let lineIndex = 0;

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

    function setStats(summary) {
        const s = summary || {};
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsReqTotal', String(s.total ?? 0));
        set('wmsReqPending', String(s.pending ?? 0));
        set('wmsReqApproved', String(s.approved ?? 0));
        set('wmsReqUrgent', String(s.urgent ?? 0));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function applyClientFilter(items) {
        const q = (document.getElementById('wmsReqSearch')?.value || '').trim().toLowerCase();
        if (!q) return items;
        return items.filter((r) => {
            const hay = [r.request_number, r.store_name, r.warehouse_name, r.requested_by_name, r.status, r.priority]
                .join(' ').toLowerCase();
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
            <th>${esc(t('wms_col_request'))}</th>
            <th>${esc(t('wms_col_store'))}</th>
            <th>${esc(t('wms_nav_warehouses'))}</th>
            <th>${esc(t('wms_col_priority'))}</th>
            <th>${esc(t('wms_col_items'))}</th>
            <th>${esc(t('wms_col_qty'))}</th>
            <th>${esc(t('col_date'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${list.map((r) => {
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
                <td>${esc(AdminAPI.formatDate(r.created_at, { dateStyle: 'short', timeStyle: 'short' }))}</td>
                <td>${statusBadge(r.status)}</td>
                <td class="cr-actions">
                    <button type="button" class="cr-btn cr-btn--ghost" data-req-view="${r.id}">${esc(t('wms_view_details'))}</button>
                    ${canMgrApprove ? `<button type="button" class="cr-btn" data-req-approve-mgr="${r.id}">${esc(t('wms_approve'))}</button>` : ''}
                    ${canWhApprove ? `<button type="button" class="cr-btn" data-req-approve-wh="${r.id}">${esc(t('wms_approve_warehouse'))}</button>` : ''}
                    ${canReject ? `<button type="button" class="cr-btn cr-btn--warn" data-req-reject="${r.id}">${esc(t('wms_reject'))}</button>` : ''}
                </td>
            </tr>`;
        }).join('')}</tbody></table></div>`;

        root.querySelectorAll('[data-req-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.reqView)));
        });
        root.querySelectorAll('[data-req-approve-mgr]').forEach((btn) => {
            btn.addEventListener('click', () => approveRequest(Number(btn.dataset.reqApproveMgr), 'manager'));
        });
        root.querySelectorAll('[data-req-approve-wh]').forEach((btn) => {
            btn.addEventListener('click', () => approveRequest(Number(btn.dataset.reqApproveWh), 'warehouse'));
        });
        root.querySelectorAll('[data-req-reject]').forEach((btn) => {
            btn.addEventListener('click', () => rejectRequest(Number(btn.dataset.reqReject)));
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
        fill(document.getElementById('wmsReqWarehouse'), true);
        fill(document.getElementById('wmsReqFormWarehouse'), false, t('wms_select_warehouse'));
    }

    async function loadStores() {
        if (!stores.length) {
            const res = await AdminAPI.listStores();
            stores = res.status === 'success' ? (res.data || []) : [];
        }
        const fill = (sel, includeAll) => {
            if (!sel) return;
            const cur = sel.value;
            const opts = includeAll ? `<option value="">${esc(t('wms_all_stores'))}</option>` : '';
            sel.innerHTML = opts + stores.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
            if (cur) sel.value = cur;
        };
        fill(document.getElementById('wmsReqStore'), true);
        fill(document.getElementById('wmsReqFormStore'), false);
        const formStore = document.getElementById('wmsReqFormStore');
        if (formStore && !formStore.options.length) {
            formStore.innerHTML = `<option value="">${esc(t('wms_select_store'))}</option>` +
                stores.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
        }
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
            const wh = document.getElementById('wmsReqWarehouse')?.value;
            const store = document.getElementById('wmsReqStore')?.value;
            const status = document.getElementById('wmsReqStatus')?.value;
            const q = document.getElementById('wmsReqSearch')?.value?.trim();
            const res = await AdminAPI.getWmsRequests(wh, store, status, q);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allRequests = res.data || [];
            setStats(res.summary);
            renderTable(allRequests);
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

    function productOptions(selectedId = '', filter = '') {
        const q = filter.trim().toLowerCase();
        const list = q ? products.filter((p) => `${p.name || ''} ${p.sku || ''}`.toLowerCase().includes(q)) : products;
        return `<option value="">${esc(t('wms_select_product'))}</option>` +
            list.map((p) => `<option value="${p.id}" ${String(p.id) === String(selectedId) ? 'selected' : ''}>${esc(p.name)}${p.sku ? ` · ${esc(p.sku)}` : ''}</option>`).join('');
    }

    function refreshProductSelects() {
        const filter = document.getElementById('wmsReqProductFilter')?.value || '';
        document.querySelectorAll('#wmsReqLineItems .wms-grn-line--request').forEach((row) => {
            const idx = row.dataset.lineIdx;
            const sel = row.querySelector(`[name="product_id_${idx}"]`);
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = productOptions(cur, filter);
            if (cur) sel.value = cur;
        });
    }

    function updateLineSummary() {
        const rows = document.querySelectorAll('#wmsReqLineItems .wms-grn-line--request');
        const countEl = document.getElementById('wmsReqLineCount');
        if (countEl) countEl.textContent = t('wms_grn_lines_count', rows.length);
        rows.forEach((row, i) => {
            const num = row.querySelector('.wms-grn-line__num');
            if (num) num.textContent = String(i + 1);
        });
        document.querySelectorAll('.wms-grn-line--request .wms-grn-line__remove').forEach((btn) => {
            btn.disabled = rows.length <= 1;
        });
    }

    function bindLineInputs(row, idx) {
        row.querySelectorAll('input, select').forEach((el) => {
            el.addEventListener('input', updateLineSummary);
            el.addEventListener('change', updateLineSummary);
        });
        row.querySelector('[data-remove-line]')?.addEventListener('click', () => {
            if (document.querySelectorAll('#wmsReqLineItems .wms-grn-line--request').length <= 1) return;
            row.remove();
            updateLineSummary();
        });
    }

    function addLineRow(data = {}) {
        const container = document.getElementById('wmsReqLineItems');
        if (!container) return;
        const idx = lineIndex++;
        const filter = document.getElementById('wmsReqProductFilter')?.value || '';
        const row = document.createElement('article');
        row.className = 'wms-grn-line wms-grn-line--request';
        row.dataset.lineIdx = String(idx);
        const lineNo = container.querySelectorAll('.wms-grn-line--request').length + 1;
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
            <label class="wms-grn-line__field wms-grn-line__field--qty">
                <span>${esc(t('wms_qty_short'))}</span>
                <input type="number" name="qty_${idx}" min="1" value="${data.quantity || 1}" required>
            </label>
        `;
        bindLineInputs(row, idx);
        container.appendChild(row);
        updateLineSummary();
    }

    async function openCreateModal() {
        await Promise.all([loadProducts(), loadStores(), loadWarehouses()]);
        document.getElementById('wmsReqCreateForm')?.reset();
        document.getElementById('wmsReqProductFilter').value = '';
        document.getElementById('wmsReqLineItems').innerHTML = '';
        lineIndex = 0;
        const storeSel = document.getElementById('wmsReqFormStore');
        if (storeSel && window.ADMIN_PAGE?.storeId) storeSel.value = String(window.ADMIN_PAGE.storeId);
        addLineRow();
        openModal('wmsReqCreateModal');
    }

    async function submitCreate(e) {
        e.preventDefault();
        const form = e.target;
        const items = [];
        document.querySelectorAll('#wmsReqLineItems .wms-grn-line--request').forEach((row) => {
            const idx = row.dataset.lineIdx;
            const productId = row.querySelector(`[name="product_id_${idx}"]`)?.value;
            const qty = parseInt(row.querySelector(`[name="qty_${idx}"]`)?.value, 10);
            if (!productId || !qty) return;
            items.push({ product_id: Number(productId), quantity: qty });
        });
        if (!items.length) {
            showError(t('wms_select_product'));
            return;
        }
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
            showError(res.message || t('error'));
            return;
        }
        closeModal('wmsReqCreateModal');
        hideError();
        await load();
        if (res.data?.id) openDetail(res.data.id);
    }

    async function openDetail(id) {
        const body = document.getElementById('wmsReqDetailBody');
        if (!body) return;
        body.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        openModal('wmsReqDetailModal');
        try {
            const res = await AdminAPI.getWmsRequest(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            document.getElementById('wmsReqDetailTitle').textContent = `${t('wms_request_details')} — ${r.request_number}`;
            const sub = document.getElementById('wmsReqDetailSubtitle');
            if (sub) sub.textContent = `${r.store_name || '—'} → ${r.warehouse_name || '—'}`;
            const items = r.items || [];
            const canMgrApprove = canManage && r.status === 'pending';
            const canWhApprove = canManage && r.status === 'manager_approved';
            const canReject = canManage && ['pending', 'manager_approved'].includes(r.status);
            body.innerHTML = `
                <dl class="wms-detail-grid">
                    <div><dt>${esc(t('wms_col_store'))}</dt><dd>${esc(r.store_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_priority'))}</dt><dd>${priorityBadge(r.priority)}</dd></div>
                    <div><dt>${esc(t('wms_col_requested_by'))}</dt><dd>${esc(r.requested_by_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(AdminAPI.formatDate(r.created_at))}</dd></div>
                </dl>
                ${r.notes ? `<p class="cr-muted"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(r.notes)}</p>` : ''}
                <div class="cr-table-wrap"><table class="modern-table"><thead><tr>
                    <th>${esc(t('wms_col_product'))}</th><th>SKU</th><th>${esc(t('wms_col_qty'))}</th>
                    <th>Approved</th><th>Delivered</th>
                </tr></thead><tbody>${items.map((i) => `<tr>
                    <td>${esc(i.product_name)}</td><td>${esc(i.sku || '—')}</td><td>${i.quantity_requested}</td>
                    <td>${i.quantity_approved || 0}</td><td>${i.quantity_delivered || 0}</td>
                </tr>`).join('')}</tbody></table></div>
                ${canMgrApprove || canWhApprove || canReject ? `<div class="cr-form-actions" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
                    ${canMgrApprove ? `<button type="button" class="cr-btn" id="wmsReqDetailApproveMgr">${esc(t('wms_approve'))}</button>` : ''}
                    ${canWhApprove ? `<button type="button" class="cr-btn" id="wmsReqDetailApproveWh">${esc(t('wms_approve_warehouse'))}</button>` : ''}
                    ${canReject ? `<button type="button" class="cr-btn cr-btn--warn" id="wmsReqDetailReject">${esc(t('wms_reject'))}</button>` : ''}
                </div>` : ''}`;
            document.getElementById('wmsReqDetailApproveMgr')?.addEventListener('click', () => approveRequest(r.id, 'manager', true));
            document.getElementById('wmsReqDetailApproveWh')?.addEventListener('click', () => approveRequest(r.id, 'warehouse', true));
            document.getElementById('wmsReqDetailReject')?.addEventListener('click', () => rejectRequest(r.id, true));
        } catch (e) {
            body.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
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
        if (fromDetail) closeModal('wmsReqDetailModal');
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
        if (fromDetail) closeModal('wmsReqDetailModal');
        await load();
    }

    document.getElementById('wmsReqRefresh')?.addEventListener('click', load);
    document.getElementById('wmsReqWarehouse')?.addEventListener('change', load);
    document.getElementById('wmsReqStore')?.addEventListener('change', load);
    document.getElementById('wmsReqStatus')?.addEventListener('change', load);
    document.getElementById('wmsReqSearch')?.addEventListener('input', () => renderTable(allRequests));

    document.getElementById('wmsReqNewBtn')?.addEventListener('click', openCreateModal);
    document.getElementById('wmsReqAddLine')?.addEventListener('click', () => loadProducts().then(addLineRow));
    document.getElementById('wmsReqProductFilter')?.addEventListener('input', refreshProductSelects);
    document.getElementById('wmsReqCreateForm')?.addEventListener('submit', submitCreate);
    ['wmsReqCreateClose', 'wmsReqCreateCancel'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', () => closeModal('wmsReqCreateModal'));
    });
    document.getElementById('wmsReqDetailClose')?.addEventListener('click', () => closeModal('wmsReqDetailModal'));
    document.getElementById('wmsReqCreateModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'wmsReqCreateModal') closeModal('wmsReqCreateModal');
    });
    document.getElementById('wmsReqDetailModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'wmsReqDetailModal') closeModal('wmsReqDetailModal');
    });

    document.addEventListener('wms:refresh', load);
    Promise.all([loadWarehouses(), loadStores()]).then(load);
});
