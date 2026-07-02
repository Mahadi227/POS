/**
 * Warehouse outgoing transfers — approve and dispatch stock leaving source warehouses
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whTroutTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canApprove = !!window.WH_PAGE?.canManage && !window.WH_PAGE?.readOnly;
    const canDispatch = !!window.WH_PAGE?.canTransfer && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
        requested: 'wms_status_requested',
        approved: 'wms_status_approved',
        picking: 'wms_status_picking',
        in_transit: 'wms_status_in_transit',
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
    const STATUS_ORDER = ['requested', 'approved', 'picking', 'in_transit', 'completed', 'rejected', 'cancelled'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        searchTimer: null,
    };

    const els = {
        search: document.getElementById('whTroutSearch'),
        warehouse: document.getElementById('whTroutWarehouse'),
        status: document.getElementById('whTroutStatus'),
        refresh: document.getElementById('whTroutRefreshBtn'),
        exportBtn: document.getElementById('whTroutExportBtn'),
        heroMeta: document.getElementById('whTroutHeroMeta'),
        breakdownPanel: document.getElementById('whTroutBreakdownPanel'),
        statusChips: document.getElementById('whTroutStatusChips'),
        statRequested: document.getElementById('whTroutStatRequested'),
        statProgress: document.getElementById('whTroutStatProgress'),
        statCompleted: document.getElementById('whTroutStatCompleted'),
        statActive: document.getElementById('whTroutStatActive'),
        loading: document.getElementById('whTroutLoading'),
        empty: document.getElementById('whTroutEmpty'),
        pagination: document.getElementById('whTroutPagination'),
        prev: document.getElementById('whTroutPrev'),
        next: document.getElementById('whTroutNext'),
        pageMeta: document.getElementById('whTroutPageMeta'),
        detailModal: document.getElementById('whTroutDetailModal'),
        detailClose: document.getElementById('whTroutDetailClose'),
        detailTitle: document.getElementById('whTroutDetailTitle'),
        detailSubtitle: document.getElementById('whTroutDetailSubtitle'),
        detailBody: document.getElementById('whTroutDetailBody'),
        toast: document.getElementById('whTroutToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-trout-toast show${type === 'error' ? ' wh-trout-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

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

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return AdminAPI.formatDate(iso, { dateStyle: 'short', timeStyle: 'short' });
        } catch {
            return iso;
        }
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-trout-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statRequested) els.statRequested.textContent = String(s.requested ?? 0);
        if (els.statProgress) els.statProgress.textContent = String(s.in_progress ?? 0);
        if (els.statCompleted) els.statCompleted.textContent = String(s.completed ?? 0);
        if (els.statActive) els.statActive.textContent = String(s.active ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_trout_hero_meta', s.requested ?? 0, s.in_progress ?? 0);
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
        const activeStatus = els.status?.value || 'outgoing_active';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const chipStatus = r.status === 'requested' ? 'outgoing_pending' : r.status;
            const isActive = activeStatus === r.status || activeStatus === chipStatus;
            return `<button type="button" class="wh-trout-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(chipStatus)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-trout-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.status) els.status.value = btn.dataset.status || 'outgoing_active';
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            direction: 'outgoing',
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-trout-table">
<thead><tr>
    <th>${esc(t('wms_col_transfer'))}</th>
    <th>${esc(t('wms_col_type'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_to'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r) => {
    const canApproveRow = canApprove && r.status === 'requested';
    const canDispatchRow = canDispatch && r.status === 'approved';
    const canRejectRow = canApprove && r.status === 'requested';
    return `<tr>
        <td><strong>${esc(r.transfer_number)}</strong></td>
        <td>${esc(typeLabel(r.transfer_type))}</td>
        <td>${esc(r.from_warehouse_name || r.from_store_name || '—')}</td>
        <td>${esc(endpointLabel(r, 'to'))}</td>
        <td>${Number(r.total_items || 0)}</td>
        <td>${esc(money(r.total_value))}</td>
        <td>${esc(formatDate(r.created_at))}</td>
        <td>${statusBadge(r.status)}</td>
        <td class="wh-trout-row-actions">
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-trout-view="${r.id}">${esc(t('wms_view_details'))}</button>
            ${canApproveRow ? `<button type="button" class="wh-btn wh-btn--sm" data-trout-approve="${r.id}">${esc(t('wms_approve'))}</button>` : ''}
            ${canDispatchRow ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--primary" data-trout-dispatch="${r.id}">${esc(t('wh_trout_dispatch'))}</button>` : ''}
            ${canRejectRow ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--warn" data-trout-reject="${r.id}">${esc(t('wms_reject'))}</button>` : ''}
            ${r.status === 'completed' ? `<span class="wh-trout-done-tag">${esc(t('wh_trout_dispatched_badge'))}</span>` : ''}
        </td>
    </tr>`;
}).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-trout-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.troutView)));
        });
        tableWrap.querySelectorAll('[data-trout-approve]').forEach((btn) => {
            btn.addEventListener('click', () => approveTransfer(Number(btn.dataset.troutApprove)));
        });
        tableWrap.querySelectorAll('[data-trout-dispatch]').forEach((btn) => {
            btn.addEventListener('click', () => dispatchTransfer(Number(btn.dataset.troutDispatch)));
        });
        tableWrap.querySelectorAll('[data-trout-reject]').forEach((btn) => {
            btn.addEventListener('click', () => rejectTransfer(Number(btn.dataset.troutReject)));
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
            const res = await AdminAPI.getWmsTransfers(buildParams());
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
            [t('wms_col_transfer'), t('wms_col_type'), t('wms_col_from'), t('wms_col_to'), t('wms_col_items'), t('wms_col_value'), t('col_status')],
            ...items.map((r) => [r.transfer_number, r.transfer_type, endpointLabel(r, 'from'), endpointLabel(r, 'to'), r.total_items, r.total_value, r.status]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsTransfers(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`outgoing-transfers-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
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
    }

    async function approveTransfer(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_approve_trf'))) return;
        const res = await AdminAPI.approveWmsTransfer(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_trout_toast_approved'));
        if (fromDetail) closeModal();
        await load();
    }

    async function dispatchTransfer(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_complete_trf'))) return;
        const res = await AdminAPI.completeWmsTransfer(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_trout_toast_dispatched'));
        if (fromDetail) closeModal();
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
        toast(t('wh_trout_toast_rejected'));
        if (fromDetail) closeModal();
        await load();
    }

    async function openDetail(id) {
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        openModal();
        try {
            const res = await AdminAPI.getWmsTransfer(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.detailTitle) els.detailTitle.textContent = `${t('wms_transfer_details')} — ${r.transfer_number}`;
            if (els.detailSubtitle) {
                els.detailSubtitle.textContent = `${typeLabel(r.transfer_type)} · ${endpointLabel(r, 'from')} → ${endpointLabel(r, 'to')}`;
            }
            const items = r.items || [];
            const canApproveRow = canApprove && r.status === 'requested';
            const canDispatchRow = canDispatch && r.status === 'approved';
            const canRejectRow = canApprove && r.status === 'requested';
            els.detailBody.innerHTML = `
                <dl class="wh-trout-detail-grid">
                    <div><dt>${esc(t('wms_col_type'))}</dt><dd>${esc(typeLabel(r.transfer_type))}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_from'))}</dt><dd>${esc(endpointLabel(r, 'from'))}</dd></div>
                    <div><dt>${esc(t('wms_col_to'))}</dt><dd>${esc(endpointLabel(r, 'to'))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.total_value))}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at))}</dd></div>
                    ${r.requested_by_name ? `<div><dt>${esc(t('wms_col_requested_by'))}</dt><dd>${esc(r.requested_by_name)}</dd></div>` : ''}
                </dl>
                ${r.reason ? `<p class="wh-trout-detail-notes"><strong>${esc(t('wms_col_reason'))}:</strong> ${esc(r.reason)}</p>` : ''}
                <div class="wh-trout-lines-wrap">
                    <table class="modern-table wh-table wh-trout-lines-table">
                        <thead><tr>
                            <th>${esc(t('wms_col_product'))}</th>
                            <th>${esc(t('wms_col_sku'))}</th>
                            <th>${esc(t('wms_col_qty'))}</th>
                            <th>${esc(t('wms_unit_cost'))}</th>
                        </tr></thead>
                        <tbody>${items.map((i) => `<tr>
                            <td>${esc(i.product_name)}</td>
                            <td>${esc(i.sku || '—')}</td>
                            <td><strong>${i.quantity_requested}</strong></td>
                            <td>${esc(money(i.unit_cost))}</td>
                        </tr>`).join('')}</tbody>
                    </table>
                </div>
                ${canApproveRow || canDispatchRow || canRejectRow ? `<div class="wh-trout-detail-actions">
                    ${canApproveRow ? `<button type="button" class="wh-btn" id="whTroutApproveBtn">${esc(t('wms_approve'))}</button>` : ''}
                    ${canDispatchRow ? `<button type="button" class="wh-btn wh-btn--primary" id="whTroutDispatchBtn">${esc(t('wh_trout_dispatch'))}</button>` : ''}
                    ${canRejectRow ? `<button type="button" class="wh-btn wh-btn--warn" id="whTroutRejectBtn">${esc(t('wms_reject'))}</button>` : ''}
                </div>` : ''}
                ${r.status === 'completed' ? `<p class="wh-trout-done-note"><span class="material-icons-round">check_circle</span> ${esc(t('wh_trout_dispatched_badge'))}</p>` : ''}`;
            document.getElementById('whTroutApproveBtn')?.addEventListener('click', () => approveTransfer(r.id, true));
            document.getElementById('whTroutDispatchBtn')?.addEventListener('click', () => dispatchTransfer(r.id, true));
            document.getElementById('whTroutRejectBtn')?.addEventListener('click', () => rejectTransfer(r.id, true));
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-trout-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.exportBtn?.addEventListener('click', exportData);
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.status?.addEventListener('change', () => { state.page = 1; load(); });
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => { state.page = 1; load(); }, 350);
    });
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        const pages = Math.ceil(state.total / state.limit);
        if (state.page < pages) { state.page += 1; load(); }
    });
    els.detailClose?.addEventListener('click', closeModal);
    els.detailModal?.addEventListener('click', (e) => {
        if (e.target === els.detailModal) closeModal();
    });

    document.addEventListener('wh:refresh', load);

    loadWarehouseOptions(els.warehouse).then(load);
});
