/**
 * Surveillance synchronisation hors ligne
 */
document.addEventListener('DOMContentLoaded', () => {
    let activityChart = null;

    const $ = (id) => document.getElementById(id);

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function connLabel(c) {
        const map = { online: 'En ligne', offline: 'Hors ligne', degraded: 'Dégradé' };
        return map[c] || c;
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

    function renderStats(stats) {
        $('st-offline-branches') && ($('st-offline-branches').textContent = stats.offline_branches ?? 0);
        const pending = (stats.pending_queue ?? 0) + (stats.pending_offline ?? 0);
        $('st-pending') && ($('st-pending').textContent = pending);
        const failed = (stats.failed_queue ?? 0) + (stats.failed_offline ?? 0);
        $('st-failed') && ($('st-failed').textContent = failed);
        const conflicts = (stats.conflict_queue ?? 0) + (stats.conflict_offline ?? 0);
        $('st-conflicts') && ($('st-conflicts').textContent = conflicts);
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
                        label: 'Synchronisées',
                        data: chart.synced || [],
                        backgroundColor: c.ok,
                        borderRadius: 4,
                    },
                    {
                        label: 'Échecs / conflits',
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

    function renderBranches(list) {
        const grid = $('branchesGrid');
        if (!list.length) {
            grid.innerHTML = '<p class="ad-empty-row">Aucune succursale</p>';
            return;
        }
        grid.innerHTML = list.map((b) => {
            const conn = b.connectivity || 'offline';
            const icon = conn === 'online' ? 'wifi' : conn === 'degraded' ? 'sync_problem' : 'cloud_off';
            const lastSeen = b.last_seen_at
                ? AdminAPI.formatDate(b.last_seen_at)
                : 'Jamais signalé';
            const lastSync = b.last_sync_at
                ? AdminAPI.formatDate(b.last_sync_at)
                : '—';
            return `
            <div class="sm-branch-card ${conn}">
                <div class="sm-branch-head">
                    <div>
                        <h3>${escapeHtml(b.name)}</h3>
                        <small>${escapeHtml(b.code || '')}</small>
                    </div>
                    <span class="sm-conn-badge ${conn}">
                        <span class="material-icons-round" style="font-size:14px;">${icon}</span>
                        ${connLabel(conn)}
                    </span>
                </div>
                <div class="sm-branch-meta">
                    <span>Dernière activité : ${escapeHtml(lastSeen)}</span>
                    <span>Dernière sync : ${escapeHtml(lastSync)}</span>
                    <span>File locale : ${b.pending_local ?? 0} · Queue : ${b.queue_pending ?? 0}</span>
                    <span>Échecs : ${b.queue_failed ?? 0} · Conflits : ${b.offline_conflicts ?? 0}</span>
                </div>
            </div>`;
        }).join('');
    }

    function actionButtons(item, type) {
        if (item.source === 'queue' && type === 'failed') {
            return `<button type="button" class="as-btn retry-item" data-source="queue" data-id="${item.id}">Réessayer</button>`;
        }
        if (type === 'conflicts') {
            return `
                <button type="button" class="as-btn resolve-item" data-source="${escapeHtml(item.source)}" data-id="${item.id}" data-action="retry">Réessayer</button>
                <button type="button" class="as-btn resolve-item" data-source="${escapeHtml(item.source)}" data-id="${item.id}" data-action="dismiss">Ignorer</button>`;
        }
        if (item.source === 'offline' && type === 'failed') {
            return `<button type="button" class="as-btn resolve-item" data-source="offline" data-id="${item.id}" data-action="retry">Réessayer</button>`;
        }
        return '—';
    }

    function renderTable(tbodyId, rows, type) {
        const tbody = $(tbodyId);
        if (!rows.length) {
            const msg = type === 'queue' ? 'File vide — aucune opération en attente'
                : type === 'failed' ? 'Aucun échec de synchronisation'
                : 'Aucun conflit';
            tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${msg}</td></tr>`;
            return;
        }
        tbody.innerHTML = rows.map((r) => {
            const actions = type === 'queue'
                ? `<button type="button" class="as-btn retry-item" data-source="queue" data-id="${r.id}">Réessayer</button>`
                : actionButtons(r, type);
            const cols = type === 'queue'
                ? `
                <td style="white-space:nowrap;">${AdminAPI.formatDate(r.created_at)}</td>
                <td>${escapeHtml(r.store_name)}</td>
                <td><code>${escapeHtml(r.action)}</code></td>
                <td>${escapeHtml(r.receipt_no || r.local_uuid || '—')}</td>
                <td><span class="status-badge pending">${escapeHtml(r.status)}</span></td>
                <td class="sm-row-actions">${actions}</td>`
                : `
                <td style="white-space:nowrap;">${AdminAPI.formatDate(r.created_at)}</td>
                <td><span class="sm-source-badge">${escapeHtml(r.source)}</span></td>
                <td>${escapeHtml(r.store_name)}</td>
                <td>${escapeHtml(r.receipt_no || r.local_uuid || '—')}</td>
                <td><small>${escapeHtml(r.error_message || '—')}</small></td>
                <td class="sm-row-actions">${actions}</td>`;
            return `<tr>${cols}</tr>`;
        }).join('');

        tbody.querySelectorAll('.retry-item').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const res = await AdminAPI.retrySyncQueueItem(btn.dataset.id);
                alert(res.message || (res.status === 'success' ? 'OK' : 'Erreur'));
                if (res.status === 'success') loadAll();
            });
        });

        tbody.querySelectorAll('.resolve-item').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const res = await AdminAPI.resolveSyncConflict(btn.dataset.id, {
                    source: btn.dataset.source,
                    action: btn.dataset.action,
                });
                alert(res.message || (res.status === 'success' ? 'OK' : 'Erreur'));
                if (res.status === 'success') loadAll();
            });
        });
    }

    function showError(msg) {
        const b = $('syncError');
        if (!b) return;
        b.classList.add('is-visible');
        b.querySelector('.ad-error-text').textContent = msg;
    }

    async function loadAll() {
        $('refreshSync')?.classList.add('spinning');
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
                showError(mon.message || 'Erreur chargement');
            }

            if (branches.status === 'success') renderBranches(branches.data || []);
            if (queue.status === 'success') renderTable('queueBody', queue.data || [], 'queue');
            if (failed.status === 'success') renderTable('failedBody', failed.data || [], 'failed');
            if (conflicts.status === 'success') renderTable('conflictsBody', conflicts.data || [], 'conflicts');
        } catch (e) {
            showError(e.message || 'Erreur réseau');
        } finally {
            $('refreshSync')?.classList.remove('spinning');
        }
    }

    document.querySelectorAll('.sm-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.sm-tab').forEach((t) => t.classList.remove('active'));
            tab.classList.add('active');
            document.querySelectorAll('.sm-panel').forEach((p) => p.classList.add('hidden'));
            $(`panel-${tab.dataset.panel}`)?.classList.remove('hidden');
        });
    });

    $('refreshSync')?.addEventListener('click', loadAll);
    document.addEventListener('store-switched', loadAll);

    const themeBtn = $('theme-toggle');
    const saved = localStorage.getItem('admin-theme');
    if (saved) {
        document.documentElement.setAttribute('data-theme', saved);
        themeBtn?.querySelector('.material-icons-round')?.replaceChildren?.();
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

    loadAll();
    setInterval(loadAll, 60000);
});
