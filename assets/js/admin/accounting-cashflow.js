/**
 * Accounting cash flow v1 — inflows, outflows, treasury, trends
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('accCfHeroStats')) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;

    const TREASURY_COLORS = { cash: '#059669', bank: '#2563eb', mobile: '#7c3aed' };

    const state = { period: 'month', data: null, charts: {} };

    const els = {
        periodLabel: document.getElementById('accCfPeriodLabel'),
        storeScope: document.getElementById('accCfStoreScope'),
        statIn: document.getElementById('accCfStatIn'),
        statOut: document.getElementById('accCfStatOut'),
        statNet: document.getElementById('accCfStatNet'),
        statTreasury: document.getElementById('accCfStatTreasury'),
        avgIn: document.getElementById('accCfAvgIn'),
        avgOut: document.getElementById('accCfAvgOut'),
        ratio: document.getElementById('accCfRatio'),
        runway: document.getElementById('accCfRunway'),
        dateFrom: document.getElementById('accCfDateFrom'),
        dateTo: document.getElementById('accCfDateTo'),
        periodTabs: document.getElementById('accCfPeriod'),
        exportBtn: document.getElementById('accCfExportBtn'),
        printBtn: document.getElementById('accCfPrintBtn'),
        refreshBtn: document.getElementById('accCfRefreshBtn'),
        detailRoot: document.getElementById('accCfDetailRoot'),
        combinedEmpty: document.getElementById('accCfCombinedEmpty'),
        netEmpty: document.getElementById('accCfNetEmpty'),
        treasuryEmpty: document.getElementById('accCfTreasuryEmpty'),
        treasuryLegend: document.getElementById('accCfTreasuryLegend'),
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

    function setLoading(on) {
        document.querySelectorAll('.acc-cf-stat__value, .acc-cf-insight__value').forEach((el) => {
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
        return rows?.some((x) => Number(x.amount) !== 0);
    }

    function renderCombinedChart(inRows, outRows) {
        const ctx = document.getElementById('accCfCombined');
        if (!ctx || !window.Chart) return;
        destroyChart('accCfCombined');
        const hasData = hasTrendData(inRows) || hasTrendData(outRows);
        if (els.combinedEmpty) els.combinedEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const labels = [...new Set([...(inRows || []), ...(outRows || [])].map((x) => x.day))].sort();
        const inMap = Object.fromEntries((inRows || []).map((x) => [x.day, x.amount]));
        const outMap = Object.fromEntries((outRows || []).map((x) => [x.day, x.amount]));

        state.charts.accCfCombined = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.map(formatDayLabel),
                datasets: [
                    {
                        label: t('cash_in'),
                        data: labels.map((d) => inMap[d] || 0),
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5,150,105,0.08)',
                        fill: true,
                        tension: 0.35,
                    },
                    {
                        label: t('cash_out'),
                        data: labels.map((d) => outMap[d] || 0),
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

    function renderNetChart(rows) {
        const ctx = document.getElementById('accCfNet');
        if (!ctx || !window.Chart) return;
        destroyChart('accCfNet');
        const hasData = hasTrendData(rows);
        if (els.netEmpty) els.netEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        state.charts.accCfNet = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: (rows || []).map((x) => formatDayLabel(x.day)),
                datasets: [{
                    label: t('net_cash_flow'),
                    data: (rows || []).map((x) => x.amount),
                    backgroundColor: (rows || []).map((x) => (Number(x.amount) >= 0 ? 'rgba(5,150,105,0.75)' : 'rgba(220,38,38,0.75)')),
                    borderRadius: 6,
                }],
            },
            options: {
                ...baseLineOptions(),
                plugins: {
                    ...baseLineOptions().plugins,
                    legend: { display: false },
                },
            },
        });
    }

    function renderTreasuryDonut(items) {
        const ctx = document.getElementById('accCfTreasury');
        if (!ctx || !window.Chart) return;
        destroyChart('accCfTreasury');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.treasuryEmpty) els.treasuryEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (els.treasuryLegend) {
            els.treasuryLegend.innerHTML = hasData
                ? filtered.map((item) => {
                    const color = TREASURY_COLORS[item.key] || '#64748b';
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(treasuryLabel(item.key))}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.charts.accCfTreasury = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map((x) => treasuryLabel(x.key)),
                datasets: [{
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((x) => TREASURY_COLORS[x.key] || '#64748b'),
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

    function renderHero(data) {
        if (els.statIn) els.statIn.textContent = money(data.cash_in?.total);
        if (els.statOut) els.statOut.textContent = money(data.cash_out?.total);
        if (els.statNet) {
            els.statNet.textContent = money(data.net_cash_flow);
            els.statNet.classList.toggle('acc-cell--pos', Number(data.net_cash_flow) >= 0);
            els.statNet.classList.toggle('acc-cell--neg', Number(data.net_cash_flow) < 0);
        }
        if (els.statTreasury) els.statTreasury.textContent = money(data.treasury_total);

        const ins = data.insights || {};
        if (els.avgIn) els.avgIn.textContent = money(ins.avg_daily_in);
        if (els.avgOut) els.avgOut.textContent = money(ins.avg_daily_out);
        if (els.ratio) {
            const ratio = Number(ins.in_out_ratio || 0);
            els.ratio.textContent = ratio > 0 ? `${ratio.toLocaleString(window.ADMIN_CONFIG?.locale || 'fr-FR', { maximumFractionDigits: 2 })}×` : '—';
        }
        if (els.runway) {
            els.runway.textContent = ins.treasury_runway_days != null
                ? `${ins.treasury_runway_days} ${t('cf_runway_days')}`
                : '—';
        }

        document.querySelectorAll('.acc-cf-stat__value, .acc-cf-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.periodLabel) els.periodLabel.textContent = periodLabelText();
        if (els.storeScope) {
            els.storeScope.textContent = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
        }
    }

    function renderDetail(data) {
        if (!els.detailRoot) return;
        const bal = data.balances || {};
        const out = data.outstanding || {};
        const netClass = Number(data.net_cash_flow) >= 0 ? 'acc-cell--pos' : 'acc-cell--neg';

        els.detailRoot.innerHTML = `
            <div class="acc-cf-cf-grid">
                <section class="acc-cf-section">
                    <h4 class="acc-cf-section__title">${esc(t('rpt_cash_in_section'))}</h4>
                    <dl class="acc-cf-dl">
                        <div><dt>${esc(t('rpt_sales_in'))}</dt><dd>${esc(money(data.cash_in?.sales))}</dd></div>
                        <div><dt>${esc(t('rpt_ar_collected'))}</dt><dd>${esc(money(data.cash_in?.receivables_collected))}</dd></div>
                        <div class="acc-cf-dl__total"><dt>${esc(t('cash_in'))}</dt><dd>${esc(money(data.cash_in?.total))}</dd></div>
                    </dl>
                </section>
                <section class="acc-cf-section">
                    <h4 class="acc-cf-section__title">${esc(t('rpt_cash_out_section'))}</h4>
                    <dl class="acc-cf-dl">
                        <div><dt>${esc(t('rpt_expenses_out'))}</dt><dd>${esc(money(data.cash_out?.expenses))}</dd></div>
                        <div><dt>${esc(t('rpt_ap_paid'))}</dt><dd>${esc(money(data.cash_out?.payables_paid))}</dd></div>
                        <div class="acc-cf-dl__total"><dt>${esc(t('cash_out'))}</dt><dd>${esc(money(data.cash_out?.total))}</dd></div>
                    </dl>
                </section>
            </div>
            <section class="acc-cf-section acc-cf-section--full">
                <h4 class="acc-cf-section__title">${esc(t('rpt_treasury_balances'))}</h4>
                <div class="acc-cf-cards">
                    <article class="acc-cf-card"><span>${esc(t('kpi_cash'))}</span><strong>${esc(money(bal.cash))}</strong></article>
                    <article class="acc-cf-card"><span>${esc(t('kpi_bank'))}</span><strong>${esc(money(bal.bank))}</strong></article>
                    <article class="acc-cf-card"><span>${esc(t('kpi_mobile'))}</span><strong>${esc(money(bal.mobile_money))}</strong></article>
                    <article class="acc-cf-card acc-cf-card--highlight">
                        <span>${esc(t('net_cash_flow'))}</span>
                        <strong class="${netClass}">${esc(money(data.net_cash_flow))}</strong>
                    </article>
                </div>
            </section>
            <section class="acc-cf-section acc-cf-section--full">
                <h4 class="acc-cf-section__title">${esc(t('cf_outstanding_title'))}</h4>
                <div class="acc-cf-cards acc-cf-cards--2">
                    <article class="acc-cf-card acc-cf-card--success">
                        <span>${esc(t('cf_insight_receivable'))}</span>
                        <strong>${esc(money(out.receivable))}</strong>
                    </article>
                    <article class="acc-cf-card acc-cf-card--warn">
                        <span>${esc(t('cf_insight_payable'))}</span>
                        <strong>${esc(money(out.payable))}</strong>
                    </article>
                </div>
            </section>`;
    }

    function renderCharts(data) {
        const charts = data.charts || {};
        renderCombinedChart(charts.cash_in_trend, charts.cash_out_trend);
        renderNetChart(charts.net_trend);
        renderTreasuryDonut(data.treasury_mix);
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
            const res = await AdminAPI.getAccounting('cashflow', queryParams());
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            state.data = res.data || {};
            renderHero(state.data);
            renderCharts(state.data);
            renderDetail(state.data);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyAllCharts();
            if (els.detailRoot) {
                els.detailRoot.innerHTML = `<div class="acc-empty"><span class="material-icons-round">payments</span><p>${esc(e.message)}</p></div>`;
            }
        }
    }

    function exportData() {
        if (!state.data) return;
        const d = state.data;
        const q = queryParams();
        const rows = [
            [t('nav_cashflow'), periodLabelText()],
            [],
            [t('rpt_sales_in'), d.cash_in?.sales],
            [t('rpt_ar_collected'), d.cash_in?.receivables_collected],
            [t('cash_in'), d.cash_in?.total],
            [t('rpt_expenses_out'), d.cash_out?.expenses],
            [t('rpt_ap_paid'), d.cash_out?.payables_paid],
            [t('cash_out'), d.cash_out?.total],
            [t('net_cash_flow'), d.net_cash_flow],
            [],
            [t('kpi_cash'), d.balances?.cash],
            [t('kpi_bank'), d.balances?.bank],
            [t('kpi_mobile'), d.balances?.mobile_money],
            [t('cf_stat_treasury'), d.treasury_total],
            [],
            [t('cf_insight_receivable'), d.outstanding?.receivable],
            [t('cf_insight_payable'), d.outstanding?.payable],
        ];
        exportCsv(`cashflow-${q.to || 'export'}.csv`, rows);
    }

    function applyPeriod(period) {
        state.period = period;
        const range = periodRange(period);
        if (els.dateFrom) els.dateFrom.value = range.from;
        if (els.dateTo) els.dateTo.value = range.to;
        els.periodTabs?.querySelectorAll('.acc-cf-chip').forEach((chip) => {
            const active = chip.dataset.period === period;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        load();
    }

    els.periodTabs?.querySelectorAll('.acc-cf-chip').forEach((chip) => {
        chip.addEventListener('click', () => applyPeriod(chip.dataset.period || 'month'));
    });

    els.dateFrom?.addEventListener('change', () => {
        els.periodTabs?.querySelectorAll('.acc-cf-chip').forEach((c) => c.classList.remove('is-active'));
        load();
    });
    els.dateTo?.addEventListener('change', () => {
        els.periodTabs?.querySelectorAll('.acc-cf-chip').forEach((c) => c.classList.remove('is-active'));
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
