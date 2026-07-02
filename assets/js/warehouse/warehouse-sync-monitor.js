/**
 * Warehouse offline sync monitor — pending queue, conflicts, warehouse health
 */
document.addEventListener('DOMContentLoaded', () => {
    const whGrid = document.getElementById('whSyncWhGrid');
    if (!whGrid) return;

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, loadWarehouseOptions } = WarehouseUI;

    let chart = null;
    let warehousesCache = [];
    let refreshTimer = null;
    let activePanel = 'warehouses';

    const ENTITY_KEYS = {
        receipt: 'wms_sync_entity_receipt',
        transfer: 'wms_sync_entity_transfer',
        movement: 'wms_sync_entity_movement',
    };

    const els = {
        heroMeta: document.getElementById('whSyncHeroMeta'),
        statPending: document.getElementById('whSyncStatPending'),
        statConflicts: document.getElementById('whSyncStatConflicts'),
        statSyncedToday: document.getElementById('whSyncStatSyncedToday'),
        statWhIssues: document.getElementById('whSyncStatWhIssues'),
        statTotalWh: document.getElementById('whSyncStatTotalWh'),
        chart: document.getElementById('whSyncChart'),
        whSearch: document.getElementById('whSyncWhSearch'),
        whFilter: document.getElementById('whSyncWhFilter'),
        warehouse: document.getElementById('whSyncWarehouse'),
        refresh: document.getElementById('whSyncRefreshBtn'),
        badgeWh: document.getElementById('whSyncBadgeWh'),
        badgePending: document.getElementById('whSyncBadgePending'),
        badgeConflicts: document.getElementById('whSyncBadgeConflicts'),
        pendingBody: document.getElementById('whSyncPendingBody'),
        conflictsBody: document.getElementById('whSyncConflictsBody'),
        toast: document.getElementById('whSyncToast'),
        tabs: document.querySelectorAll('.wh-sync-tab'),
        panels: {
            warehouses: document.getElementById('whSyncPanelWarehouses'),
            pending: document.getElementById('whSyncPanelPending'),
            conflicts: document.getElementById('whSyncPanelConflicts'),
        },
    };

    function entityLabel(entity) {
        return t(ENTITY_KEYS[entity] || entity) || entity;
    }

    function toast(msg, type = 'success') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `wh-sync-toast show${type === 'error' ? ' wh-sync-toast--error' : ''}`;
        clearTimeout(els.toast._t);
        els.toast._t = setTimeout(() => els.toast.classList.remove('show'), 3200);
    }

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: dark ? '#374151' : '#e5e7eb',
            text: dark ? '#9ca3af' : '#6b7280',
            ok: '#10b981',
            err: '#ef4444',
        };
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-sync-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function formatDate(value) {
        if (!value) return '—';
        try {
            return new Date(value).toLocaleString(window.WH_CONFIG?.locale || 'fr-FR', {
                year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
            });
        } catch {
            return value;
        }
    }

    function renderStats(stats) {
        const pending = stats.pending ?? 0;
        const conflicts = stats.conflicts ?? 0;
        if (els.statPending) els.statPending.textContent = String(pending);
        if (els.statConflicts) els.statConflicts.textContent = String(conflicts);
        if (els.statSyncedToday) els.statSyncedToday.textContent = String(stats.synced_today ?? 0);
        if (els.statWhIssues) els.statWhIssues.textContent = String(stats.warehouses_with_issues ?? 0);
        if (els.statTotalWh) els.statTotalWh.textContent = String(stats.total_warehouses ?? 0);
        if (els.badgePending) els.badgePending.textContent = String(pending);
        if (els.badgeConflicts) els.badgeConflicts.textContent = String(conflicts);
        if (els.heroMeta) {
            els.heroMeta.textContent = t('wms_sync_summary', pending, conflicts, stats.synced_today ?? 0);
        }
        setStatsLoading(false);
    }

    function renderChart(data) {
        if (!els.chart || typeof Chart === 'undefined') return;
        chart?.destroy();
        const c = chartColors();
        chart = new Chart(els.chart, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [
                    { label: t('wms_chart_synced'), data: data.synced || [], backgroundColor: c.ok, borderRadius: 4 },
                    { label: t('wms_chart_conflicts'), data: data.conflicts || [], backgroundColor: c.err, borderRadius: 4 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: c.text } } },
                scales: {
                    x: { stacked: true, ticks: { color: c.text }, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { color: c.text }, grid: { color: c.grid } },
                },
            },
        });
    }

    function filterWarehouses(list) {
        const q = (els.whSearch?.value || '').trim().toLowerCase();
        const filter = els.whFilter?.value || 'all';
        return list.filter((w) => {
            const hasIssues = (w.pending ?? 0) > 0 || (w.conflicts ?? 0) > 0;
            if (filter === 'issues' && !hasIssues) return false;
            if (filter === 'clean' && hasIssues) return false;
            if (!q) return true;
            const hay = `${w.name || ''} ${w.code || ''} ${w.store_name || ''}`.toLowerCase();
            return hay.includes(q);
        });
    }

    function renderWarehouses(list) {
        warehousesCache = list;
        const filtered = filterWarehouses(list);
        if (els.badgeWh) els.badgeWh.textContent = String(filtered.length);

        if (!filtered.length) {
            whGrid.innerHTML = `<div class="wh-sync-empty-inline">${esc(t('wms_sync_no_wh'))}</div>`;
            return;
        }

        whGrid.innerHTML = filtered.map((w) => {
            const hasIssues = (w.pending ?? 0) > 0 || (w.conflicts ?? 0) > 0;
            const conn = hasIssues ? 'degraded' : 'online';
            const icon = hasIssues ? 'sync_problem' : 'cloud_done';
            return `
<article class="wh-sync-wh-card wh-sync-wh-card--${conn}">
    <header class="wh-sync-wh-card__head">
        <div>
            <h3>${esc(w.name)}</h3>
            <p class="wh-sync-wh-card__meta">${esc(w.code || '')}${w.store_name ? ` · ${esc(w.store_name)}` : ''}</p>
        </div>
        <span class="wh-sync-conn wh-sync-conn--${conn}">
            <span class="material-icons-round">${icon}</span>
            ${esc(w.status || '')}
        </span>
    </header>
    <div class="wh-sync-wh-card__stats">
        <span><strong>${esc(t('wms_sync_wh_pending'))}</strong> ${w.pending ?? 0}</span>
        <span><strong>${esc(t('wms_sync_wh_conflicts'))}</strong> ${w.conflicts ?? 0}</span>
        <span><strong>${esc(t('wms_sync_wh_offline'))}</strong> ${w.synced_offline ?? 0}</span>
    </div>
</article>`;
        }).join('');
    }

    function actionButtons(row, type) {
        const canManage = !!window.WH_PAGE?.canManage;
        if (!canManage) return '—';
        const retry = `<button type="button" class="wh-btn wh-btn--ghost wh-btn--sm wh-sync-action" data-id="${row.id}" data-entity="${esc(row.entity)}" data-action="retry">${esc(t('wms_sync_retry'))}</button>`;
        const dismiss = `<button type="button" class="wh-btn wh-btn--ghost wh-btn--sm wh-sync-action" data-id="${row.id}" data-entity="${esc(row.entity)}" data-action="dismiss">${esc(t('wms_sync_dismiss'))}</button>`;
        return type === 'conflicts' ? `${retry} ${dismiss}` : retry;
    }

    function bindRowActions(tbody) {
        tbody?.querySelectorAll('.wh-sync-action').forEach((btn) => {
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                const res = await AdminAPI.resolveWmsSyncItem(btn.dataset.id, {
                    entity: btn.dataset.entity,
                    action: btn.dataset.action,
                });
                if (res.status === 'success') {
                    toast(btn.dataset.action === 'dismiss' ? t('wms_sync_resolve_ok') : t('wms_sync_retry_ok'));
                    loadAll();
                } else {
                    toast(res.message || t('wms_sync_action_failed'), 'error');
                    btn.disabled = false;
                }
            });
        });
    }

    function renderTable(tbody, rows, type) {
        if (!tbody) return;
        const emptyMsg = type === 'conflicts' ? t('wms_sync_no_conflicts') : t('wms_sync_queue_empty');
        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="wh-sync-empty-row">${esc(emptyMsg)}</td></tr>`;
            return;
        }

        const labels = {
            date: t('col_date'),
            entity: t('wms_col_entity'),
            reference: t('wms_col_reference'),
            warehouse: t('wms_nav_warehouses'),
            uuid: t('wms_col_uuid'),
            status: t('col_status'),
        };

        tbody.innerHTML = rows.map((r) => `
<tr>
    <td class="wh-sync-nowrap" data-label="${esc(labels.date)}">${formatDate(r.created_at)}</td>
    <td data-label="${esc(labels.entity)}"><span class="wh-sync-entity-badge">${esc(entityLabel(r.entity))}</span></td>
    <td data-label="${esc(labels.reference)}"><code class="wh-sync-code">${esc(r.reference || '—')}</code></td>
    <td data-label="${esc(labels.warehouse)}">${esc(r.warehouse_name || '—')}</td>
    <td data-label="${esc(labels.uuid)}"><small class="wh-sync-uuid">${esc(r.local_uuid || '—')}</small></td>
    <td data-label="${esc(labels.status)}"><span class="cr-badge cr-badge--warn">${esc(r.status)}</span></td>
    <td class="wh-sync-row-actions" data-label="${esc(t('wms_col_actions'))}">${actionButtons(r, type)}</td>
</tr>`).join('');

        bindRowActions(tbody);
    }

    function warehouseId() {
        const v = els.warehouse?.value;
        return v ? parseInt(v, 10) : undefined;
    }

    async function loadAll() {
        setStatsLoading(true);
        hideError();

        try {
            const whId = warehouseId();
            const [mon, wh, pending, conflicts] = await Promise.all([
                AdminAPI.getWmsSyncMonitor(),
                AdminAPI.getWmsSyncWarehouses(),
                AdminAPI.getWmsSyncPending(whId),
                AdminAPI.getWmsSyncConflicts(whId),
            ]);

            setMigrationHint(mon.module_ready !== false);

            if (mon.status === 'success') {
                renderStats(mon.data?.stats || {});
                renderChart(mon.data?.chart || {});
            } else {
                showError(mon.message || t('load_error'));
            }

            if (wh.status === 'success') renderWarehouses(wh.data || []);
            if (pending.status === 'success') renderTable(els.pendingBody, pending.data || [], 'pending');
            if (conflicts.status === 'success') renderTable(els.conflictsBody, conflicts.data || [], 'conflicts');

            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('connection_error'));
        } finally {
            setStatsLoading(false);
        }
    }

    function switchPanel(name) {
        activePanel = name;
        els.tabs.forEach((tab) => {
            const active = tab.dataset.panel === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        Object.entries(els.panels).forEach(([key, panel]) => {
            if (panel) panel.hidden = key !== name;
        });
    }

    els.tabs.forEach((tab) => {
        tab.addEventListener('click', () => switchPanel(tab.dataset.panel || 'warehouses'));
    });

    els.whSearch?.addEventListener('input', () => renderWarehouses(warehousesCache));
    els.whFilter?.addEventListener('change', () => renderWarehouses(warehousesCache));
    els.warehouse?.addEventListener('change', loadAll);
    els.refresh?.addEventListener('click', loadAll);

    document.addEventListener('wh:refresh', loadAll);
    document.addEventListener('store-switched', loadAll);

    loadWarehouseOptions(els.warehouse).then(loadAll);
    refreshTimer = setInterval(loadAll, 60000);
    window.addEventListener('beforeunload', () => clearInterval(refreshTimer));
});
