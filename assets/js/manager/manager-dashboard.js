/**
 * Manager dashboard — KPIs & previews
 */
(() => {
    const $ = (id) => document.getElementById(id);

    function toast(msg, type = 'success') {
        const el = $('mgrToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast mgr-toast show ${type}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
    }

    function setLoading(ids, loading) {
        ids.forEach((id) => $(id)?.classList.toggle('is-loading', loading));
    }

    function renderApprovalsPreview(items) {
        const root = $('dashboardApprovals');
        if (!root) return;
        if (!items?.length) {
            root.innerHTML = '<p class="mgr-empty">Aucune approbation en attente</p>';
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
            root.innerHTML = '<p class="mgr-empty">Aucune caisse active</p>';
            root.classList.remove('mgr-list--loading');
            return;
        }
        root.innerHTML = items.map((r) => `
            <div class="mgr-list-item">
                <div>
                    <strong>${escapeHtml(r.cashier_name)}</strong>
                    <span class="mgr-muted">${r.online ? 'En ligne' : 'Hors ligne'}</span>
                </div>
                <span class="mgr-badge mgr-badge--ok">Actif</span>
            </div>`).join('');
        root.classList.remove('mgr-list--loading');
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    async function loadDashboard() {
        const kpis = ['kpi-sales', 'kpi-pending', 'kpi-live', 'kpi-alerts'];
        setLoading(kpis, true);
        try {
            const res = await ManagerAPI.getDashboard();
            if (res.status !== 'success') throw new Error(res.message);

            const d = res.data || {};
            $('kpi-sales-val').textContent = ManagerAPI.formatCurrency(d.sales_today?.revenue);
            $('kpi-sales-sub').textContent = `${d.sales_today?.count ?? 0} transaction(s)`;
            $('kpi-pending-val').textContent = String(d.pending_approvals ?? 0);
            $('kpi-live-val').textContent = String(d.live_registers ?? 0);
            $('kpi-alerts-val').textContent = String(d.inventory_alerts ?? 0);

            const badge = $('sidebar-pending-approvals');
            if (badge) {
                const n = d.pending_approvals ?? 0;
                badge.textContent = n;
                badge.classList.toggle('hidden', n <= 0);
            }

            renderApprovalsPreview(d.approvals_preview);
            renderRegistersPreview(d.registers_preview);
        } catch (e) {
            console.error(e);
            toast(e.message || 'Erreur chargement', 'error');
        }
        setLoading(kpis, false);
    }

    document.addEventListener('DOMContentLoaded', loadDashboard);
    document.addEventListener('mgr:refresh', loadDashboard);
})();
