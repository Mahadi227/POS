/**
 * Accounting profit & loss v1 — revenue, margins, expense breakdown
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('accPlHeroStats')) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;

    const CATEGORY_COLORS = ['#059669', '#2563eb', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#ca8a04', '#64748b'];

    const state = { period: 'month', data: null, charts: {} };

    const els = {
        periodLabel: document.getElementById('accPlPeriodLabel'),
        storeScope: document.getElementById('accPlStoreScope'),
        statRevenue: document.getElementById('accPlStatRevenue'),
        statGross: document.getElementById('accPlStatGross'),
        statExpenses: document.getElementById('accPlStatExpenses'),
        statNet: document.getElementById('accPlStatNet'),
        grossMargin: document.getElementById('accPlGrossMargin'),
        netMargin: document.getElementById('accPlNetMargin'),
        expenseRatio: document.getElementById('accPlExpenseRatio'),
        avgProfit: document.getElementById('accPlAvgProfit'),
        dateFrom: document.getElementById('accPlDateFrom'),
        dateTo: document.getElementById('accPlDateTo'),
        periodTabs: document.getElementById('accPlPeriod'),
        exportBtn: document.getElementById('accPlExportBtn'),
        printBtn: document.getElementById('accPlPrintBtn'),
        refreshBtn: document.getElementById('accPlRefreshBtn'),
        detailRoot: document.getElementById('accPlDetailRoot'),
        combinedEmpty: document.getElementById('accPlCombinedEmpty'),
        categoriesEmpty: document.getElementById('accPlCategoriesEmpty'),
        categoriesLegend: document.getElementById('accPlCategoriesLegend'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: dark ? 'rgba(148,163,184,0.12)' : 'rgba(0,0,0,0.06)',
            text: dark ? '#9ca3af' : '#6b7280',
        };
    }

    function locale() {
        return window.ADMIN_CONFIG?.locale || 'fr-FR';
    }

    function pct(n) {
        return `${Number(n || 0).toLocaleString(locale(), { maximumFractionDigits: 1 })}%`;
    }

    function periodRange(period) {
        const to = new Date();
        const from = new Date(to);
        if (period === 'week') {
            from.setDate(to.getDate() - 6);
        } else if (period === 'quarter') {
            from.setMonth(Math.floor(to.getMonth() / 3) * 3, 1);
        } else if (period === 'year') {
            from.setMonth(0, 1);
        } else {
            from.setDate(1);
        }
        const fmt = (d) => d.toISOString().slice(0, 10);
        return { from: fmt(from), to: fmt(to) };
    }

    function periodLabelText() {
        const from = els.dateFrom?.value;
        const to = els.dateTo?.value;
        if (!from || !to) return '—';
        const f = new Date(`${from}T12:00:00`).toLocaleDateString(locale(), { day: 'numeric', month: 'short', year: 'numeric' });
        const tDate = new Date(`${to}T12:00:00`).toLocaleDateString(locale(), { day: 'numeric', month: 'short', year: 'numeric' });
        return `${f} — ${tDate}`;
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-pl-stat__value, .acc-pl-insight__value').forEach((el) => {
            el.classList.toggle('is-loading', on);
        });
    }

    function destroyChart(id) {
        if (state.charts[id]) {
            state.charts[id].destroy();
            delete state.charts[id];
        }
    }

    function destroyAllCharts() {
        Object.keys(state.charts).forEach(destroyChart);
    }

    function baseLineOptions() {
        const c = chartColors();
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            return `${ctx.dataset.label || ''}: ${money(ctx.parsed?.y ?? 0)}`;
                        },
                    },
                },
            },
            scales: {
                x: { grid: { color: c.grid }, ticks: { color: c.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                y: { grid: { color: c.grid }, ticks: { color: c.text } },
            },
        };
    }

    function hasTrendData(rows) {
        return rows?.some((x) => Number(x.amount) > 0);
    }

    function formatDayLabel(day) {
        if (!day) return '';
        const d = new Date(`${day}T12:00:00`);
        return d.toLocaleDateString(locale(), { day: '2-digit', month: 'short' });
    }

    function barPct(value, max) {
        const m = Math.abs(Number(max) || 1);
        const v = Math.abs(Number(value) || 0);
        return Math.min(100, Math.round((v / m) * 100));
    }

    function renderCombinedChart(revRows, expRows) {
        const ctx = document.getElementById('accPlCombined');
        if (!ctx || !window.Chart) return;
        destroyChart('accPlCombined');
        const hasData = hasTrendData(revRows) || hasTrendData(expRows);
        if (els.combinedEmpty) els.combinedEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const labels = [...new Set([...(revRows || []), ...(expRows || [])].map((x) => x.day))].sort();
        const revMap = Object.fromEntries((revRows || []).map((x) => [x.day, x.amount]));
        const expMap = Object.fromEntries((expRows || []).map((x) => [x.day, x.amount]));

        state.charts.accPlCombined = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.map(formatDayLabel),
                datasets: [
                    {
                        label: t('kpi_revenue'),
                        data: labels.map((d) => revMap[d] || 0),
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5,150,105,0.08)',
                        fill: true,
                        tension: 0.35,
                    },
                    {
                        label: t('kpi_expenses'),
                        data: labels.map((d) => expMap[d] || 0),
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217,119,6,0.08)',
                        fill: true,
                        tension: 0.35,
                    },
                ],
            },
            options: {
                ...baseLineOptions(),
                plugins: {
                    ...baseLineOptions().plugins,
                    legend: { display: true, position: 'top', labels: { color: chartColors().text, boxWidth: 12 } },
                },
            },
        });
    }

    function renderCategoryDonut(rows) {
        const ctx = document.getElementById('accPlCategories');
        if (!ctx || !window.Chart) return;
        destroyChart('accPlCategories');
        const filtered = (rows || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.categoriesEmpty) els.categoriesEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (els.categoriesLegend) {
            els.categoriesLegend.innerHTML = hasData
                ? filtered.map((item, i) => {
                    const color = CATEGORY_COLORS[i % CATEGORY_COLORS.length];
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(item.category || '—')}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.charts.accPlCategories = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map((x) => x.category || '—'),
                datasets: [{
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((_, i) => CATEGORY_COLORS[i % CATEGORY_COLORS.length]),
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: { legend: { display: false } },
            },
        });
    }

    function renderWaterfall(data) {
        if (!els.detailRoot) return;
        const revenue = Number(data.revenue) || 0;
        const netClass = Number(data.net_profit) >= 0 ? 'acc-cell--pos' : 'acc-cell--neg';

        els.detailRoot.innerHTML = `
            <div class="acc-pl-waterfall">
                <div class="acc-pl-waterfall__row">
                    <span>${esc(t('kpi_revenue'))}</span>
                    <div class="acc-pl-bar acc-pl-bar--pos" style="width:${barPct(data.revenue, data.revenue)}%"></div>
                    <strong>${esc(money(data.revenue))}</strong>
                </div>
                <div class="acc-pl-waterfall__row">
                    <span>${esc(t('pl_cogs'))}</span>
                    <div class="acc-pl-bar acc-pl-bar--neg" style="width:${barPct(data.cogs, data.revenue)}%"></div>
                    <strong>−${esc(money(data.cogs))}</strong>
                </div>
                <div class="acc-pl-waterfall__row acc-pl-waterfall__row--sub">
                    <span>${esc(t('kpi_gross_profit'))}</span>
                    <strong>${esc(money(data.gross_profit))}</strong>
                </div>
                <div class="acc-pl-waterfall__row">
                    <span>${esc(t('pl_opex'))}</span>
                    <div class="acc-pl-bar acc-pl-bar--neg" style="width:${barPct(data.operating_expenses ?? data.expenses, data.revenue)}%"></div>
                    <strong>−${esc(money(data.operating_expenses ?? data.expenses))}</strong>
                </div>
                <div class="acc-pl-waterfall__row acc-pl-waterfall__row--total">
                    <span>${esc(t('kpi_net_profit'))}</span>
                    <strong class="${netClass}">${esc(money(data.net_profit))}</strong>
                </div>
            </div>
            <div class="acc-pl-summary">
                <article class="acc-pl-summary-card">
                    <span>${esc(t('kpi_revenue'))}</span>
                    <strong>${esc(money(data.revenue))}</strong>
                </article>
                <article class="acc-pl-summary-card">
                    <span>${esc(t('pl_cogs'))}</span>
                    <strong>${esc(money(data.cogs))}</strong>
                </article>
                <article class="acc-pl-summary-card acc-pl-summary-card--success">
                    <span>${esc(t('kpi_gross_profit'))}</span>
                    <strong>${esc(money(data.gross_profit))}</strong>
                </article>
                <article class="acc-pl-summary-card acc-pl-summary-card--warn">
                    <span>${esc(t('kpi_expenses'))}</span>
                    <strong>${esc(money(data.expenses))}</strong>
                </article>
                <article class="acc-pl-summary-card acc-pl-summary-card--highlight">
                    <span>${esc(t('kpi_net_profit'))}</span>
                    <strong class="${netClass}">${esc(money(data.net_profit))}</strong>
                </article>
            </div>`;
    }

    function renderHero(data) {
        if (els.statRevenue) els.statRevenue.textContent = money(data.revenue);
        if (els.statGross) els.statGross.textContent = money(data.gross_profit);
        if (els.statExpenses) els.statExpenses.textContent = money(data.expenses);
        if (els.statNet) {
            els.statNet.textContent = money(data.net_profit);
            els.statNet.classList.toggle('acc-cell--pos', Number(data.net_profit) >= 0);
            els.statNet.classList.toggle('acc-cell--neg', Number(data.net_profit) < 0);
        }

        const ins = data.insights || {};
        if (els.grossMargin) els.grossMargin.textContent = pct(ins.gross_margin);
        if (els.netMargin) els.netMargin.textContent = pct(ins.net_margin);
        if (els.expenseRatio) els.expenseRatio.textContent = pct(ins.expense_ratio);
        if (els.avgProfit) els.avgProfit.textContent = money(ins.avg_daily_profit);

        document.querySelectorAll('.acc-pl-stat__value, .acc-pl-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.periodLabel) els.periodLabel.textContent = periodLabelText();
        if (els.storeScope) {
            els.storeScope.textContent = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
        }
    }

    function renderCharts(data) {
        const charts = data.charts || {};
        renderCombinedChart(charts.revenue_trend, charts.expense_trend);
        renderCategoryDonut(charts.expense_by_category);
    }

    function queryParams() {
        return {
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
        };
    }

    async function load() {
        hideError();
        setLoading(true);
        if (els.detailRoot) {
            els.detailRoot.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        }
        try {
            const res = await AdminAPI.getAccounting('profit-loss', queryParams());
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            state.data = res.data || {};
            renderHero(state.data);
            renderCharts(state.data);
            renderWaterfall(state.data);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyAllCharts();
            if (els.detailRoot) {
                els.detailRoot.innerHTML = `<div class="acc-empty"><span class="material-icons-round">assessment</span><p>${esc(e.message)}</p></div>`;
            }
        }
    }

    function exportData() {
        if (!state.data) return;
        const d = state.data;
        const q = queryParams();
        const rows = [
            [t('nav_profit_loss'), periodLabelText()],
            [],
            [t('kpi_revenue'), d.revenue],
            [t('pl_cogs'), d.cogs],
            [t('kpi_gross_profit'), d.gross_profit],
            [t('pl_opex'), d.operating_expenses ?? d.expenses],
            [t('kpi_expenses'), d.expenses],
            [t('kpi_net_profit'), d.net_profit],
            [],
            [t('pl_insight_gross_margin'), `${d.insights?.gross_margin ?? 0}%`],
            [t('pl_insight_net_margin'), `${d.insights?.net_margin ?? 0}%`],
            [t('pl_insight_expense_ratio'), `${d.insights?.expense_ratio ?? 0}%`],
        ];
        exportCsv(`profit-loss-${q.to || 'export'}.csv`, rows);
    }

    function applyPeriod(period) {
        state.period = period;
        const range = periodRange(period);
        if (els.dateFrom) els.dateFrom.value = range.from;
        if (els.dateTo) els.dateTo.value = range.to;
        els.periodTabs?.querySelectorAll('.acc-pl-chip').forEach((chip) => {
            const active = chip.dataset.period === period;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        load();
    }

    els.periodTabs?.querySelectorAll('.acc-pl-chip').forEach((chip) => {
        chip.addEventListener('click', () => applyPeriod(chip.dataset.period || 'month'));
    });

    els.dateFrom?.addEventListener('change', () => {
        els.periodTabs?.querySelectorAll('.acc-pl-chip').forEach((c) => c.classList.remove('is-active'));
        load();
    });
    els.dateTo?.addEventListener('change', () => {
        els.periodTabs?.querySelectorAll('.acc-pl-chip').forEach((c) => c.classList.remove('is-active'));
        load();
    });

    els.refreshBtn?.addEventListener('click', load);
    els.exportBtn?.addEventListener('click', exportData);
    els.printBtn?.addEventListener('click', () => window.print());

    document.addEventListener('acc:refresh', load);
    document.addEventListener('themechange', () => {
        if (state.data) renderCharts(state.data);
    });

    load();
});
