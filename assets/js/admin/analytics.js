/**
 * Analyses & rapports — Chart.js + API reports
 */
document.addEventListener('DOMContentLoaded', () => {
    let reportData = null;
    let currentPeriod = 'month';
    const charts = {};

    const els = {
        periodLabel: document.getElementById('analytics-period-label'),
        refreshBtn: document.getElementById('refreshAnalytics'),
        exportBtn: document.getElementById('exportReportBtn'),
        errorBanner: document.getElementById('analyticsError'),
        revenue: document.getElementById('ar-revenue'),
        transactions: document.getElementById('ar-transactions'),
        avgTicket: document.getElementById('ar-avg-ticket'),
        activeCustomers: document.getElementById('ar-active-customers'),
        newCustomers: document.getElementById('ar-new-customers'),
        storePill: document.getElementById('store-pill'),
        storePillText: document.getElementById('store-pill-text'),
    };

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function chartColors() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: isDark ? '#374151' : '#e5e7eb',
            text: isDark ? '#9ca3af' : '#6b7280',
            primary: '#2563eb',
            fill: isDark ? 'rgba(37, 99, 235, 0.2)' : 'rgba(37, 99, 235, 0.1)',
            palette: ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#ec4899', '#84cc16'],
        };
    }

    function destroyChart(id) {
        charts[id]?.destroy();
        delete charts[id];
    }

    function destroyAllCharts() {
        Object.keys(charts).forEach(destroyChart);
    }

    function baseOptions(c) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: c.text } } },
        };
    }

    function lineChart(id, labels, data, label) {
        const ctx = document.getElementById(id);
        if (!ctx) return;
        const c = chartColors();
        destroyChart(id);
        charts[id] = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label,
                    data,
                    borderColor: c.primary,
                    backgroundColor: c.fill,
                    tension: 0.35,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 3,
                }],
            },
            options: {
                ...baseOptions(c),
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
                    x: { grid: { display: false }, ticks: { color: c.text, maxRotation: 45 } },
                },
            },
        });
    }

    function barChart(id, labels, data, label, horizontal = false) {
        const ctx = document.getElementById(id);
        if (!ctx) return;
        const c = chartColors();
        destroyChart(id);
        charts[id] = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label,
                    data,
                    backgroundColor: c.palette.slice(0, labels.length),
                    borderRadius: 6,
                }],
            },
            options: {
                ...baseOptions(c),
                indexAxis: horizontal ? 'y' : 'x',
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: c.grid },
                        ticks: { color: c.text },
                    },
                    x: { grid: { display: false }, ticks: { color: c.text } },
                },
            },
        });
    }

    function doughnutChart(id, labels, data) {
        const ctx = document.getElementById(id);
        if (!ctx) return;
        const c = chartColors();
        const hasData = data.some((v) => v > 0);
        destroyChart(id);
        charts[id] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: hasData ? data : [1],
                    backgroundColor: hasData ? c.palette : ['#e5e7eb'],
                    borderWidth: 0,
                }],
            },
            options: {
                ...baseOptions(c),
                plugins: {
                    legend: { position: 'bottom', labels: { color: c.text, padding: 12 } },
                },
            },
        });
    }

    function paymentLabel(method) {
        return AdminAPI.paymentLabel(method) || method || '—';
    }

    function setLoading(loading) {
        document.querySelectorAll('.ar-stat').forEach((card) => {
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

    function renderSummary(d) {
        const s = d.summary || {};
        const cust = d.customer_analytics || {};
        if (els.revenue) els.revenue.textContent = AdminAPI.formatCurrency(s.revenue);
        if (els.transactions) els.transactions.textContent = (s.transactions ?? 0).toLocaleString('fr-FR');
        if (els.avgTicket) els.avgTicket.textContent = AdminAPI.formatCurrency(s.avg_ticket);
        if (els.activeCustomers) els.activeCustomers.textContent = (cust.active_customers ?? 0).toLocaleString('fr-FR');
        if (els.newCustomers) {
            els.newCustomers.textContent = `+${cust.new_customers ?? 0} nouveaux sur la période`;
        }
        if (els.periodLabel) {
            const scope = d.store_name ? ` · ${d.store_name}` : (d.is_global ? ' · Toutes succursales' : '');
            els.periodLabel.textContent = `${d.period_label || ''}${scope}`;
        }
        if (els.storePill && els.storePillText) {
            if (d.store_name) {
                els.storePill.classList.remove('hidden');
                els.storePillText.textContent = d.store_name;
            } else if (d.is_global) {
                els.storePill.classList.remove('hidden');
                els.storePillText.textContent = 'Vue globale';
            } else {
                els.storePill.classList.add('hidden');
            }
        }
    }

    function renderDaily(ds) {
        const labels = ds.labels || [];
        const currencySymbol = AdminAPI.getCurrencySymbol();
        lineChart('dailyRevenueChart', labels, ds.revenues || [], `CA (${currencySymbol})`);
        barChart('dailyCountChart', labels, ds.counts || [], 'Transactions');

        const pm = ds.payment_mix || {};
        const payLabels = (pm.labels || []).map(paymentLabel);
        doughnutChart('paymentMixChart', payLabels, pm.amounts || []);

        const tbody = document.getElementById('dailyTableBody');
        if (!tbody) return;
        if (!labels.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="ad-empty-row">Aucune vente</td></tr>';
            return;
        }
        tbody.innerHTML = labels.map((lbl, i) => `
            <tr>
                <td>${escapeHtml(lbl)}</td>
                <td>${AdminAPI.formatCurrency((ds.revenues || [])[i])}</td>
                <td>${((ds.counts || [])[i] ?? 0).toLocaleString('fr-FR')}</td>
            </tr>
        `).join('');
    }

    function renderBranches(ba) {
        const labels = ba.labels || [];
        const currencySymbol = AdminAPI.getCurrencySymbol();
        barChart('branchRevenueChart', labels, ba.revenues || [], `CA (${currencySymbol})`);
        barChart('branchTxChart', labels, ba.transactions || [], 'Transactions');

        const tbody = document.getElementById('branchTableBody');
        if (!tbody) return;
        const stores = ba.stores || [];
        if (!stores.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="ad-empty-row">Aucune succursale</td></tr>';
            return;
        }
        tbody.innerHTML = stores.map((s) => `
            <tr>
                <td><strong>${escapeHtml(s.name)}</strong></td>
                <td>${escapeHtml(s.code || '—')}</td>
                <td>${AdminAPI.formatCurrency(s.revenue)}</td>
                <td>${(s.transactions ?? 0).toLocaleString('fr-FR')}</td>
                <td>${AdminAPI.formatCurrency(s.avg_ticket)}</td>
            </tr>
        `).join('');
    }

    function renderCashiers(cp) {
        const labels = cp.labels || [];
        const currencySymbol = AdminAPI.getCurrencySymbol();
        barChart('cashierRevenueChart', labels, cp.revenues || [], `CA (${currencySymbol})`, true);
        barChart('cashierCountChart', labels, cp.counts || [], 'Transactions', true);

        const tbody = document.getElementById('cashierTableBody');
        if (!tbody) return;
        const list = cp.cashiers || [];
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="ad-empty-row">Aucune vente par caissier</td></tr>';
            return;
        }
        tbody.innerHTML = list.map((c, i) => {
            const rankCls = i < 3 ? ` ar-rank--${i + 1}` : '';
            return `
            <tr>
                <td><span class="ar-rank${rankCls}">${i + 1}</span></td>
                <td><strong>${escapeHtml(c.name)}</strong></td>
                <td>${AdminAPI.formatCurrency(c.revenue)}</td>
                <td>${(c.transactions ?? 0).toLocaleString('fr-FR')}</td>
                <td>${AdminAPI.formatCurrency(c.avg_ticket)}</td>
            </tr>`;
        }).join('');
    }

    function renderInventory(inv) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('inv-total', (inv.total_products ?? 0).toLocaleString('fr-FR'));
        set('inv-out', (inv.out_of_stock ?? 0).toLocaleString('fr-FR'));
        set('inv-low', (inv.low_stock ?? 0).toLocaleString('fr-FR'));
        set('inv-value', AdminAPI.formatCurrency(inv.inventory_value));

        const cat = inv.category_chart || {};
        doughnutChart('invCategoryChart', cat.labels || [], cat.values || []);

        const st = inv.stock_status || {};
        doughnutChart('invStockChart', st.labels || [], st.counts || []);

        const tbody = document.getElementById('invMovingBody');
        if (!tbody) return;
        const moving = inv.top_moving || [];
        if (!moving.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="ad-empty-row">Aucune vente produit</td></tr>';
            return;
        }
        tbody.innerHTML = moving.map((p) => `
            <tr>
                <td>${escapeHtml(p.name)}</td>
                <td>${Number(p.qty_sold || 0).toLocaleString('fr-FR')}</td>
                <td>${AdminAPI.formatCurrency(p.revenue)}</td>
            </tr>
        `).join('');
    }

    function renderCustomers(cu) {
        const growth = cu.growth_chart || {};
        if (growth.labels?.length) {
            lineChart('customerGrowthChart', growth.labels, growth.counts || [], 'Nouveaux clients');
        } else {
            destroyChart('customerGrowthChart');
        }

        const split = cu.loyalty_split || {};
        doughnutChart('customerSplitChart', split.labels || [], split.counts || []);

        const tbody = document.getElementById('customerTopBody');
        if (!tbody) return;
        const top = cu.top_customers || [];
        if (!top.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="ad-empty-row">Aucun client identifié</td></tr>';
            return;
        }
        tbody.innerHTML = top.map((c) => `
            <tr>
                <td><strong>${escapeHtml(c.name)}</strong></td>
                <td>${escapeHtml(c.phone || '—')}</td>
                <td>${(c.visits ?? 0).toLocaleString('fr-FR')}</td>
                <td>${AdminAPI.formatCurrency(c.spent)}</td>
            </tr>
        `).join('');
    }

    function renderAll(d) {
        destroyAllCharts();
        renderSummary(d);
        renderDaily(d.daily_sales || {});
        renderBranches(d.branch_analytics || {});
        renderCashiers(d.cashier_performance || {});
        renderInventory(d.inventory_analytics || {});
        renderCustomers(d.customer_analytics || {});
    }

    async function loadReport() {
        setLoading(true);
        hideError();
        try {
            const res = await AdminAPI.getReports({ period: currentPeriod });
            if (res.status !== 'success') {
                showError(res.message || 'Impossible de charger les rapports');
                return;
            }
            reportData = res.data;
            renderAll(reportData);
        } catch (e) {
            showError(e.message || 'Erreur réseau');
        } finally {
            setLoading(false);
        }
    }

    function exportCsv() {
        if (!reportData) {
            alert('Chargez d\'abord les données (actualiser).');
            return;
        }
        const d = reportData;
        const lines = [];
        const sep = ';';
        const push = (arr) => lines.push(arr.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(sep));

        push(['Rapport RetailPOS']);
        push(['Période', d.period_label]);
        push(['Du', d.from, 'Au', d.to]);
        push(['CA total', d.summary?.revenue]);
        push(['Transactions', d.summary?.transactions]);
        push(['Panier moyen', d.summary?.avg_ticket]);
        push([]);

        push(['--- Ventes quotidiennes ---']);
        push(['Date', 'CA', 'Transactions']);
        (d.daily_sales?.labels || []).forEach((lbl, i) => {
            push([lbl, (d.daily_sales.revenues || [])[i], (d.daily_sales.counts || [])[i]]);
        });
        push([]);

        push(['--- Succursales ---']);
        push(['Nom', 'Code', 'CA', 'Transactions', 'Panier moyen']);
        (d.branch_analytics?.stores || []).forEach((s) => {
            push([s.name, s.code, s.revenue, s.transactions, s.avg_ticket]);
        });
        push([]);

        push(['--- Caissiers ---']);
        push(['Nom', 'CA', 'Transactions', 'Panier moyen']);
        (d.cashier_performance?.cashiers || []).forEach((c) => {
            push([c.name, c.revenue, c.transactions, c.avg_ticket]);
        });
        push([]);

        push(['--- Inventaire ---']);
        push(['Produits', d.inventory_analytics?.total_products]);
        push(['Rupture', d.inventory_analytics?.out_of_stock]);
        push(['Stock bas', d.inventory_analytics?.low_stock]);
        push(['Valeur stock', d.inventory_analytics?.inventory_value]);
        push([]);

        push(['--- Top clients ---']);
        push(['Nom', 'Téléphone', 'Visites', 'Total']);
        (d.customer_analytics?.top_customers || []).forEach((c) => {
            push([c.name, c.phone, c.visits, c.spent]);
        });

        const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `rapport-pos-${d.period}-${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    }

    function initTabs() {
        document.querySelectorAll('.ar-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.ar-tab').forEach((t) => t.classList.remove('active'));
                tab.classList.add('active');
                document.querySelectorAll('.ar-panel').forEach((p) => p.classList.add('hidden'));
                document.getElementById(`panel-${tab.dataset.panel}`)?.classList.remove('hidden');
            });
        });
    }

    function initPeriodChips() {
        document.querySelectorAll('.as-chip[data-period]').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('.as-chip[data-period]').forEach((c) => c.classList.remove('active'));
                chip.classList.add('active');
                currentPeriod = chip.dataset.period || 'month';
                loadReport();
            });
        });
    }

    els.refreshBtn?.addEventListener('click', loadReport);
    els.exportBtn?.addEventListener('click', exportCsv);

    document.addEventListener('store-switched', loadReport);

    const themeBtn = document.getElementById('theme-toggle');
    const savedTheme = localStorage.getItem('admin-theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
        const icon = themeBtn?.querySelector('.material-icons-round');
        if (icon) icon.textContent = savedTheme === 'dark' ? 'light_mode' : 'dark_mode';
    }
    themeBtn?.addEventListener('click', () => {
        const html = document.documentElement;
        const isDark = html.getAttribute('data-theme') === 'dark';
        html.setAttribute('data-theme', isDark ? 'light' : 'dark');
        themeBtn.querySelector('.material-icons-round').textContent = isDark ? 'dark_mode' : 'light_mode';
        localStorage.setItem('admin-theme', isDark ? 'light' : 'dark');
        if (reportData) renderAll(reportData);
    });

    initTabs();
    initPeriodChips();
    loadReport();
});
