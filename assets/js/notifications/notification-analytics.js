/**
 * Notification analytics dashboard
 */
(() => {
    const t = (k) => (window.NOTIF_I18N || {})[k] || k;

    async function load() {
        const res = await NotificationAPI.analytics();
        if (res.status !== 'success') return;
        const d = res.data;
        const grid = document.getElementById('notifStats');
        if (grid) {
            grid.innerHTML = [
                ['total_sent', d.total_sent],
                ['unread', d.unread],
                ['critical_alerts', d.critical_alerts],
                ['failed_deliveries', d.failed_deliveries],
            ].map(([k, v]) => `<div class="notif-stat"><span>${t(k)}</span><strong>${v}</strong></div>`).join('');
        }
        if (window.Chart) {
            const dayCtx = document.getElementById('notifChartDay');
            if (dayCtx) {
                new Chart(dayCtx, {
                    type: 'line',
                    data: {
                        labels: (d.by_day || []).map((r) => r.day),
                        datasets: [{ label: t('total_sent'), data: (d.by_day || []).map((r) => r.cnt), borderColor: '#2563eb', tension: 0.3 }],
                    },
                    options: { responsive: true, plugins: { legend: { display: false } } },
                });
            }
            const catCtx = document.getElementById('notifChartCategory');
            if (catCtx) {
                new Chart(catCtx, {
                    type: 'doughnut',
                    data: {
                        labels: (d.by_category || []).map((r) => r.category_slug),
                        datasets: [{ data: (d.by_category || []).map((r) => r.cnt), backgroundColor: ['#2563eb','#f59e0b','#10b981','#ef4444','#8b5cf6'] }],
                    },
                    options: { responsive: true },
                });
            }
        }
    }
    document.addEventListener('DOMContentLoaded', load);
})();
