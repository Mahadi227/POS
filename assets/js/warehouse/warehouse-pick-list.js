/**
 * Warehouse pick list — assign draft dispatches to pickers (draft → picking)
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whPickTableWrap');
    if (!tableWrap) return;

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canPick = !!window.WH_PAGE?.canDispatch && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
        draft: 'wms_status_draft',
        picking: 'wms_status_picking',
    };
    const PICK_ORDER = ['draft', 'picking'];

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
        search: document.getElementById('whPickSearch'),
        warehouse: document.getElementById('whPickWarehouse'),
        status: document.getElementById('whPickStatus'),
        refresh: document.getElementById('whPickRefreshBtn'),
        exportBtn: document.getElementById('whPickExportBtn'),
        heroMeta: document.getElementById('whPickHeroMeta'),
        breakdownPanel: document.getElementById('whPickBreakdownPanel'),
        statusChips: document.getElementById('whPickStatusChips'),
        statQueue: document.getElementById('whPickStatQueue'),
        statProgress: document.getElementById('whPickStatProgress'),
        statTotal: document.getElementById('whPickStatTotal'),
        loading: document.getElementById('whPickLoading'),
        empty: document.getElementById('whPickEmpty'),
        pagination: document.getElementById('whPickPagination'),
        prev: document.getElementById('whPickPrev'),
        next: document.getElementById('whPickNext'),
        pageMeta: document.getElementById('whPickPageMeta'),
        detailModal: document.getElementById('whPickDetailModal'),
        detailClose: document.getElementById('whPickDetailClose'),
        detailTitle: document.getElementById('whPickDetailTitle'),
        detailSubtitle: document.getElementById('whPickDetailSubtitle'),
        detailBody: document.getElementById('whPickDetailBody'),
        toast: document.getElementById('whPickToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-pick-toast show${type === 'error' ? ' wh-pick-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'picking' ? 'warn' : 'off';
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function destinationLabel(row) {
        if (row.to_store_name) return row.to_store_name;
        if (row.to_warehouse_name) return row.to_warehouse_name;
        return '—';
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
        document.querySelectorAll('.wh-pick-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statQueue) els.statQueue.textContent = String(s.queue ?? 0);
        if (els.statProgress) els.statProgress.textContent = String(s.progress ?? 0);
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_pick_hero_meta', s.queue ?? 0, s.progress ?? 0);
        }
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const list = (items || []).filter((r) => PICK_ORDER.includes(r.status) && Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const sorted = [...list].sort((a, b) => PICK_ORDER.indexOf(a.status) - PICK_ORDER.indexOf(b.status));
        const activeStatus = els.status?.value || 'picking_active';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const chipStatus = r.status === 'draft' ? 'picking_queue' : 'picking_progress';
            const isActive = activeStatus === r.status || activeStatus === chipStatus;
            return `<button type="button" class="wh-pick-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(chipStatus)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-pick-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.status) els.status.value = btn.dataset.status || 'picking_active';
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            view: 'picking',
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-pick-table">
<thead><tr>
    <th>${esc(t('wms_col_dispatch'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_destination'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_driver'))}</th>
    <th>${esc(t('wms_col_delivery_date'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((d) => {
    const canStart = canPick && d.status === 'draft';
    return `<tr>
        <td><strong>${esc(d.dispatch_number)}</strong></td>
        <td>${esc(d.from_warehouse_name || '—')}</td>
        <td>${esc(destinationLabel(d))}</td>
        <td>${Number(d.total_items || 0)}</td>
        <td>${esc(d.driver_name || '—')}</td>
        <td>${esc(d.delivery_date || '—')}</td>
        <td>${statusBadge(d.status)}</td>
        <td class="wh-pick-row-actions">
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-pick-view="${d.id}">${esc(t('wms_view_details'))}</button>
            ${canStart ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--primary" data-pick-start="${d.id}">${esc(t('wms_start_picking'))}</button>` : ''}
            ${d.status === 'picking' ? `<a class="wh-pick-go-link" href="packing.php">${esc(t('wh_pick_go_packing'))}</a>` : ''}
        </td>
    </tr>`;
}).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-pick-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.pickView)));
        });
        tableWrap.querySelectorAll('[data-pick-start]').forEach((btn) => {
            btn.addEventListener('click', () => startPicking(Number(btn.dataset.pickStart)));
        });
    }

    function renderPagination() {
        if (!els.pagination) return;
        const pages = Math.max(1, Math.ceil(state.total / state.limit));
        const show = state.total > state.limit;
        els.pagination.hidden = !show;
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
            const res = await AdminAPI.getWmsDispatches(buildParams());
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
            [t('wms_col_dispatch'), t('wms_nav_warehouses'), t('wms_col_destination'), t('wms_col_items'), t('wms_col_driver'), t('wms_col_delivery_date'), t('col_status')],
            ...items.map((d) => [d.dispatch_number, d.from_warehouse_name, destinationLabel(d), d.total_items, d.driver_name, d.delivery_date, d.status]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsDispatches(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`pick-list-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
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

    async function startPicking(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_pick'))) return;
        const res = await AdminAPI.updateWmsDispatchStatus(id, 'picking');
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_pick_toast_started'));
        if (fromDetail) closeModal();
        await load();
    }

    async function openDetail(id) {
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        openModal();
        try {
            const res = await AdminAPI.getWmsDispatch(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const d = res.data;
            if (els.detailTitle) els.detailTitle.textContent = `${t('wms_dispatch_details')} — ${d.dispatch_number}`;
            if (els.detailSubtitle) {
                const dest = d.to_store_name
                    ? `${t('wms_dest_store')}: ${d.to_store_name}`
                    : (d.to_warehouse_name ? `${t('wms_dest_warehouse')}: ${d.to_warehouse_name}` : '—');
                els.detailSubtitle.textContent = dest;
            }
            const items = d.items || [];
            const canStart = canPick && d.status === 'draft';
            els.detailBody.innerHTML = `
                <dl class="wh-pick-detail-grid">
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(d.from_warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(d.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_items'))}</dt><dd>${Number(d.total_items || items.length)}</dd></div>
                    <div><dt>${esc(t('wms_col_driver'))}</dt><dd>${esc(d.driver_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_vehicle'))}</dt><dd>${esc(d.vehicle_number || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_delivery_date'))}</dt><dd>${esc(d.delivery_date || '—')}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(d.created_at))}</dd></div>
                </dl>
                ${d.notes ? `<p class="wh-pick-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(d.notes)}</p>` : ''}
                <div class="wh-pick-lines-wrap">
                    <table class="modern-table wh-table wh-pick-lines-table">
                        <thead><tr>
                            <th class="wh-pick-check-col"></th>
                            <th>${esc(t('wms_col_product'))}</th>
                            <th>${esc(t('wms_col_sku'))}</th>
                            <th>${esc(t('wms_col_qty'))}</th>
                        </tr></thead>
                        <tbody>${items.map((i) => `<tr>
                            <td class="wh-pick-check-col"><span class="material-icons-round wh-pick-check-icon" aria-hidden="true">check_box_outline_blank</span></td>
                            <td>${esc(i.product_name)}</td>
                            <td>${esc(i.sku || '—')}</td>
                            <td><strong>${i.quantity}</strong></td>
                        </tr>`).join('')}</tbody>
                    </table>
                </div>
                ${canStart ? `<div class="wh-pick-detail-actions">
                    <button type="button" class="wh-btn wh-btn--primary" id="whPickStartBtn" data-id="${d.id}">${esc(t('wms_start_picking'))}</button>
                </div>` : ''}
                ${d.status === 'picking' ? `<p class="wh-pick-progress-note"><span class="material-icons-round">inventory_2</span> ${esc(t('wh_pick_progress_badge'))} <a href="packing.php">${esc(t('wh_pick_go_packing'))}</a></p>` : ''}`;
            document.getElementById('whPickStartBtn')?.addEventListener('click', () => {
                startPicking(Number(d.id), true);
            });
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-pick-empty-inline">${esc(e.message || t('load_error'))}</p>`;
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
