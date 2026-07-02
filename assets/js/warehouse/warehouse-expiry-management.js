/**
 * Expiry management — monitor batches at expiry risk
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whExpTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canManage = !!window.WH_PAGE?.canManage && !window.WH_PAGE?.readOnly;

    const FILTER_KEYS = {
        at_risk: 'wms_filter_at_risk',
        expiring_soon: 'wms_filter_expiring_only',
        expired: 'wms_filter_expired_only',
    };
    const STATUS_KEYS = {
        active: 'wms_status_active',
        expired: 'wms_status_expired',
        recalled: 'wms_status_recalled',
        depleted: 'wms_status_depleted',
    };
    const FILTER_ORDER = ['expiring_soon', 'expired', 'at_risk'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        detailId: null,
        searchTimer: null,
    };

    const els = {
        search: document.getElementById('whExpSearch'),
        warehouse: document.getElementById('whExpWarehouse'),
        period: document.getElementById('whExpPeriod'),
        filter: document.getElementById('whExpFilter'),
        refresh: document.getElementById('whExpRefreshBtn'),
        exportBtn: document.getElementById('whExpExportBtn'),
        heroMeta: document.getElementById('whExpHeroMeta'),
        breakdownPanel: document.getElementById('whExpBreakdownPanel'),
        statusChips: document.getElementById('whExpStatusChips'),
        statSoon: document.getElementById('whExpStatSoon'),
        statPast: document.getElementById('whExpStatPast'),
        statUnits: document.getElementById('whExpStatUnits'),
        statValue: document.getElementById('whExpStatValue'),
        loading: document.getElementById('whExpLoading'),
        empty: document.getElementById('whExpEmpty'),
        pagination: document.getElementById('whExpPagination'),
        prev: document.getElementById('whExpPrev'),
        next: document.getElementById('whExpNext'),
        pageMeta: document.getElementById('whExpPageMeta'),
        detailModal: document.getElementById('whExpDetailModal'),
        detailClose: document.getElementById('whExpDetailClose'),
        detailTitle: document.getElementById('whExpDetailTitle'),
        detailSubtitle: document.getElementById('whExpDetailSubtitle'),
        detailBody: document.getElementById('whExpDetailBody'),
        detailActions: document.getElementById('whExpDetailActions'),
        expiredBtn: document.getElementById('whExpExpiredBtn'),
        recallBtn: document.getElementById('whExpRecallBtn'),
        depleteBtn: document.getElementById('whExpDepleteBtn'),
        toast: document.getElementById('whExpToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-exp-toast show${type === 'error' ? ' wh-exp-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function filterLabel(filter) {
        return t(FILTER_KEYS[filter] || filter) || filter || '—';
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'active' ? 'ok' : (status === 'recalled' || status === 'expired' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function formatDate(val, withTime = false) {
        if (!val) return '—';
        try {
            return AdminAPI.formatDate(val, withTime ? { dateStyle: 'short', timeStyle: 'short' } : { dateStyle: 'short' });
        } catch {
            return val;
        }
    }

    function urgencyBadge(row) {
        const days = row.days_to_expiry != null ? Number(row.days_to_expiry) : null;
        if (days == null) return '—';
        if (days < 0 || row.status === 'expired') {
            return `<span class="cr-badge cr-badge--off">${esc(t('wms_urgency_expired'))}</span>`;
        }
        if (days <= 7) {
            return `<span class="cr-badge cr-badge--off">${esc(t('wms_urgency_critical'))}</span>`;
        }
        if (days <= 30) {
            return `<span class="cr-badge cr-badge--warn">${esc(t('wms_urgency_warning'))}</span>`;
        }
        return `<span class="cr-badge cr-badge--ok">${days}d</span>`;
    }

    function expiryCell(row) {
        const exp = row.expiry_date;
        if (!exp) return '—';
        const days = row.days_to_expiry != null ? Number(row.days_to_expiry) : null;
        let cls = '';
        if (days != null) {
            if (days < 0) cls = 'wh-exp-expiry--past';
            else if (days <= 30) cls = 'wh-exp-expiry--soon';
        }
        const hint = days != null ? ` <small class="wh-exp-expiry-days">(${days}d)</small>` : '';
        return `<span class="wh-exp-expiry ${cls}">${esc(formatDate(exp))}${hint}</span>`;
    }

    function rowClass(row) {
        const days = Number(row.days_to_expiry);
        if (days < 0) return 'wh-exp-row--past';
        if (days <= 7) return 'wh-exp-row--critical';
        return '';
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-exp-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statSoon) els.statSoon.textContent = String(s.expiring_soon ?? 0);
        if (els.statPast) els.statPast.textContent = String(s.past_expiry ?? 0);
        if (els.statUnits) els.statUnits.textContent = Number(s.units_at_risk ?? 0).toLocaleString();
        if (els.statValue) els.statValue.textContent = money(s.value_at_risk);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_exp_hero_meta', s.expiring_soon ?? 0, s.past_expiry ?? 0);
        }
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const list = (items || []).filter((r) => FILTER_ORDER.includes(r.status) && Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const sorted = [...list].sort((a, b) => {
            const ai = FILTER_ORDER.indexOf(a.status);
            const bi = FILTER_ORDER.indexOf(b.status);
            return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
        });
        const activeFilter = els.filter?.value || '';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const isActive = activeFilter === r.status;
            return `<button type="button" class="wh-exp-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(filterLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-exp-status-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (els.filter) els.filter.value = btn.dataset.status || '';
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
            days: Number(els.period?.value || 30),
            status: els.filter?.value?.trim() || 'at_risk',
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
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
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-exp-table">
<thead><tr>
    <th>${esc(t('wms_col_batch'))}</th>
    <th>${esc(t('wms_col_product'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_qty'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th>${esc(t('wms_col_expiry'))}</th>
    <th>${esc(t('wms_days_to_expiry'))}</th>
    <th>${esc(t('col_status'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r) => `<tr class="${rowClass(r)}">
    <td><strong>${esc(r.batch_number)}</strong></td>
    <td>${esc(r.product_name)}<br><code class="wms-sku">${esc(r.sku || '')}</code></td>
    <td>${esc(r.warehouse_name || '—')}</td>
    <td>${Number(r.quantity || 0)}</td>
    <td>${esc(money(r.stock_value))}</td>
    <td>${expiryCell(r)}</td>
    <td>${urgencyBadge(r)}</td>
    <td>${statusBadge(r.status)}</td>
    <td class="wh-exp-row-actions">
        <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-exp-view="${r.id}">${esc(t('wms_view_details'))}</button>
    </td>
</tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-exp-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.expView)));
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
            const res = await AdminAPI.getWmsExpiry(buildParams());
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
            [t('wms_col_batch'), t('wms_col_product'), 'SKU', t('wms_nav_warehouses'), t('wms_col_qty'), t('wms_col_value'), t('wms_col_expiry'), t('wms_days_to_expiry'), t('col_status')],
            ...items.map((r) => [r.batch_number, r.product_name, r.sku, r.warehouse_name, r.quantity, r.stock_value, r.expiry_date, r.days_to_expiry, r.status]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsExpiry(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`expiry-management-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    function openModal(el) {
        el?.classList.add('is-open');
        el?.setAttribute('aria-hidden', 'false');
    }

    function closeModal(el) {
        el?.classList.remove('is-open');
        el?.setAttribute('aria-hidden', 'true');
    }

    function updateDetailActions(row) {
        if (!els.detailActions) return;
        els.detailActions.hidden = !(canManage && row && row.status === 'active');
    }

    async function openDetail(id) {
        state.detailId = id;
        if (!els.detailBody) return;
        els.detailBody.innerHTML = `<div class="wh-loading">${esc(t('loading'))}</div>`;
        updateDetailActions(null);
        openModal(els.detailModal);
        try {
            const res = await AdminAPI.getWmsBatch(id);
            if (res.status !== 'success' || !res.data) throw new Error(res.message || t('load_error'));
            const r = res.data;
            if (els.detailTitle) els.detailTitle.textContent = r.batch_number || t('wms_expiry_details');
            if (els.detailSubtitle) {
                els.detailSubtitle.textContent = [r.product_name, r.sku, r.warehouse_name].filter(Boolean).join(' · ');
            }
            const days = r.days_to_expiry != null ? Number(r.days_to_expiry) : null;
            els.detailBody.innerHTML = `
                <dl class="wh-exp-detail-grid">
                    <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(r.status)} ${urgencyBadge(r)}</dd></div>
                    <div><dt>${esc(t('wms_col_product'))}</dt><dd>${esc(r.product_name)} <code class="wms-sku">${esc(r.sku || '')}</code></dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_qty'))}</dt><dd>${Number(r.quantity || 0)}</dd></div>
                    <div><dt>${esc(t('wms_unit_cost'))}</dt><dd>${esc(money(r.unit_cost))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.stock_value))}</dd></div>
                    <div><dt>${esc(t('wms_col_mfg'))}</dt><dd>${esc(formatDate(r.manufacturing_date))}</dd></div>
                    <div><dt>${esc(t('wms_col_expiry'))}</dt><dd>${expiryCell(r)}</dd></div>
                    <div><dt>${esc(t('wms_days_to_expiry'))}</dt><dd>${days != null ? `${days} ${t('wms_days_short') || 'd'}` : '—'}</dd></div>
                    <div><dt>${esc(t('wms_col_barcode'))}</dt><dd>${esc(r.barcode || r.product_barcode || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_serial'))}</dt><dd>${esc(r.serial_number || '—')}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at, true))}</dd></div>
                </dl>`;
            updateDetailActions(r);
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-exp-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function updateStatus(status) {
        if (!state.detailId) return;
        const confirmKeys = {
            recalled: 'wms_confirm_recall',
            depleted: 'wms_confirm_deplete',
            expired: 'wms_confirm_mark_expired',
        };
        if (!window.confirm(t(confirmKeys[status] || 'error'))) return;
        try {
            const res = await AdminAPI.updateWmsBatchStatus(state.detailId, status);
            if (res.status !== 'success') throw new Error(res.message || t('error'));
            closeModal(els.detailModal);
            toast(t('save'));
            await load();
        } catch (e) {
            toast(e.message || t('error'), 'error');
        }
    }

    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => {
            state.page = 1;
            load();
        }, 350);
    });
    els.warehouse?.addEventListener('change', () => { state.page = 1; load(); });
    els.period?.addEventListener('change', () => { state.page = 1; load(); });
    els.filter?.addEventListener('change', () => { state.page = 1; load(); });
    els.refresh?.addEventListener('click', load);
    els.exportBtn?.addEventListener('click', exportData);
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.expiredBtn?.addEventListener('click', () => updateStatus('expired'));
    els.recallBtn?.addEventListener('click', () => updateStatus('recalled'));
    els.depleteBtn?.addEventListener('click', () => updateStatus('depleted'));
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        const pages = Math.max(1, Math.ceil(state.total / state.limit));
        if (state.page < pages) { state.page += 1; load(); }
    });

    document.addEventListener('wh:refresh', load);
    loadWarehouseOptions(els.warehouse).then(load);
});
