/**
 * Offline sync monitor — i18n, filters, toasts
 */
(() => {
    const i18n = window.SYNC_I18N || {};
    const locale = window.ADMIN_PAGE?.locale || (window.ADMIN_PAGE?.lang === 'fr' ? 'fr-FR' : 'en-US');

    let activityChart = null;
    let branchesCache = [];
    let lastFetchAt = null;
    let refreshTimer = null;

    const $ = (id) => document.getElementById(id);

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => { str = str.replace('%s', val); });
        return str;
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function escapeAttr(str) {
        return String(str ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function columnLabels() {
        return {
            date: t('col_date'),
            store: t('col_store'),
            action: t('col_action'),
            receipt: t('col_receipt'),
            status: t('col_status'),
            actions: t('col_actions'),
            source: t('col_source'),
            error: t('col_error'),
            reason: t('col_reason'),
        };
    }

    function toast(msg, type = 'success') {
        const el = $('syncToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast sm-toast show${type === 'error' ? ' error' : ''}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
    }

    function connLabel(c) {
        const map = {
            online: 'conn_online',
            offline: 'conn_offline',
            degraded: 'conn_degraded',
            unknown: 'conn_unknown',
        };
        return t(map[c] || c);
    }

    function connIcon(c) {
        const map = {
            online: 'wifi',
            offline: 'cloud_off',
            degraded: 'sync_problem',
            unknown: 'devices',
        };
        return map[c] || 'help_outline';
    }

    function sourceLabel(src) {
        return src === 'queue' ? t('source_queue') : src === 'offline' ? t('source_offline') : src;
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
        document.querySelectorAll('.sm-stat').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function updateHeader() {
        const dateEl = $('syncDate');
        if (dateEl) {
            dateEl.textContent = new Date().toLocaleDateString(locale, {
                weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            });
        }
        if ($('lastUpdated') && lastFetchAt) {
            const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
            $('lastUpdated').textContent = t('last_updated', time);
        }
    }

    function renderStats(stats) {
        const pending = (stats.pending_queue ?? 0) + (stats.pending_offline ?? 0);
        const failed = (stats.failed_queue ?? 0) + (stats.failed_offline ?? 0);
        const conflicts = (stats.conflict_queue ?? 0) + (stats.conflict_offline ?? 0);

        if ($('st-online-branches')) $('st-online-branches').textContent = stats.online_branches ?? 0;
        if ($('st-offline-branches')) $('st-offline-branches').textContent = stats.offline_branches ?? 0;
        if ($('st-pending')) $('st-pending').textContent = pending;
        if ($('st-failed')) $('st-failed').textContent = failed;
        if ($('st-conflicts')) $('st-conflicts').textContent = conflicts;
        if ($('st-synced-today')) $('st-synced-today').textContent = stats.synced_today ?? 0;

        if ($('badge-queue')) $('badge-queue').textContent = pending;
        if ($('badge-failed')) $('badge-failed').textContent = failed;
        if ($('badge-conflicts')) $('badge-conflicts').textContent = conflicts;

        const summary = $('branchesSummary');
        if (summary) {
            summary.textContent = t(
                'branches_summary',
                stats.online_branches ?? 0,
                stats.offline_branches ?? 0,
                stats.degraded_branches ?? 0,
                stats.unknown_branches ?? 0
            );
        }
    }

    function renderActivityChart(chart) {
        const ctx = $('syncActivityChart');
        if (!ctx) return;
        activityChart?.destroy();
        const c = chartColors();
        activityChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chart.labels || [],
                datasets: [
                    {
                        label: t('chart_synced'),
                        data: chart.synced || [],
                        backgroundColor: c.ok,
                        borderRadius: 4,
                    },
                    {
                        label: t('chart_failed_conflicts'),
                        data: chart.failed || [],
                        backgroundColor: c.err,
                        borderRadius: 4,
                    },
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

    function filterBranches(list) {
        const q = ($('branchSearch')?.value || '').trim().toLowerCase();
        const conn = $('connectivityFilter')?.value || 'all';
        return list.filter((b) => {
            if (conn !== 'all' && b.connectivity !== conn) return false;
            if (!q) return true;
            const hay = `${b.name || ''} ${b.code || ''}`.toLowerCase();
            return hay.includes(q);
        });
    }

    function formatLastSeen(b) {
        if (b.connectivity === 'unknown') return t('branch_never_seen');
        if (b.last_seen_at) return AdminAPI.formatDate(b.last_seen_at);
        return t('branch_never_seen');
    }

    function renderBranches(list) {
        branchesCache = list;
        const filtered = filterBranches(list);
        const grid = $('branchesGrid');
        if (!grid) return;

        if ($('badge-branches')) $('badge-branches').textContent = filtered.length;

        if (!filtered.length) {
            grid.innerHTML = `<p class="ad-empty-row">${escapeHtml(t('no_branches'))}</p>`;
            return;
        }

        grid.innerHTML = filtered.map((b) => {
            const conn = b.connectivity || 'unknown';
            const icon = connIcon(conn);
            const lastSync = b.last_sync_at ? AdminAPI.formatDate(b.last_sync_at) : '—';
            const minsHint = b.minutes_since_seen != null
                ? `<span class="sm-mins-hint">${escapeHtml(t('minutes_ago', b.minutes_since_seen))}</span>`
                : '';

            return `
            <div class="sm-branch-card ${conn}">
                <div class="sm-branch-head">
                    <div>
                        <h3>${escapeHtml(b.name)}</h3>
                        <small>${escapeHtml(b.code || '')}</small>
                    </div>
                    <span class="sm-conn-badge ${conn}">
                        <span class="material-icons-round">${icon}</span>
                        ${escapeHtml(connLabel(conn))}
                    </span>
                </div>
                <div class="sm-branch-meta">
                    <span><strong>${escapeHtml(t('branch_last_seen'))}:</strong> ${escapeHtml(formatLastSeen(b))} ${minsHint}</span>
                    <span><strong>${escapeHtml(t('branch_last_sync'))}:</strong> ${escapeHtml(lastSync)}</span>
                    <span><strong>${escapeHtml(t('branch_local_queue'))}:</strong> ${b.pending_local ?? 0} · <strong>${escapeHtml(t('branch_server_queue'))}:</strong> ${b.queue_pending ?? 0}</span>
                    <span><strong>${escapeHtml(t('branch_failures_conflicts'))}:</strong> ${b.queue_failed ?? 0} · ${b.offline_conflicts ?? 0}</span>
                </div>
            </div>`;
        }).join('');
    }

    function actionButtons(item, type) {
        if (item.source === 'queue' && type === 'failed') {
            return `<button type="button" class="inv-btn inv-btn-outline sm-action-btn retry-item" data-source="queue" data-id="${item.id}">${escapeHtml(t('btn_retry'))}</button>`;
        }
        if (type === 'conflicts') {
            return `
                <button type="button" class="inv-btn inv-btn-outline sm-action-btn resolve-item" data-source="${escapeHtml(item.source)}" data-id="${item.id}" data-action="retry">${escapeHtml(t('btn_retry'))}</button>
                <button type="button" class="inv-btn inv-btn-outline sm-action-btn resolve-item" data-source="${escapeHtml(item.source)}" data-id="${item.id}" data-action="dismiss">${escapeHtml(t('btn_dismiss'))}</button>`;
        }
        if (item.source === 'offline' && type === 'failed') {
            return `<button type="button" class="inv-btn inv-btn-outline sm-action-btn resolve-item" data-source="offline" data-id="${item.id}" data-action="retry">${escapeHtml(t('btn_retry'))}</button>`;
        }
        return '—';
    }

    function bindRowActions(tbody) {
        tbody.querySelectorAll('.retry-item').forEach((btn) => {
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                const res = await AdminAPI.retrySyncQueueItem(btn.dataset.id);
                if (res.status === 'success') toast(t('retry_success'));
                else toast(res.message || t('action_failed'), 'error');
                if (res.status === 'success') loadAll();
                else btn.disabled = false;
            });
        });

        tbody.querySelectorAll('.resolve-item').forEach((btn) => {
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                const res = await AdminAPI.resolveSyncConflict(btn.dataset.id, {
                    source: btn.dataset.source,
                    action: btn.dataset.action,
                });
                if (res.status === 'success') toast(t('resolve_success'));
                else toast(res.message || t('action_failed'), 'error');
                if (res.status === 'success') loadAll();
                else btn.disabled = false;
            });
        });
    }

    function renderTable(tbodyId, rows, type) {
        const tbody = $(tbodyId);
        if (!tbody) return;

        const emptyMsg = {
            queue: t('queue_empty'),
            failed: t('no_failures'),
            conflicts: t('no_conflicts'),
        }[type] || t('no_branches');

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${escapeHtml(emptyMsg)}</td></tr>`;
            return;
        }

        const L = columnLabels();
        tbody.innerHTML = rows.map((r) => {
            const actions = type === 'queue'
                ? `<button type="button" class="inv-btn inv-btn-outline sm-action-btn retry-item" data-source="queue" data-id="${r.id}">${escapeHtml(t('btn_retry'))}</button>`
                : actionButtons(r, type);

            if (type === 'queue') {
                return `<tr>
                    <td class="sm-nowrap" data-label="${escapeAttr(L.date)}">${AdminAPI.formatDate(r.created_at)}</td>
                    <td data-label="${escapeAttr(L.store)}">${escapeHtml(r.store_name)}</td>
                    <td data-label="${escapeAttr(L.action)}"><code class="sm-code">${escapeHtml(r.action)}</code></td>
                    <td data-label="${escapeAttr(L.receipt)}">${escapeHtml(r.receipt_no || r.local_uuid || '—')}</td>
                    <td data-label="${escapeAttr(L.status)}"><span class="status-badge pending">${escapeHtml(r.status)}</span></td>
                    <td class="sm-row-actions sm-col-actions" data-label="${escapeAttr(L.actions)}">${actions}</td>
                </tr>`;
            }

            return `<tr>
                <td class="sm-nowrap" data-label="${escapeAttr(L.date)}">${AdminAPI.formatDate(r.created_at)}</td>
                <td data-label="${escapeAttr(L.source)}"><span class="sm-source-badge">${escapeHtml(sourceLabel(r.source))}</span></td>
                <td data-label="${escapeAttr(L.store)}">${escapeHtml(r.store_name)}</td>
                <td data-label="${escapeAttr(L.receipt)}">${escapeHtml(r.receipt_no || r.local_uuid || '—')}</td>
                <td data-label="${escapeAttr(type === 'conflicts' ? L.reason : L.error)}"><small class="sm-error-text">${escapeHtml(r.error_message || '—')}</small></td>
                <td class="sm-row-actions sm-col-actions" data-label="${escapeAttr(L.actions)}">${actions}</td>
            </tr>`;
        }).join('');

        bindRowActions(tbody);
    }

    function showError(msg) {
        const b = $('syncError');
        if (!b) return;
        b.classList.add('is-visible');
        b.querySelector('.ad-error-text').textContent = msg;
    }

    function hideError() {
        $('syncError')?.classList.remove('is-visible');
    }

    async function loadAll() {
        $('refreshSync')?.classList.add('spinning');
        setStatsLoading(true);
        hideError();

        try {
            const [mon, branches, queue, failed, conflicts] = await Promise.all([
                AdminAPI.getSyncMonitor(),
                AdminAPI.getSyncBranches(),
                AdminAPI.getSyncQueue(),
                AdminAPI.getSyncFailed(),
                AdminAPI.getSyncConflicts(),
            ]);

            if (mon.status === 'success') {
                renderStats(mon.data?.stats || {});
                renderActivityChart(mon.data?.chart || {});
            } else {
                showError(mon.message || t('load_error'));
            }

            if (branches.status === 'success') renderBranches(branches.data || []);
            if (queue.status === 'success') renderTable('queueBody', queue.data || [], 'queue');
            if (failed.status === 'success') renderTable('failedBody', failed.data || [], 'failed');
            if (conflicts.status === 'success') renderTable('conflictsBody', conflicts.data || [], 'conflicts');

            lastFetchAt = new Date();
            updateHeader();
        } catch (e) {
            showError(e.message || t('connection_error'));
        } finally {
            setStatsLoading(false);
            $('refreshSync')?.classList.remove('spinning');
        }
    }

    function initTabs() {
        document.querySelectorAll('.sm-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.sm-tab').forEach((t) => t.classList.remove('active'));
                tab.classList.add('active');
                document.querySelectorAll('.sm-panel').forEach((p) => p.classList.add('hidden'));
                $(`panel-${tab.dataset.panel}`)?.classList.remove('hidden');
            });
        });
    }

    function initFilters() {
        $('branchSearch')?.addEventListener('input', () => renderBranches(branchesCache));
        $('connectivityFilter')?.addEventListener('change', () => renderBranches(branchesCache));
    }

    function initTheme() {
        const themeBtn = $('theme-toggle');
        const saved = localStorage.getItem('admin-theme');
        if (saved) {
            document.documentElement.setAttribute('data-theme', saved);
            const icon = themeBtn?.querySelector('.material-icons-round');
            if (icon) icon.textContent = saved === 'dark' ? 'light_mode' : 'dark_mode';
        }
        themeBtn?.addEventListener('click', () => {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-theme') === 'dark';
            html.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('admin-theme', isDark ? 'light' : 'dark');
            const icon = themeBtn.querySelector('.material-icons-round');
            if (icon) icon.textContent = isDark ? 'dark_mode' : 'light_mode';
            loadAll();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        $('refreshSync')?.addEventListener('click', loadAll);
        document.addEventListener('store-switched', loadAll);
        initTabs();
        initFilters();
        initTheme();
        updateHeader();
        loadAll();
        refreshTimer = setInterval(loadAll, 60000);
        window.addEventListener('beforeunload', () => clearInterval(refreshTimer));
    });
})();
