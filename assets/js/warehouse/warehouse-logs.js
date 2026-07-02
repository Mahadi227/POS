/**
 * Warehouse audit logs — operations trace and activity breakdown
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whLogTableWrap');
    if (!tableWrap) return;

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WarehouseUI;

    const ACTION_KEYS = {
        warehouse_created: 'wms_log_warehouse_created',
        warehouse_updated: 'wms_log_warehouse_updated',
        warehouse_deleted: 'wms_log_warehouse_deleted',
        location_created: 'wms_log_location_created',
        stock_adjusted: 'wms_log_stock_adjusted',
        transfer_requested: 'wms_log_transfer_requested',
        transfer_approved: 'wms_log_transfer_approved',
        transfer_rejected: 'wms_log_transfer_rejected',
        transfer_received: 'wms_log_transfer_received',
        dispatch_created: 'wms_log_dispatch_created',
        dispatch_out: 'wms_log_dispatch_out',
        request_created: 'wms_log_request_created',
        request_approved: 'wms_log_request_approved',
        request_rejected: 'wms_log_request_rejected',
        batch_created: 'wms_log_batch_created',
        batch_status_updated: 'wms_log_batch_status_updated',
        audit_created: 'wms_log_audit_created',
        audit_submitted: 'wms_log_audit_submitted',
        audit_approved: 'wms_log_audit_approved',
        audit_rejected: 'wms_log_audit_rejected',
        low_stock: 'wms_log_low_stock',
        damaged_stock: 'wms_log_damaged_stock',
        expired_product: 'wms_log_expired_product',
        incoming_delivery: 'wms_log_incoming_delivery',
        purchase_received: 'wms_log_purchase_received',
        warehouse_full: 'wms_log_warehouse_full',
    };

    const ENTITY_KEYS = {
        warehouse: 'wms_entity_warehouse',
        warehouse_location: 'wms_entity_location',
        warehouse_transfer: 'wms_entity_transfer',
        warehouse_dispatch: 'wms_entity_dispatch',
        warehouse_request: 'wms_entity_request',
        warehouse_movement: 'wms_entity_movement',
        batch_tracking: 'wms_entity_batch',
        warehouse_audit: 'wms_entity_audit',
        notification: 'wms_entity_notification',
    };

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
        warehouse: document.getElementById('whLogWarehouse'),
        search: document.getElementById('whLogSearch'),
        action: document.getElementById('whLogAction'),
        entity: document.getElementById('whLogEntity'),
        dateFrom: document.getElementById('whLogDateFrom'),
        dateTo: document.getElementById('whLogDateTo'),
        refresh: document.getElementById('whLogRefreshBtn'),
        exportCsv: document.getElementById('whLogExportCsv'),
        exportPdf: document.getElementById('whLogExportPdf'),
        heroMeta: document.getElementById('whLogHeroMeta'),
        statTotal: document.getElementById('whLogStatTotal'),
        statToday: document.getElementById('whLogStatToday'),
        statUsers: document.getElementById('whLogStatUsers'),
        statEntities: document.getElementById('whLogStatEntities'),
        breakdownPanel: document.getElementById('whLogBreakdownPanel'),
        breakdownChips: document.getElementById('whLogBreakdownChips'),
        loading: document.getElementById('whLogLoading'),
        empty: document.getElementById('whLogEmpty'),
        pagination: document.getElementById('whLogPagination'),
        prev: document.getElementById('whLogPrev'),
        next: document.getElementById('whLogNext'),
        pageMeta: document.getElementById('whLogPageMeta'),
        modal: document.getElementById('whLogDetailModal'),
        modalClose: document.getElementById('whLogDetailClose'),
        modalTitle: document.getElementById('whLogDetailTitle'),
        modalSubtitle: document.getElementById('whLogDetailSubtitle'),
        modalBody: document.getElementById('whLogDetailBody'),
    };

    function actionLabel(action) {
        if (!action) return '—';
        return t(ACTION_KEYS[action] || action) || action.replace(/_/g, ' ');
    }

    function entityLabel(type) {
        if (!type) return '—';
        return t(ENTITY_KEYS[type] || type) || type.replace(/_/g, ' ');
    }

    function actionBadge(action) {
        const warn = ['rejected', 'deleted', 'damaged', 'expired', 'low_stock'].some((k) => action?.includes(k));
        const ok = ['approved', 'created', 'received'].some((k) => action?.includes(k));
        const cls = warn ? 'off' : (ok ? 'ok' : 'idle');
        return `<span class="cr-badge cr-badge--${cls}">${esc(actionLabel(action))}</span>`;
    }

    function entityCell(row) {
        if (!row.entity_type) return '—';
        const label = entityLabel(row.entity_type);
        return row.entity_id
            ? `${esc(label)} <code class="wms-sku">#${row.entity_id}</code>`
            : esc(label);
    }

    function detailsPreview(row) {
        if (!row.details) return '—';
        try {
            const obj = typeof row.details === 'string' ? JSON.parse(row.details) : row.details;
            if (obj && typeof obj === 'object') {
                const msg = obj.message || obj.status || obj.role || obj.name;
                if (msg) return esc(String(msg));
                const keys = Object.keys(obj).slice(0, 2);
                return esc(keys.map((k) => `${k}: ${obj[k]}`).join(', '));
            }
        } catch { /* ignore */ }
        const s = String(row.details);
        return esc(s.length > 60 ? `${s.slice(0, 57)}…` : s);
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return AdminAPI.formatDate(iso, { dateStyle: 'short', timeStyle: 'short' });
        } catch {
            return iso;
        }
    }

    function filters() {
        return {
            from: els.dateFrom?.value || undefined,
            to: els.dateTo?.value || undefined,
            action: els.action?.value || undefined,
            entity_type: els.entity?.value || undefined,
            q: els.search?.value?.trim() || undefined,
        };
    }

    function periodLabel() {
        const f = filters();
        return [f.from, f.to].filter(Boolean).join(' → ') || '—';
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-log-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statToday) els.statToday.textContent = String(s.today ?? 0);
        if (els.statUsers) els.statUsers.textContent = String(s.users ?? 0);
        if (els.statEntities) els.statEntities.textContent = String(s.entities ?? 0);
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.breakdownChips) return;
        if (!items.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        els.breakdownPanel.hidden = false;
        const activeAction = els.action?.value || '';
        els.breakdownChips.innerHTML = items.map((r) => {
            const isActive = activeAction === r.action;
            return `<button type="button" class="wh-log-action-chip${isActive ? ' is-active' : ''}" data-action="${esc(r.action)}">
                <span>${esc(actionLabel(r.action))}</span>
                <strong>${Number(r.event_count || 0)}</strong>
            </button>`;
        }).join('');
        els.breakdownChips.querySelectorAll('.wh-log-action-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action || '';
                if (els.action) els.action.value = action;
                state.page = 1;
                load();
            });
        });
    }

    function fillActionFilter(actions) {
        if (!els.action) return;
        const cur = els.action.value;
        const list = actions?.length ? actions : Object.keys(ACTION_KEYS);
        els.action.innerHTML = `<option value="">${esc(t('wms_filter_all_actions'))}</option>`
            + list.map((a) => `<option value="${esc(a)}">${esc(actionLabel(a))}</option>`).join('');
        if (cur) els.action.value = cur;
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-log-table">
<thead><tr>
    <th>${esc(t('col_date'))}</th>
    <th>${esc(t('wms_col_action'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_user'))}</th>
    <th>${esc(t('wms_col_entity'))}</th>
    <th>${esc(t('wms_col_details'))}</th>
    <th>${esc(t('wms_col_ip'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r) => `<tr>
    <td>${esc(formatDate(r.created_at))}</td>
    <td>${actionBadge(r.action)}</td>
    <td>${esc(r.warehouse_name || '—')}</td>
    <td>${esc(r.user_name || '—')}</td>
    <td>${entityCell(r)}</td>
    <td class="wh-log-details-preview">${detailsPreview(r)}</td>
    <td><code class="wms-sku">${esc(r.ip_address || '—')}</code></td>
    <td class="cr-actions">
        <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-log-view="${r.id}">${esc(t('wms_view_details'))}</button>
    </td>
</tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-log-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.logView)));
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

    function updateHeroMeta() {
        if (!els.heroMeta) return;
        els.heroMeta.textContent = periodLabel();
    }

    function buildParams(forExport = false) {
        const params = {
            ...filters(),
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        return params;
    }

    async function loadWarehouses() {
        const res = await AdminAPI.getWmsWarehouses({ limit: 200 });
        const items = res.status === 'success' ? (res.data || []) : [];
        if (!els.warehouse) return;
        const cur = els.warehouse.value || String(window.WH_PAGE?.warehouseId || '');
        els.warehouse.innerHTML = `<option value="">${esc(t('wh_all_warehouses'))}</option>`
            + items.map((w) => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
        if (cur && items.some((w) => String(w.id) === String(cur))) {
            els.warehouse.value = cur;
        }
    }

    function openModal() {
        if (!els.modal) return;
        els.modal.classList.add('is-open');
        els.modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        if (!els.modal) return;
        els.modal.classList.remove('is-open');
        els.modal.setAttribute('aria-hidden', 'true');
    }

    async function openDetail(id) {
        if (!els.modalBody) return;
        els.modalBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        openModal();
        try {
            const res = await AdminAPI.getWmsLog(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.modalTitle) els.modalTitle.textContent = actionLabel(r.action);
            if (els.modalSubtitle) {
                els.modalSubtitle.textContent = [r.warehouse_name, r.user_name].filter(Boolean).join(' · ');
            }
            let detailsHtml = '—';
            if (r.details_parsed) {
                detailsHtml = `<pre class="wh-log-json">${esc(JSON.stringify(r.details_parsed, null, 2))}</pre>`;
            } else if (r.details) {
                detailsHtml = `<pre class="wh-log-json">${esc(String(r.details))}</pre>`;
            }
            els.modalBody.innerHTML = `
                <dl class="wh-log-detail-grid">
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at))}</dd></div>
                    <div><dt>${esc(t('wms_col_action'))}</dt><dd>${actionBadge(r.action)}</dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_user'))}</dt><dd>${esc(r.user_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_entity'))}</dt><dd>${entityCell(r)}</dd></div>
                    <div><dt>${esc(t('wms_col_ip'))}</dt><dd><code class="wms-sku">${esc(r.ip_address || '—')}</code></dd></div>
                </dl>
                <h4 class="wh-log-details-heading">${esc(t('wms_col_details'))}</h4>
                ${detailsHtml}`;
        } catch (e) {
            els.modalBody.innerHTML = `<p class="wh-log-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function load() {
        hideError();
        updateHeroMeta();
        if (els.loading) els.loading.hidden = false;
        if (els.empty) els.empty.hidden = true;
        setStatsLoading(true);

        try {
            const res = await AdminAPI.getWmsLogs(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            fillActionFilter(res.actions);
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
            [t('col_date'), t('wms_col_action'), t('wms_nav_warehouses'), t('wms_col_user'),
                t('wms_col_entity'), 'Entity ID', t('wms_col_details'), t('wms_col_ip')],
            ...items.map((r) => [
                r.created_at, r.action, r.warehouse_name, r.user_name,
                r.entity_type, r.entity_id, r.details, r.ip_address,
            ]),
        ];
    }

    async function exportAllCsv() {
        const wh = els.warehouse?.value?.trim();
        const res = await AdminAPI.getWmsLogs({ ...buildParams(true), warehouse_id: wh || undefined });
        const items = res.status === 'success' ? (res.data || []) : state.items;
        if (!items.length) return;
        exportCsv(`warehouse-logs-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
    }

    async function exportPdf() {
        const wh = els.warehouse?.value?.trim();
        const res = await AdminAPI.getWmsLogs({ ...buildParams(true), warehouse_id: wh || undefined });
        const items = res.status === 'success' ? (res.data || []) : state.items;
        if (!items.length || !window.WmsReportExport) return;
        const head = buildExportRows([])[0];
        const rows = buildExportRows(items).slice(1);
        const result = await WmsReportExport.exportPdf({
            title: t('wms_logs_title'),
            periodLabel: periodLabel(),
            generatedLabel: t('last_updated') || 'Generated',
            locale: window.WH_CONFIG?.locale,
            head,
            rows,
            filename: `warehouse-logs-${new Date().toISOString().slice(0, 10)}.pdf`,
        });
        if (result?.fallback) window.print();
    }

    els.refresh?.addEventListener('click', () => { state.page = 1; load(); });
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.action?.addEventListener('change', () => { state.page = 1; load(); });
    els.entity?.addEventListener('change', () => { state.page = 1; load(); });
    els.dateFrom?.addEventListener('change', () => { state.page = 1; load(); });
    els.dateTo?.addEventListener('change', () => { state.page = 1; load(); });
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => { state.page = 1; load(); }, 350);
    });
    els.exportCsv?.addEventListener('click', exportAllCsv);
    els.exportPdf?.addEventListener('click', exportPdf);
    els.modalClose?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        const pages = Math.ceil(state.total / state.limit);
        if (state.page < pages) { state.page += 1; load(); }
    });

    document.addEventListener('wh:refresh', load);

    loadWarehouses().then(load);
});
