/**
 * Warehouse receiving history — completed and rejected goods receipts
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whRhistTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;

    const STATUS_KEYS = {
        completed: 'wms_status_completed',
        rejected: 'wms_status_rejected',
    };
    const INSP_KEYS = {
        pending: 'wms_insp_status_pending',
        passed: 'wms_insp_status_passed',
        failed: 'wms_insp_status_failed',
        partial: 'wms_insp_status_partial',
    };
    const HISTORY_STATUSES = ['completed', 'rejected'];

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
        search: document.getElementById('whRhistSearch'),
        warehouse: document.getElementById('whRhistWarehouse'),
        status: document.getElementById('whRhistStatus'),
        dateFrom: document.getElementById('whRhistDateFrom'),
        dateTo: document.getElementById('whRhistDateTo'),
        refresh: document.getElementById('whRhistRefreshBtn'),
        exportBtn: document.getElementById('whRhistExportBtn'),
        heroMeta: document.getElementById('whRhistHeroMeta'),
        breakdownPanel: document.getElementById('whRhistBreakdownPanel'),
        statusChips: document.getElementById('whRhistStatusChips'),
        statTotal: document.getElementById('whRhistStatTotal'),
        statCompleted: document.getElementById('whRhistStatCompleted'),
        statRejected: document.getElementById('whRhistStatRejected'),
        statItems: document.getElementById('whRhistStatItems'),
        statValue: document.getElementById('whRhistStatValue'),
        loading: document.getElementById('whRhistLoading'),
        empty: document.getElementById('whRhistEmpty'),
        pagination: document.getElementById('whRhistPagination'),
        prev: document.getElementById('whRhistPrev'),
        next: document.getElementById('whRhistNext'),
        pageMeta: document.getElementById('whRhistPageMeta'),
        detailModal: document.getElementById('whRhistDetailModal'),
        detailClose: document.getElementById('whRhistDetailClose'),
        detailTitle: document.getElementById('whRhistDetailTitle'),
        detailBody: document.getElementById('whRhistDetailBody'),
    };

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function inspLabel(status) {
        return t(INSP_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'completed' ? 'ok' : 'off';
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

    function periodLabel() {
        const from = els.dateFrom?.value || '';
        const to = els.dateTo?.value || '';
        if (from && to) return `${from} → ${to}`;
        return '—';
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-rhist-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statCompleted) els.statCompleted.textContent = String(s.completed ?? 0);
        if (els.statRejected) els.statRejected.textContent = String(s.rejected ?? 0);
        if (els.statItems) els.statItems.textContent = String(s.total_items ?? 0);
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_rhist_hero_meta', s.completed ?? 0, s.rejected ?? 0);
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
        const activeStatus = els.status?.value || 'all';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = list.map((r) => {
            const isActive = activeStatus === r.status;
            return `<button type="button" class="wh-rhist-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-rhist-status-chip').forEach((btn) => {
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-rhist-table">
<thead><tr>
    <th>${esc(t('wms_col_grn'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_supplier'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
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
    <td>${esc(money(r.total_value))}</td>
    <td>${esc(formatDate(r.received_at))}</td>
    <td>${statusBadge(r.status)}</td>
    <td>${inspBadge(r.inspection_status || 'pending')}</td>
    <td class="wh-rhist-row-actions">
        <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-rhist-view="${r.id}">${esc(t('wms_view_details'))}</button>
    </td>
</tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-rhist-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.rhistView)));
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
            [t('wms_col_grn'), t('wms_nav_warehouses'), t('wms_col_supplier'), t('wms_col_items'), t('wms_col_value'), t('col_date'), t('col_status'), t('wms_insp_col_result')],
            ...items.map((r) => [r.grn_number, r.warehouse_name, r.supplier_name, r.total_items, r.total_value, r.received_at, r.status, r.inspection_status]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsReceipts(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`receiving-history-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
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
            const res = await AdminAPI.getWmsReceipt(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.detailTitle) els.detailTitle.textContent = `${t('wms_receipt_details')} — ${r.grn_number}`;
            const items = r.items || [];
            els.detailBody.innerHTML = `
                <dl class="wh-rhist-detail-grid">
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_supplier'))}</dt><dd>${esc(r.supplier_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_insp_col_result'))}</dt><dd>${inspBadge(r.inspection_status || 'pending')}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.total_value))}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.received_at))}</dd></div>
                    <div><dt>${esc(t('wms_col_received_by'))}</dt><dd>${esc(r.received_by_name || '—')}</dd></div>
                    ${r.inspected_at ? `<div><dt>${esc(t('wh_rhist_inspected_at'))}</dt><dd>${esc(formatDate(r.inspected_at))}</dd></div>` : ''}
                </dl>
                ${r.notes ? `<p class="wh-rhist-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(r.notes)}</p>` : ''}
                <div class="wh-rhist-detail-table-wrap"><table class="modern-table wh-table"><thead><tr>
                    <th>${esc(t('wms_col_product'))}</th><th>${esc(t('wms_col_sku'))}</th><th>${esc(t('wms_qty_received'))}</th>
                    <th>${esc(t('wms_qty_damaged'))}</th><th>${esc(t('wms_qty_ok'))}</th><th>${esc(t('wms_unit_cost'))}</th>
                </tr></thead><tbody>${items.map((i) => {
                    const ok = Math.max(0, (i.quantity_received || 0) - (i.quantity_damaged || 0));
                    return `<tr>
                        <td>${esc(i.product_name)}</td><td>${esc(i.sku || '—')}</td><td>${i.quantity_received}</td>
                        <td>${i.quantity_damaged || 0}</td><td>${ok}</td><td>${esc(money(i.unit_cost))}</td>
                    </tr>`;
                }).join('')}</tbody></table></div>`;
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-rhist-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.status?.addEventListener('change', () => { state.page = 1; load(); });
    els.dateFrom?.addEventListener('change', () => { state.page = 1; load(); });
    els.dateTo?.addEventListener('change', () => { state.page = 1; load(); });
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
