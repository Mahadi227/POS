/**
 * Admin inventory analytics — charts, stats, period filters, i18n
 */
(() => {
    const CFG = window.INVENTORY_CONFIG || {};
    const i18n = window.INVENTORY_I18N || {};
    const locale = CFG.locale || (CFG.lang === 'fr' ? 'fr-FR' : 'en-US');

    const MOVEMENT_KEYS = {
        purchase: 'mov_purchase',
        sale: 'mov_sale',
        return: 'mov_return',
        transfer_in: 'mov_transfer_in',
        transfer_out: 'mov_transfer_out',
        adjustment: 'mov_adjustment',
        damaged: 'mov_damaged',
        expired: 'mov_expired',
        manual_edit: 'mov_manual_edit',
        transfer: 'type_transfer',
        restock: 'reason_restock',
        damage: 'reason_damage',
        correction: 'reason_correction',
    };

    const PERIOD_KEYS = {
        today: 'period_today',
        week: 'period_week',
        month: 'period_month',
        '90d': 'period_90d',
        all: 'period_all',
    };

    const $ = (id) => document.getElementById(id);
    let analyticsData = null;
    let activePeriod = 'month';
    let lastFetchAt = null;
    const charts = {};

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function escapeAttr(s) {
        return String(s ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function movementTableLabels() {
        return {
            type: t('col_movement_type'),
            count: t('col_count'),
            stockIn: t('stat_total_in'),
            stockOut: t('stat_total_out'),
        };
    }

    function topProductLabels() {
        return {
            product: t('col_product'),
            units: t('col_sold_units'),
            value: t('col_sold_value'),
            profit: t('col_profit'),
        };
    }

    function toast(msg, type = 'success') {
        const el = $('invToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast show ${type === 'error' ? 'error' : ''}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('#iaSummaryCards .ad-kpi').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function clearKpiLoading(el) {
        if (!el) return;
        el.closest('.ad-kpi')?.classList.remove('is-loading');
    }

    function showError(msg) {
        const banner = $('analyticsError');
        if (!banner) return;
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        banner.classList.add('is-visible');
    }

    function hideError() {
        $('analyticsError')?.classList.remove('is-visible');
    }

    function updateDateHeader() {
        const label = new Date().toLocaleDateString(locale, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
        const header = $('analyticsDate');
        if (header) header.textContent = label;

        const periodEl = $('iaHeroPeriod');
        if (periodEl) periodEl.textContent = t('report_generated', periodLabel(activePeriod));

        const scopeEl = $('iaHeroScope');
        if (scopeEl) {
            scopeEl.textContent = CFG.storeName || window.ADMIN_PAGE?.storeName || t('dash_all_stores');
        }
    }

    function updateLowStockAlert(count) {
        const alertEl = $('iaLowStockAlert');
        const alertText = $('iaLowStockAlertText');
        if (!alertEl) return;

        let msg = '';
        const n = parseInt(count, 10) || 0;
        if (n > 0 && alertText) {
            const raw = t('low_stock_alert');
            if (raw && raw !== 'low_stock_alert') {
                msg = raw.includes('%s') ? raw.replace('%s', String(n)) : `${n} — ${raw}`;
                alertText.textContent = msg;
            }
        } else if (alertText) {
            alertText.textContent = '';
        }
        alertEl.hidden = !(n > 0 && msg.trim());
    }

    function syncPeriodChips() {
        document.querySelectorAll('.ia-chips .inv-chip').forEach((chip) => {
            const active = (chip.dataset.period || 'month') === activePeriod;
            chip.classList.toggle('active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function updateLastUpdated() {
        const el = $('lastUpdated');
        if (!el || !lastFetchAt) return;
        const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        el.textContent = t('last_updated', time);
    }

    function periodLabel(period) {
        return t(PERIOD_KEYS[period] || 'period_month');
    }

    function movementLabel(type) {
        const key = MOVEMENT_KEYS[type];
        return key ? t(key) : (type || '—');
    }

    function chartColors() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: isDark ? '#374151' : '#e5e7eb',
            text: isDark ? '#9ca3af' : '#6b7280',
            primary: '#2563eb',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
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

    function baseChartOptions(c) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { color: c.text, boxWidth: 12 } } },
        };
    }

    function renderTrendChart(trend) {
        const ctx = $('movementTrendChart');
        if (!ctx || typeof Chart === 'undefined') return;
        const c = chartColors();
        destroyChart('movementTrendChart');

        const labels = trend?.labels || [];
        if (!labels.length) {
            charts.movementTrendChart = new Chart(ctx, {
                type: 'line',
                data: { labels: ['—'], datasets: [{ data: [0], borderColor: c.grid }] },
                options: { ...baseChartOptions(c), plugins: { legend: { display: false } } },
            });
            return;
        }

        charts.movementTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: t('stat_total_in'),
                        data: trend.stock_in || [],
                        borderColor: c.success,
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        tension: 0.35,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 2,
                    },
                    {
                        label: t('stat_total_out'),
                        data: trend.stock_out || [],
                        borderColor: c.danger,
                        backgroundColor: 'rgba(239, 68, 68, 0.08)',
                        tension: 0.35,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 2,
                    },
                ],
            },
            options: {
                ...baseChartOptions(c),
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: c.grid },
                        ticks: { color: c.text, callback: (v) => Number(v).toLocaleString(locale) },
                    },
                    x: { grid: { display: false }, ticks: { color: c.text, maxRotation: 45 } },
                },
            },
        });
    }

    function renderTypesChart(rows) {
        const ctx = $('movementTypesChart');
        if (!ctx || typeof Chart === 'undefined') return;
        const c = chartColors();
        destroyChart('movementTypesChart');

        const labels = (rows || []).map((r) => movementLabel(r.movement_type));
        const data = (rows || []).map((r) => parseInt(r.count, 10) || 0);

        charts.movementTypesChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels.length ? labels : ['—'],
                datasets: [{
                    data: data.length ? data : [1],
                    backgroundColor: c.palette,
                    borderWidth: 0,
                }],
            },
            options: {
                ...baseChartOptions(c),
                plugins: { legend: { position: 'bottom', labels: { color: c.text, padding: 12 } } },
            },
        });
    }

    function renderTopProductsChart(rows) {
        const ctx = $('topProductsChart');
        if (!ctx || typeof Chart === 'undefined') return;
        const c = chartColors();
        destroyChart('topProductsChart');

        const items = (rows || []).slice(0, 8);
        const labels = items.map((r) => {
            const name = r.name || '—';
            return name.length > 22 ? `${name.slice(0, 20)}…` : name;
        });
        const data = items.map((r) => parseInt(r.sold_units, 10) || 0);

        charts.topProductsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels.length ? labels : ['—'],
                datasets: [{
                    label: t('col_sold_units'),
                    data: data.length ? data : [0],
                    backgroundColor: c.primary,
                    borderRadius: 6,
                }],
            },
            options: {
                ...baseChartOptions(c),
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: c.grid },
                        ticks: { color: c.text },
                    },
                    y: { grid: { display: false }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderStockChart(status) {
        const ctx = $('stockStatusChart');
        if (!ctx || typeof Chart === 'undefined') return;
        const c = chartColors();
        destroyChart('stockStatusChart');

        const values = [
            parseInt(status?.in_stock, 10) || 0,
            parseInt(status?.low_stock, 10) || 0,
            parseInt(status?.out_of_stock, 10) || 0,
        ];

        charts.stockStatusChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: [t('stock_status_in_stock'), t('stock_status_low'), t('stock_status_out')],
                datasets: [{
                    data: values,
                    backgroundColor: [c.success, c.warning, c.danger],
                    borderWidth: 2,
                    borderColor: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1f2937' : '#fff',
                }],
            },
            options: {
                ...baseChartOptions(c),
                plugins: { legend: { position: 'bottom', labels: { color: c.text } } },
            },
        });
    }

    function renderStats(stats) {
        setStatsLoading(false);
        if (!stats) return;

        const movementsEl = $('stat-movements-val');
        if (movementsEl) {
            movementsEl.textContent = fmtNum(stats.total_movements);
            clearKpiLoading(movementsEl);
        }
        const profitEl = $('stat-profit-val');
        if (profitEl) {
            profitEl.textContent = AdminAPI.formatCurrency(stats.estimated_profit ?? 0);
            clearKpiLoading(profitEl);
        }
        const lowEl = $('stat-low-stock-val');
        if (lowEl) {
            lowEl.textContent = fmtNum(stats.low_stock);
            clearKpiLoading(lowEl);
        }
        const valueEl = $('stat-inventory-value-val');
        if (valueEl) {
            valueEl.textContent = AdminAPI.formatCurrency(stats.inventory_value ?? 0);
            clearKpiLoading(valueEl);
        }

        const flowMeta = $('ia-kpi-flow-meta');
        if (flowMeta) {
            flowMeta.textContent = `${t('stat_total_in')}: ${fmtNum(stats.total_in)} · ${t('stat_total_out')}: ${fmtNum(stats.total_out)}`;
        }

        updateLowStockAlert(parseInt(stats.low_stock, 10) || 0);
        updateDateHeader();
    }

    function fmtNum(n) {
        return Number(n || 0).toLocaleString(locale);
    }

    function renderMovementTable(rows) {
        const body = $('movementTypesBody');
        if (!body) return;

        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="4" class="ad-empty-row">${t('no_analytics_data')}</td></tr>`;
            return;
        }

        const lbl = movementTableLabels();
        body.innerHTML = rows.map((row) => `
            <tr>
                <td data-label="${escapeAttr(lbl.type)}"><strong>${escapeHtml(movementLabel(row.movement_type))}</strong></td>
                <td data-label="${escapeAttr(lbl.count)}">${escapeHtml(fmtNum(row.count))}</td>
                <td class="num" data-label="${escapeAttr(lbl.stockIn)}">${escapeHtml(fmtNum(row.total_in))}</td>
                <td class="num" data-label="${escapeAttr(lbl.stockOut)}">${escapeHtml(fmtNum(row.total_out))}</td>
            </tr>
        `).join('');
    }

    function renderTopTable(rows) {
        const body = $('topProductsBody');
        if (!body) return;

        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="4" class="ad-empty-row">${t('no_analytics_data')}</td></tr>`;
            return;
        }

        const lbl = topProductLabels();
        body.innerHTML = rows.map((row) => `
            <tr>
                <td data-label="${escapeAttr(lbl.product)}"><strong>${escapeHtml(row.name || '—')}</strong></td>
                <td data-label="${escapeAttr(lbl.units)}" style="font-weight:600;">${escapeHtml(fmtNum(row.sold_units))}</td>
                <td data-label="${escapeAttr(lbl.value)}">${escapeHtml(AdminAPI.formatCurrency(row.sold_value ?? 0))}</td>
                <td data-label="${escapeAttr(lbl.profit)}">${escapeHtml(AdminAPI.formatCurrency(row.profit_value ?? 0))}</td>
            </tr>
        `).join('');
    }

    function renderAll(data) {
        const stats = data?.stats || {};
        renderStats(stats);
        renderTrendChart(data?.daily_trend || {});
        renderTypesChart(data?.movement_by_type || []);
        renderTopProductsChart(data?.top_products || []);
        renderStockChart(data?.stock_status || {});
        renderMovementTable(data?.movement_by_type || []);
        renderTopTable(data?.top_products || []);
    }

    async function loadAnalytics() {
        const btn = $('refreshAnalyticsBtn');
        setStatsLoading(true);
        btn?.classList.add('spinning');

        try {
            const result = await AdminAPI.getInventoryAnalytics({ period: activePeriod });
            if (result.status !== 'success') {
                showError(result.message || t('load_error'));
                analyticsData = null;
                destroyAllCharts();
                return;
            }

            hideError();
            analyticsData = result.data || {};
            lastFetchAt = new Date();
            updateLastUpdated();
            renderAll(analyticsData);
        } catch (e) {
            console.error(e);
            showError(t('connection_error'));
            setStatsLoading(false);
            destroyAllCharts();
        } finally {
            btn?.classList.remove('spinning');
        }
    }

    function applyPeriod(period) {
        activePeriod = period;
        syncPeriodChips();
        loadAnalytics();
    }

    function bindEvents() {
        $('refreshAnalyticsBtn')?.addEventListener('click', loadAnalytics);

        document.addEventListener('store-switched', () => loadAnalytics());

        document.querySelectorAll('.ia-chips .inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => applyPeriod(chip.dataset.period || 'month'));
        });

        document.getElementById('theme-toggle')?.addEventListener('click', () => {
            setTimeout(() => {
                if (analyticsData) renderAll(analyticsData);
            }, 80);
        });
    }

    async function init() {
        if (typeof Chart === 'undefined') {
            showError(t('load_error'));
            return;
        }
        updateDateHeader();
        syncPeriodChips();
        bindEvents();
        await loadAnalytics();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
