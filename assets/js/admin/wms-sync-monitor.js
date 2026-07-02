/**

 * WMS offline sync monitor

 */

document.addEventListener('DOMContentLoaded', () => {

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, loadWarehouseOptions } = WmsUI;



    let chart = null;

    let warehousesCache = [];

    let refreshTimer = null;



    const ENTITY_KEYS = {

        receipt: 'wms_sync_entity_receipt',

        transfer: 'wms_sync_entity_transfer',

        movement: 'wms_sync_entity_movement',

    };



    function entityLabel(entity) {

        return t(ENTITY_KEYS[entity] || entity) || entity;

    }



    function toast(msg, type = 'success') {

        const el = document.getElementById('wmsSyncToast');

        if (!el) return;

        el.textContent = msg;

        el.className = `inv-toast sm-toast show${type === 'error' ? ' error' : ''}`;

        clearTimeout(el._t);

        el._t = setTimeout(() => el.classList.remove('show'), 3200);

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

        document.querySelectorAll('.sm-stat').forEach((el) => el.classList.toggle('is-loading', loading));

    }



    function formatDate(value) {

        if (!value) return '—';

        try {

            return new Date(value).toLocaleString(window.ADMIN_CONFIG?.locale || 'fr-FR', {

                year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',

            });

        } catch (e) {

            return value;

        }

    }



    function renderStats(stats) {

        const pending = stats.pending ?? 0;

        const conflicts = stats.conflicts ?? 0;

        document.getElementById('wmsStPending').textContent = pending;

        document.getElementById('wmsStConflicts').textContent = conflicts;

        document.getElementById('wmsStSyncedToday').textContent = stats.synced_today ?? 0;

        document.getElementById('wmsStWhIssues').textContent = stats.warehouses_with_issues ?? 0;

        document.getElementById('wmsStTotalWh').textContent = stats.total_warehouses ?? 0;

        document.getElementById('wmsBadgePending').textContent = pending;

        document.getElementById('wmsBadgeConflicts').textContent = conflicts;



        const summary = document.getElementById('wmsSyncSummary');

        if (summary) {

            summary.textContent = t(

                'wms_sync_summary',

                pending,

                conflicts,

                stats.synced_today ?? 0

            );

        }

    }



    function renderChart(data) {

        const ctx = document.getElementById('wmsSyncChart');

        if (!ctx || typeof Chart === 'undefined') return;

        chart?.destroy();

        const c = chartColors();

        chart = new Chart(ctx, {

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

        const q = (document.getElementById('wmsSyncWhSearch')?.value || '').trim().toLowerCase();

        const filter = document.getElementById('wmsSyncWhFilter')?.value || 'all';

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

        const grid = document.getElementById('wmsSyncWhGrid');

        if (!grid) return;



        document.getElementById('wmsBadgeWh').textContent = filtered.length;



        if (!filtered.length) {

            grid.innerHTML = `<p class="ad-empty-row">${esc(t('wms_sync_no_wh'))}</p>`;

            return;

        }



        grid.innerHTML = filtered.map((w) => {

            const hasIssues = (w.pending ?? 0) > 0 || (w.conflicts ?? 0) > 0;

            const conn = hasIssues ? 'degraded' : 'online';

            const icon = hasIssues ? 'sync_problem' : 'cloud_done';

            return `

            <div class="sm-branch-card ${conn}">

                <div class="sm-branch-head">

                    <div>

                        <h3>${esc(w.name)}</h3>

                        <small>${esc(w.code || '')}${w.store_name ? ` · ${esc(w.store_name)}` : ''}</small>

                    </div>

                    <span class="sm-conn-badge ${conn}">

                        <span class="material-icons-round">${icon}</span>

                        ${esc(w.status || '')}

                    </span>

                </div>

                <div class="sm-branch-meta">

                    <span><strong>${esc(t('wms_sync_wh_pending'))}:</strong> ${w.pending ?? 0}</span>

                    <span><strong>${esc(t('wms_sync_wh_conflicts'))}:</strong> ${w.conflicts ?? 0}</span>

                    <span><strong>${esc(t('wms_sync_wh_offline'))}:</strong> ${w.synced_offline ?? 0}</span>

                </div>

            </div>`;

        }).join('');

    }



    function actionButtons(row, type) {

        const retry = `<button type="button" class="cr-btn cr-btn--ghost sm-action-btn wms-sync-resolve" data-id="${row.id}" data-entity="${esc(row.entity)}" data-action="retry">${esc(t('wms_sync_retry'))}</button>`;

        const dismiss = `<button type="button" class="cr-btn cr-btn--ghost sm-action-btn wms-sync-resolve" data-id="${row.id}" data-entity="${esc(row.entity)}" data-action="dismiss">${esc(t('wms_sync_dismiss'))}</button>`;

        return type === 'conflicts' ? `${retry} ${dismiss}` : retry;

    }



    function renderTable(tbodyId, rows, type) {

        const tbody = document.getElementById(tbodyId);

        if (!tbody) return;



        const emptyMsg = type === 'conflicts' ? t('wms_sync_no_conflicts') : t('wms_sync_queue_empty');

        if (!rows.length) {

            tbody.innerHTML = `<tr><td colspan="7" class="ad-empty-row">${esc(emptyMsg)}</td></tr>`;

            return;

        }



        tbody.innerHTML = rows.map((r) => `

            <tr>

                <td class="sm-nowrap">${formatDate(r.created_at)}</td>

                <td><span class="sm-source-badge">${esc(entityLabel(r.entity))}</span></td>

                <td><code class="sm-code">${esc(r.reference || '—')}</code></td>

                <td>${esc(r.warehouse_name || '—')}</td>

                <td><small class="sm-error-text">${esc(r.local_uuid || '—')}</small></td>

                <td><span class="status-badge pending">${esc(r.status)}</span></td>

                <td class="sm-row-actions">${actionButtons(r, type)}</td>

            </tr>

        `).join('');



        tbody.querySelectorAll('.wms-sync-resolve').forEach((btn) => {

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



    function warehouseId() {

        const v = document.getElementById('wmsSyncWarehouse')?.value;

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



            const ready = mon.module_ready !== false;

            setMigrationHint(ready);



            if (mon.status === 'success') {

                renderStats(mon.data?.stats || {});

                renderChart(mon.data?.chart || {});

            } else {

                showError(mon.message || t('load_error'));

            }



            if (wh.status === 'success') renderWarehouses(wh.data || []);

            if (pending.status === 'success') renderTable('wmsPendingBody', pending.data || [], 'pending');

            if (conflicts.status === 'success') renderTable('wmsConflictsBody', conflicts.data || [], 'conflicts');



            updateLastUpdated();

        } catch (e) {

            showError(e.message || t('connection_error'));

        } finally {

            setStatsLoading(false);

        }

    }



    function initTabs() {

        document.querySelectorAll('.sm-tab').forEach((tab) => {

            tab.addEventListener('click', () => {

                document.querySelectorAll('.sm-tab').forEach((el) => el.classList.remove('active'));

                tab.classList.add('active');

                document.querySelectorAll('.sm-panel').forEach((p) => p.classList.add('hidden'));

                document.getElementById(`panel-${tab.dataset.panel}`)?.classList.remove('hidden');

            });

        });

    }



    loadWarehouseOptions(document.getElementById('wmsSyncWarehouse'));

    initTabs();



    document.getElementById('wmsSyncWhSearch')?.addEventListener('input', () => renderWarehouses(warehousesCache));

    document.getElementById('wmsSyncWhFilter')?.addEventListener('change', () => renderWarehouses(warehousesCache));

    document.getElementById('wmsSyncWarehouse')?.addEventListener('change', loadAll);

    document.addEventListener('wms:refresh', loadAll);

    document.addEventListener('store-switched', loadAll);



    loadAll();

    refreshTimer = setInterval(loadAll, 60000);

    window.addEventListener('beforeunload', () => clearInterval(refreshTimer));

});

