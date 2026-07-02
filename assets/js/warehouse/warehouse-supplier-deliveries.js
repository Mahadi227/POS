/**
 * Warehouse supplier deliveries — incoming GRN inspection pipeline
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whSdelTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canReceive = !!window.WH_PAGE?.canReceive && !window.WH_PAGE?.readOnly;

    const STATUS_KEYS = {
        pending: 'wms_status_pending',
        inspecting: 'wms_status_inspecting',
        accepted: 'wms_status_accepted',
    };
    const INCOMING_STATUSES = ['pending', 'inspecting', 'accepted'];
    const STATUS_ORDER = ['pending', 'inspecting', 'accepted'];

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
        search: document.getElementById('whSdelSearch'),
        warehouse: document.getElementById('whSdelWarehouse'),
        status: document.getElementById('whSdelStatus'),
        refresh: document.getElementById('whSdelRefreshBtn'),
        exportBtn: document.getElementById('whSdelExportBtn'),
        heroMeta: document.getElementById('whSdelHeroMeta'),
        breakdownPanel: document.getElementById('whSdelBreakdownPanel'),
        statusChips: document.getElementById('whSdelStatusChips'),
        statTotal: document.getElementById('whSdelStatTotal'),
        statPending: document.getElementById('whSdelStatPending'),
        statInspecting: document.getElementById('whSdelStatInspecting'),
        statAccepted: document.getElementById('whSdelStatAccepted'),
        statValue: document.getElementById('whSdelStatValue'),
        loading: document.getElementById('whSdelLoading'),
        empty: document.getElementById('whSdelEmpty'),
        pagination: document.getElementById('whSdelPagination'),
        prev: document.getElementById('whSdelPrev'),
        next: document.getElementById('whSdelNext'),
        pageMeta: document.getElementById('whSdelPageMeta'),
        detailModal: document.getElementById('whSdelDetailModal'),
        detailClose: document.getElementById('whSdelDetailClose'),
        detailTitle: document.getElementById('whSdelDetailTitle'),
        detailBody: document.getElementById('whSdelDetailBody'),
        toast: document.getElementById('whSdelToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-sdel-toast show${type === 'error' ? ' wh-sdel-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'accepted' ? 'ok' : 'warn';
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
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
        document.querySelectorAll('.wh-sdel-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statPending) els.statPending.textContent = String(s.pending ?? 0);
        if (els.statInspecting) els.statInspecting.textContent = String(s.inspecting ?? 0);
        if (els.statAccepted) els.statAccepted.textContent = String(s.accepted ?? 0);
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_sdel_hero_meta', s.pending ?? 0, s.inspecting ?? 0);
        }
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const list = (items || [])
            .filter((r) => INCOMING_STATUSES.includes(r.status) && Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const sorted = [...list].sort((a, b) => {
            const ai = STATUS_ORDER.indexOf(a.status);
            const bi = STATUS_ORDER.indexOf(b.status);
            return ai - bi;
        });
        const activeStatus = els.status?.value || 'all';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const isActive = activeStatus === r.status;
            return `<button type="button" class="wh-sdel-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-sdel-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.status) els.status.value = btn.dataset.status || 'all';
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            scope: 'incoming',
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
        if (!canReceive) {
            return `<button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-sdel-view="${r.id}">${esc(t('wms_view_details'))}</button>`;
        }
        const parts = [`<button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-sdel-view="${r.id}">${esc(t('wms_view_details'))}</button>`];
        if (r.status === 'pending') {
            parts.push(`<button type="button" class="wh-btn wh-btn--sm" data-sdel-inspect="${r.id}">${esc(t('wms_start_inspection'))}</button>`);
        } else if (r.status === 'inspecting') {
            parts.push(`<button type="button" class="wh-btn wh-btn--sm" data-sdel-accept="${r.id}">${esc(t('wms_accept_delivery'))}</button>`);
        } else if (r.status === 'accepted') {
            parts.push(`<button type="button" class="wh-btn wh-btn--primary wh-btn--sm" data-sdel-complete="${r.id}">${esc(t('wms_complete'))}</button>`);
        }
        if (r.status !== 'accepted') {
            parts.push(`<button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-sdel-reject="${r.id}">${esc(t('wms_reject'))}</button>`);
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-sdel-table">
<thead><tr>
    <th>${esc(t('wms_col_grn'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_supplier'))}</th>
    <th>${esc(t('wms_col_items'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('col_status'))}</th>
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
    <td class="wh-sdel-row-actions">${rowActions(r)}</td>
</tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-sdel-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.sdelView)));
        });
        tableWrap.querySelectorAll('[data-sdel-inspect]').forEach((btn) => {
            btn.addEventListener('click', () => advanceStatus(Number(btn.dataset.sdelInspect), 'inspect', 'wms_toast_inspecting'));
        });
        tableWrap.querySelectorAll('[data-sdel-accept]').forEach((btn) => {
            btn.addEventListener('click', () => advanceStatus(Number(btn.dataset.sdelAccept), 'accept', 'wms_toast_accepted'));
        });
        tableWrap.querySelectorAll('[data-sdel-complete]').forEach((btn) => {
            btn.addEventListener('click', () => completeReceipt(Number(btn.dataset.sdelComplete)));
        });
        tableWrap.querySelectorAll('[data-sdel-reject]').forEach((btn) => {
            btn.addEventListener('click', () => advanceStatus(Number(btn.dataset.sdelReject), 'reject', 'wms_toast_rejected'));
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
            [t('wms_col_grn'), t('wms_nav_warehouses'), t('wms_col_supplier'), t('wms_col_items'), t('wms_col_value'), t('col_date'), t('col_status')],
            ...items.map((r) => [r.grn_number, r.warehouse_name, r.supplier_name, r.total_items, r.total_value, r.received_at, r.status]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsReceipts(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`supplier-deliveries-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    function openModal(el) {
        if (!el) return;
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
    }

    function closeModal(el) {
        if (!el) return;
        el.classList.remove('is-open');
        el.setAttribute('aria-hidden', 'true');
    }

    function detailActions(r) {
        if (!canReceive) return '';
        const parts = [];
        if (r.status === 'pending') {
            parts.push(`<button type="button" class="wh-btn wh-btn--sm" data-sdel-inspect="${r.id}">${esc(t('wms_start_inspection'))}</button>`);
        } else if (r.status === 'inspecting') {
            parts.push(`<button type="button" class="wh-btn wh-btn--sm" data-sdel-accept="${r.id}">${esc(t('wms_accept_delivery'))}</button>`);
        } else if (r.status === 'accepted') {
            parts.push(`<button type="button" class="wh-btn wh-btn--primary" data-sdel-complete="${r.id}">${esc(t('wms_complete'))}</button>`);
        }
        if (r.status !== 'accepted') {
            parts.push(`<button type="button" class="wh-btn wh-btn--ghost" data-sdel-reject="${r.id}">${esc(t('wms_reject'))}</button>`);
        }
        if (!parts.length) return '';
        return `<div class="wh-sdel-detail-actions">${parts.join('')}</div>`;
    }

    function bindDetailActions(r) {
        els.detailBody?.querySelector('[data-sdel-inspect]')?.addEventListener('click', () => {
            advanceStatus(r.id, 'inspect', 'wms_toast_inspecting', true);
        });
        els.detailBody?.querySelector('[data-sdel-accept]')?.addEventListener('click', () => {
            advanceStatus(r.id, 'accept', 'wms_toast_accepted', true);
        });
        els.detailBody?.querySelector('[data-sdel-complete]')?.addEventListener('click', () => {
            completeReceipt(r.id, true);
        });
        els.detailBody?.querySelector('[data-sdel-reject]')?.addEventListener('click', () => {
            advanceStatus(r.id, 'reject', 'wms_toast_rejected', true);
        });
    }

    async function openDetail(id) {
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        openModal(els.detailModal);
        try {
            const res = await AdminAPI.getWmsReceipt(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.detailTitle) els.detailTitle.textContent = `${t('wms_receipt_details')} — ${r.grn_number}`;
            const items = r.items || [];
            els.detailBody.innerHTML = `
                <dl class="wh-sdel-detail-grid">
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_supplier'))}</dt><dd>${esc(r.supplier_name || '—')}</dd></div>
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.total_value))}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.received_at))}</dd></div>
                    <div><dt>${esc(t('wms_col_received_by'))}</dt><dd>${esc(r.received_by_name || '—')}</dd></div>
                </dl>
                ${r.notes ? `<p class="wh-sdel-detail-notes"><strong>${esc(t('wms_receipt_notes'))}:</strong> ${esc(r.notes)}</p>` : ''}
                <div class="wh-sdel-detail-table-wrap"><table class="modern-table wh-table"><thead><tr>
                    <th>${esc(t('wms_col_product'))}</th><th>${esc(t('wms_col_sku'))}</th><th>${esc(t('wms_qty_received'))}</th>
                    <th>${esc(t('wms_unit_cost'))}</th><th>${esc(t('wms_line_subtotal'))}</th>
                </tr></thead><tbody>${items.map((i) => `<tr>
                    <td>${esc(i.product_name)}</td><td>${esc(i.sku || '—')}</td><td>${i.quantity_received}</td>
                    <td>${esc(money(i.unit_cost))}</td><td>${esc(money((i.quantity_received || 0) * (i.unit_cost || 0)))}</td>
                </tr>`).join('')}</tbody></table></div>
                ${detailActions(r)}`;
            bindDetailActions(r);
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-sdel-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function advanceStatus(id, action, toastKey, fromDetail = false) {
        const confirms = {
            inspect: t('wms_confirm_inspect'),
            accept: t('wms_confirm_accept'),
            reject: t('wms_confirm_reject_receipt'),
        };
        if (confirms[action] && !window.confirm(confirms[action])) return;

        const api = {
            inspect: AdminAPI.inspectWmsReceipt,
            accept: AdminAPI.acceptWmsReceipt,
            reject: AdminAPI.rejectWmsReceipt,
        }[action];
        const res = await api(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t(toastKey));
        if (fromDetail) closeModal(els.detailModal);
        if (action === 'reject') state.page = 1;
        await load();
    }

    async function completeReceipt(id, fromDetail = false) {
        if (!window.confirm(t('wms_confirm_complete'))) return;
        const res = await AdminAPI.completeWmsReceipt(id);
        if (res.status !== 'success') {
            showError(res.message || t('error'));
            return;
        }
        hideError();
        toast(t('wh_sdel_toast_completed'));
        if (fromDetail) closeModal(els.detailModal);
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
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.detailModal?.addEventListener('click', (e) => { if (e.target === els.detailModal) closeModal(els.detailModal); });
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        const pages = Math.ceil(state.total / state.limit);
        if (state.page < pages) { state.page += 1; load(); }
    });

    document.addEventListener('wh:refresh', load);
    document.addEventListener('store-switched', () => { state.page = 1; load(); });

    loadWarehouseOptions(els.warehouse).then(load);
});
