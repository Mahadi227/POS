/**
 * FIFO / FEFO rotation queue — pick order for active batches
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whFifoTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const canManage = !!window.WH_PAGE?.canManage && !window.WH_PAGE?.readOnly;

    const STRATEGY_KEYS = {
        fefo: 'wh_fifo_strategy_fefo',
        fifo: 'wh_fifo_strategy_fifo',
    };
    const STRATEGY_ORDER = ['fefo', 'fifo'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        strategy: 'fefo',
        items: [],
        summary: null,
        breakdown: [],
        detailId: null,
        searchTimer: null,
    };

    const els = {
        search: document.getElementById('whFifoSearch'),
        warehouse: document.getElementById('whFifoWarehouse'),
        strategy: document.getElementById('whFifoStrategy'),
        refresh: document.getElementById('whFifoRefreshBtn'),
        exportBtn: document.getElementById('whFifoExportBtn'),
        heroMeta: document.getElementById('whFifoHeroMeta'),
        breakdownPanel: document.getElementById('whFifoBreakdownPanel'),
        strategyChips: document.getElementById('whFifoStrategyChips'),
        statBatches: document.getElementById('whFifoStatBatches'),
        statUnits: document.getElementById('whFifoStatUnits'),
        statExpiry: document.getElementById('whFifoStatExpiry'),
        statExp7d: document.getElementById('whFifoStatExp7d'),
        loading: document.getElementById('whFifoLoading'),
        empty: document.getElementById('whFifoEmpty'),
        pagination: document.getElementById('whFifoPagination'),
        prev: document.getElementById('whFifoPrev'),
        next: document.getElementById('whFifoNext'),
        pageMeta: document.getElementById('whFifoPageMeta'),
        detailModal: document.getElementById('whFifoDetailModal'),
        detailClose: document.getElementById('whFifoDetailClose'),
        detailTitle: document.getElementById('whFifoDetailTitle'),
        detailSubtitle: document.getElementById('whFifoDetailSubtitle'),
        detailBody: document.getElementById('whFifoDetailBody'),
        detailActions: document.getElementById('whFifoDetailActions'),
        depleteBtn: document.getElementById('whFifoDepleteBtn'),
        toast: document.getElementById('whFifoToast'),
    };

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-fifo-toast show${type === 'error' ? ' wh-fifo-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function strategyLabel(strategy) {
        return t(STRATEGY_KEYS[strategy] || strategy) || strategy || '—';
    }

    function formatDate(val, withTime = false) {
        if (!val) return '—';
        try {
            return AdminAPI.formatDate(val, withTime ? { dateStyle: 'short', timeStyle: 'short' } : { dateStyle: 'short' });
        } catch {
            return val;
        }
    }

    function receivedDate(row) {
        return row.manufacturing_date || row.created_at || null;
    }

    function expiryCell(row) {
        const exp = row.expiry_date;
        if (!exp) return '—';
        const days = row.days_to_expiry != null ? Number(row.days_to_expiry) : null;
        let cls = '';
        if (days != null) {
            if (days < 0) cls = 'wh-fifo-expiry--past';
            else if (days <= 7) cls = 'wh-fifo-expiry--soon';
        }
        const hint = days != null ? ` <small class="wh-fifo-expiry-days">(${days}d)</small>` : '';
        return `<span class="wh-fifo-expiry ${cls}">${esc(formatDate(exp))}${hint}</span>`;
    }

    function rowClass(row) {
        const days = Number(row.days_to_expiry);
        if (!Number.isFinite(days)) return '';
        if (days < 0) return 'wh-fifo-row--past';
        if (days <= 7) return 'wh-fifo-row--critical';
        return '';
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-fifo-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statBatches) els.statBatches.textContent = String(s.batches ?? 0);
        if (els.statUnits) els.statUnits.textContent = Number(s.units ?? 0).toLocaleString();
        if (els.statExpiry) els.statExpiry.textContent = String(s.with_expiry ?? 0);
        if (els.statExp7d) els.statExp7d.textContent = String(s.expiring_7d ?? 0);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wh_fifo_hero_meta', s.batches ?? 0, s.units ?? 0);
        }
        setStatsLoading(false);
    }

    function syncStrategyUi() {
        if (els.strategy) els.strategy.value = state.strategy;
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.strategyChips) return;
        const list = (items || []).filter((r) => STRATEGY_ORDER.includes(r.status));
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        els.breakdownPanel.hidden = false;
        els.strategyChips.innerHTML = list.map((r) => {
            const isActive = state.strategy === r.status;
            return `<button type="button" class="wh-fifo-strategy-chip${isActive ? ' is-active' : ''}" data-strategy="${esc(r.status)}">
                <span>${esc(strategyLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.strategyChips.querySelectorAll('.wh-fifo-strategy-chip').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.strategy = btn.dataset.strategy || 'fefo';
                syncStrategyUi();
                state.page = 1;
                load();
            });
        });
    }

    function buildParams(forExport = false) {
        const params = {
            scope: 'fifo',
            strategy: state.strategy,
            status: 'active',
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
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
        const baseRank = (state.page - 1) * state.limit;
        tableWrap.innerHTML = `<table class="modern-table wh-table wh-fifo-table">
<thead><tr>
    <th>${esc(t('wh_fifo_col_rank'))}</th>
    <th>${esc(t('wms_col_batch'))}</th>
    <th>${esc(t('wms_col_product'))}</th>
    <th>${esc(t('wms_nav_warehouses'))}</th>
    <th>${esc(t('wms_col_qty'))}</th>
    <th>${esc(t('wh_fifo_col_received'))}</th>
    <th>${esc(t('wms_col_expiry'))}</th>
    <th>${esc(t('wms_col_value'))}</th>
    <th></th>
</tr></thead>
<tbody>${items.map((r, i) => `<tr class="${rowClass(r)}">
    <td><span class="wh-fifo-rank">${baseRank + i + 1}</span></td>
    <td><strong>${esc(r.batch_number)}</strong></td>
    <td>${esc(r.product_name)}<br><code class="wms-sku">${esc(r.sku || '')}</code></td>
    <td>${esc(r.warehouse_name || '—')}</td>
    <td>${Number(r.quantity || 0)}</td>
    <td>${esc(formatDate(receivedDate(r)))}</td>
    <td>${expiryCell(r)}</td>
    <td>${esc(money(r.stock_value))}</td>
    <td class="wh-fifo-row-actions">
        <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" data-fifo-view="${r.id}">${esc(t('wms_view_details'))}</button>
    </td>
</tr>`).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('[data-fifo-view]').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.fifoView)));
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
            const res = await AdminAPI.getWmsBatches(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.strategy = res.strategy || state.strategy || 'fefo';
            syncStrategyUi();
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
            [t('wh_fifo_col_rank'), t('wms_col_batch'), t('wms_col_product'), 'SKU', t('wms_nav_warehouses'), t('wms_col_qty'), t('wh_fifo_col_received'), t('wms_col_expiry'), t('wms_col_value')],
            ...items.map((r, i) => [i + 1, r.batch_number, r.product_name, r.sku, r.warehouse_name, r.quantity, receivedDate(r), r.expiry_date, r.stock_value]),
        ];
    }

    async function exportData() {
        try {
            const res = await AdminAPI.getWmsBatches(buildParams(true));
            const items = res.status === 'success' ? (res.data || []) : state.items;
            if (!items.length) return;
            exportCsv(`fifo-fefo-${state.strategy}-${new Date().toISOString().slice(0, 10)}.csv`, buildExportRows(items));
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
        els.detailActions.hidden = !(canManage && row && row.status === 'active' && Number(row.quantity || 0) > 0);
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
            if (els.detailTitle) els.detailTitle.textContent = r.batch_number || t('wms_batch_details');
            if (els.detailSubtitle) {
                els.detailSubtitle.textContent = [r.product_name, r.sku, r.warehouse_name].filter(Boolean).join(' · ');
            }
            const days = r.days_to_expiry != null ? Number(r.days_to_expiry) : null;
            els.detailBody.innerHTML = `
                <dl class="wh-fifo-detail-grid">
                    <div><dt>${esc(t('col_status'))}</dt><dd>${esc(t('wms_status_active'))}</dd></div>
                    <div><dt>${esc(t('wms_col_product'))}</dt><dd>${esc(r.product_name)} <code class="wms-sku">${esc(r.sku || '')}</code></dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(r.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_qty'))}</dt><dd>${Number(r.quantity || 0)}</dd></div>
                    <div><dt>${esc(t('wms_unit_cost'))}</dt><dd>${esc(money(r.unit_cost))}</dd></div>
                    <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(r.stock_value))}</dd></div>
                    <div><dt>${esc(t('wh_fifo_col_received'))}</dt><dd>${esc(formatDate(receivedDate(r)))}</dd></div>
                    <div><dt>${esc(t('wms_col_expiry'))}</dt><dd>${expiryCell(r)}</dd></div>
                    <div><dt>${esc(t('wms_days_to_expiry'))}</dt><dd>${days != null ? `${days} ${t('wms_days_short') || 'd'}` : '—'}</dd></div>
                    <div><dt>${esc(t('wms_col_barcode'))}</dt><dd>${esc(r.barcode || r.product_barcode || '—')}</dd></div>
                    <div><dt>${esc(t('wms_col_serial'))}</dt><dd>${esc(r.serial_number || '—')}</dd></div>
                    <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(r.created_at, true))}</dd></div>
                </dl>`;
            updateDetailActions(r);
        } catch (e) {
            els.detailBody.innerHTML = `<p class="wh-fifo-empty-inline">${esc(e.message || t('load_error'))}</p>`;
        }
    }

    async function markDepleted() {
        if (!state.detailId) return;
        if (!window.confirm(t('wms_confirm_deplete'))) return;
        try {
            const res = await AdminAPI.updateWmsBatchStatus(state.detailId, 'depleted');
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
    els.strategy?.addEventListener('change', () => {
        state.strategy = els.strategy.value || 'fefo';
        state.page = 1;
        load();
    });
    els.refresh?.addEventListener('click', load);
    els.exportBtn?.addEventListener('click', exportData);
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.depleteBtn?.addEventListener('click', markDepleted);
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => {
        const pages = Math.max(1, Math.ceil(state.total / state.limit));
        if (state.page < pages) { state.page += 1; load(); }
    });

    document.addEventListener('wh:refresh', load);
    loadWarehouseOptions(els.warehouse).then(load);
});
