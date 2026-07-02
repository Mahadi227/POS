/**
 * Accounting analytics v2 — trends, insights, branch comparison
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('accAnCharts')) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;

    const CATEGORY_COLORS = ['#059669', '#2563eb', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#ca8a04', '#64748b'];
    const TREASURY_COLORS = { cash: '#059669', bank: '#2563eb', mobile: '#7c3aed' };

    const state = { period: 'month', data: null, charts: {} };

    const els = {
        periodLabel: document.getElementById('accAnPeriodLabel'),
        storeScope: document.getElementById('accAnStoreScope'),
        statRevenue: document.getElementById('accAnStatRevenue'),
        statExpenses: document.getElementById('accAnStatExpenses'),
        statNet: document.getElementById('accAnStatNet'),
        statMargin: document.getElementById('accAnStatMargin'),
        avgRevenue: document.getElementById('accAnAvgRevenue'),
        avgExpense: document.getElementById('accAnAvgExpense'),
        grossMargin: document.getElementById('accAnGrossMargin'),
        expenseRatio: document.getElementById('accAnExpenseRatio'),
        dateFrom: document.getElementById('accAnDateFrom'),
        dateTo: document.getElementById('accAnDateTo'),
        periodTabs: document.getElementById('accAnPeriod'),
        exportBtn: document.getElementById('accAnExportBtn'),
        refreshBtn: document.getElementById('accAnRefreshBtn'),
        branchMeta: document.getElementById('accAnBranchMeta'),
        branchRoot: document.getElementById('accAnBranchRoot'),
        combinedEmpty: document.getElementById('accAnCombinedEmpty'),
        revenueEmpty: document.getElementById('accAnRevenueEmpty'),
        expenseEmpty: document.getElementById('accAnExpenseEmpty'),
        breakdownEmpty: document.getElementById('accAnBreakdownEmpty'),
        treasuryEmpty: document.getElementById('accAnTreasuryEmpty'),
        branchesEmpty: document.getElementById('accAnBranchesEmpty'),
        breakdownLegend: document.getElementById('accAnBreakdownLegend'),
        treasuryLegend: document.getElementById('accAnTreasuryLegend'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: dark ? 'rgba(148,163,184,0.12)' : 'rgba(0,0,0,0.06)',
            text: dark ? '#9ca3af' : '#6b7280',
        };
    }

    function formatDayLabel(day) {
        if (!day) return '';
        const d = new Date(`${day}T12:00:00`);
        return d.toLocaleDateString(window.ADMIN_CONFIG?.locale || 'fr-FR', { day: '2-digit', month: 'short' });
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
        const locale = window.ADMIN_CONFIG?.locale || 'fr-FR';
        const f = new Date(`${from}T12:00:00`).toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' });
        const tDate = new Date(`${to}T12:00:00`).toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' });
        return `${f} — ${tDate}`;
    }

    function treasuryLabel(key) {
        const map = { cash: t('kpi_cash'), bank: t('kpi_bank'), mobile: t('kpi_mobile') };
        return map[key] || key;
    }

    function pct(n) {
        return `${Number(n || 0).toLocaleString(window.ADMIN_CONFIG?.locale || 'fr-FR', { maximumFractionDigits: 1 })}%`;
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-an-stat__value, .acc-an-insight__value').forEach((el) => {
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

    function renderLineChart(id, rows, label, color, emptyEl) {
        const ctx = document.getElementById(id);
        if (!ctx || !window.Chart) return;
        destroyChart(id);
        const hasData = hasTrendData(rows);
        if (emptyEl) emptyEl.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;
        state.charts[id] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: rows.map((x) => formatDayLabel(x.day)),
                datasets: [{
                    label,
                    data: rows.map((x) => x.amount),
                    borderColor: color,
                    backgroundColor: color.replace(')', ',0.12)').replace('rgb', 'rgba'),
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                }],
            },
            options: baseLineOptions(),
        });
    }

    function renderCombinedChart(revRows, expRows) {
        const ctx = document.getElementById('accAnCombined');
        if (!ctx || !window.Chart) return;
        destroyChart('accAnCombined');
        const hasData = hasTrendData(revRows) || hasTrendData(expRows);
        if (els.combinedEmpty) els.combinedEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const labels = [...new Set([...(revRows || []), ...(expRows || [])].map((x) => x.day))].sort();
        const revMap = Object.fromEntries((revRows || []).map((x) => [x.day, x.amount]));
        const expMap = Object.fromEntries((expRows || []).map((x) => [x.day, x.amount]));

        state.charts.accAnCombined = new Chart(ctx, {
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

    function renderDonut(id, items, labelFn, colorFn, legendEl, emptyEl) {
        const ctx = document.getElementById(id);
        if (!ctx || !window.Chart) return;
        destroyChart(id);
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (emptyEl) emptyEl.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (legendEl) {
            legendEl.innerHTML = hasData
                ? filtered.map((item, i) => {
                    const color = typeof colorFn === 'function' ? colorFn(item, i) : colorFn[i % colorFn.length];
                    const label = typeof labelFn === 'function' ? labelFn(item) : labelFn;
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(label)}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;
        state.charts[id] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map(labelFn),
                datasets: [{
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((item, i) => (typeof colorFn === 'function' ? colorFn(item, i) : colorFn[i % colorFn.length])),
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

    function renderBranchChart(branches) {
        const ctx = document.getElementById('accAnBranches');
        if (!ctx || !window.Chart) return;
        destroyChart('accAnBranches');
        const rows = (branches || []).filter((b) => Number(b.revenue) > 0 || Number(b.expenses) > 0);
        const hasData = rows.length > 0;
        if (els.branchesEmpty) els.branchesEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;
        const c = chartColors();
        state.charts.accAnBranches = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: rows.map((b) => b.name),
                datasets: [
                    { label: t('kpi_revenue'), data: rows.map((b) => b.revenue), backgroundColor: 'rgba(5,150,105,0.75)', borderRadius: 6 },
                    { label: t('kpi_expenses'), data: rows.map((b) => b.expenses), backgroundColor: 'rgba(217,119,6,0.75)', borderRadius: 6 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top', labels: { color: c.text } },
                    tooltip: {
                        callbacks: { label(ctx) { return `${ctx.dataset.label}: ${money(ctx.parsed.y)}`; } },
                    },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: c.text } },
                    y: { grid: { color: c.grid }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderBranchTable(branches) {
        if (!els.branchRoot) return;
        if (!branches?.length) {
            els.branchRoot.innerHTML = `<div class="acc-empty"><span class="material-icons-round">store</span><p>${esc(t('no_data'))}</p></div>`;
            if (els.branchMeta) els.branchMeta.textContent = t('no_data');
            return;
        }
        if (els.branchMeta) els.branchMeta.textContent = t('dash_branch_meta', branches.length);
        const rows = branches.map((b) => ({ ...b, net: Number(b.revenue || 0) - Number(b.expenses || 0) }));
        els.branchRoot.innerHTML = `
            <div class="acc-an-table-wrap">
                <table class="modern-table acc-table">
                    <thead><tr>
                        <th>${esc(t('branch'))}</th>
                        <th>${esc(t('kpi_revenue'))}</th>
                        <th>${esc(t('kpi_expenses'))}</th>
                        <th>${esc(t('kpi_net_profit'))}</th>
                    </tr></thead>
                    <tbody>${rows.map((b) => `<tr>
                        <td>${esc(b.name)}</td>
                        <td>${esc(money(b.revenue))}</td>
                        <td>${esc(money(b.expenses))}</td>
                        <td class="${b.net >= 0 ? 'acc-cell--pos' : 'acc-cell--neg'}">${esc(money(b.net))}</td>
                    </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-an-branch-cards">${rows.map((b) => `
                <article class="acc-an-branch-card">
                    <h4>${esc(b.name)}</h4>
                    <dl>
                        <div><dt>${esc(t('kpi_revenue'))}</dt><dd>${esc(money(b.revenue))}</dd></div>
                        <div><dt>${esc(t('kpi_expenses'))}</dt><dd>${esc(money(b.expenses))}</dd></div>
                        <div><dt>${esc(t('kpi_net_profit'))}</dt><dd class="${b.net >= 0 ? 'acc-cell--pos' : 'acc-cell--neg'}">${esc(money(b.net))}</dd></div>
                    </dl>
                </article>`).join('')}
            </div>`;
    }

    function renderHero(data) {
        const h = data.hero || {};
        const ins = data.insights || {};
        const s = data.summary || {};
        if (els.statRevenue) els.statRevenue.textContent = money(h.total_revenue ?? s.total_revenue);
        if (els.statExpenses) els.statExpenses.textContent = money(h.total_expenses ?? s.total_expenses);
        if (els.statNet) els.statNet.textContent = money(h.net_profit ?? s.net_profit);
        if (els.statMargin) els.statMargin.textContent = pct(ins.profit_margin);
        if (els.avgRevenue) els.avgRevenue.textContent = money(ins.avg_daily_revenue);
        if (els.avgExpense) els.avgExpense.textContent = money(ins.avg_daily_expense);
        if (els.grossMargin) els.grossMargin.textContent = pct(ins.gross_margin);
        if (els.expenseRatio) els.expenseRatio.textContent = pct(ins.expense_ratio);
        document.querySelectorAll('.acc-an-stat__value, .acc-an-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.periodLabel) els.periodLabel.textContent = periodLabelText();
        if (els.storeScope) {
            els.storeScope.textContent = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
        }
    }

    function renderCharts(data) {
        const charts = data.charts || {};
        renderCombinedChart(charts.revenue_trend, charts.expense_trend);
        renderLineChart('accAnRevenue', charts.revenue_trend, t('kpi_revenue'), '#059669', els.revenueEmpty);
        renderLineChart('accAnExpense', charts.expense_trend, t('kpi_expenses'), '#d97706', els.expenseEmpty);
        renderDonut(
            'accAnBreakdown',
            (charts.expense_by_category || []).map((x) => ({ category: x.category, amount: x.amount })),
            (x) => x.category || '—',
            (_, i) => CATEGORY_COLORS[i % CATEGORY_COLORS.length],
            els.breakdownLegend,
            els.breakdownEmpty,
        );
        renderDonut(
            'accAnTreasury',
            data.treasury_mix || [],
            (x) => treasuryLabel(x.key),
            (x) => TREASURY_COLORS[x.key] || '#64748b',
            els.treasuryLegend,
            els.treasuryEmpty,
        );
        renderBranchChart(data.branch_comparison);
        renderBranchTable(data.branch_comparison);
    }

    async function load() {
        hideError();
        setLoading(true);
        if (els.branchRoot) {
            els.branchRoot.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        }
        try {
            const res = await AdminAPI.getAccounting('analytics', {
                from: els.dateFrom?.value || '',
                to: els.dateTo?.value || '',
            });
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? res.data?.module_ready ?? true);
            state.data = res.data;
            destroyAllCharts();
            renderHero(res.data);
            renderCharts(res.data);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            if (els.branchRoot) {
                els.branchRoot.innerHTML = `<div class="acc-empty"><p>${esc(e.message)}</p></div>`;
            }
        }
    }

    function exportData() {
        if (!state.data) return;
        const d = state.data;
        const ins = d.insights || {};
        const s = d.summary || {};
        const rows = [
            [t('nav_analytics'), periodLabelText()],
            [],
            [t('kpi_revenue'), s.total_revenue],
            [t('kpi_expenses'), s.total_expenses],
            [t('kpi_net_profit'), s.net_profit],
            [t('an_stat_margin'), ins.profit_margin],
            [t('an_insight_avg_revenue'), ins.avg_daily_revenue],
            [t('an_insight_avg_expense'), ins.avg_daily_expense],
            [],
            [t('branch'), t('kpi_revenue'), t('kpi_expenses'), t('kpi_net_profit')],
        ];
        (d.branch_comparison || []).forEach((b) => {
            rows.push([b.name, b.revenue, b.expenses, Number(b.revenue || 0) - Number(b.expenses || 0)]);
        });
        exportCsv(`accounting-analytics-${els.dateTo?.value || 'export'}.csv`, rows);
    }

    function setPeriod(period) {
        state.period = period;
        const range = periodRange(period);
        if (els.dateFrom) els.dateFrom.value = range.from;
        if (els.dateTo) els.dateTo.value = range.to;
        els.periodTabs?.querySelectorAll('.acc-an-chip').forEach((chip) => {
            const active = chip.dataset.period === period;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        load();
    }

    els.periodTabs?.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-period]');
        if (!chip) return;
        setPeriod(chip.dataset.period);
    });

    [els.dateFrom, els.dateTo].forEach((input) => {
        input?.addEventListener('change', () => {
            els.periodTabs?.querySelectorAll('.acc-an-chip').forEach((c) => c.classList.remove('is-active'));
            load();
        });
    });

    els.exportBtn?.addEventListener('click', exportData);
    els.refreshBtn?.addEventListener('click', load);
    document.addEventListener('acc:refresh', load);

    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        setTimeout(() => { if (state.data) { destroyAllCharts(); renderCharts(state.data); } }, 120);
    });

    setPeriod('month');
});
