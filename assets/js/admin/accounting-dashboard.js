/**
 * Accounting dashboard v2 — period filters, hero KPIs, charts, branch comparison, CSV export
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('accDashHeroStats')) return;

    const { t, esc, money, currencyCode, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;

    const CATEGORY_COLORS = ['#059669', '#2563eb', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#ca8a04', '#64748b'];
    const TREASURY_COLORS = { cash: '#059669', bank: '#2563eb', mobile: '#7c3aed' };

    const state = { period: 'month', data: null, charts: {} };

    const els = {
        periodLabel: document.getElementById('accDashPeriodLabel'),
        storeScope: document.getElementById('accDashStoreScope'),
        heroRevenue: document.getElementById('accHeroRevenue'),
        heroExpenses: document.getElementById('accHeroExpenses'),
        heroNet: document.getElementById('accHeroNet'),
        heroTreasury: document.getElementById('accHeroTreasury'),
        heroCurrency: document.getElementById('accHeroCurrency'),
        dateFrom: document.getElementById('accDashDateFrom'),
        dateTo: document.getElementById('accDashDateTo'),
        periodTabs: document.getElementById('accDashPeriod'),
        exportBtn: document.getElementById('accDashExportBtn'),
        refreshBtn: document.getElementById('accDashRefreshBtn'),
        pendingAlert: document.getElementById('accDashPendingAlert'),
        pendingText: document.getElementById('accDashPendingText'),
        branchMeta: document.getElementById('accBranchMeta'),
        revenueEmpty: document.getElementById('accRevenueEmpty'),
        expenseEmpty: document.getElementById('accExpenseEmpty'),
        breakdownEmpty: document.getElementById('accBreakdownEmpty'),
        treasuryEmpty: document.getElementById('accTreasuryEmpty'),
        breakdownLegend: document.getElementById('accBreakdownLegend'),
        treasuryLegend: document.getElementById('accTreasuryLegend'),
    };

    const kpiMap = {
        AccKpiRevenue: 'total_revenue',
        AccKpiExpenses: 'total_expenses',
        AccKpiGross: 'gross_profit',
        AccKpiNet: 'net_profit',
        AccKpiCash: 'cash_balance',
        AccKpiBank: 'bank_balance',
        AccKpiMobile: 'mobile_money_balance',
        AccKpiAr: 'accounts_receivable',
        AccKpiAp: 'accounts_payable',
        AccKpiInventory: 'inventory_value',
        AccKpiDaily: 'daily_sales',
        AccKpiMonthly: 'monthly_sales',
        AccKpiOutstanding: 'outstanding_debts',
        AccKpiPending: 'pending_expenses',
    };

    const heroMap = {
        accHeroRevenue: 'total_revenue',
        accHeroExpenses: 'total_expenses',
        accHeroNet: 'net_profit',
        accHeroTreasury: 'treasury_total',
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
        const t = new Date(`${to}T12:00:00`).toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' });
        return `${f} — ${t}`;
    }

    function treasuryLabel(key) {
        const map = { cash: t('kpi_cash'), bank: t('kpi_bank'), mobile: t('kpi_mobile') };
        return map[key] || key;
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-kpi').forEach((card) => {
            card.classList.toggle('is-loading', on);
        });
    }

    function setKpiAmount(elId, amount) {
        const el = document.getElementById(elId);
        if (!el) return;
        el.textContent = money(amount);
        el.closest('.acc-kpi')?.classList.remove('is-loading');
    }

    function destroyChart(id) {
        if (state.charts[id]) {
            state.charts[id].destroy();
            delete state.charts[id];
        }
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
                            return `${ctx.dataset.label || ''}: ${money(ctx.parsed?.y ?? 0)}`;
                        },
                    },
                },
            },
            scales: {
                x: { grid: { color: c.grid }, ticks: { color: c.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                y: { grid: { color: c.grid }, ticks: { color: c.text } },
            },
        };
    }

    function renderLineChart(id, rows, label, color, emptyEl) {
        const ctx = document.getElementById(id);
        if (!ctx || !window.Chart) return;
        destroyChart(id);
        const hasData = rows?.some((x) => Number(x.amount) > 0);
        if (emptyEl) {
            emptyEl.hidden = !!hasData;
            ctx.style.display = hasData ? 'block' : 'none';
        }
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
                    const key = item.key || item.category || '';
                    const color = typeof colorFn === 'function' ? colorFn(item, i) : colorFn[i % colorFn.length];
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(labelFn(item))}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
            legendEl.setAttribute('aria-hidden', hasData ? 'false' : 'true');
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
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(ctx) {
                                return `${ctx.label}: ${money(ctx.parsed)}`;
                            },
                        },
                    },
                },
            },
        });
    }

    function renderBranchCompare(branches) {
        const root = document.getElementById('accBranchCompare');
        if (!root) return;
        if (!branches?.length) {
            root.innerHTML = `<p class="acc-empty"><span class="material-icons-round">store</span>${esc(t('no_data'))}</p>`;
            if (els.branchMeta) els.branchMeta.textContent = t('no_data');
            return;
        }
        if (els.branchMeta) {
            els.branchMeta.textContent = t('dash_branch_meta', branches.length);
        }
        const rows = branches.map((b) => {
            const net = Number(b.revenue || 0) - Number(b.expenses || 0);
            return { ...b, net };
        });
        root.innerHTML = `
            <div class="acc-table-wrap">
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
                    </tr>`).join('')}</tbody>
                </table>
            </div>
            <div class="acc-branch-cards">${rows.map((b) => `
                <article class="acc-branch-card">
                    <h4>${esc(b.name)}</h4>
                    <dl>
                        <div><dt>${esc(t('kpi_revenue'))}</dt><dd>${esc(money(b.revenue))}</dd></div>
                        <div><dt>${esc(t('kpi_expenses'))}</dt><dd>${esc(money(b.expenses))}</dd></div>
                        <div><dt>${esc(t('kpi_net_profit'))}</dt><dd class="${b.net >= 0 ? 'acc-cell--pos' : 'acc-cell--neg'}">${esc(money(b.net))}</dd></div>
                    </dl>
                </article>`).join('')}
            </div>`;
    }

    function renderAll(data) {
        const s = data?.summary || {};
        const h = data?.hero || {};

        Object.entries(kpiMap).forEach(([elId, key]) => {
            setKpiAmount(elId, s[key]);
        });

        Object.entries(heroMap).forEach(([elId, key]) => {
            setKpiAmount(elId, h[key]);
        });

        if (els.heroCurrency) {
            els.heroCurrency.textContent = currencyCode();
        }

        const pending = Number(s.pending_expenses || 0);
        if (els.pendingAlert) {
            let msg = '';
            if (pending > 0 && els.pendingText) {
                const raw = t('dash_pending_alert');
                if (raw && raw !== 'dash_pending_alert') {
                    msg = raw.includes('%s') ? raw.replace('%s', money(pending)) : `${money(pending)} — ${raw}`;
                    els.pendingText.textContent = msg;
                }
            } else if (els.pendingText) {
                els.pendingText.textContent = '';
            }
            els.pendingAlert.hidden = !(pending > 0 && msg.trim());
        }

        if (els.periodLabel) els.periodLabel.textContent = periodLabelText();
        if (els.storeScope) {
            const name = window.ADMIN_PAGE?.storeName;
            els.storeScope.textContent = name ? name : t('dash_all_stores');
        }

        const charts = data?.charts || {};
        renderLineChart('accRevenueChart', charts.revenue_trend || [], t('kpi_revenue'), '#059669', els.revenueEmpty);
        renderLineChart('accExpenseChart', charts.expense_trend || [], t('kpi_expenses'), '#d97706', els.expenseEmpty);
        renderDonut(
            'accExpenseBreakdownChart',
            (charts.expense_by_category || []).map((x) => ({ category: x.category, amount: x.amount })),
            (x) => x.category || '—',
            (_, i) => CATEGORY_COLORS[i % CATEGORY_COLORS.length],
            els.breakdownLegend,
            els.breakdownEmpty,
        );
        renderDonut(
            'accTreasuryChart',
            data?.treasury_mix || [],
            (x) => treasuryLabel(x.key),
            (x) => TREASURY_COLORS[x.key] || '#64748b',
            els.treasuryLegend,
            els.treasuryEmpty,
        );
        renderBranchCompare(data?.branch_comparison || []);
    }

    function exportDashboard() {
        if (!state.data) return;
        const s = state.data.summary || {};
        const rows = [
            [t('kpi_revenue'), s.total_revenue],
            [t('kpi_expenses'), s.total_expenses],
            [t('kpi_gross_profit'), s.gross_profit],
            [t('kpi_net_profit'), s.net_profit],
            [t('kpi_cash'), s.cash_balance],
            [t('kpi_bank'), s.bank_balance],
            [t('kpi_mobile'), s.mobile_money_balance],
            [],
            [t('branch'), t('kpi_revenue'), t('kpi_expenses'), t('kpi_net_profit')],
        ];
        (state.data.branch_comparison || []).forEach((b) => {
            rows.push([b.name, b.revenue, b.expenses, Number(b.revenue || 0) - Number(b.expenses || 0)]);
        });
        exportCsv(`accounting-dashboard-${els.dateFrom?.value || 'export'}.csv`, rows);
    }

    async function load() {
        hideError();
        setLoading(true);
        try {
            const query = {
                from: els.dateFrom?.value || '',
                to: els.dateTo?.value || '',
            };
            const res = await AdminAPI.getAccountingDashboard(query);
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? res.data?.module_ready ?? true);
            state.data = res.data;
            renderAll(res.data);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setLoading(false);
        }
    }

    function setPeriod(period) {
        state.period = period;
        const range = periodRange(period);
        if (els.dateFrom) els.dateFrom.value = range.from;
        if (els.dateTo) els.dateTo.value = range.to;
        els.periodTabs?.querySelectorAll('.acc-dash-chip').forEach((btn) => {
            const active = btn.dataset.period === period;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        load();
    }

    els.periodTabs?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-period]');
        if (!btn) return;
        setPeriod(btn.dataset.period);
    });

    [els.dateFrom, els.dateTo].forEach((input) => {
        input?.addEventListener('change', () => {
            els.periodTabs?.querySelectorAll('.acc-dash-chip').forEach((btn) => btn.classList.remove('is-active'));
            load();
        });
    });

    els.exportBtn?.addEventListener('click', exportDashboard);
    els.refreshBtn?.addEventListener('click', load);
    document.addEventListener('acc:refresh', load);

    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        setTimeout(() => { if (state.data) renderAll(state.data); }, 120);
    });

    setPeriod('month');
});
