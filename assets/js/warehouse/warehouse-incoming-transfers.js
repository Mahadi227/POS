/**
 * Warehouse incoming transfers — receive stock arriving at destination warehouses
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whTrinTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canReceive = !!window.WH_PAGE?.canTransfer && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
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
    const STATUS_ORDER = ['approved', 'picking', 'in_transit', 'received', 'completed', 'rejected', 'cancelled'];

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
        search: document.getElementById('whTrinSearch'),
        warehouse: document.getElementById('whTrinWarehouse'),
        status: document.getElementById('whTrinStatus'),
        refresh: document.getElementById('whTrinRefreshBtn'),
        exportBtn: document.getElementById('whTrinExportBtn'),
        heroMeta: document.getElementById('whTrinHeroMeta'),
        breakdownPanel: document.getElementById('whTrinBreakdownPanel'),
        statusChips: document.getElementById('whTrinStatusChips'),
        statPending: document.getElementById('whTrinStatPending'),
        statTransit: document.getElementById('whTrinStatTransit'),
        statCompleted: document.getElementById('whTrinStatCompleted'),
        statActive: document.getElementById('whTrinStatActive'),
        loading: document.getElementById('whTrinLoading'),
        empty: document.getElementById('whTrinEmpty'),
        pagination: document.getElementById('whTrinPagination'),
        prev: document.getElementById('whTrinPrev'),
        next: document.getElementById('whTrinNext'),
        pageMeta: document.getElementById('whTrinPageMeta'),
        detailModal: document.getElementById('whTrinDetailModal'),
        detailClose: document.getElementById('whTrinDetailClose'),
        detailTitle: document.getElementById('whTrinDetailTitle'),
        detailSubtitle: document.getElementById('whTrinDetailSubtitle'),
        detailBody: document.getElementById('whTrinDetailBody'),
        toast: document.getElementById('whTrinToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-trin-toast show${type === 'error' ? ' wh-trin-toast--error' : ''}`;
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
        document.querySelectorAll('.wh-trin-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statPending) els.statPending.textContent = String(s.pending ?? 0);
        if (els.statTransit) els.statTransit.textContent = String(s.in_transit ?? 0);
        if (els.statCompleted) els.statCompleted.textContent = String(s.completed ?? 0);
        if (els.statActive) els.statActive.textContent = String(s.active ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_trin_hero_meta', s.pending ?? 0, s.in_transit ?? 0);
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
        const activeStatus = els.status?.value || 'incoming_active';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const chipStatus = r.status === 'approved' ? 'incoming_pending' : r.status;
            const isActive = activeStatus === r.status || activeStatus === chipStatus;
            return `<button type="button" class="wh-trin-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status === 'approved' ? 'incoming_pending' : r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-trin-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.status) els.status.value = btn.dataset.status || 'incoming_active';
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            direction: 'incoming',
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-trin-table">
<thead><tr>
    <th>${esc(t('wms_col_transfer'))}</th>
    <th>${esc(t('wms_col_type'))}</th>
    <th>${esc(t('wms_col_from'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r) => {
    const canReceiveRow = canReceive && r.status === 'approved';
    return `<tr>
        <td><strong>${esc(r.transfer_number)}</strong></td>
        <td>${esc(typeLabel(r.transfer_type))}</td>
        <td>${esc(endpointLabel(r, 'from'))}</td>
        <td>${esc(r.to_warehouse_name || '—')}</td>
        <td>${Number(r.total_items || 0)}</td>
        <td>${esc(money(r.total_value))}</td>
        <td>${esc(formatDate(r.created_at))}</td>
        <td>${statusBadge(r.status)}</td>
        <td class="wh-trin-row-actions">
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-trin-view="${r.id}">${esc(t('wms_view_details'))}</button>
            ${canReceiveRow ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--primary" data-trin-receive="${r.id}">${esc(t('wh_trin_receive'))}</button>` : ''}
            ${r.status === 'completed' ? `<span class="wh-trin-done-tag">${esc(t('wh_trin_ready_badge'))}</span>` : ''}
        </td>
    </tr>`;
}).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-trin-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.trinView)));
        });
        tableWrap.querySelectorAll('[data-trin-receive]').forEach((btn) => {
            btn.addEventListener('click', () => receiveTransfer(Number(btn.dataset.trinReceive)));
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
            exportCsv(`incoming-transfers-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
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

    async function receiveTransfer(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_complete_trf'))) return;
        const res = await AdminAPI.completeWmsTransfer(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_trin_toast_received'));
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
            const canReceiveRow = canReceive && r.status === 'approved';
            els.detailBody.innerHTML = `
                <dl class="wh-trin-detail-grid">
                    <div><dt>${esc(t('wms_col_type'))}</dt><dd>${esc(typeLabel(r.transfer_type))}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_from'))}</dt><dd>${esc(endpointLabel(r, 'from'))}</dd></div>
                    <div><dt>${esc(t('wms_col_to'))}</dt><dd>${esc(endpointLabel(r, 'to'))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.total_value))}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at))}</dd></div>
                    ${r.requested_by_name ? `<div><dt>${esc(t('wms_col_requested_by'))}</dt><dd>${esc(r.requested_by_name)}</dd></div>` : ''}
                </dl>
                ${r.reason ? `<p class="wh-trin-detail-notes"><strong>${esc(t('wms_col_reason'))}:</strong> ${esc(r.reason)}</p>` : ''}
                <div class="wh-trin-lines-wrap">
                    <table class="modern-table wh-table wh-trin-lines-table">
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
                ${canReceiveRow ? `<div class="wh-trin-detail-actions">
                    <button type="button" class="wh-btn wh-btn--primary" id="whTrinReceiveBtn">${esc(t('wh_trin_receive'))}</button>
                </div>` : ''}
                ${r.status === 'completed' ? `<p class="wh-trin-done-note"><span class="material-icons-round">check_circle</span> ${esc(t('wh_trin_ready_badge'))}</p>` : ''}`;
            document.getElementById('whTrinReceiveBtn')?.addEventListener('click', () => receiveTransfer(r.id, true));
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-trin-empty-inline">${esc(e.message || t('load_error'))}</p>`;
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
