/**
 * Admin dashboard — dynamic API data + Chart.js, i18n
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.ADMIN_CONFIG || {};
    const i18n = window.ADMIN_I18N || {};
    const locale = cfg.locale || (cfg.lang === 'fr' ? 'fr-FR' : 'en-US');

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
        customersMeta: document.getElementById('customers-meta'),
        dashPeriod: document.getElementById('adDashPeriod'),
        dashStoreScope: document.getElementById('adDashStoreScope'),
        lowStockAlert: document.getElementById('adLowStockAlert'),
        lowStockAlertText: document.getElementById('adLowStockAlertText'),
        revenueTrend: document.getElementById('revenue-trend'),
        salesTrend: document.getElementById('sales-trend'),
        txList: document.getElementById('recent-transactions-list'),
        topProducts: document.getElementById('top-products-list'),
        currentDate: document.getElementById('current-date'),
        lastUpdated: document.getElementById('lastUpdated'),
        storePill: document.getElementById('store-pill'),
        sidebarBadge: document.getElementById('sidebar-low-stock-badge'),
        userName: document.getElementById('sidebar-user-name'),
        userRole: document.getElementById('sidebar-user-role'),
        userAvatar: document.getElementById('sidebar-user-avatar'),
        ecomOnline: document.getElementById('dash-ecom-online'),
        ecomOrdersToday: document.getElementById('dash-ecom-orders-today'),
        ecomRevenueToday: document.getElementById('dash-ecom-revenue-today'),
        ecomAccounts: document.getElementById('dash-ecom-accounts'),
        ecomOrdersList: document.getElementById('dash-ecom-orders-list'),
    };

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function setLoading(loading) {
        document.querySelectorAll('.ad-kpi').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
        document.querySelectorAll('.stat-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
        els.refreshBtn?.classList.toggle('spinning', loading);
    }

    function clearKpiLoading(el) {
        el?.closest('.ad-kpi')?.classList.remove('is-loading');
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

    function getThemeAccent() {
        const cfg = window.ADMIN_CONFIG || {};
        if (cfg.accent && /^#[0-9A-Fa-f]{6}$/.test(cfg.accent)) return cfg.accent;
        const html = document.documentElement;
        if (html.dataset.themeAccent && /^#[0-9A-Fa-f]{6}$/.test(html.dataset.themeAccent)) {
            return html.dataset.themeAccent;
        }
        const meta = document.querySelector('meta[name="theme-accent"]')?.getAttribute('content');
        if (meta && /^#[0-9A-Fa-f]{6}$/.test(meta)) return meta;
        const css = getComputedStyle(html).getPropertyValue('--theme-accent').trim();
        return css || '#2563eb';
    }

    function hexToRgba(hex, alpha) {
        let h = String(hex || '').replace('#', '');
        if (h.length === 3) h = h.split('').map((c) => c + c).join('');
        if (h.length !== 6) return `rgba(37, 99, 235, ${alpha})`;
        const r = parseInt(h.slice(0, 2), 16);
        const g = parseInt(h.slice(2, 4), 16);
        const b = parseInt(h.slice(4, 6), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function chartColors() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const primary = getThemeAccent();
        return {
            grid: isDark ? '#374151' : '#e5e7eb',
            text: isDark ? '#9ca3af' : '#6b7280',
            primary,
            fill: hexToRgba(primary, isDark ? 0.2 : 0.1),
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
                    label: t('chart_revenue_label', AdminAPI.getCurrencySymbol()),
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
                            callback: (v) => Number(v).toLocaleString(locale),
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
        const palette = hasData
            ? [c.primary, '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#06b6d4', '#ec4899', '#84cc16']
            : ['#e5e7eb'];

        categoryChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: hasData ? revenues : [1],
                    backgroundColor: palette,
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

    function formatTodayLabel() {
        return new Date().toLocaleDateString(locale, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    }

    function updateDateHeader() {
        const todayStr = formatTodayLabel();
        if (els.currentDate) {
            els.currentDate.textContent = t('today_prefix', todayStr);
        }
        if (els.dashPeriod) {
            els.dashPeriod.textContent = todayStr;
        }
    }

    function updateLastUpdated() {
        if (!els.lastUpdated) return;
        const time = new Date().toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        els.lastUpdated.textContent = t('last_updated', time);
    }

    function renderTransactions(list) {
        if (!els.txList) return;
        if (!list?.length) {
            els.txList.innerHTML =
                `<tr><td colspan="6" class="ad-empty-row">${t('no_transactions')}</td></tr>`;
            return;
        }

        const lbl = {
            receipt: t('col_receipt'),
            customer: t('col_customer'),
            date: t('col_date'),
            amount: t('col_amount'),
            status: t('col_status'),
            payment: t('col_payment'),
        };

        els.txList.innerHTML = list
            .map((tx) => {
                const receipt = tx.receipt_no || tx.receipt_number || `#${tx.id}`;
                const status =
                    tx.status === 'completed'
                        ? `<span class="status-badge success">${t('status_completed')}</span>`
                        : `<span class="status-badge warning">${escapeHtml(tx.status)}</span>`;
                return `
                    <tr>
                        <td data-label="${escapeHtml(lbl.receipt)}"><span class="receipt-link">${escapeHtml(receipt.length > 14 ? receipt.substring(0, 14) + '…' : receipt)}</span></td>
                        <td data-label="${escapeHtml(lbl.customer)}">${escapeHtml(tx.customer_name || t('walk_in'))}</td>
                        <td data-label="${escapeHtml(lbl.date)}" style="color:var(--text-secondary)">${escapeHtml(AdminAPI.formatDate(tx.created_at || tx.sale_date))}</td>
                        <td data-label="${escapeHtml(lbl.amount)}" style="font-weight:600">${escapeHtml(AdminAPI.formatCurrency(tx.total ?? tx.total_amount))}</td>
                        <td data-label="${escapeHtml(lbl.status)}">${status}</td>
                        <td data-label="${escapeHtml(lbl.payment)}">${escapeHtml(AdminAPI.paymentLabel(tx.payment_method))}</td>
                    </tr>`;
            })
            .join('');
    }

    function renderTopProducts(list) {
        if (!els.topProducts) return;
        if (!list?.length) {
            els.topProducts.innerHTML =
                `<li class="item"><div class="item-details"><p>${t('no_sales_30d')}</p></div></li>`;
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
                            <p>${t('sold_count', prod.total_sold)}</p>
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

    function clearEcomKpiLoading(el) {
        el?.closest('.ad-kpi--ecom')?.classList.remove('is-loading');
    }

    function applyEcomStats(stats) {
        if (!stats) return;
        if (els.ecomOnline) {
            els.ecomOnline.textContent = String(stats.online_products ?? 0);
            clearEcomKpiLoading(els.ecomOnline);
        }
        if (els.ecomOrdersToday) {
            els.ecomOrdersToday.textContent = String(stats.web_orders_today ?? 0);
            clearEcomKpiLoading(els.ecomOrdersToday);
        }
        if (els.ecomRevenueToday) {
            els.ecomRevenueToday.textContent = AdminAPI.formatCurrency(stats.web_revenue_today ?? 0);
            clearEcomKpiLoading(els.ecomRevenueToday);
        }
        if (els.ecomAccounts) {
            els.ecomAccounts.textContent = String(stats.storefront_accounts ?? 0);
            clearEcomKpiLoading(els.ecomAccounts);
        }
    }

    function renderEcomOrders(list) {
        if (!els.ecomOrdersList) return;
        if (!list?.length) {
            els.ecomOrdersList.innerHTML =
                `<tr><td colspan="4" class="ad-empty-row">${t('ecom_no_orders')}</td></tr>`;
            return;
        }

        const lbl = {
            receipt: t('col_receipt'),
            date: t('col_date'),
            amount: t('col_amount'),
            status: t('col_status'),
        };

        els.ecomOrdersList.innerHTML = list
            .map((o) => {
                const receipt = o.receipt_no || o.receipt_number || `#${o.id}`;
                const status =
                    o.status === 'completed'
                        ? `<span class="status-badge success">${t('status_completed')}</span>`
                        : `<span class="status-badge warning">${escapeHtml(o.status)}</span>`;
                return `
                    <tr>
                        <td data-label="${escapeHtml(lbl.receipt)}">${escapeHtml(receipt)}</td>
                        <td data-label="${escapeHtml(lbl.date)}" style="color:var(--text-secondary)">${escapeHtml(AdminAPI.formatDate(o.created_at || o.sale_date))}</td>
                        <td data-label="${escapeHtml(lbl.amount)}" style="font-weight:600">${escapeHtml(AdminAPI.formatCurrency(o.total ?? o.total_amount))}</td>
                        <td data-label="${escapeHtml(lbl.status)}">${status}</td>
                    </tr>`;
            })
            .join('');
    }

    async function loadEcommerceSection() {
        if (!window.ADMIN_PAGE?.hasEcommerce || !AdminAPI.getEcommerceDashboard) return;

        try {
            const [stats, orders] = await Promise.all([
                AdminAPI.getEcommerceDashboard(),
                AdminAPI.getEcommerceOrders({ limit: 5 }),
            ]);

            if (stats.status === 'ok') {
                applyEcomStats(stats);
            }

            if (orders.status === 'ok') {
                renderEcomOrders(orders.items || []);
            } else if (els.ecomOrdersList) {
                renderEcomOrders([]);
            }
        } catch (err) {
            console.error('E-commerce dashboard:', err);
        }
    }

    async function loadDashboard() {
        setLoading(true);
        hideError();
        destroyCharts();

        try {
            const result = await AdminAPI.getDashboard();

            if (result.status !== 'success' || !result.data) {
                throw new Error(result.message || t('load_dashboard_error'));
            }

            const d = result.data;

            updateUserWidget(d.user);

            updateDateHeader();

            if (els.dashStoreScope) {
                els.dashStoreScope.textContent = d.store_name || t('dash_all_stores');
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
                const amount = Number(d.revenue_today || 0).toLocaleString(locale, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
                els.revenueToday.innerHTML = `${amount} <span class="currency">${currencySymbol}</span>`;
                clearKpiLoading(els.revenueToday);
            }
            if (els.revenueMonth) {
                els.revenueMonth.textContent = AdminAPI.formatCurrency(d.revenue_month);
                clearKpiLoading(els.revenueMonth);
            }
            if (els.salesToday) {
                els.salesToday.textContent = String(d.sales_today ?? 0);
                clearKpiLoading(els.salesToday);
            }
            if (els.lowStock) {
                els.lowStock.textContent = String(d.low_stock_count ?? 0);
                clearKpiLoading(els.lowStock);
            }
            if (els.customers) {
                els.customers.textContent = String(d.active_customers ?? 0);
                clearKpiLoading(els.customers);
            }
            if (els.customersMeta) {
                const extra = d.new_customers_today > 0 ? t('new_customers_today', d.new_customers_today) : '';
                els.customersMeta.textContent = extra || t('customer_base_hint');
            }

            const lowStockCount = parseInt(d.low_stock_count, 10) || 0;
            if (els.lowStockAlert) {
                let msg = '';
                if (lowStockCount > 0 && els.lowStockAlertText) {
                    const raw = t('dash_low_stock_alert');
                    if (raw && raw !== 'dash_low_stock_alert') {
                        msg = raw.includes('%s') ? raw.replace('%s', String(lowStockCount)) : `${lowStockCount} — ${raw}`;
                        els.lowStockAlertText.textContent = msg;
                    }
                } else if (els.lowStockAlertText) {
                    els.lowStockAlertText.textContent = '';
                }
                els.lowStockAlert.hidden = !(lowStockCount > 0 && msg.trim());
            }

            if (els.revenueTrend) {
                els.revenueTrend.innerHTML = AdminAPI.trendHtml(d.trends?.revenue_pct);
            }
            if (els.salesTrend) {
                els.salesTrend.innerHTML = AdminAPI.trendHtml(d.trends?.sales_pct);
            }

            if (els.sidebarBadge) {
                const n = lowStockCount;
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
            updateLastUpdated();
            await loadEcommerceSection();
        } catch (err) {
            console.error(err);
            showError(err.message || t('load_error_hint'));
        }

        setLoading(false);
    }

    els.refreshBtn?.addEventListener('click', loadDashboard);
    document.addEventListener('store-switched', loadDashboard);

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

    updateDateHeader();
    loadDashboard();
    loadEcommerceSection();
});
