document.addEventListener('DOMContentLoaded', () => {
    const { t, esc, money, formatDate, updateLastUpdated } = EcommerceUI;
    let chart;

    async function load() {
        const data = await AdminAPI.getEcommerceDashboard();
        if (data.status !== 'ok') return;

        const map = {
            ecomKpiOnline: data.online_products,
            ecomKpiOrdersToday: data.web_orders_today,
            ecomKpiRevenueToday: money(data.web_revenue_today),
            ecomKpiOrdersTotal: data.web_orders_total,
            ecomKpiRevenueTotal: money(data.web_revenue_total),
            ecomKpiAccounts: data.storefront_accounts,
            ecomKpiBrands: data.brands,
            ecomKpiBlog: data.blog_posts,
        };
        Object.entries(map).forEach(([id, val]) => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = val;
                el.classList.remove('is-loading');
            }
        });

        document.getElementById('ecomKpiTotalProducts')?.remove();
        const totalEl = document.createElement('strong');
        totalEl.id = 'ecomKpiTotalProducts';
        totalEl.hidden = true;

        const orders = await AdminAPI.getEcommerceOrders({ limit: 5 });
        const tbody = document.querySelector('#ecomRecentOrders tbody');
        if (tbody && orders.status === 'ok') {
            const items = orders.items || [];
            tbody.innerHTML = items.length
                ? items.map((o) => `<tr>
                    <td>${esc(o.receipt_no)}</td>
                    <td>${formatDate(o.created_at)}</td>
                    <td>${money(o.total)}</td>
                    <td><span class="ecom-badge">${esc(o.status)}</span></td>
                </tr>`).join('')
                : `<tr><td colspan="4">${esc(t('ecom_no_orders'))}</td></tr>`;
        }

        renderChart(data);
        updateLastUpdated();
    }

    function renderChart(stats) {
        const canvas = document.getElementById('ecomOrdersChart');
        if (!canvas || typeof Chart === 'undefined') return;
        if (chart) chart.destroy();
        chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: [t('ecom_kpi_orders_today'), t('ecom_kpi_orders_total')],
                datasets: [{
                    data: [stats.web_orders_today || 0, Math.max(0, (stats.web_orders_total || 0) - (stats.web_orders_today || 0))],
                    backgroundColor: ['#e11d48', '#fda4af'],
                }],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
    }

    load();
    document.addEventListener('ecom:refresh', load);
});
