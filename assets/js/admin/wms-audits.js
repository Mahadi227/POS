document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsAudRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WmsUI;
    const canManage = !!window.ADMIN_PAGE?.canManage;

    let allAudits = [];
    let warehouses = [];
    let products = [];
    let lineIndex = 0;
    let detailId = null;

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

    function auditRef(id) {
        return `AUD-${String(id).padStart(5, '0')}`;
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function statusBadge(status) {
        const cls = status === 'approved' ? 'ok' : (status === 'rejected' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function varianceCell(val) {
        const n = Number(val || 0);
        const cls = n > 0 ? 'wms-var--pos' : (n < 0 ? 'wms-var--neg' : '');
        const sign = n > 0 ? '+' : '';
        return `<span class="wms-variance ${cls}">${sign}${esc(money(n))}</span>`;
    }

    function qtyVarianceCell(n) {
        const v = Number(n || 0);
        if (v === 0) return '0';
        const cls = v > 0 ? 'wms-var--pos' : 'wms-var--neg';
        return `<span class="wms-variance ${cls}">${v > 0 ? '+' : ''}${v}</span>`;
    }

    function setStats(summary) {
        const s = summary || {};
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsAudTotal', String(s.total ?? 0));
        set('wmsAudOpen', String(s.open ?? 0));
        set('wmsAudVariance', String(s.with_variance ?? 0));
        set('wmsAudCompleted', String(s.completed ?? 0));
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function renderTable(items) {
        if (!items.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table wms-aud-table"><thead><tr>
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
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td><strong>${esc(auditRef(r.id))}</strong></td>
            <td>${esc(r.warehouse_name || '—')}</td>
            <td>${esc(typeLabel(r.audit_type))}</td>
            <td>${Number(r.total_items || 0)}</td>
            <td>${esc(money(r.expected_value))}</td>
            <td>${esc(money(r.counted_value))}</td>
            <td>${varianceCell(r.variance_value)}</td>
            <td>${esc(AdminAPI.formatDate(r.created_at, { dateStyle: 'short', timeStyle: 'short' }))}</td>
            <td>${statusBadge(r.status)}</td>
            <td class="cr-actions">
                <button type="button" class="cr-btn cr-btn--ghost" data-aud-view="${r.id}">${esc(t('wms_view_details'))}</button>
            </td>
        </tr>`).join('')}</tbody></table></div>`;

        root.querySelectorAll('[data-aud-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.audView)));
        });
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
        fill(document.getElementById('wmsAudWarehouse'), t('wms_all_warehouses'));
        fill(document.getElementById('wmsAudFormWarehouse'));
    }

    async function loadProducts() {
        if (products.length) return;
        const res = await AdminAPI.getInventoryProducts();
        products = res.status === 'success' ? (res.data || []) : [];
    }

    function filteredProducts() {
        const q = (document.getElementById('wmsAudProductFilter')?.value || '').trim().toLowerCase();
        if (!q) return products;
        return products.filter((p) => `${p.name || ''} ${p.sku || ''}`.toLowerCase().includes(q));
    }

    function renderLine(idx, data = {}) {
        const list = filteredProducts();
        const pid = data.product_id || '';
        return `<div class="wms-grn-line wms-grn-line--request" data-line="${idx}">
            <div class="wms-grn-line__head"><span class="wms-grn-line__num">${idx + 1}</span></div>
            <label class="wms-grn-line__field wms-grn-line__field--product">
                <span>${esc(t('wms_col_product'))}</span>
                <select name="items[${idx}][product_id]" required>
                    <option value="">${esc(t('wms_select_product'))}</option>
                    ${list.map((p) => `<option value="${p.id}"${String(p.id) === String(pid) ? ' selected' : ''}>${esc(p.name)}${p.sku ? ` (${p.sku})` : ''}</option>`).join('')}
                </select>
            </label>
            <label class="wms-grn-line__field wms-grn-line__field--qty">
                <span>${esc(t('wms_col_counted_qty'))}</span>
                <input type="number" name="items[${idx}][counted_qty]" min="0" step="1" value="${data.counted_qty ?? 0}" required>
            </label>
            <button type="button" class="wms-grn-line__remove" data-remove-line="${idx}" aria-label="${esc(t('wms_remove_line'))}">
                <span class="material-icons-round">close</span>
            </button>
        </div>`;
    }

    function bindLineEvents() {
        const container = document.getElementById('wmsAudLineItems');
        if (!container) return;
        container.querySelectorAll('[data-remove-line]').forEach((btn) => {
            btn.addEventListener('click', () => {
                btn.closest('.wms-grn-line')?.remove();
                renumberLines();
            });
        });
    }

    function renumberLines() {
        const container = document.getElementById('wmsAudLineItems');
        if (!container) return;
        [...container.querySelectorAll('.wms-grn-line')].forEach((line, i) => {
            line.dataset.line = String(i);
            const num = line.querySelector('.wms-grn-line__num');
            if (num) num.textContent = String(i + 1);
        });
        lineIndex = container.querySelectorAll('.wms-grn-line').length;
    }

    function addLine(data) {
        const container = document.getElementById('wmsAudLineItems');
        if (!container) return;
        container.insertAdjacentHTML('beforeend', renderLine(lineIndex, data));
        lineIndex += 1;
        bindLineEvents();
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.add('is-loading'));
        try {
            await loadWarehouses();
            const wh = document.getElementById('wmsAudWarehouse')?.value;
            const status = document.getElementById('wmsAudStatus')?.value;
            const auditType = document.getElementById('wmsAudType')?.value;
            const q = document.getElementById('wmsAudSearch')?.value?.trim();
            const res = await AdminAPI.getWmsAudits(wh, status, q, auditType);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            allAudits = res.data || [];
            setStats(res.summary);
            renderTable(allAudits);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
            document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
        }
    }

    function updateDetailActions(row) {
        const footer = document.getElementById('wmsAudDetailActions');
        if (!footer || !canManage || !row) {
            if (footer) footer.hidden = true;
            return;
        }
        const submitBtn = document.getElementById('wmsAudSubmitBtn');
        const approveBtn = document.getElementById('wmsAudApproveBtn');
        const rejectBtn = document.getElementById('wmsAudRejectBtn');
        const isDraft = row.status === 'draft' || row.status === 'in_progress';
        const isPending = row.status === 'pending_approval';
        if (submitBtn) submitBtn.hidden = !isDraft;
        if (approveBtn) approveBtn.hidden = !isPending;
        if (rejectBtn) rejectBtn.hidden = !isPending;
        footer.hidden = !(isDraft || isPending);
    }

    async function openDetail(id) {
        detailId = id;
        const body = document.getElementById('wmsAudDetailBody');
        if (!body) return;
        body.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        updateDetailActions(null);
        openModal('wmsAudDetailModal');
        try {
            const res = await AdminAPI.getWmsAudit(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            document.getElementById('wmsAudDetailTitle').textContent = auditRef(r.id);
            const sub = document.getElementById('wmsAudDetailSubtitle');
            if (sub) sub.textContent = [typeLabel(r.audit_type), r.warehouse_name, statusLabel(r.status)].filter(Boolean).join(' · ');
            const items = r.items || [];
            body.innerHTML = `
                <dl class="wms-detail-grid">
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_audit_type'))}</dt><dd>${esc(typeLabel(r.audit_type))}</dd></div>
                    <div><dt>${esc(t('wms_col_expected'))}</dt><dd>${esc(money(r.expected_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_counted_value'))}</dt><dd>${esc(money(r.counted_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_variance'))}</dt><dd>${varianceCell(r.variance_value)}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(AdminAPI.formatDate(r.created_at, { dateStyle: 'short', timeStyle: 'short' }))}</dd></div>
                </dl>
                ${items.length ? `<div class="cr-table-wrap"><table class="modern-table"><thead><tr>
                    <th>${esc(t('wms_col_product'))}</th>
                    <th>${esc(t('wms_col_system_qty'))}</th>
                    <th>${esc(t('wms_col_counted_qty'))}</th>
                    <th>${esc(t('wms_col_variance'))}</th>
                </tr></thead><tbody>${items.map((it) => `<tr>
                    <td>${esc(it.product_name)} <code class="wms-sku">${esc(it.sku || '')}</code></td>
                    <td>${Number(it.system_qty || 0)}</td>
                    <td>${Number(it.counted_qty || 0)}</td>
                    <td>${qtyVarianceCell(it.variance_qty)}</td>
                </tr>`).join('')}</tbody></table></div>` : ''}`;
            updateDetailActions(r);
        } catch (e) {
            body.innerHTML = `<p class="cr-empty">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function runAction(fn, confirmKey) {
        if (!detailId) return;
        if (confirmKey && !window.confirm(t(confirmKey))) return;
        try {
            const res = await fn(detailId);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            closeModal('wmsAudDetailModal');
            await load();
        } catch (e) {
            showError(e.message || t('error'));
        }
    }

    async function openCreate() {
        await loadProducts();
        await loadWarehouses();
        lineIndex = 0;
        document.getElementById('wmsAudLineItems').innerHTML = '';
        document.getElementById('wmsAudCreateForm')?.reset();
        addLine();
        openModal('wmsAudCreateModal');
    }

    let searchTimer;
    document.getElementById('wmsAudSearch')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 350);
    });
    document.getElementById('wmsAudWarehouse')?.addEventListener('change', load);
    document.getElementById('wmsAudStatus')?.addEventListener('change', load);
    document.getElementById('wmsAudType')?.addEventListener('change', load);
    document.getElementById('wmsAudRefresh')?.addEventListener('click', load);
    document.getElementById('wmsAudNewBtn')?.addEventListener('click', openCreate);
    document.getElementById('wmsAudCreateClose')?.addEventListener('click', () => closeModal('wmsAudCreateModal'));
    document.getElementById('wmsAudCreateCancel')?.addEventListener('click', () => closeModal('wmsAudCreateModal'));
    document.getElementById('wmsAudDetailClose')?.addEventListener('click', () => closeModal('wmsAudDetailModal'));
    document.getElementById('wmsAudAddLine')?.addEventListener('click', () => addLine());
    document.getElementById('wmsAudProductFilter')?.addEventListener('input', () => {
        const container = document.getElementById('wmsAudLineItems');
        if (!container) return;
        const values = [...container.querySelectorAll('.wms-grn-line')].map((line) => {
            const idx = line.dataset.line;
            return {
                product_id: line.querySelector(`select[name="items[${idx}][product_id]"]`)?.value,
                counted_qty: line.querySelector(`input[name="items[${idx}][counted_qty]"]`)?.value,
            };
        });
        container.innerHTML = '';
        lineIndex = 0;
        values.forEach((v) => addLine(v));
    });
    document.getElementById('wmsAudSubmitBtn')?.addEventListener('click', () => runAction(AdminAPI.submitWmsAudit, 'wms_confirm_submit_audit'));
    document.getElementById('wmsAudApproveBtn')?.addEventListener('click', () => runAction(AdminAPI.approveWmsAudit, 'wms_confirm_approve_audit'));
    document.getElementById('wmsAudRejectBtn')?.addEventListener('click', () => runAction(AdminAPI.rejectWmsAudit, 'wms_confirm_reject_audit'));

    document.getElementById('wmsAudCreateForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const fd = new FormData(form);
        const items = [];
        const wh = Number(fd.get('warehouse_id'));
        [...document.querySelectorAll('#wmsAudLineItems .wms-grn-line')].forEach((line) => {
            const idx = line.dataset.line;
            const productId = Number(line.querySelector(`select[name="items[${idx}][product_id]"]`)?.value);
            const countedQty = Number(line.querySelector(`input[name="items[${idx}][counted_qty]"]`)?.value);
            if (productId) items.push({ product_id: productId, counted_qty: countedQty });
        });
        if (!wh || !items.length) {
            showError(t('load_error'));
            return;
        }
        try {
            const res = await AdminAPI.createWmsAudit({
                warehouse_id: wh,
                audit_type: fd.get('audit_type'),
                notes: fd.get('notes') || null,
                items,
            });
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            closeModal('wmsAudCreateModal');
            await load();
        } catch (err) {
            showError(err.message || t('error'));
        }
    });

    document.addEventListener('wms:refresh', load);
    load();
});
