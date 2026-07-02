/**
 * Admin analytics & reports — Chart.js + i18n
 */
(() => {
    const i18n = window.ADMIN_ANALYTICS_I18N || {};
    const locale = window.ADMIN_PAGE?.locale || (window.ADMIN_PAGE?.lang === 'fr' ? 'fr-FR' : 'en-US');

    const PERIOD_KEYS = {
        today: 'period_today',
        week: 'period_week',
        month: 'period_month',
        '90d': 'period_90d',
    };

    const STOCK_LABEL_KEYS = ['stock_in_stock', 'low_stock', 'stock_out'];
    const LOYALTY_LABEL_KEYS = ['customer_identified', 'customer_anonymous'];

    let reportData = null;
    let currentPeriod = 'month';
    let lastFetchAt = null;
    const charts = {};

    const els = {
        periodLabel: document.getElementById('analytics-period-label'),
        heroPeriod: document.getElementById('arHeroPeriod'),
        heroScope: document.getElementById('arHeroScope'),
        lastUpdated: document.getElementById('lastUpdated'),
        refreshBtn: document.getElementById('refreshAnalytics'),
        exportBtn: document.getElementById('exportReportBtn'),
        exportBtnHero: document.getElementById('exportReportBtnHero'),
        errorBanner: document.getElementById('analyticsError'),
        revenue: document.getElementById('ar-revenue-val'),
        transactions: document.getElementById('ar-transactions-val'),
        avgTicket: document.getElementById('ar-avg-ticket-val'),
        activeCustomers: document.getElementById('ar-active-customers-val'),
        newCustomers: document.getElementById('ar-new-customers'),
    };

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function fmtNum(n) {
        return Number(n ?? 0).toLocaleString(locale);
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function escapeAttr(str) {
        return String(str ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function columnLabels() {
        return {
            date: t('col_date'),
            revenue: t('col_revenue'),
            transactions: t('col_transactions'),
            branch: t('col_branch'),
            code: t('col_code'),
            avgTicket: t('col_avg_ticket'),
            rank: t('col_rank'),
            cashier: t('col_cashier'),
            product: t('col_product'),
            qtySold: t('col_qty_sold'),
            revenueGenerated: t('col_revenue_generated'),
            customer: t('col_customer'),
            phone: t('col_phone'),
            visits: t('col_visits'),
            totalSpent: t('col_total_spent'),
        };
    }

    function periodLabel(period) {
        const key = PERIOD_KEYS[period];
        return key ? t(key) : period;
    }

    function translateStockLabels() {
        return STOCK_LABEL_KEYS.map((k) => t(k));
    }

    function translateLoyaltyLabels() {
        return LOYALTY_LABEL_KEYS.map((k) => t(k));
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
                            callback: (v) => fmtNum(v),
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
                    backgroundColor: c.palette.slice(0, Math.max(labels.length, 1)),
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
                labels: hasData ? labels : [t('no_chart_data')],
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
        const keys = { cash: 'pay_cash', card: 'pay_card', mobile_money: 'pay_mobile_money' };
        const key = keys[method];
        return key ? t(key) : (method || '—');
    }

    function setLoading(loading) {
        document.querySelectorAll('#arSummaryCards .ad-kpi').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
        els.refreshBtn?.classList.toggle('spinning', loading);
    }

    function clearKpiLoading(el) {
        if (!el) return;
        el.closest('.ad-kpi')?.classList.remove('is-loading');
    }

    function showError(msg) {
        if (!els.errorBanner) return;
        els.errorBanner.classList.add('is-visible');
        els.errorBanner.querySelector('.ad-error-text').textContent = msg;
    }

    function hideError() {
        els.errorBanner?.classList.remove('is-visible');
    }

    function updateLastUpdated() {
        if (!els.lastUpdated || !lastFetchAt) return;
        const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        els.lastUpdated.textContent = t('last_updated', time);
    }

    function renderSummary(d) {
        const s = d.summary || {};
        const cust = d.customer_analytics || {};
        const period = d.period || currentPeriod;

        let scopeSuffix = '';
        let scopeText = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
        if (d.store_name) {
            scopeSuffix = t('store_scope', d.store_name);
            scopeText = d.store_name;
        } else if (d.is_global) {
            scopeSuffix = t('all_branches_scope');
            scopeText = t('dash_all_stores');
        }

        if (els.revenue) {
            els.revenue.textContent = AdminAPI.formatCurrency(s.revenue);
            clearKpiLoading(els.revenue);
        }
        if (els.transactions) {
            els.transactions.textContent = fmtNum(s.transactions);
            clearKpiLoading(els.transactions);
        }
        if (els.avgTicket) {
            els.avgTicket.textContent = AdminAPI.formatCurrency(s.avg_ticket);
            clearKpiLoading(els.avgTicket);
        }
        if (els.activeCustomers) {
            els.activeCustomers.textContent = fmtNum(cust.active_customers);
            clearKpiLoading(els.activeCustomers);
        }
        if (els.newCustomers) {
            els.newCustomers.textContent = t('new_customers_period', fmtNum(cust.new_customers ?? 0));
        }

        const periodText = periodLabel(period);
        if (els.periodLabel) {
            els.periodLabel.textContent = `${periodText}${scopeSuffix}`;
        }
        if (els.heroPeriod) els.heroPeriod.textContent = periodText;
        if (els.heroScope) els.heroScope.textContent = scopeText;
    }

    function renderDaily(ds) {
        const labels = ds.labels || [];
        const currencySymbol = AdminAPI.getCurrencySymbol();
        lineChart('dailyRevenueChart', labels, ds.revenues || [], t('chart_revenue_label', currencySymbol));
        barChart('dailyCountChart', labels, ds.counts || [], t('chart_transactions_label'));

        const pm = ds.payment_mix || {};
        const payLabels = (pm.labels || []).map(paymentLabel);
        doughnutChart('paymentMixChart', payLabels, pm.amounts || []);

        const tbody = document.getElementById('dailyTableBody');
        if (!tbody) return;
        if (!labels.length) {
            tbody.innerHTML = `<tr><td colspan="3" class="ad-empty-row">${escapeHtml(t('no_sales'))}</td></tr>`;
            return;
        }
        tbody.innerHTML = labels.map((lbl, i) => {
            const L = columnLabels();
            return `
            <tr>
                <td data-label="${escapeAttr(L.date)}">${escapeHtml(lbl)}</td>
                <td data-label="${escapeAttr(L.revenue)}">${AdminAPI.formatCurrency((ds.revenues || [])[i])}</td>
                <td data-label="${escapeAttr(L.transactions)}">${fmtNum((ds.counts || [])[i])}</td>
            </tr>`;
        }).join('');
    }

    function renderBranches(ba) {
        const labels = ba.labels || [];
        const currencySymbol = AdminAPI.getCurrencySymbol();
        barChart('branchRevenueChart', labels, ba.revenues || [], t('chart_revenue_label', currencySymbol));
        barChart('branchTxChart', labels, ba.transactions || [], t('chart_transactions_label'));

        const tbody = document.getElementById('branchTableBody');
        if (!tbody) return;
        const stores = ba.stores || [];
        if (!stores.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="ad-empty-row">${escapeHtml(t('no_branch_data'))}</td></tr>`;
            return;
        }
        const L = columnLabels();
        tbody.innerHTML = stores.map((s) => `
            <tr>
                <td data-label="${escapeAttr(L.branch)}"><strong>${escapeHtml(s.name)}</strong></td>
                <td data-label="${escapeAttr(L.code)}">${escapeHtml(s.code || '—')}</td>
                <td data-label="${escapeAttr(L.revenue)}">${AdminAPI.formatCurrency(s.revenue)}</td>
                <td data-label="${escapeAttr(L.transactions)}">${fmtNum(s.transactions)}</td>
                <td data-label="${escapeAttr(L.avgTicket)}">${AdminAPI.formatCurrency(s.avg_ticket)}</td>
            </tr>
        `).join('');
    }

    function renderCashiers(cp) {
        const labels = cp.labels || [];
        const currencySymbol = AdminAPI.getCurrencySymbol();
        barChart('cashierRevenueChart', labels, cp.revenues || [], t('chart_revenue_label', currencySymbol), true);
        barChart('cashierCountChart', labels, cp.counts || [], t('chart_transactions_label'), true);

        const tbody = document.getElementById('cashierTableBody');
        if (!tbody) return;
        const list = cp.cashiers || [];
        if (!list.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="ad-empty-row">${escapeHtml(t('no_cashier_sales'))}</td></tr>`;
            return;
        }
        const L = columnLabels();
        tbody.innerHTML = list.map((c, i) => {
            const rankCls = i < 3 ? ` ar-rank--${i + 1}` : '';
            return `
            <tr>
                <td data-label="${escapeAttr(L.rank)}"><span class="ar-rank${rankCls}">${i + 1}</span></td>
                <td data-label="${escapeAttr(L.cashier)}"><strong>${escapeHtml(c.name)}</strong></td>
                <td data-label="${escapeAttr(L.revenue)}">${AdminAPI.formatCurrency(c.revenue)}</td>
                <td data-label="${escapeAttr(L.transactions)}">${fmtNum(c.transactions)}</td>
                <td data-label="${escapeAttr(L.avgTicket)}">${AdminAPI.formatCurrency(c.avg_ticket)}</td>
            </tr>`;
        }).join('');
    }

    function renderInventory(inv) {
        const set = (id, v) => {
            const el = document.getElementById(id);
            if (el) el.textContent = v;
        };
        set('inv-total-val', fmtNum(inv.total_products));
        set('inv-out-val', fmtNum(inv.out_of_stock));
        set('inv-low-val', fmtNum(inv.low_stock));
        set('inv-value-val', AdminAPI.formatCurrency(inv.inventory_value));

        const cat = inv.category_chart || {};
        const catLabels = (cat.labels || []).map((lbl) => (lbl === 'Aucune donnée' ? t('no_chart_data') : lbl));
        doughnutChart('invCategoryChart', catLabels.length ? catLabels : [t('no_chart_data')], cat.values || []);

        const st = inv.stock_status || {};
        doughnutChart('invStockChart', translateStockLabels(), st.counts || []);

        const tbody = document.getElementById('invMovingBody');
        if (!tbody) return;
        const moving = inv.top_moving || [];
        if (!moving.length) {
            tbody.innerHTML = `<tr><td colspan="3" class="ad-empty-row">${escapeHtml(t('no_product_sales'))}</td></tr>`;
            return;
        }
        const L = columnLabels();
        tbody.innerHTML = moving.map((p) => `
            <tr>
                <td data-label="${escapeAttr(L.product)}">${escapeHtml(p.name)}</td>
                <td data-label="${escapeAttr(L.qtySold)}">${fmtNum(p.qty_sold)}</td>
                <td data-label="${escapeAttr(L.revenueGenerated)}">${AdminAPI.formatCurrency(p.revenue)}</td>
            </tr>
        `).join('');
    }

    function renderCustomers(cu) {
        const growth = cu.growth_chart || {};
        if (growth.labels?.length) {
            lineChart('customerGrowthChart', growth.labels, growth.counts || [], t('chart_new_customers'));
        } else {
            destroyChart('customerGrowthChart');
        }

        const split = cu.loyalty_split || {};
        doughnutChart('customerSplitChart', translateLoyaltyLabels(), split.counts || []);

        const tbody = document.getElementById('customerTopBody');
        if (!tbody) return;
        const top = cu.top_customers || [];
        if (!top.length) {
            tbody.innerHTML = `<tr><td colspan="4" class="ad-empty-row">${escapeHtml(t('no_identified_customers'))}</td></tr>`;
            return;
        }
        const L = columnLabels();
        tbody.innerHTML = top.map((c) => `
            <tr>
                <td data-label="${escapeAttr(L.customer)}"><strong>${escapeHtml(c.name)}</strong></td>
                <td data-label="${escapeAttr(L.phone)}">${escapeHtml(c.phone || '—')}</td>
                <td data-label="${escapeAttr(L.visits)}">${fmtNum(c.visits)}</td>
                <td data-label="${escapeAttr(L.totalSpent)}">${AdminAPI.formatCurrency(c.spent)}</td>
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
                showError(res.message || t('load_report_error'));
                return;
            }
            reportData = res.data;
            lastFetchAt = new Date();
            updateLastUpdated();
            renderAll(reportData);
        } catch (e) {
            showError(e.message || t('connection_error'));
        } finally {
            setLoading(false);
        }
    }

    async function exportCsv() {
        if (!reportData) {
            alert(t('export_load_first'));
            return;
        }
        if (!window.AnalyticsReportExport) {
            alert(t('load_error'));
            return;
        }

        const btn = els.exportBtn;
        const label = btn?.querySelector('.btn-label');
        const prevLabel = label?.textContent || '';
        if (btn) btn.disabled = true;
        if (label) label.textContent = t('exporting_excel');

        try {
            await AnalyticsReportExport.exportFullExcel({
                reportData,
                locale,
                periodKey: currentPeriod,
                cfg: {
                    storeName: window.ADMIN_PAGE?.storeName || window.ADMIN_CONFIG?.storeName,
                    userName: window.ADMIN_PAGE?.userName || window.ADMIN_CONFIG?.userName,
                    currency: window.ADMIN_PAGE?.currency || window.ADMIN_CONFIG?.currency,
                },
                t,
                periodLabel,
                paymentLabel,
            });
            if (label) label.textContent = t('export_success');
            setTimeout(() => {
                if (label) label.textContent = prevLabel;
            }, 2500);
        } catch (e) {
            alert(t('export_excel_error'));
            if (label) label.textContent = prevLabel;
        } finally {
            if (btn) btn.disabled = false;
        }
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

    function syncPeriodChips() {
        document.querySelectorAll('.inv-chip[data-period]').forEach((c) => {
            const active = (c.dataset.period || 'month') === currentPeriod;
            c.classList.toggle('active', active);
            c.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function initPeriodChips() {
        document.querySelectorAll('.inv-chip[data-period]').forEach((chip) => {
            chip.addEventListener('click', () => {
                currentPeriod = chip.dataset.period || 'month';
                syncPeriodChips();
                loadReport();
            });
        });
    }

    function initTheme() {
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
    }

    document.addEventListener('DOMContentLoaded', () => {
        els.refreshBtn?.addEventListener('click', loadReport);
        els.exportBtn?.addEventListener('click', exportCsv);
        els.exportBtnHero?.addEventListener('click', exportCsv);
        document.addEventListener('store-switched', loadReport);
        syncPeriodChips();
        initTabs();
        initPeriodChips();
        initTheme();
        loadReport();
    });
})();
