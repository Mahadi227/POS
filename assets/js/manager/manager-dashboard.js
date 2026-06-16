/**
 * Manager dashboard — KPIs & previews
 */
(() => {
    const $ = (id) => document.getElementById(id);
    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    const LIVE_POLL_MS = 45000;
    let lastFetchAt = null;
    let pollTimer = null;

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function toast(msg, type = 'success') {
        const el = $('mgrToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast mgr-toast show ${type}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
    }

    function showError(msg) {
        const banner = $('mgrError');
        if (!banner) return;
        banner.classList.add('is-visible');
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        $('mgrError')?.classList.remove('is-visible');
    }

    function updateLastUpdated() {
        const el = $('lastUpdated');
        if (!el || !lastFetchAt) return;
        const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        el.textContent = `${t('last_updated')} · ${time}`;
    }

    function setLoading(ids, loading) {
        ids.forEach((id) => $(id)?.classList.toggle('is-loading', loading));
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function statusLabel(status) {
        const map = {
            online: t('register_online'),
            idle: t('register_idle'),
            offline: t('register_offline'),
        };
        return map[status] || status;
    }

    function statusBadgeClass(status) {
        if (status === 'online') return 'mgr-badge--ok';
        if (status === 'idle') return 'mgr-badge--idle';
        return 'mgr-badge--off';
    }

    function updateLiveKpis(d) {
        const online = Number(d.live_registers ?? 0);
        const idle = Number(d.live_registers_idle ?? 0);
        const active = Number(d.live_registers_active ?? online + idle);
        const onShift = Number(d.registers_on_shift ?? 0);
        const tracked = Number(d.live_registers_tracked ?? 0);

        const valEl = $('kpi-live-val');
        const subEl = $('kpi-live-sub');
        const panelCount = $('dashboardLiveCount');

        if (valEl) valEl.textContent = String(active);
        if (subEl) {
            subEl.textContent = t('live_registers_sub', String(online), String(idle), String(onShift));
        }
        if (panelCount) panelCount.textContent = String(active);
    }

    function renderApprovalsPreview(items) {
        const root = $('dashboardApprovals');
        if (!root) return;
        if (!items?.length) {
            root.innerHTML = `<p class="mgr-empty">${escapeHtml(t('no_pending_approvals'))}</p>`;
            root.classList.remove('mgr-list--loading');
            return;
        }
        root.innerHTML = items.map((a) => `
            <div class="mgr-list-item">
                <div>
                    <strong>${escapeHtml(a.type)}</strong>
                    <span class="mgr-muted">${escapeHtml(a.requester_name || '')}</span>
                </div>
                <span>${ManagerAPI.formatCurrency(a.amount)}</span>
            </div>`).join('');
        root.classList.remove('mgr-list--loading');
    }

    function renderRegistersPreview(items) {
        const root = $('dashboardLiveRegisters');
        if (!root) return;
        if (!items?.length) {
            root.innerHTML = `<p class="mgr-empty">${escapeHtml(t('no_active_registers'))}</p>`;
            root.classList.remove('mgr-list--loading');
            return;
        }
        root.innerHTML = items.map((r) => {
            const status = r.status || (r.online ? 'online' : 'offline');
            const activity = r.last_activity_at || r.last_seen || r.last_sale_at || r.opened_at;
            const salesLine = (r.sales_today ?? 0) > 0
                ? t('sales_today_short', String(r.sales_today))
                : '';
            const pageLine = r.current_page
                ? `<span class="mgr-muted">${escapeHtml(r.current_page)}</span>`
                : '';
            return `
            <div class="mgr-list-item mgr-list-item--register">
                <div>
                    <strong>${escapeHtml(r.cashier_name)}</strong>
                    <span class="mgr-muted">${escapeHtml(t('last_activity'))}: ${escapeHtml(ManagerAPI.formatRelative(activity))}</span>
                    ${pageLine}
                    ${salesLine ? `<span class="mgr-muted">${escapeHtml(salesLine)}</span>` : ''}
                </div>
                <span class="mgr-badge ${statusBadgeClass(status)}">${escapeHtml(statusLabel(status))}</span>
            </div>`;
        }).join('');
        root.classList.remove('mgr-list--loading');
    }

    async function refreshLiveBlock() {
        try {
            const res = await ManagerAPI.getLiveRegisters();
            if (res.status !== 'success') return;

            const items = res.data || [];
            const online = items.filter((r) => (r.status || (r.online ? 'online' : 'offline')) === 'online').length;
            const idle = items.filter((r) => (r.status || '') === 'idle').length;
            const active = online + idle;
            const onShift = items.filter((r) => r.shift_open).length;

            updateLiveKpis({
                live_registers: online,
                live_registers_idle: idle,
                live_registers_active: active,
                registers_on_shift: onShift,
                live_registers_tracked: items.length,
            });

            const preview = items.filter((r) => ['online', 'idle'].includes(r.status || (r.online ? 'online' : 'offline')));
            renderRegistersPreview(preview.slice(0, 6));
            lastFetchAt = new Date();
            updateLastUpdated();
        } catch (e) {
            console.warn('Live registers refresh failed', e);
        }
    }

    async function loadDashboard(options = {}) {
        const silent = options.silent === true;
        const kpis = ['kpi-sales', 'kpi-pending', 'kpi-live', 'kpi-alerts'];
        if (!silent) hideError();
        if (!silent) setLoading(kpis, true);

        const approvalsRoot = $('dashboardApprovals');
        const registersRoot = $('dashboardLiveRegisters');
        if (!silent && approvalsRoot) {
            approvalsRoot.innerHTML = escapeHtml(t('loading'));
            approvalsRoot.classList.add('mgr-list--loading');
        }
        if (!silent && registersRoot) {
            registersRoot.innerHTML = escapeHtml(t('loading'));
            registersRoot.classList.add('mgr-list--loading');
        }

        try {
            const res = await ManagerAPI.getDashboard();
            if (res.status !== 'success') throw new Error(res.message);

            const d = res.data || {};
            $('kpi-sales-val').textContent = ManagerAPI.formatCurrency(d.sales_today?.revenue);
            $('kpi-sales-sub').textContent = t('kpi_transactions', String(d.sales_today?.count ?? 0));
            $('kpi-pending-val').textContent = String(d.pending_approvals ?? 0);
            updateLiveKpis(d);
            $('kpi-alerts-val').textContent = String(d.inventory_alerts ?? 0);

            const badge = $('sidebar-pending-approvals');
            if (badge) {
                const n = d.pending_approvals ?? 0;
                badge.textContent = n;
                badge.classList.toggle('hidden', n <= 0);
            }

            renderApprovalsPreview(d.approvals_preview);
            renderRegistersPreview(d.registers_preview);

            lastFetchAt = new Date();
            updateLastUpdated();

            await refreshLiveBlock();
        } catch (e) {
            console.error(e);
            const msg = e.message || t('load_error');
            if (!silent) showError(msg);
            if (!silent) toast(msg, 'error');
            if (!silent && approvalsRoot) {
                approvalsRoot.innerHTML = `<p class="mgr-empty">${escapeHtml(t('load_error'))}</p>`;
                approvalsRoot.classList.remove('mgr-list--loading');
            }
            if (!silent && registersRoot) {
                registersRoot.innerHTML = `<p class="mgr-empty">${escapeHtml(t('load_error'))}</p>`;
                registersRoot.classList.remove('mgr-list--loading');
            }
        }
        if (!silent) setLoading(kpis, false);
    }

    function startLivePolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                refreshLiveBlock();
            }
        }, LIVE_POLL_MS);
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadDashboard();
        startLivePolling();
    });
    document.addEventListener('mgr:refresh', () => loadDashboard());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refreshLiveBlock();
        }
    });
})();
