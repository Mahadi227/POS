/**
 * Cash register analytics
 */
document.addEventListener('DOMContentLoaded', () => {
    const page = document.body.dataset.crPage;
    if (page !== 'analytics') return;

    const { t, esc, money, showError, hideError, updateLastUpdated } = CashRegistersUI;
    let charts = [];

    async function load() {
        hideError();
        try {
            const period = document.getElementById('crAnalyticsPeriod')?.value || 'month';
            const res = await AdminAPI.getCashRegisterAnalytics(period);
            if (res.status !== 'success') throw new Error(res.message);
            const d = res.data || {};
            charts.forEach((c) => c.destroy());
            charts = [];

            const dailyCtx = document.getElementById('crDailyChart');
            if (dailyCtx && d.daily_collection?.length) {
                charts.push(new Chart(dailyCtx, {
                    type: 'line',
                    data: {
                        labels: d.daily_collection.map((x) => x.day),
                        datasets: [{ label: t('cr_stat_cash'), data: d.daily_collection.map((x) => x.amount), borderColor: '#2563eb', tension: 0.25 }],
                    },
                    options: { responsive: true },
                }));
            }

            const branchCtx = document.getElementById('crBranchChart');
            if (branchCtx && d.branch_comparison?.length) {
                charts.push(new Chart(branchCtx, {
                    type: 'bar',
                    data: {
                        labels: d.branch_comparison.map((x) => x.name),
                        datasets: [{ label: t('cr_stat_cash_balance'), data: d.branch_comparison.map((x) => x.balance), backgroundColor: '#7c3aed' }],
                    },
                    options: { responsive: true, indexAxis: 'y' },
                }));
            }

            const cashierRoot = document.getElementById('crCashierPerf');
            if (cashierRoot) {
                cashierRoot.innerHTML = (d.cashier_performance || []).map((c) => `
                    <div class="cr-status-item"><div><strong>${esc(c.name)}</strong><span class="cr-muted">${esc(String(c.sessions))} sessions</span></div><span>${esc(money(c.revenue))}</span></div>`).join('') || `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`;
            }
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    document.getElementById('crAnalyticsPeriod')?.addEventListener('change', load);
    load();
    document.addEventListener('cr:refresh', load);
});
