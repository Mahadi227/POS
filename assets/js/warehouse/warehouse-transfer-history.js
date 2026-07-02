/**
 * Transfer history — audit completed, rejected and cancelled transfers
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whTrhTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;

    const STATUS_KEYS = {
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
    const HISTORY_STATUSES = ['completed', 'rejected', 'cancelled'];
    const STATUS_ORDER = ['completed', 'rejected', 'cancelled'];

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
        search: document.getElementById('whTrhSearch'),
        warehouse: document.getElementById('whTrhWarehouse'),
        type: document.getElementById('whTrhType'),
        status: document.getElementById('whTrhStatus'),
        dateFrom: document.getElementById('whTrhDateFrom'),
        dateTo: document.getElementById('whTrhDateTo'),
        refresh: document.getElementById('whTrhRefreshBtn'),
        exportBtn: document.getElementById('whTrhExportBtn'),
        heroMeta: document.getElementById('whTrhHeroMeta'),
        breakdownPanel: document.getElementById('whTrhBreakdownPanel'),
        statusChips: document.getElementById('whTrhStatusChips'),
        statTotal: document.getElementById('whTrhStatTotal'),
        statCompleted: document.getElementById('whTrhStatCompleted'),
        statRejected: document.getElementById('whTrhStatRejected'),
        statItems: document.getElementById('whTrhStatItems'),
        statValue: document.getElementById('whTrhStatValue'),
        loading: document.getElementById('whTrhLoading'),
        empty: document.getElementById('whTrhEmpty'),
        pagination: document.getElementById('whTrhPagination'),
        prev: document.getElementById('whTrhPrev'),
        next: document.getElementById('whTrhNext'),
        pageMeta: document.getElementById('whTrhPageMeta'),
        detailModal: document.getElementById('whTrhDetailModal'),
        detailClose: document.getElementById('whTrhDetailClose'),
        detailTitle: document.getElementById('whTrhDetailTitle'),
        detailSubtitle: document.getElementById('whTrhDetailSubtitle'),
        detailBody: document.getElementById('whTrhDetailBody'),
    };

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function statusBadge(status) {
        const cls = status === 'completed' ? 'ok' : 'off';
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

    function eventDate(row) {
        return row.completed_at || row.approved_at || row.created_at;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-trh-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statCompleted) els.statCompleted.textContent = String(s.completed ?? 0);
        if (els.statRejected) els.statRejected.textContent = String((s.rejected ?? 0) + (s.cancelled ?? 0));
        if (els.statItems) els.statItems.textContent = String(s.total_items ?? 0);
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_trh_hero_meta', s.completed ?? 0, (s.rejected ?? 0) + (s.cancelled ?? 0));
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
            return `<button type="button" class="wh-trh-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-trh-status-chip').forEach((btn) => {
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
        const type = els.type?.value?.trim();
        if (type) params.transfer_type = type;
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-trh-table">
<thead><tr>
    <th>${esc(t('wms_col_transfer'))}</th>
    <th>${esc(t('wms_col_type'))}</th>
    <th>${esc(t('wms_col_from'))}</th>
    <th>${esc(t('wms_col_to'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r) => `<tr>
    <td><strong>${esc(r.transfer_number)}</strong></td>
    <td>${esc(typeLabel(r.transfer_type))}</td>
    <td>${esc(endpointLabel(r, 'from'))}</td>
    <td>${esc(endpointLabel(r, 'to'))}</td>
    <td>${Number(r.total_items || 0)}</td>
    <td>${esc(money(r.total_value))}</td>
    <td>${esc(formatDate(eventDate(r)))}</td>
    <td>${statusBadge(r.status)}</td>
    <td class="wh-trh-row-actions">
        <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-trh-view="${r.id}">${esc(t('wms_view_details'))}</button>
    </td>
</tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-trh-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.trhView)));
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
            [t('wms_col_transfer'), t('wms_col_type'), t('wms_col_from'), t('wms_col_to'), t('wms_col_items'), t('wms_col_value'), t('col_status'), t('wms_col_requested_by')],
            ...items.map((r) => [r.transfer_number, r.transfer_type, endpointLabel(r, 'from'), endpointLabel(r, 'to'), r.total_items, r.total_value, r.status, r.requested_by_name]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsTransfers(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`transfer-history-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
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
            const res = await AdminAPI.getWmsTransfer(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.detailTitle) els.detailTitle.textContent = `${t('wms_transfer_details')} — ${r.transfer_number}`;
            if (els.detailSubtitle) {
                els.detailSubtitle.textContent = `${typeLabel(r.transfer_type)} · ${endpointLabel(r, 'from')} → ${endpointLabel(r, 'to')}`;
            }
            const items = r.items || [];
            els.detailBody.innerHTML = `
                <dl class="wh-trh-detail-grid">
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_type'))}</dt><dd>${esc(typeLabel(r.transfer_type))}</dd></div>
                    <div><dt>${esc(t('wms_col_from'))}</dt><dd>${esc(endpointLabel(r, 'from'))}</dd></div>
                    <div><dt>${esc(t('wms_col_to'))}</dt><dd>${esc(endpointLabel(r, 'to'))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.total_value))}</dd></div>
                    <div><dt>${esc(t('wh_trh_completed_at'))}</dt><dd>${esc(formatDate(r.completed_at || r.approved_at))}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at))}</dd></div>
                    ${r.requested_by_name ? `<div><dt>${esc(t('wms_col_requested_by'))}</dt><dd>${esc(r.requested_by_name)}</dd></div>` : ''}
                </dl>
                ${r.reason ? `<p class="wh-trh-detail-notes"><strong>${esc(t('wms_col_reason'))}:</strong> ${esc(r.reason)}</p>` : ''}
                <div class="wh-trh-lines-wrap">
                    <table class="modern-table wh-table wh-trh-lines-table">
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
                </div>`;
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-trh-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.exportBtn?.addEventListener('click', exportData);
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.type?.addEventListener('change', () => { state.page = 1; load(); });
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
