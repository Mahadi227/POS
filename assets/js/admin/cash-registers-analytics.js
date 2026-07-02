/**
 * Cash register analytics — KPIs, charts, cashier ranking
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crCashierPerf');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = CashRegistersUI;

    const PAYMENT_COLORS = {
        cash: '#2563eb',
        card: '#7c3aed',
        mobile_money: '#059669',
        split: '#d97706',
    };

    const state = { period: 'month', data: null };
    const charts = {};

    const els = {
        periodLabel: document.getElementById('crAnalyticsPeriodLabel'),
        statRevenue: document.getElementById('crAnalyticsStatRevenue'),
        statSessions: document.getElementById('crAnalyticsStatSessions'),
        statRefunds: document.getElementById('crAnalyticsStatRefunds'),
        statAvg: document.getElementById('crAnalyticsStatAvg'),
        periodTabs: document.getElementById('crAnalyticsPeriod'),
        cashierMeta: document.getElementById('crCashierMeta'),
        exportBtn: document.getElementById('crAnalyticsExportBtn'),
        refreshBtn: document.getElementById('crAnalyticsRefreshBtn'),
        dailyEmpty: document.getElementById('crDailyEmpty'),
        registerEmpty: document.getElementById('crRegisterEmpty'),
        refundEmpty: document.getElementById('crRefundEmpty'),
        paymentEmpty: document.getElementById('crPaymentEmpty'),
        paymentLegend: document.getElementById('crPaymentLegend'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: dark ? 'rgba(148,163,184,0.12)' : 'rgba(0,0,0,0.06)',
            text: dark ? '#9ca3af' : '#6b7280',
        };
    }

    function periodLabel(period) {
        const map = {
            week: t('cr_analytics_period_week'),
            month: t('cr_analytics_period_month'),
            year: t('cr_analytics_period_year'),
        };
        return map[period] || period;
    }

    function paymentLabel(method) {
        const map = {
            cash: t('cr_stat_cash'),
            card: t('cr_stat_card'),
            mobile_money: t('cr_stat_mobile'),
            split: 'Split',
        };
        return map[method] || method;
    }

    function formatDayLabel(day) {
        if (!day) return '';
        const d = new Date(day + 'T12:00:00');
        return d.toLocaleDateString(window.ADMIN_CONFIG?.locale || 'fr-FR', { day: '2-digit', month: 'short' });
    }

    function baseLineOptions() {
        const c = chartColors();
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const val = ctx.parsed?.y ?? 0;
                            return money(val);
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { color: c.grid },
                    ticks: { color: c.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: c.grid },
                    ticks: {
                        color: c.text,
                        callback: (v) => Number(v).toLocaleString(window.ADMIN_CONFIG?.locale || 'fr-FR'),
                    },
                },
            },
        };
    }

    function baseBarOptions(horizontal = false) {
        const c = chartColors();
        const opts = baseLineOptions();
        if (horizontal) {
            opts.indexAxis = 'y';
            opts.scales.x.ticks.callback = (v) => Number(v).toLocaleString(window.ADMIN_CONFIG?.locale || 'fr-FR');
            delete opts.scales.y.ticks.callback;
        }
        return opts;
    }

    function destroyChart(key) {
        if (charts[key]) {
            charts[key].destroy();
            delete charts[key];
        }
    }

    function toggleEmpty(el, show) {
        if (el) el.hidden = !show;
    }

    function computeStats(d) {
        const revenue = (d.daily_collection || []).reduce((s, x) => s + Number(x.amount || 0), 0);
        const sessions = (d.cashier_performance || []).reduce((s, x) => s + Number(x.sessions || 0), 0);
        const refunds = (d.refund_trends || []).reduce((s, x) => s + Number(x.amount || 0), 0);
        const avg = sessions > 0 ? revenue / sessions : 0;
        return { revenue, sessions, refunds, avg };
    }

    function updateStats(d) {
        const stats = computeStats(d);
        const set = (el, val) => { if (el) { el.textContent = val; el.classList.remove('is-loading'); } };
        set(els.statRevenue, money(stats.revenue));
        set(els.statSessions, String(stats.sessions));
        set(els.statRefunds, money(stats.refunds));
        set(els.statAvg, money(stats.avg));
        if (els.periodLabel) {
            els.periodLabel.textContent = periodLabel(state.period);
        }
    }

    function renderDailyChart(d) {
        const ctx = document.getElementById('crDailyChart');
        if (!ctx || !window.Chart) return;
        destroyChart('daily');
        const rows = d.daily_collection || [];
        const hasData = rows.some((x) => Number(x.amount) > 0);
        toggleEmpty(els.dailyEmpty, !hasData);
        ctx.style.display = hasData ? '' : 'none';
        if (!hasData) return;
        charts.daily = new Chart(ctx, {
            type: 'line',
            data: {
                labels: rows.map((x) => formatDayLabel(x.day)),
                datasets: [{
                    label: t('cr_analytics_stat_revenue'),
                    data: rows.map((x) => x.amount),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.1)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    borderWidth: 2,
                }],
            },
            options: baseLineOptions(),
        });
    }

    function renderRegisterChart(d) {
        const ctx = document.getElementById('crRegisterChart');
        if (!ctx || !window.Chart) return;
        destroyChart('register');
        const useBranches = !(d.register_comparison?.length) && (d.branch_comparison?.length);
        const titleEl = document.getElementById('crRegisterChartTitle');
        if (titleEl) {
            titleEl.textContent = t(useBranches ? 'cr_analytics_chart_branches' : 'cr_analytics_chart_registers');
        }
        const rows = d.register_comparison?.length ? d.register_comparison : (d.branch_comparison || []).map((b) => ({
            name: b.name,
            revenue: b.balance,
        }));
        const hasData = rows.some((x) => Number(x.revenue ?? x.balance) > 0);
        toggleEmpty(els.registerEmpty, !hasData);
        ctx.style.display = hasData ? '' : 'none';
        if (!hasData) return;
        charts.register = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: rows.map((x) => x.name),
                datasets: [{
                    label: t('cr_analytics_stat_revenue'),
                    data: rows.map((x) => x.revenue ?? x.balance ?? 0),
                    backgroundColor: 'rgba(124, 58, 237, 0.75)',
                    borderRadius: 6,
                    maxBarThickness: 40,
                }],
            },
            options: baseBarOptions(true),
        });
    }

    function renderRefundChart(d) {
        const ctx = document.getElementById('crRefundChart');
        if (!ctx || !window.Chart) return;
        destroyChart('refund');
        const rows = d.refund_trends || [];
        const hasData = rows.some((x) => Number(x.amount) > 0);
        toggleEmpty(els.refundEmpty, !hasData);
        ctx.style.display = hasData ? '' : 'none';
        if (!hasData) return;
        charts.refund = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: rows.map((x) => formatDayLabel(x.day)),
                datasets: [{
                    label: t('cr_analytics_stat_refunds'),
                    data: rows.map((x) => x.amount),
                    backgroundColor: 'rgba(245, 158, 11, 0.75)',
                    borderRadius: 4,
                    maxBarThickness: 28,
                }],
            },
            options: baseLineOptions(),
        });
    }

    function renderPaymentChart(d) {
        const ctx = document.getElementById('crPaymentChart');
        if (!ctx || !window.Chart) return;
        destroyChart('payment');
        const payments = d.payment_breakdown || {};
        const entries = Object.entries(payments).filter(([, v]) => Number(v) > 0);
        const hasData = entries.length > 0;
        toggleEmpty(els.paymentEmpty, !hasData);
        ctx.style.display = hasData ? '' : 'none';
        if (els.paymentLegend) {
            if (!hasData) {
                els.paymentLegend.innerHTML = '';
            } else {
                const total = entries.reduce((s, [, v]) => s + Number(v), 0);
                els.paymentLegend.innerHTML = entries.map(([method, amount]) => {
                    const pct = total > 0 ? Math.round((Number(amount) / total) * 100) : 0;
                    return `<li><span class="cr-analytics-legend__dot" style="background:${PAYMENT_COLORS[method] || '#64748b'}"></span>${esc(paymentLabel(method))} <strong>${esc(money(amount))}</strong> <em>${pct}%</em></li>`;
                }).join('');
            }
        }
        if (!hasData) return;
        charts.payment = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: entries.map(([m]) => paymentLabel(m)),
                datasets: [{
                    data: entries.map(([, v]) => v),
                    backgroundColor: entries.map(([m]) => PAYMENT_COLORS[m] || '#64748b'),
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

    function renderCashierTable(d) {
        const rows = d.cashier_performance || [];
        if (els.cashierMeta) {
            els.cashierMeta.textContent = rows.length ? `${rows.length}` : '0';
        }
        if (!rows.length) {
            root.innerHTML = `<div class="cr-analytics-empty-block"><span class="material-icons-round">person_off</span><p>${esc(t('cr_no_data'))}</p></div>`;
            return;
        }
        const maxRevenue = Math.max(...rows.map((r) => Number(r.revenue || 0)), 1);
        root.innerHTML = `
            <div class="cr-analytics-table-wrap">
                <table class="modern-table cr-analytics-table">
                    <thead><tr>
                        <th>${esc(t('cr_analytics_rank'))}</th>
                        <th>${esc(t('cr_col_cashier'))}</th>
                        <th>${esc(t('cr_analytics_cashier_sessions'))}</th>
                        <th>${esc(t('cr_analytics_stat_revenue'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                        ${rows.map((c, i) => {
                            const pct = Math.round((Number(c.revenue || 0) / maxRevenue) * 100);
                            return `<tr>
                                <td><span class="cr-analytics-rank">${i + 1}</span></td>
                                <td><strong>${esc(c.name)}</strong></td>
                                <td>${esc(String(c.sessions ?? 0))}</td>
                                <td><strong>${esc(money(c.revenue))}</strong></td>
                                <td><div class="cr-analytics-bar"><span style="width:${pct}%"></span></div></td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>
            <div class="cr-analytics-list">
                ${rows.map((c, i) => `
                    <article class="cr-analytics-list__item">
                        <span class="cr-analytics-rank">${i + 1}</span>
                        <div class="cr-analytics-list__main">
                            <strong>${esc(c.name)}</strong>
                            <span>${esc(String(c.sessions ?? 0))} ${esc(t('cr_analytics_cashier_sessions'))}</span>
                        </div>
                        <strong>${esc(money(c.revenue))}</strong>
                    </article>`).join('')}
            </div>`;
    }

    function exportData() {
        const rows = state.data?.cashier_performance || [];
        if (!rows.length) return;
        exportCsv(`cash-analytics-${state.period}-${new Date().toISOString().slice(0, 10)}.csv`, [
            [t('cr_col_cashier'), t('cr_analytics_cashier_sessions'), t('cr_analytics_stat_revenue')],
            ...rows.map((c) => [c.name, c.sessions, c.revenue]),
        ]);
    }

    function setPeriod(period) {
        state.period = period;
        els.periodTabs?.querySelectorAll('.cr-reg-chip').forEach((chip) => {
            const active = chip.dataset.period === period;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function initToolbar() {
        els.periodTabs?.querySelectorAll('[data-period]').forEach((chip) => {
            chip.addEventListener('click', () => {
                setPeriod(chip.dataset.period || 'month');
                load();
            });
        });
        els.exportBtn?.addEventListener('click', exportData);
        els.refreshBtn?.addEventListener('click', () => load());
    }

    async function load(silent = false) {
        if (!silent) {
            hideError();
            root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
            document.querySelectorAll('.cr-analytics-stat__value').forEach((el) => el.classList.add('is-loading'));
        }
        try {
            const res = await AdminAPI.getCashRegisterAnalytics(state.period);
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            state.data = res.data || {};
            updateStats(state.data);
            renderDailyChart(state.data);
            renderPaymentChart(state.data);
            renderRegisterChart(state.data);
            renderRefundChart(state.data);
            renderCashierTable(state.data);
            updateLastUpdated();
        } catch (e) {
            console.error(e);
            showError(e.message || t('load_error'));
            root.innerHTML = `<div class="cr-analytics-empty-block"><span class="material-icons-round">error_outline</span><p>${esc(e.message)}</p></div>`;
        }
    }

    initToolbar();
    load();
    document.addEventListener('cr:refresh', () => load(true));
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        setTimeout(() => {
            if (state.data) {
                renderDailyChart(state.data);
                renderPaymentChart(state.data);
                renderRegisterChart(state.data);
                renderRefundChart(state.data);
            }
        }, 120);
    });
});
