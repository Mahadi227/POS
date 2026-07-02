/**
 * Transfer approval queue — review and approve pending (requested) transfers
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whAprTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canApprove = (!!window.WH_PAGE?.canManage || !!window.WH_PAGE?.canTransfer) && !window.WH_PAGE?.readOnly;

    const TYPE_KEYS = {
        warehouse_to_warehouse: 'wms_type_wh_wh',
        warehouse_to_store: 'wms_type_wh_store',
        store_to_warehouse: 'wms_type_store_wh',
        branch_to_branch: 'wms_type_branch',
    };
    const TYPE_ORDER = ['warehouse_to_warehouse', 'warehouse_to_store', 'store_to_warehouse', 'branch_to_branch'];

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
        search: document.getElementById('whAprSearch'),
        warehouse: document.getElementById('whAprWarehouse'),
        type: document.getElementById('whAprType'),
        refresh: document.getElementById('whAprRefreshBtn'),
        exportBtn: document.getElementById('whAprExportBtn'),
        heroMeta: document.getElementById('whAprHeroMeta'),
        breakdownPanel: document.getElementById('whAprBreakdownPanel'),
        typeChips: document.getElementById('whAprTypeChips'),
        statPending: document.getElementById('whAprStatPending'),
        statWarehouse: document.getElementById('whAprStatWarehouse'),
        statBranch: document.getElementById('whAprStatBranch'),
        statValue: document.getElementById('whAprStatValue'),
        loading: document.getElementById('whAprLoading'),
        empty: document.getElementById('whAprEmpty'),
        pagination: document.getElementById('whAprPagination'),
        prev: document.getElementById('whAprPrev'),
        next: document.getElementById('whAprNext'),
        pageMeta: document.getElementById('whAprPageMeta'),
        detailModal: document.getElementById('whAprDetailModal'),
        detailClose: document.getElementById('whAprDetailClose'),
        detailTitle: document.getElementById('whAprDetailTitle'),
        detailSubtitle: document.getElementById('whAprDetailSubtitle'),
        detailBody: document.getElementById('whAprDetailBody'),
        toast: document.getElementById('whAprToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-apr-toast show${type === 'error' ? ' wh-apr-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
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
        document.querySelectorAll('.wh-apr-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statPending) els.statPending.textContent = String(s.pending ?? 0);
        if (els.statWarehouse) els.statWarehouse.textContent = String(s.warehouse ?? 0);
        if (els.statBranch) els.statBranch.textContent = String(s.branch ?? 0);
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_apr_hero_meta', s.pending ?? 0, s.warehouse ?? 0, s.branch ?? 0);
        }
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.typeChips) return;
        const list = (items || []).filter((r) => TYPE_ORDER.includes(r.type) && Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const sorted = [...list].sort((a, b) => {
            const ai = TYPE_ORDER.indexOf(a.type);
            const bi = TYPE_ORDER.indexOf(b.type);
            return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
        });
        const activeType = els.type?.value || '';
        els.breakdownPanel.hidden = false;
        els.typeChips.innerHTML = sorted.map((r) => {
            const isActive = activeType === r.type;
            return `<button type="button" class="wh-apr-type-chip${isActive ? ' is-active' : ''}" data-type="${esc(r.type)}">
                <span>${esc(typeLabel(r.type))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.typeChips.querySelectorAll('.wh-apr-type-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.type) els.type.value = btn.dataset.type || '';
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            queue: 'approval',
            status: 'requested',
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        const type = els.type?.value?.trim();
        if (type) params.transfer_type = type;
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-apr-table">
<thead><tr>
    <th>${esc(t('wms_col_transfer'))}</th>
    <th>${esc(t('wms_col_type'))}</th>
    <th>${esc(t('wms_col_from'))}</th>
    <th>${esc(t('wms_col_to'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('wms_col_requested_by'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r) => `<tr>
    <td><strong>${esc(r.transfer_number)}</strong></td>
    <td>${esc(typeLabel(r.transfer_type))}</td>
    <td>${esc(endpointLabel(r, 'from'))}</td>
    <td>${esc(endpointLabel(r, 'to'))}</td>
    <td>${Number(r.total_items || 0)}</td>
    <td>${esc(money(r.total_value))}</td>
    <td>${esc(formatDate(r.created_at))}</td>
    <td>${esc(r.requested_by_name || '—')}</td>
    <td class="wh-apr-row-actions">
        <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-apr-view="${r.id}">${esc(t('wms_view_details'))}</button>
        ${canApprove ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--primary" data-apr-approve="${r.id}">${esc(t('wh_apr_approve_btn'))}</button>` : ''}
        ${canApprove ? `<button type="button" class="wh-btn wh-btn--sm wh-btn--warn" data-apr-reject="${r.id}">${esc(t('wh_apr_reject_btn'))}</button>` : ''}
    </td>
</tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-apr-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.aprView)));
        });
        tableWrap.querySelectorAll('[data-apr-approve]').forEach((btn) => {
            btn.addEventListener('click', () => approveTransfer(Number(btn.dataset.aprApprove)));
        });
        tableWrap.querySelectorAll('[data-apr-reject]').forEach((btn) => {
            btn.addEventListener('click', () => rejectTransfer(Number(btn.dataset.aprReject)));
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
            [t('wms_col_transfer'), t('wms_col_type'), t('wms_col_from'), t('wms_col_to'), t('wms_col_items'), t('wms_col_value'), t('wms_col_requested_by')],
            ...items.map((r) => [r.transfer_number, r.transfer_type, endpointLabel(r, 'from'), endpointLabel(r, 'to'), r.total_items, r.total_value, r.requested_by_name]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsTransfers(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`transfer-approvals-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
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
        if (!canApprove) return;
        if (!window.confirm(t('wms_confirm_approve_trf'))) return;
        const res = await AdminAPI.approveWmsTransfer(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_apr_toast_approved'));
        if (fromDetail) closeModal();
        await load();
    }

    async function rejectTransfer(id, fromDetail = false) {
        if (!canApprove) return;
        if (!window.confirm(t('wms_confirm_reject_trf'))) return;
        const res = await AdminAPI.rejectWmsTransfer(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_apr_toast_rejected'));
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
            els.detailBody.innerHTML = `
                <dl class="wh-apr-detail-grid">
                    <div><dt>${esc(t('wms_col_type'))}</dt><dd>${esc(typeLabel(r.transfer_type))}</dd></div>
                    <div><dt>${esc(t('wms_col_from'))}</dt><dd>${esc(endpointLabel(r, 'from'))}</dd></div>
                    <div><dt>${esc(t('wms_col_to'))}</dt><dd>${esc(endpointLabel(r, 'to'))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.total_value))}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at))}</dd></div>
                    ${r.requested_by_name ? `<div><dt>${esc(t('wms_col_requested_by'))}</dt><dd>${esc(r.requested_by_name)}</dd></div>` : ''}
                </dl>
                ${r.reason ? `<p class="wh-apr-detail-notes"><strong>${esc(t('wms_col_reason'))}:</strong> ${esc(r.reason)}</p>` : ''}
                <div class="wh-apr-lines-wrap">
                    <table class="modern-table wh-table wh-apr-lines-table">
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
                ${canApprove && r.status === 'requested' ? `<div class="wh-apr-detail-actions">
                    <button type="button" class="wh-btn wh-btn--primary" id="whAprApproveBtn">${esc(t('wh_apr_approve_btn'))}</button>
                    <button type="button" class="wh-btn wh-btn--warn" id="whAprRejectBtn">${esc(t('wh_apr_reject_btn'))}</button>
                </div>` : ''}`;
            document.getElementById('whAprApproveBtn')?.addEventListener('click', () => approveTransfer(r.id, true));
            document.getElementById('whAprRejectBtn')?.addEventListener('click', () => rejectTransfer(r.id, true));
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-apr-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.exportBtn?.addEventListener('click', exportData);
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.type?.addEventListener('change', () => { state.page = 1; load(); });
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
