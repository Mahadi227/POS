/**
 * Warehouse dispatch history — audit log of shipped and completed dispatches
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whDsphTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;

    const STATUS_KEYS = {
        delivered: 'wms_status_delivered',
        cancelled: 'wms_status_cancelled',
        dispatched: 'wms_status_dispatched',
        in_transit: 'wms_status_in_transit',
    };
    const HISTORY_STATUSES = ['delivered', 'cancelled', 'dispatched', 'in_transit'];
    const STATUS_ORDER = ['delivered', 'dispatched', 'in_transit', 'cancelled'];

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
        search: document.getElementById('whDsphSearch'),
        warehouse: document.getElementById('whDsphWarehouse'),
        status: document.getElementById('whDsphStatus'),
        dateFrom: document.getElementById('whDsphDateFrom'),
        dateTo: document.getElementById('whDsphDateTo'),
        refresh: document.getElementById('whDsphRefreshBtn'),
        exportBtn: document.getElementById('whDsphExportBtn'),
        heroMeta: document.getElementById('whDsphHeroMeta'),
        breakdownPanel: document.getElementById('whDsphBreakdownPanel'),
        statusChips: document.getElementById('whDsphStatusChips'),
        statTotal: document.getElementById('whDsphStatTotal'),
        statDelivered: document.getElementById('whDsphStatDelivered'),
        statCancelled: document.getElementById('whDsphStatCancelled'),
        statItems: document.getElementById('whDsphStatItems'),
        statValue: document.getElementById('whDsphStatValue'),
        loading: document.getElementById('whDsphLoading'),
        empty: document.getElementById('whDsphEmpty'),
        pagination: document.getElementById('whDsphPagination'),
        prev: document.getElementById('whDsphPrev'),
        next: document.getElementById('whDsphNext'),
        pageMeta: document.getElementById('whDsphPageMeta'),
        detailModal: document.getElementById('whDsphDetailModal'),
        detailClose: document.getElementById('whDsphDetailClose'),
        detailTitle: document.getElementById('whDsphDetailTitle'),
        detailSubtitle: document.getElementById('whDsphDetailSubtitle'),
        detailBody: document.getElementById('whDsphDetailBody'),
    };

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'delivered' ? 'ok' : (status === 'cancelled' ? 'off' : 'warn');
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

    function eventDate(row) {
        return row.received_at || row.delivery_date || row.created_at;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-dsph-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statDelivered) els.statDelivered.textContent = String(s.delivered ?? 0);
        if (els.statCancelled) els.statCancelled.textContent = String(s.cancelled ?? 0);
        if (els.statItems) els.statItems.textContent = String(s.total_items ?? 0);
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_dsph_hero_meta', s.delivered ?? 0, s.cancelled ?? 0);
        }
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const list = (items || []).filter((r) => HISTORY_STATUSES.includes(r.status) && Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const sorted = [...list].sort((a, b) => {
            const ai = STATUS_ORDER.indexOf(a.status);
            const bi = STATUS_ORDER.indexOf(b.status);
            return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
        });
        const activeStatus = els.status?.value || 'all';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const isActive = activeStatus === r.status;
            return `<button type="button" class="wh-dsph-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-dsph-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.status) els.status.value = btn.dataset.status || 'all';
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            scope: 'history',
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        const status = els.status?.value?.trim();
        if (status && status !== 'all') params.status = status;
        const q = els.search?.value?.trim();
        if (q) params.q = q;
        const from = els.dateFrom?.value?.trim();
        const to = els.dateTo?.value?.trim();
        if (from) params.date_from = from;
        if (to) params.date_to = to;
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-dsph-table">
<thead><tr>
    <th>${esc(t('wms_col_dispatch'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_destination'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th>${esc(t('wms_col_driver'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((d) => `<tr>
    <td><strong>${esc(d.dispatch_number)}</strong></td>
    <td>${esc(d.from_warehouse_name || '—')}</td>
    <td>${esc(destinationLabel(d))}</td>
    <td>${Number(d.total_items || 0)}</td>
    <td>${esc(money(d.total_value))}</td>
    <td>${esc(d.driver_name || '—')}</td>
    <td>${esc(formatDate(eventDate(d)))}</td>
    <td>${statusBadge(d.status)}</td>
    <td class="wh-dsph-row-actions">
        <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-dsph-view="${d.id}">${esc(t('wms_view_details'))}</button>
    </td>
</tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-dsph-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.dsphView)));
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
            [t('wms_col_dispatch'), t('wms_nav_warehouses'), t('wms_col_destination'), t('wms_col_items'), t('wms_col_value'), t('wms_col_driver'), t('col_status'), t('wms_col_received_by')],
            ...items.map((d) => [d.dispatch_number, d.from_warehouse_name, destinationLabel(d), d.total_items, d.total_value, d.driver_name, d.status, d.received_by_name]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsDispatches(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`dispatch-history-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
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
            els.detailBody.innerHTML = `
                <dl class="wh-dsph-detail-grid">
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(d.from_warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(d.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_destination'))}</dt><dd>${esc(d.to_store_name || d.to_warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(d.total_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_driver'))}</dt><dd>${esc(d.driver_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_vehicle'))}</dt><dd>${esc(d.vehicle_number || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_delivery_date'))}</dt><dd>${esc(d.delivery_date || '—')}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(eventDate(d)))}</dd></div>
                    ${d.received_by_name ? `<div><dt>${esc(t('wms_col_received_by'))}</dt><dd>${esc(d.received_by_name)}</dd></div>` : ''}
                </dl>
                ${d.notes ? `<p class="wh-dsph-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(d.notes)}</p>` : ''}
                <div class="wh-dsph-lines-wrap">
                    <table class="modern-table wh-table wh-dsph-lines-table">
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
                </div>`;
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-dsph-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.exportBtn?.addEventListener('click', exportData);
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.status?.addEventListener('change', () => { state.page = 1; load(); });
    els.dateFrom?.addEventListener('change', () => { state.page = 1; load(); });
    els.dateTo?.addEventListener('change', () => { state.page = 1; load(); });
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
