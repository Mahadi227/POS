/**
 * Warehouse quality inspection — GRN inspection queue with line-level damage tracking
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whInspTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canReceive = !!window.WH_PAGE?.canReceive && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
        pending: 'wms_status_pending',
        inspecting: 'wms_status_inspecting',
    };
    const INSP_KEYS = {
        pending: 'wms_insp_status_pending',
        passed: 'wms_insp_status_passed',
        failed: 'wms_insp_status_failed',
        partial: 'wms_insp_status_partial',
    };
    const QUEUE_ORDER = ['pending', 'inspecting'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        searchTimer: null,
        activeReceipt: null,
    };

    const els = {
        search: document.getElementById('whInspSearch'),
        warehouse: document.getElementById('whInspWarehouse'),
        status: document.getElementById('whInspStatus'),
        refresh: document.getElementById('whInspRefreshBtn'),
        exportBtn: document.getElementById('whInspExportBtn'),
        heroMeta: document.getElementById('whInspHeroMeta'),
        breakdownPanel: document.getElementById('whInspBreakdownPanel'),
        statusChips: document.getElementById('whInspStatusChips'),
        statQueue: document.getElementById('whInspStatQueue'),
        statPending: document.getElementById('whInspStatPending'),
        statInspecting: document.getElementById('whInspStatInspecting'),
        statPassed: document.getElementById('whInspStatPassed'),
        statRejected: document.getElementById('whInspStatRejected'),
        loading: document.getElementById('whInspLoading'),
        empty: document.getElementById('whInspEmpty'),
        pagination: document.getElementById('whInspPagination'),
        prev: document.getElementById('whInspPrev'),
        next: document.getElementById('whInspNext'),
        pageMeta: document.getElementById('whInspPageMeta'),
        detailModal: document.getElementById('whInspDetailModal'),
        detailClose: document.getElementById('whInspDetailClose'),
        detailTitle: document.getElementById('whInspDetailTitle'),
        detailBody: document.getElementById('whInspDetailBody'),
        toast: document.getElementById('whInspToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-insp-toast show${type === 'error' ? ' wh-insp-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function inspLabel(status) {
        return t(INSP_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'inspecting' ? 'warn' : 'off';
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function inspBadge(status) {
        const cls = status === 'passed' ? 'ok' : (status === 'failed' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(inspLabel(status))}</span>`;
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
        document.querySelectorAll('.wh-insp-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        const queue = (s.pending ?? 0) + (s.inspecting ?? 0);
        if (els.statQueue) els.statQueue.textContent = String(queue);
        if (els.statPending) els.statPending.textContent = String(s.pending ?? 0);
        if (els.statInspecting) els.statInspecting.textContent = String(s.inspecting ?? 0);
        if (els.statPassed) els.statPassed.textContent = String(s.passed_today ?? 0);
        if (els.statRejected) els.statRejected.textContent = String(s.rejected_today ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_insp_hero_meta', s.pending ?? 0, s.inspecting ?? 0);
        }
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const list = (items || []).filter((r) => QUEUE_ORDER.includes(r.status) && Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const sorted = [...list].sort((a, b) => QUEUE_ORDER.indexOf(a.status) - QUEUE_ORDER.indexOf(b.status));
        const activeStatus = els.status?.value || 'all';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const isActive = activeStatus === r.status;
            return `<button type="button" class="wh-insp-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-insp-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.status) els.status.value = btn.dataset.status || 'all';
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            scope: 'inspection',
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

    function rowActions(r) {
        const parts = [`<button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-insp-open="${r.id}">${esc(t('wms_view_details'))}</button>`];
        if (!canReceive) return parts.join('');
        if (r.status === 'pending') {
            parts.push(`<button type="button" class="wh-btn wh-btn--sm" data-insp-start="${r.id}">${esc(t('wms_start_inspection'))}</button>`);
        } else if (r.status === 'inspecting') {
            parts.push(`<button type="button" class="wh-btn wh-btn--primary wh-btn--sm" data-insp-open="${r.id}">${esc(t('wms_continue_inspection'))}</button>`);
        }
        return parts.join('');
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-insp-table">
<thead><tr>
    <th>${esc(t('wms_col_grn'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_supplier'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th>${esc(t('wms_insp_col_result'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r) => `<tr>
    <td><strong>${esc(r.grn_number)}</strong></td>
    <td>${esc(r.warehouse_name || '—')}</td>
    <td>${esc(r.supplier_name || '—')}</td>
    <td>${Number(r.total_items || 0)}</td>
    <td>${esc(formatDate(r.received_at))}</td>
    <td>${statusBadge(r.status)}</td>
    <td>${inspBadge(r.inspection_status || 'pending')}</td>
    <td class="wh-insp-row-actions">${rowActions(r)}</td>
</tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-insp-open]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.inspOpen)));
        });
        tableWrap.querySelectorAll('[data-insp-start]').forEach((btn) => {
            btn.addEventListener('click', () => startInspection(Number(btn.dataset.inspStart)));
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
            [t('wms_col_grn'), t('wms_nav_warehouses'), t('wms_col_supplier'), t('wms_col_items'), t('col_date'), t('col_status'), t('wms_insp_col_result')],
            ...items.map((r) => [r.grn_number, r.warehouse_name, r.supplier_name, r.total_items, r.received_at, r.status, r.inspection_status]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsReceipts(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`quality-inspection-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    function openModal() {
        els.detailModal?.classList.add('is-open');
        els.detailModal?.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        els.detailModal?.classList.remove('is-open');
        els.detailModal?.setAttribute('aria-hidden', 'true');
        state.activeReceipt = null;
    }

    function collectInspectionItems() {
        const items = [];
        els.detailBody?.querySelectorAll('[data-insp-line]').forEach((row) => {
            const id = Number(row.dataset.inspLine);
            const received = parseInt(row.querySelector('[data-insp-received]')?.value, 10);
            const damaged = parseInt(row.querySelector('[data-insp-damaged]')?.value, 10);
            if (!id) return;
            items.push({
                id,
                quantity_received: Number.isFinite(received) ? received : 0,
                quantity_damaged: Number.isFinite(damaged) ? damaged : 0,
            });
        });
        return items;
    }

    function renderInspectionForm(r) {
        const editable = canReceive && ['pending', 'inspecting'].includes(r.status);
        const items = r.items || [];
        const rows = items.map((i) => {
            const ok = Math.max(0, (i.quantity_received || 0) - (i.quantity_damaged || 0));
            if (editable) {
                return `<tr data-insp-line="${i.id}">
                    <td>${esc(i.product_name)}<br><small>${esc(i.sku || '')}</small></td>
                    <td><input type="number" class="wh-insp-input" data-insp-received min="0" value="${i.quantity_received || 0}"></td>
                    <td><input type="number" class="wh-insp-input" data-insp-damaged min="0" value="${i.quantity_damaged || 0}"></td>
                    <td class="wh-insp-ok-cell" data-insp-ok="${i.id}">${ok}</td>
                </tr>`;
            }
            return `<tr>
                <td>${esc(i.product_name)}<br><small>${esc(i.sku || '')}</small></td>
                <td>${i.quantity_received}</td>
                <td>${i.quantity_damaged || 0}</td>
                <td>${ok}</td>
            </tr>`;
        }).join('');

        const actions = editable ? `
            <div class="wh-insp-detail-actions">
                ${r.status === 'pending' ? `<button type="button" class="wh-btn wh-btn--sm" id="whInspStartBtn">${esc(t('wms_start_inspection'))}</button>` : ''}
                <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" id="whInspSaveBtn">${esc(t('save'))}</button>
                <button type="button" class="wh-btn wh-btn--primary wh-btn--sm" id="whInspPassBtn">${esc(t('wms_pass_inspection'))}</button>
                <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" id="whInspFailBtn">${esc(t('wms_fail_inspection'))}</button>
            </div>` : '';

        return `
            <dl class="wh-insp-detail-grid">
                <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                <div><dt>${esc(t('wms_col_supplier'))}</dt><dd>${esc(r.supplier_name || '—')}</dd></div>
                <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                <div><dt>${esc(t('wms_insp_col_result'))}</dt><dd>${inspBadge(r.inspection_status || 'pending')}</dd></div>
                <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.received_at))}</dd></div>
                <div><dt>${esc(t('wms_col_received_by'))}</dt><dd>${esc(r.received_by_name || '—')}</dd></div>
            </dl>
            ${r.notes ? `<p class="wh-insp-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(r.notes)}</p>` : ''}
            <div class="wh-insp-lines-wrap">
                <table class="modern-table wh-table wh-insp-lines-table">
                    <thead><tr>
                        <th>${esc(t('wms_col_product'))}</th>
                        <th>${esc(t('wms_qty_received'))}</th>
                        <th>${esc(t('wms_qty_damaged'))}</th>
                        <th>${esc(t('wms_qty_ok'))}</th>
                    </tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            ${actions}`;
    }

    function bindInspectionForm(r) {
        els.detailBody?.querySelectorAll('[data-insp-received], [data-insp-damaged]').forEach((input) => {
            input.addEventListener('input', () => {
                const row = input.closest('[data-insp-line]');
                if (!row) return;
                const received = parseInt(row.querySelector('[data-insp-received]')?.value, 10) || 0;
                let damaged = parseInt(row.querySelector('[data-insp-damaged]')?.value, 10) || 0;
                if (damaged > received) {
                    damaged = received;
                    const dEl = row.querySelector('[data-insp-damaged]');
                    if (dEl) dEl.value = String(damaged);
                }
                const okCell = row.querySelector('.wh-insp-ok-cell');
                if (okCell) okCell.textContent = String(Math.max(0, received - damaged));
            });
        });

        document.getElementById('whInspStartBtn')?.addEventListener('click', () => startInspection(r.id, true));
        document.getElementById('whInspSaveBtn')?.addEventListener('click', () => saveInspection(r.id, false));
        document.getElementById('whInspPassBtn')?.addEventListener('click', () => finalizeInspection(r.id, 'pass'));
        document.getElementById('whInspFailBtn')?.addEventListener('click', () => finalizeInspection(r.id, 'fail'));
    }

    async function openDetail(id) {
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        openModal();
        try {
            const res = await AdminAPI.getWmsReceipt(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            state.activeReceipt = r;
            if (els.detailTitle) els.detailTitle.textContent = `${t('wms_insp_details')} — ${r.grn_number}`;
            els.detailBody.innerHTML = renderInspectionForm(r);
            bindInspectionForm(r);
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-insp-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function saveInspection(id, quiet = false) {
        const items = collectInspectionItems();
        const res = await AdminAPI.saveWmsReceiptInspection(id, { items });
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return false;
        }
        hideError();
        if (!quiet) toast(t('wms_toast_inspection_saved'));
        state.activeReceipt = res.data;
        if (els.detailBody && res.data) {
            els.detailBody.innerHTML = renderInspectionForm(res.data);
            bindInspectionForm(res.data);
        }
        return true;
    }

    async function startInspection(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_inspect'))) return;
        const res = await AdminAPI.inspectWmsReceipt(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wms_toast_inspecting'));
        await load();
        if (fromDetail) await openDetail(id);
    }

    async function finalizeInspection(id, outcome) {
        const confirmKey = outcome === 'pass' ? 'wms_confirm_pass_inspection' : 'wms_confirm_fail_inspection';
        if (!window.confirm(t(confirmKey))) return;
        if (!(await saveInspection(id, true))) return;
        const api = outcome === 'pass' ? AdminAPI.acceptWmsReceipt : AdminAPI.rejectWmsReceipt;
        const res = await api(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(outcome === 'pass' ? t('wms_toast_accepted') : t('wms_toast_rejected'));
        closeModal();
        state.page = 1;
        await load();
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.status?.addEventListener('change', () => { state.page = 1; load(); });
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => { state.page = 1; load(); }, 350);
    });
    els.exportBtn?.addEventListener('click', exportData);
    els.detailClose?.addEventListener('click', closeModal);
    els.detailModal?.addEventListener('click', (e) => { if (e.target === els.detailModal) closeModal(); });
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        if (state.page < Math.ceil(state.total / state.limit)) { state.page += 1; load(); }
    });

    document.addEventListener('wh:refresh', load);
    document.addEventListener('store-switched', () => { state.page = 1; load(); });

    loadWarehouseOptions(els.warehouse).then(load);
});
