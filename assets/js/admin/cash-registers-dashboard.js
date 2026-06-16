/**
 * Cash register admin dashboard
 */
document.addEventListener('DOMContentLoaded', () => {
    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = CashRegistersUI;
    let collectionChart;
    let performanceChart;

    const els = {
        total: document.getElementById('crTotalRegisters'),
        open: document.getElementById('crOpenRegisters'),
        closed: document.getElementById('crClosedRegisters'),
        balance: document.getElementById('crCashBalance'),
        expected: document.getElementById('crExpectedCash'),
        difference: document.getElementById('crCashDifference'),
        sales: document.getElementById('crSalesToday'),
        cash: document.getElementById('crCashCollected'),
        mobile: document.getElementById('crMobileCollected'),
        card: document.getElementById('crCardCollected'),
        pending: document.getElementById('crPendingRecon'),
        cashiers: document.getElementById('crActiveCashiers'),
        statusRoot: document.getElementById('crStatusList'),
        activityRoot: document.getElementById('crActivityList'),
        cards: document.querySelectorAll('.cr-kpi-card'),
    };

    function setLoading(on) {
        els.cards.forEach((c) => c.classList.toggle('is-loading', on));
    }

    function renderStatus(items) {
        if (!els.statusRoot) return;
        if (!items?.length) {
            els.statusRoot.innerHTML = `<p class="cr-empty">${esc(t('cr_no_registers'))}</p>`;
            return;
        }
        els.statusRoot.innerHTML = items.map((r) => `
            <div class="cr-status-item">
                <div>
                    <strong>${esc(r.name)}</strong>
                    <span class="cr-muted">${esc(r.store_name)} · ${esc(r.code)}</span>
                </div>
                <span class="cr-badge cr-badge--${r.session_status === 'open' ? 'ok' : 'off'}">${esc(r.session_status === 'open' ? t('cr_session_open') : t('cr_session_closed'))}</span>
                <span>${esc(money(r.balance))}</span>
            </div>`).join('');
    }

    function renderActivity(items) {
        if (!els.activityRoot) return;
        if (!items?.length) {
            els.activityRoot.innerHTML = `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`;
            return;
        }
        els.activityRoot.innerHTML = items.map((a) => `
            <div class="cr-activity-item">
                <span class="material-icons-round">history</span>
                <div>
                    <strong>${esc(a.action)}</strong>
                    <span class="cr-muted">${esc(a.user_name || '')} · ${esc(AdminAPI.formatDate(a.created_at))}</span>
                </div>
            </div>`).join('');
    }

    function renderCharts(data) {
        const collCtx = document.getElementById('crCollectionChart');
        const perfCtx = document.getElementById('crPerformanceChart');
        if (collCtx && window.Chart) {
            const labels = (data.collection_chart || []).map((h) => `${String(h.hour).padStart(2, '0')}:00`);
            const values = (data.collection_chart || []).map((h) => h.amount);
            collectionChart?.destroy();
            collectionChart = new Chart(collCtx, {
                type: 'line',
                data: { labels, datasets: [{ label: t('cr_stat_cash'), data: values, borderColor: '#2563eb', tension: 0.3, fill: true, backgroundColor: 'rgba(37,99,235,0.08)' }] },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
            });
        }
        if (perfCtx && window.Chart) {
            const perf = data.performance_chart || [];
            performanceChart?.destroy();
            performanceChart = new Chart(perfCtx, {
                type: 'bar',
                data: { labels: perf.map((p) => p.label), datasets: [{ label: t('cr_stat_sales_today'), data: perf.map((p) => p.value), backgroundColor: '#7c3aed' }] },
                options: { responsive: true, plugins: { legend: { display: false } } },
            });
        }
    }

    async function load(silent = false) {
        if (!silent) { setLoading(true); hideError(); }
        try {
            const res = await AdminAPI.getCashRegisterDashboard();
            if (res.status !== 'success') throw new Error(res.message);
            const d = res.data || {};
            setMigrationHint(d.module_ready);
            const s = d.summary || {};
            if (els.total) els.total.textContent = String(s.total_registers ?? 0);
            if (els.open) els.open.textContent = String(s.open_registers ?? 0);
            if (els.closed) els.closed.textContent = String(s.closed_registers ?? 0);
            if (els.balance) els.balance.textContent = money(s.current_cash_balance);
            if (els.expected) els.expected.textContent = money(s.expected_cash);
            if (els.difference) els.difference.textContent = money(s.cash_difference);
            if (els.sales) els.sales.textContent = money(s.sales_today);
            if (els.cash) els.cash.textContent = money(s.cash_collected);
            if (els.mobile) els.mobile.textContent = money(s.mobile_collected);
            if (els.card) els.card.textContent = money(s.card_collected);
            if (els.pending) els.pending.textContent = String(s.pending_reconciliation ?? 0);
            if (els.cashiers) els.cashiers.textContent = String(s.active_cashiers ?? 0);
            renderStatus(d.register_status);
            renderActivity(d.recent_activities);
            renderCharts(d);
            updateLastUpdated();
            await CashRegisterOffline.sync();
        } catch (e) {
            console.error(e);
            if (!silent) showError(e.message || t('load_error'));
        }
        if (!silent) setLoading(false);
    }

    load();
    document.addEventListener('cr:refresh', () => load(true));
    setInterval(() => { if (document.visibilityState === 'visible') load(true); }, 60000);
});
