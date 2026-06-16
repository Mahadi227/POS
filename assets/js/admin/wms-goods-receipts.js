document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsGrnRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = WmsUI;
    const canManage = !!window.ADMIN_PAGE?.canManage;

    let allReceipts = [];
    let warehouses = [];
    let products = [];
    let lineIndex = 0;

    const STATUS_KEYS = {
        pending: 'wms_status_pending',
        inspecting: 'wms_status_inspecting',
        accepted: 'wms_status_accepted',
        completed: 'wms_status_completed',
        rejected: 'wms_status_rejected',
    };

    function statusLabel(status) {
        const key = STATUS_KEYS[status] || status;
        return t(key) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'completed' ? 'ok' : (status === 'rejected' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function setStats(items) {
        const pending = items.filter((r) => ['pending', 'inspecting', 'accepted'].includes(r.status)).length;
        const value = items.reduce((sum, r) => sum + Number(r.total_value || 0), 0);
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsGrnTotal', String(items.length));
        set('wmsGrnPending', String(pending));
        set('wmsGrnValue', money(value));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function applyClientFilter(items) {
        const q = (document.getElementById('wmsGrnSearch')?.value || '').trim().toLowerCase();
        if (!q) return items;
        return items.filter((r) => {
            const hay = [r.grn_number, r.warehouse_name, r.supplier_name, r.received_by_name, r.status]
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
            <th>${esc(t('wms_col_grn'))}</th>
            <th>${esc(t('wms_nav_warehouses'))}</th>
            <th>${esc(t('wms_col_supplier'))}</th>
            <th>${esc(t('wms_col_items'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('col_date'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${list.map((r) => {
            const canComplete = canManage && r.status !== 'completed' && r.status !== 'rejected';
            return `<tr>
                <td><strong>${esc(r.grn_number)}</strong></td>
                <td>${esc(r.warehouse_name || '—')}</td>
                <td>${esc(r.supplier_name || '—')}</td>
                <td>${Number(r.total_items || 0)}</td>
                <td>${esc(money(r.total_value))}</td>
                <td>${esc(AdminAPI.formatDate(r.received_at, { dateStyle: 'short', timeStyle: 'short' }))}</td>
                <td>${statusBadge(r.status)}</td>
                <td class="cr-actions">
                    <button type="button" class="cr-btn cr-btn--ghost" data-grn-view="${r.id}">${esc(t('wms_view_details'))}</button>
                    ${canComplete ? `<button type="button" class="cr-btn" data-grn-complete="${r.id}">${esc(t('wms_complete'))}</button>` : ''}
                </td>
            </tr>`;
        }).join('')}</tbody></table></div>`;

        root.querySelectorAll('[data-grn-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.grnView)));
        });
        root.querySelectorAll('[data-grn-complete]').forEach((btn) => {
            btn.addEventListener('click', () => completeReceipt(Number(btn.dataset.grnComplete)));
        });
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses();
        warehouses = res.status === 'success' ? (res.data || []) : [];
        const fill = (sel, includeAll) => {
            if (!sel) return;
            const cur = sel.value;
            const opts = includeAll ? `<option value="">${esc(t('wms_all_warehouses'))}</option>` : '';
            sel.innerHTML = opts + warehouses.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
            if (cur) sel.value = cur;
        };
        fill(document.getElementById('wmsGrnWarehouse'), true);
        fill(document.getElementById('wmsGrnFormWarehouse'), false);
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
            await loadWarehouses();
            const wh = document.getElementById('wmsGrnWarehouse')?.value;
            const status = document.getElementById('wmsGrnStatus')?.value;
            const res = await AdminAPI.getWmsReceipts(wh, status);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allReceipts = res.data || [];
            setStats(allReceipts);
            renderTable(allReceipts);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
            document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
        }
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('is-open');
            el.setAttribute('aria-hidden', 'false');
        }
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.remove('is-open');
            el.setAttribute('aria-hidden', 'true');
        }
    }

    function productOptions(selectedId = '', filter = '') {
        const q = filter.trim().toLowerCase();
        const list = q
            ? products.filter((p) => {
                const hay = `${p.name || ''} ${p.sku || ''}`.toLowerCase();
                return hay.includes(q);
            })
            : products;
        const opts = list.map((p) =>
            `<option value="${p.id}" data-cost="${p.cost_price || p.price || 0}" ${String(p.id) === String(selectedId) ? 'selected' : ''}>${esc(p.name)}${p.sku ? ` · ${esc(p.sku)}` : ''}</option>`
        ).join('');
        return `<option value="">${esc(t('wms_select_product'))}</option>` + opts;
    }

    function refreshProductSelects() {
        const filter = document.getElementById('wmsGrnProductFilter')?.value || '';
        document.querySelectorAll('#wmsGrnLineItems .wms-grn-line').forEach((row) => {
            const idx = row.dataset.lineIdx;
            const sel = row.querySelector(`[name="product_id_${idx}"]`);
            if (!sel) return;
            const cur = sel.value;
            sel.innerHTML = productOptions(cur, filter);
            if (cur) sel.value = cur;
        });
    }

    function updateLineSummary() {
        const rows = document.querySelectorAll('#wmsGrnLineItems .wms-grn-line');
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
        const countEl = document.getElementById('wmsGrnLineCount');
        const totalEl = document.getElementById('wmsGrnEstTotal');
        const emptyEl = document.getElementById('wmsGrnLinesEmpty');
        if (countEl) countEl.textContent = t('wms_grn_lines_count', rows.length);
        if (totalEl) totalEl.textContent = money(total);
        if (emptyEl) emptyEl.hidden = rows.length > 0;
        rows.forEach((row, i) => {
            const num = row.querySelector('.wms-grn-line__num');
            if (num) num.textContent = String(i + 1);
        });
        document.querySelectorAll('.wms-grn-line__remove').forEach((btn) => {
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
            if (document.querySelectorAll('#wmsGrnLineItems .wms-grn-line').length <= 1) return;
            row.remove();
            updateLineSummary();
        });
    }

    function addLineRow(data = {}) {
        const container = document.getElementById('wmsGrnLineItems');
        if (!container) return;
        const idx = lineIndex++;
        const filter = document.getElementById('wmsGrnProductFilter')?.value || '';
        const row = document.createElement('article');
        row.className = 'wms-grn-line';
        row.dataset.lineIdx = String(idx);
        const lineNo = container.querySelectorAll('.wms-grn-line').length + 1;
        const hasTracking = !!(data.batch_number || data.expiry_date);
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
            <button type="button" class="wms-grn-line__tracking-toggle" data-toggle-tracking aria-expanded="${hasTracking ? 'true' : 'false'}" title="${esc(t('wms_line_tracking'))}">
                <span class="material-icons-round">inventory_2</span>
                <span class="wms-grn-line__toggle-text">${esc(t('wms_line_tracking'))}</span>
                <span class="material-icons-round wms-grn-line__chevron">expand_more</span>
            </button>
            <div class="wms-grn-line__tracking" ${hasTracking ? '' : 'hidden'}>
                <label class="wms-grn-line__field">
                    <span>${esc(t('wms_batch_optional'))}</span>
                    <input type="text" name="batch_${idx}" value="${esc(data.batch_number || '')}" placeholder="—">
                </label>
                <label class="wms-grn-line__field">
                    <span>${esc(t('wms_expiry_optional'))}</span>
                    <input type="date" name="expiry_${idx}" value="${esc(data.expiry_date || '')}">
                </label>
            </div>
        `;
        bindLineInputs(row, idx);
        if (hasTracking) {
            row.querySelector('[data-toggle-tracking]')?.classList.add('is-open');
        }
        container.appendChild(row);
        updateLineSummary();
    }

    async function openCreateModal() {
        await loadProducts();
        document.getElementById('wmsGrnCreateForm')?.reset();
        const filter = document.getElementById('wmsGrnProductFilter');
        if (filter) filter.value = '';
        const lines = document.getElementById('wmsGrnLineItems');
        if (lines) lines.innerHTML = '';
        lineIndex = 0;
        addLineRow();
        updateLineSummary();
        openModal('wmsGrnCreateModal');
    }

    async function submitCreate(e) {
        e.preventDefault();
        const form = e.target;
        const wh = form.warehouse_id?.value;
        if (!wh) return;
        const items = [];
        document.querySelectorAll('#wmsGrnLineItems .wms-grn-line').forEach((row) => {
            const idx = row.dataset.lineIdx;
            const productId = row.querySelector(`[name="product_id_${idx}"]`)?.value;
            const qty = parseInt(row.querySelector(`[name="qty_${idx}"]`)?.value, 10);
            const cost = parseFloat(row.querySelector(`[name="cost_${idx}"]`)?.value);
            const batch = row.querySelector(`[name="batch_${idx}"]`)?.value?.trim();
            const expiry = row.querySelector(`[name="expiry_${idx}"]`)?.value;
            if (!productId || !qty) return;
            items.push({
                product_id: Number(productId),
                quantity_received: qty,
                unit_cost: Number.isFinite(cost) ? cost : 0,
                batch_number: batch || null,
                expiry_date: expiry || null,
            });
        });
        if (!items.length) {
            showError(t('wms_select_product'));
            return;
        }
        const payload = {
            warehouse_id: Number(wh),
            notes: form.notes?.value?.trim() || null,
            status: 'pending',
            items,
        };
        const res = await AdminAPI.createWmsReceipt(payload);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        closeModal('wmsGrnCreateModal');
        hideError();
        await load();
        if (res.data?.id) openDetail(res.data.id);
    }

    async function openDetail(id) {
        const body = document.getElementById('wmsGrnDetailBody');
        if (!body) return;
        body.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        openModal('wmsGrnDetailModal');
        try {
            const res = await AdminAPI.getWmsReceipt(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            document.getElementById('wmsGrnDetailTitle').textContent = `${t('wms_receipt_details')} — ${r.grn_number}`;
            const items = r.items || [];
            body.innerHTML = `
                <dl class="wms-detail-grid">
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || warehouses.find((w) => w.id === Number(r.warehouse_id))?.name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_supplier'))}</dt><dd>${esc(r.supplier_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.total_value))}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(AdminAPI.formatDate(r.received_at))}</dd></div>
                    <div><dt>${esc(t('wms_col_received_by'))}</dt><dd>${esc(r.received_by_name || '—')}</dd></div>
                </dl>
                ${r.notes ? `<p class="cr-muted"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(r.notes)}</p>` : ''}
                <div class="cr-table-wrap"><table class="modern-table"><thead><tr>
                    <th>${esc(t('wms_col_product'))}</th><th>SKU</th><th>${esc(t('wms_qty_received'))}</th>
                    <th>${esc(t('wms_unit_cost'))}</th><th>${esc(t('wms_batch_optional'))}</th><th>${esc(t('wms_expiry_optional'))}</th>
                </tr></thead><tbody>${items.map((i) => `<tr>
                    <td>${esc(i.product_name)}</td><td>${esc(i.sku || '—')}</td><td>${i.quantity_received}</td>
                    <td>${esc(money(i.unit_cost))}</td><td>${esc(i.batch_number || '—')}</td><td>${esc(i.expiry_date || '—')}</td>
                </tr>`).join('')}</tbody></table></div>
                ${canManage && r.status !== 'completed' && r.status !== 'rejected' ? `
                <div class="cr-form-actions" style="margin-top:16px">
                    <button type="button" class="cr-btn" id="wmsGrnDetailComplete" data-id="${r.id}">${esc(t('wms_complete'))}</button>
                </div>` : ''}`;
            document.getElementById('wmsGrnDetailComplete')?.addEventListener('click', () => {
                completeReceipt(Number(r.id), true);
            });
        } catch (e) {
            body.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function completeReceipt(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_complete'))) return;
        const res = await AdminAPI.completeWmsReceipt(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        if (fromDetail) closeModal('wmsGrnDetailModal');
        await load();
    }

    document.getElementById('wmsGrnRefresh')?.addEventListener('click', load);
    document.getElementById('wmsGrnWarehouse')?.addEventListener('change', load);
    document.getElementById('wmsGrnStatus')?.addEventListener('change', load);
    document.getElementById('wmsGrnSearch')?.addEventListener('input', () => renderTable(allReceipts));

    document.getElementById('wmsGrnNewBtn')?.addEventListener('click', openCreateModal);
    document.getElementById('wmsGrnAddLine')?.addEventListener('click', () => { loadProducts().then(addLineRow); });
    document.getElementById('wmsGrnProductFilter')?.addEventListener('input', refreshProductSelects);
    document.getElementById('wmsGrnCreateForm')?.addEventListener('submit', submitCreate);
    ['wmsGrnCreateClose', 'wmsGrnCreateCancel'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', () => closeModal('wmsGrnCreateModal'));
    });
    document.getElementById('wmsGrnDetailClose')?.addEventListener('click', () => closeModal('wmsGrnDetailModal'));
    document.getElementById('wmsGrnCreateModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'wmsGrnCreateModal') closeModal('wmsGrnCreateModal');
    });
    document.getElementById('wmsGrnDetailModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'wmsGrnDetailModal') closeModal('wmsGrnDetailModal');
    });

    document.addEventListener('wms:refresh', load);
    load();
});
