/**
 * Tableau de bord admin — données dynamiques API + graphiques Chart.js
 */
document.addEventListener('DOMContentLoaded', () => {
    let revenueChart = null;
    let categoryChart = null;

    const els = {
        refreshBtn: document.getElementById('refreshDashboard'),
        errorBanner: document.getElementById('dashboardError'),
        revenueToday: document.getElementById('revenue-today-val'),
        revenueMonth: document.getElementById('revenue-month-val'),
        salesToday: document.getElementById('sales-today-val'),
        lowStock: document.getElementById('low-stock-val'),
        customers: document.getElementById('active-customers-val'),
        revenueTrend: document.getElementById('revenue-trend'),
        salesTrend: document.getElementById('sales-trend'),
        txList: document.getElementById('recent-transactions-list'),
        topProducts: document.getElementById('top-products-list'),
        currentDate: document.getElementById('current-date'),
        storePill: document.getElementById('store-pill'),
        sidebarBadge: document.getElementById('sidebar-low-stock-badge'),
        userName: document.getElementById('sidebar-user-name'),
        userRole: document.getElementById('sidebar-user-role'),
        userAvatar: document.getElementById('sidebar-user-avatar'),
    };

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function setLoading(loading) {
        document.querySelectorAll('.stat-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
        els.refreshBtn?.classList.toggle('spinning', loading);
    }

    function showError(msg) {
        if (!els.errorBanner) return;
        els.errorBanner.classList.add('is-visible');
        els.errorBanner.querySelector('.ad-error-text').textContent = msg;
    }

    function hideError() {
        els.errorBanner?.classList.remove('is-visible');
    }

    function destroyCharts() {
        revenueChart?.destroy();
        categoryChart?.destroy();
        revenueChart = null;
        categoryChart = null;
    }

    function chartColors() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: isDark ? '#374151' : '#e5e7eb',
            text: isDark ? '#9ca3af' : '#6b7280',
            primary: '#2563eb',
            fill: isDark ? 'rgba(37, 99, 235, 0.2)' : 'rgba(37, 99, 235, 0.1)',
        };
    }

    function renderRevenueChart(labels, revenues) {
        const ctx = document.getElementById('revenueChart');
        if (!ctx) return;
        const c = chartColors();
        revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: `Revenus (${AdminAPI.getCurrencySymbol()})`,
                    data: revenues,
                    borderColor: c.primary,
                    backgroundColor: c.fill,
                    tension: 0.35,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: c.grid },
                        ticks: {
                            color: c.text,
                            callback: (v) => Number(v).toLocaleString('fr-FR'),
                        },
                    },
                    x: { grid: { display: false }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderCategoryChart(labels, revenues) {
        const ctx = document.getElementById('categoryChart');
        if (!ctx) return;
        const c = chartColors();
        const hasData = revenues.some((v) => v > 0);

        categoryChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: hasData ? revenues : [1],
                    backgroundColor: hasData
                        ? ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#ec4899', '#84cc16']
                        : ['#e5e7eb'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 16, color: c.text },
                    },
                },
                cutout: '68%',
            },
        });
    }

    function renderTransactions(list) {
        if (!els.txList) return;
        if (!list?.length) {
            els.txList.innerHTML =
                '<tr><td colspan="6" class="ad-empty-row">Aucune transaction récente</td></tr>';
            return;
        }

        els.txList.innerHTML = list
            .map((tx) => {
                const receipt = tx.receipt_no || tx.receipt_number || `#${tx.id}`;
                const status =
                    tx.status === 'completed'
                        ? '<span class="status-badge success">Complété</span>'
                        : `<span class="status-badge warning">${escapeHtml(tx.status)}</span>`;
                return `
                    <tr>
                        <td><span class="receipt-link">${escapeHtml(receipt.length > 14 ? receipt.substring(0, 14) + '…' : receipt)}</span></td>
                        <td>${escapeHtml(tx.customer_name || 'Client passage')}</td>
                        <td style="color:var(--text-secondary)">${escapeHtml(AdminAPI.formatDate(tx.created_at || tx.sale_date))}</td>
                        <td style="font-weight:600">${escapeHtml(AdminAPI.formatCurrency(tx.total ?? tx.total_amount))}</td>
                        <td>${status}</td>
                        <td>${escapeHtml(AdminAPI.paymentLabel(tx.payment_method))}</td>
                    </tr>`;
            })
            .join('');
    }

    function renderTopProducts(list) {
        if (!els.topProducts) return;
        if (!list?.length) {
            els.topProducts.innerHTML =
                '<li class="item"><div class="item-details"><p>Aucune vente sur les 30 derniers jours</p></div></li>';
            return;
        }

        els.topProducts.innerHTML = list
            .map((prod, i) => {
                const rankClass = i < 3 ? `rank-${i + 1}` : '';
                return `
                    <li class="item">
                        <div class="item-icon ${rankClass}">#${i + 1}</div>
                        <div class="item-details">
                            <h4>${escapeHtml(prod.name)}</h4>
                            <p>${prod.total_sold} vendu(s)</p>
                        </div>
                        <div class="item-action">
                            <span style="font-weight:600;font-size:0.85rem">${escapeHtml(AdminAPI.formatCurrency(prod.total_revenue))}</span>
                        </div>
                    </li>`;
            })
            .join('');
    }

    function updateUserWidget(user) {
        if (!user) return;
        if (els.userName) els.userName.textContent = user.name || 'Admin';
        if (els.userRole) els.userRole.textContent = user.role || '';
        if (els.userAvatar) {
            els.userAvatar.textContent = (user.name || 'A').charAt(0).toUpperCase();
        }
    }

    async function loadDashboard() {
        setLoading(true);
        hideError();
        destroyCharts();

        try {
            const result = await AdminAPI.getDashboard();

            if (result.status !== 'success' || !result.data) {
                throw new Error(result.message || 'Impossible de charger le tableau de bord');
            }

            const d = result.data;

            updateUserWidget(d.user);

            if (els.currentDate) {
                const todayStr = new Date().toLocaleDateString('fr-FR', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                });
                els.currentDate.textContent = `Aujourd'hui, ${todayStr}`;
            }

            if (els.storePill) {
                const pillText = document.getElementById('store-pill-text');
                if (d.store_name) {
                    if (pillText) pillText.textContent = d.store_name;
                    els.storePill.classList.remove('hidden');
                } else {
                    els.storePill.classList.add('hidden');
                }
            }

            if (els.revenueToday) {
                const currencySymbol = AdminAPI.getCurrencySymbol();
                els.revenueToday.innerHTML = `${Number(d.revenue_today).toLocaleString('fr-FR')} <span class="currency">${currencySymbol}</span>`;
            }
            if (els.revenueMonth) {
                els.revenueMonth.textContent = AdminAPI.formatCurrency(d.revenue_month);
            }
            if (els.salesToday) els.salesToday.textContent = String(d.sales_today ?? 0);
            if (els.lowStock) {
                els.lowStock.innerHTML = `${d.low_stock_count ?? 0} <span class="subtitle">articles</span>`;
            }
            if (els.customers) {
                const extra = d.new_customers_today > 0 ? ` (+${d.new_customers_today} auj.)` : '';
                els.customers.innerHTML = `${d.active_customers ?? 0}<span class="subtitle">${extra}</span>`;
            }

            if (els.revenueTrend) {
                els.revenueTrend.innerHTML = AdminAPI.trendHtml(d.trends?.revenue_pct);
            }
            if (els.salesTrend) {
                els.salesTrend.innerHTML = AdminAPI.trendHtml(d.trends?.sales_pct);
            }

            if (els.sidebarBadge) {
                const n = parseInt(d.low_stock_count, 10) || 0;
                if (n > 0) {
                    els.sidebarBadge.textContent = n > 99 ? '99+' : String(n);
                    els.sidebarBadge.classList.remove('hidden');
                } else {
                    els.sidebarBadge.classList.add('hidden');
                }
            }

            renderTransactions(d.recent_transactions);
            renderTopProducts(d.top_products);
            renderRevenueChart(d.chart?.labels || [], d.chart?.revenues || []);
            renderCategoryChart(
                d.category_chart?.labels || [],
                d.category_chart?.revenues || []
            );
        } catch (err) {
            console.error(err);
            showError(err.message || 'Erreur de chargement. Vérifiez la base de données (voir migrations).');
        }

        setLoading(false);
    }

    els.refreshBtn?.addEventListener('click', loadDashboard);

    const themeBtn = document.getElementById('theme-toggle');
    themeBtn?.addEventListener('click', () => {
        const html = document.documentElement;
        const isDark = html.getAttribute('data-theme') === 'dark';
        html.setAttribute('data-theme', isDark ? 'light' : 'dark');
        themeBtn.querySelector('.material-icons-round').textContent = isDark
            ? 'dark_mode'
            : 'light_mode';
        localStorage.setItem('admin-theme', isDark ? 'light' : 'dark');
        loadDashboard();
    });

    const savedTheme = localStorage.getItem('admin-theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
        const icon = themeBtn?.querySelector('.material-icons-round');
        if (icon) icon.textContent = savedTheme === 'dark' ? 'light_mode' : 'dark_mode';
    }

    loadDashboard();
});
