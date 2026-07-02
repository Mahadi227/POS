/**
 * Warehouse delivery confirmation — confirm receipt of dispatched shipments
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whDelTableWrap');
    if (!tableWrap) return;

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canConfirm = !!window.WH_PAGE?.canDispatch && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
        dispatched: 'wms_status_dispatched',
        in_transit: 'wms_status_in_transit',
        delivered: 'wms_status_delivered',
    };
    const DEL_ORDER = ['dispatched', 'in_transit', 'delivered'];

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
        search: document.getElementById('whDelSearch'),
        warehouse: document.getElementById('whDelWarehouse'),
        status: document.getElementById('whDelStatus'),
        refresh: document.getElementById('whDelRefreshBtn'),
        exportBtn: document.getElementById('whDelExportBtn'),
        heroMeta: document.getElementById('whDelHeroMeta'),
        breakdownPanel: document.getElementById('whDelBreakdownPanel'),
        statusChips: document.getElementById('whDelStatusChips'),
        statPending: document.getElementById('whDelStatPending'),
        statToday: document.getElementById('whDelStatToday'),
        statDelivered: document.getElementById('whDelStatDelivered'),
        loading: document.getElementById('whDelLoading'),
        empty: document.getElementById('whDelEmpty'),
        pagination: document.getElementById('whDelPagination'),
        prev: document.getElementById('whDelPrev'),
        next: document.getElementById('whDelNext'),
        pageMeta: document.getElementById('whDelPageMeta'),
        detailModal: document.getElementById('whDelDetailModal'),
        detailClose: document.getElementById('whDelDetailClose'),
        detailTitle: document.getElementById('whDelDetailTitle'),
        detailSubtitle: document.getElementById('whDelDetailSubtitle'),
        detailBody: document.getElementById('whDelDetailBody'),
        toast: document.getElementById('whDelToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-del-toast show${type === 'error' ? ' wh-del-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'delivered' ? 'ok' : (status === 'in_transit' ? 'warn' : 'off');
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
        document.querySelectorAll('.wh-del-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statPending) els.statPending.textContent = String(s.pending ?? 0);
        if (els.statToday) els.statToday.textContent = String(s.delivered_today ?? 0);
        if (els.statDelivered) els.statDelivered.textContent = String(s.delivered ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_del_hero_meta', s.pending ?? 0, s.delivered_today ?? 0);
        }
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const list = (items || []).filter((r) => DEL_ORDER.includes(r.status) && Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const sorted = [...list].sort((a, b) => DEL_ORDER.indexOf(a.status) - DEL_ORDER.indexOf(b.status));
        const activeStatus = els.status?.value || 'delivery_pending';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const isActive = activeStatus === r.status;
            return `<button type="button" class="wh-del-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-del-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.status) els.status.value = btn.dataset.status || 'delivery_pending';
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            view: 'delivery',
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-del-table">
<thead><tr>
    <th>${esc(t('wms_col_dispatch'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_destination'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_driver'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((d) => {
    const canMark = canConfirm && (d.status === 'dispatched' || d.status === 'in_transit');
    const delivered = d.status === 'delivered';
    return `<tr>
        <td><strong>${esc(d.dispatch_number)}</strong></td>
        <td>${esc(d.from_warehouse_name || '—')}</td>
        <td>${esc(destinationLabel(d))}</td>
        <td>${Number(d.total_items || 0)}</td>
        <td>${esc(d.driver_name || '—')}</td>
        <td>${esc(formatDate(delivered ? d.received_at : d.created_at))}</td>
        <td>${statusBadge(d.status)}</td>
        <td class="wh-del-row-actions">
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-del-view="${d.id}">${esc(t('wms_view_details'))}</button>
            ${canMark ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--primary" data-del-confirm="${d.id}">${esc(t('wms_mark_delivered'))}</button>` : ''}
            ${delivered ? `<span class="wh-del-confirmed-tag">${esc(t('wh_del_confirmed_badge'))}</span>` : ''}
        </td>
    </tr>`;
}).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-del-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.delView)));
        });
        tableWrap.querySelectorAll('[data-del-confirm]').forEach((btn) => {
            btn.addEventListener('click', () => confirmDelivery(Number(btn.dataset.delConfirm)));
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
            [t('wms_col_dispatch'), t('wms_nav_warehouses'), t('wms_col_destination'), t('wms_col_items'), t('wms_col_driver'), t('col_status'), t('wms_col_received_by')],
            ...items.map((d) => [d.dispatch_number, d.from_warehouse_name, destinationLabel(d), d.total_items, d.driver_name, d.status, d.received_by_name]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsDispatches(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`delivery-confirmation-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
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

    async function confirmDelivery(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_delivery'))) return;
        const res = await AdminAPI.updateWmsDispatchStatus(id, 'delivered');
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_del_toast_confirmed'));
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
            const canMark = canConfirm && (d.status === 'dispatched' || d.status === 'in_transit');
            const delivered = d.status === 'delivered';
            els.detailBody.innerHTML = `
                <dl class="wh-del-detail-grid">
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(d.from_warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(d.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_destination'))}</dt><dd>${esc(d.to_store_name || d.to_warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_driver'))}</dt><dd>${esc(d.driver_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_vehicle'))}</dt><dd>${esc(d.vehicle_number || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_delivery_date'))}</dt><dd>${esc(d.delivery_date || '—')}</dd></div>
                    ${delivered ? `<div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(d.received_at))}</dd></div>` : ''}
                    ${delivered ? `<div><dt>${esc(t('wms_col_received_by'))}</dt><dd>${esc(d.received_by_name || '—')}</dd></div>` : ''}
                </dl>
                ${d.notes ? `<p class="wh-del-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(d.notes)}</p>` : ''}
                <div class="wh-del-lines-wrap">
                    <table class="modern-table wh-table wh-del-lines-table">
                        <thead><tr>
                            <th>${esc(t('wms_col_product'))}</th>
                            <th>${esc(t('wms_col_sku'))}</th>
                            <th>${esc(t('wms_col_qty'))}</th>
                        </tr></thead>
                        <tbody>${items.map((i) => `<tr>
                            <td>${esc(i.product_name)}</td>
                            <td>${esc(i.sku || '—')}</td>
                            <td><strong>${i.quantity}</strong></td>
                        </tr>`).join('')}</tbody>
                    </table>
                </div>
                ${canMark ? `<div class="wh-del-detail-actions">
                    <button type="button" class="wh-btn wh-btn--primary" id="whDelConfirmBtn" data-id="${d.id}">
                        <span class="material-icons-round">done_all</span>${esc(t('wms_mark_delivered'))}
                    </button>
                </div>` : ''}
                ${delivered ? `<p class="wh-del-confirmed-note"><span class="material-icons-round">check_circle</span> ${esc(t('wh_del_confirmed_badge'))}</p>` : ''}`;
            document.getElementById('whDelConfirmBtn')?.addEventListener('click', () => {
                confirmDelivery(Number(d.id), true);
            });
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-del-empty-inline">${esc(e.message || t('load_error'))}</p>`;
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
