/**
 * Accounting financial reports — P&L, balance sheet, cash flow hub
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accRptRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;

    const TAB_TITLES = {
        'profit-loss': 'rpt_tab_pl',
        'balance-sheet': 'rpt_tab_balance',
        cashflow: 'rpt_tab_cashflow',
    };

    const state = {
        report: 'profit-loss',
        period: 'year',
        hub: null,
        data: null,
    };

    const els = {
        periodLabel: document.getElementById('accRptPeriodLabel'),
        statNet: document.getElementById('accRptStatNet'),
        statAssets: document.getElementById('accRptStatAssets'),
        statCashflow: document.getElementById('accRptStatCashflow'),
        statTreasury: document.getElementById('accRptStatTreasury'),
        stats: document.getElementById('accRptStats'),
        tabs: document.getElementById('accRptTabs'),
        periodTabs: document.getElementById('accRptPeriod'),
        dateFrom: document.getElementById('accRptDateFrom'),
        dateTo: document.getElementById('accRptDateTo'),
        dateFromWrap: document.getElementById('accRptDateFromWrap'),
        dateToLabel: document.getElementById('accRptDateToLabel'),
        panelTitle: document.getElementById('accRptPanelTitle'),
        meta: document.getElementById('accRptMeta'),
        storeScope: document.getElementById('accRptStoreScope'),
        exportBtn: document.getElementById('accRptExportBtn'),
        printBtn: document.getElementById('accRptPrintBtn'),
        refreshBtn: document.getElementById('accRptRefreshBtn'),
    };

    function locale() {
        return window.ADMIN_CONFIG?.locale || 'fr-FR';
    }

    function formatPeriodLabel() {
        const from = els.dateFrom?.value;
        const to = els.dateTo?.value;
        if (!from || !to) return '—';
        const f = new Date(`${from}T12:00:00`).toLocaleDateString(locale(), { day: 'numeric', month: 'short', year: 'numeric' });
        const tDate = new Date(`${to}T12:00:00`).toLocaleDateString(locale(), { day: 'numeric', month: 'short', year: 'numeric' });
        if (state.report === 'balance-sheet') {
            return `${t('rpt_as_of_label')} ${tDate}`;
        }
        return `${f} — ${tDate}`;
    }

    function periodRange(period) {
        const to = new Date();
        const from = new Date(to);
        if (period === 'month') {
            from.setDate(1);
        } else if (period === 'quarter') {
            const q = Math.floor(to.getMonth() / 3) * 3;
            from.setMonth(q, 1);
        } else {
            from.setMonth(0, 1);
        }
        const fmt = (d) => d.toISOString().slice(0, 10);
        return { from: fmt(from), to: fmt(to) };
    }

    function queryParams() {
        return {
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
        };
    }

    function setStatsLoading(on) {
        document.querySelectorAll('.acc-rpt-stat__value').forEach((el) => el.classList.toggle('is-loading', on));
    }

    function renderHub(hub) {
        state.hub = hub;
        if (els.statNet) els.statNet.textContent = money(hub?.net_profit);
        if (els.statAssets) els.statAssets.textContent = money(hub?.total_assets);
        if (els.statCashflow) els.statCashflow.textContent = money(hub?.net_cash_flow);
        if (els.statTreasury) els.statTreasury.textContent = money(hub?.treasury_total);
        document.querySelectorAll('.acc-rpt-stat__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.periodLabel) els.periodLabel.textContent = formatPeriodLabel();
        if (els.storeScope) {
            els.storeScope.textContent = window.ADMIN_PAGE?.storeName || '';
        }
    }

    function sectionTable(title, rows, total) {
        if (!rows?.length) {
            return `<section class="acc-rpt-section">
                <h4 class="acc-rpt-section__title">${esc(title)}</h4>
                <p class="acc-rpt-empty">${esc(t('no_data'))}</p>
            </section>`;
        }
        return `<section class="acc-rpt-section">
            <h4 class="acc-rpt-section__title">${esc(title)}</h4>
            <div class="acc-rpt-table-wrap">
                <table class="modern-table acc-table acc-rpt-table">
                    <thead><tr>
                        <th>${esc(t('rpt_code'))}</th>
                        <th>${esc(t('rpt_account'))}</th>
                        <th class="acc-rpt-num">${esc(t('rpt_col_balance'))}</th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr>
                            <td><code>${esc(r.code)}</code></td>
                            <td>${esc(r.name)}</td>
                            <td class="acc-rpt-num">${esc(money(r.balance))}</td>
                        </tr>`).join('')}
                    </tbody>
                    <tfoot><tr>
                        <th colspan="2">${esc(t('rpt_total'))}</th>
                        <th class="acc-rpt-num">${esc(money(total))}</th>
                    </tr></tfoot>
                </table>
            </div>
        </section>`;
    }

    function barPct(value, max) {
        const m = Math.abs(Number(max) || 1);
        const v = Math.abs(Number(value) || 0);
        return Math.min(100, Math.round((v / m) * 100));
    }

    function renderProfitLoss(data) {
        return `
            <div class="acc-rpt-cards">
                <article class="acc-rpt-card acc-rpt-card--primary">
                    <span>${esc(t('kpi_revenue'))}</span>
                    <strong>${esc(money(data.revenue))}</strong>
                </article>
                <article class="acc-rpt-card">
                    <span>COGS</span>
                    <strong>${esc(money(data.cogs))}</strong>
                </article>
                <article class="acc-rpt-card acc-rpt-card--success">
                    <span>${esc(t('kpi_gross_profit'))}</span>
                    <strong>${esc(money(data.gross_profit))}</strong>
                </article>
                <article class="acc-rpt-card acc-rpt-card--warn">
                    <span>${esc(t('kpi_expenses'))}</span>
                    <strong>${esc(money(data.expenses))}</strong>
                </article>
                <article class="acc-rpt-card acc-rpt-card--highlight">
                    <span>${esc(t('kpi_net_profit'))}</span>
                    <strong>${esc(money(data.net_profit))}</strong>
                </article>
            </div>
            <div class="acc-rpt-waterfall">
                <div class="acc-rpt-waterfall__row">
                    <span>${esc(t('kpi_revenue'))}</span>
                    <div class="acc-rpt-bar acc-rpt-bar--pos" style="width:${barPct(data.revenue, data.revenue)}%"></div>
                    <strong>${esc(money(data.revenue))}</strong>
                </div>
                <div class="acc-rpt-waterfall__row">
                    <span>COGS</span>
                    <div class="acc-rpt-bar acc-rpt-bar--neg" style="width:${barPct(data.cogs, data.revenue)}%"></div>
                    <strong>−${esc(money(data.cogs))}</strong>
                </div>
                <div class="acc-rpt-waterfall__row acc-rpt-waterfall__row--sub">
                    <span>${esc(t('kpi_gross_profit'))}</span>
                    <strong>${esc(money(data.gross_profit))}</strong>
                </div>
                <div class="acc-rpt-waterfall__row">
                    <span>${esc(t('kpi_expenses'))}</span>
                    <div class="acc-rpt-bar acc-rpt-bar--neg" style="width:${barPct(data.expenses, data.revenue)}%"></div>
                    <strong>−${esc(money(data.expenses))}</strong>
                </div>
                <div class="acc-rpt-waterfall__row acc-rpt-waterfall__row--total">
                    <span>${esc(t('kpi_net_profit'))}</span>
                    <strong class="${Number(data.net_profit) >= 0 ? 'acc-cell--pos' : 'acc-cell--neg'}">${esc(money(data.net_profit))}</strong>
                </div>
            </div>`;
    }

    function renderBalanceSheet(data) {
        const totals = data.totals || {};
        return `
            <div class="acc-rpt-bs-summary">
                <article class="acc-rpt-card acc-rpt-card--primary">
                    <span>${esc(t('report_assets'))}</span>
                    <strong>${esc(money(totals.asset))}</strong>
                </article>
                <article class="acc-rpt-card acc-rpt-card--warn">
                    <span>${esc(t('report_liabilities'))}</span>
                    <strong>${esc(money(totals.liability))}</strong>
                </article>
                <article class="acc-rpt-card acc-rpt-card--success">
                    <span>${esc(t('report_equity'))}</span>
                    <strong>${esc(money(totals.equity))}</strong>
                </article>
            </div>
            <div class="acc-rpt-bs-grid">
                ${sectionTable(t('rpt_section_assets'), data.assets, totals.asset)}
                ${sectionTable(t('rpt_section_liabilities'), data.liabilities, totals.liability)}
                ${sectionTable(t('rpt_section_equity'), data.equity, totals.equity)}
            </div>`;
    }

    function renderCashFlow(data) {
        const bal = data.balances || {};
        return `
            <div class="acc-rpt-cards acc-rpt-cards--3">
                <article class="acc-rpt-card acc-rpt-card--success">
                    <span>${esc(t('cash_in'))}</span>
                    <strong>${esc(money(data.cash_in?.total))}</strong>
                </article>
                <article class="acc-rpt-card acc-rpt-card--warn">
                    <span>${esc(t('cash_out'))}</span>
                    <strong>${esc(money(data.cash_out?.total))}</strong>
                </article>
                <article class="acc-rpt-card acc-rpt-card--highlight">
                    <span>${esc(t('net_cash_flow'))}</span>
                    <strong class="${Number(data.net_cash_flow) >= 0 ? 'acc-cell--pos' : 'acc-cell--neg'}">${esc(money(data.net_cash_flow))}</strong>
                </article>
            </div>
            <div class="acc-rpt-cf-grid">
                <section class="acc-rpt-section">
                    <h4 class="acc-rpt-section__title">${esc(t('rpt_cash_in_section'))}</h4>
                    <dl class="acc-rpt-dl">
                        <div><dt>${esc(t('rpt_sales_in'))}</dt><dd>${esc(money(data.cash_in?.sales))}</dd></div>
                        <div><dt>${esc(t('rpt_ar_collected'))}</dt><dd>${esc(money(data.cash_in?.receivables_collected))}</dd></div>
                        <div class="acc-rpt-dl__total"><dt>${esc(t('cash_in'))}</dt><dd>${esc(money(data.cash_in?.total))}</dd></div>
                    </dl>
                </section>
                <section class="acc-rpt-section">
                    <h4 class="acc-rpt-section__title">${esc(t('rpt_cash_out_section'))}</h4>
                    <dl class="acc-rpt-dl">
                        <div><dt>${esc(t('rpt_expenses_out'))}</dt><dd>${esc(money(data.cash_out?.expenses))}</dd></div>
                        <div><dt>${esc(t('rpt_ap_paid'))}</dt><dd>${esc(money(data.cash_out?.payables_paid))}</dd></div>
                        <div class="acc-rpt-dl__total"><dt>${esc(t('cash_out'))}</dt><dd>${esc(money(data.cash_out?.total))}</dd></div>
                    </dl>
                </section>
            </div>
            <section class="acc-rpt-section">
                <h4 class="acc-rpt-section__title">${esc(t('rpt_treasury_balances'))}</h4>
                <div class="acc-rpt-cards acc-rpt-cards--3">
                    <article class="acc-rpt-card"><span>${esc(t('kpi_cash'))}</span><strong>${esc(money(bal.cash))}</strong></article>
                    <article class="acc-rpt-card"><span>${esc(t('kpi_bank'))}</span><strong>${esc(money(bal.bank))}</strong></article>
                    <article class="acc-rpt-card"><span>${esc(t('kpi_mobile'))}</span><strong>${esc(money(bal.mobile_money))}</strong></article>
                </div>
            </section>`;
    }

    function renderReport(data) {
        if (state.report === 'balance-sheet') return renderBalanceSheet(data);
        if (state.report === 'cashflow') return renderCashFlow(data);
        return renderProfitLoss(data);
    }

    function updateDateUi() {
        const isBs = state.report === 'balance-sheet';
        if (els.dateFromWrap) els.dateFromWrap.hidden = isBs;
        if (els.dateToLabel) {
            els.dateToLabel.textContent = isBs ? t('rpt_as_of_label') : t('end_date');
        }
    }

    function updatePanelMeta() {
        if (els.panelTitle) els.panelTitle.textContent = t(TAB_TITLES[state.report] || 'rpt_tab_pl');
        if (els.meta) els.meta.textContent = formatPeriodLabel();
    }

    async function loadReport() {
        hideError();
        setStatsLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        updatePanelMeta();
        updateDateUi();
        try {
            const [hubRes, rptRes] = await Promise.all([
                AdminAPI.getAccounting('reports/hub', queryParams()),
                AdminAPI.getAccounting(`reports/${state.report}`, queryParams()),
            ]);
            if (hubRes.status !== 'success' || rptRes.status !== 'success') {
                throw new Error(hubRes.message || rptRes.message);
            }
            setMigrationHint(hubRes.module_ready ?? rptRes.module_ready ?? true);
            state.data = rptRes.data;
            renderHub(hubRes.data || {});
            root.innerHTML = renderReport(rptRes.data || {});
            if (els.periodLabel) els.periodLabel.textContent = formatPeriodLabel();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">summarize</span><p>${esc(e.message)}</p></div>`;
        }
    }

    function exportReport() {
        if (!state.data) return;
        const q = queryParams();
        const rows = [[`${t(TAB_TITLES[state.report])} — ${formatPeriodLabel()}`], []];

        if (state.report === 'profit-loss') {
            const d = state.data;
            rows.push([t('kpi_revenue'), d.revenue], ['COGS', d.cogs], [t('kpi_gross_profit'), d.gross_profit],
                [t('kpi_expenses'), d.expenses], [t('kpi_net_profit'), d.net_profit]);
        } else if (state.report === 'balance-sheet') {
            rows.push([t('rpt_code'), t('rpt_account'), t('rpt_col_balance')]);
            [
                ['assets', 'asset', t('report_assets')],
                ['liabilities', 'liability', t('report_liabilities')],
                ['equity', 'equity', t('report_equity')],
            ].forEach(([key, totalKey, label]) => {
                rows.push([]);
                rows.push([label]);
                (state.data[key] || []).forEach((r) => rows.push([r.code, r.name, r.balance]));
                rows.push([t('rpt_total'), '', state.data.totals?.[totalKey]]);
            });
        } else {
            const d = state.data;
            rows.push([t('rpt_sales_in'), d.cash_in?.sales], [t('rpt_ar_collected'), d.cash_in?.receivables_collected],
                [t('cash_in'), d.cash_in?.total], [t('rpt_expenses_out'), d.cash_out?.expenses],
                [t('rpt_ap_paid'), d.cash_out?.payables_paid], [t('cash_out'), d.cash_out?.total],
                [t('net_cash_flow'), d.net_cash_flow]);
        }
        exportCsv(`financial-report-${state.report}-${q.to || 'export'}.csv`, rows);
    }

    function setReport(report) {
        state.report = report;
        els.tabs?.querySelectorAll('.acc-rpt-tab').forEach((tab) => {
            const active = tab.dataset.report === report;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        loadReport();
    }

    function setPeriod(period) {
        state.period = period;
        const range = periodRange(period);
        if (els.dateFrom) els.dateFrom.value = range.from;
        if (els.dateTo) els.dateTo.value = range.to;
        els.periodTabs?.querySelectorAll('.acc-rpt-chip').forEach((chip) => {
            chip.classList.toggle('is-active', chip.dataset.period === period);
        });
        loadReport();
    }

    els.tabs?.addEventListener('click', (e) => {
        const tab = e.target.closest('[data-report]');
        if (!tab) return;
        setReport(tab.dataset.report);
    });

    els.stats?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-rpt-tab]');
        if (!btn) return;
        setReport(btn.dataset.rptTab);
    });

    els.periodTabs?.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-period]');
        if (!chip) return;
        setPeriod(chip.dataset.period);
    });

    [els.dateFrom, els.dateTo].forEach((input) => {
        input?.addEventListener('change', () => {
            els.periodTabs?.querySelectorAll('.acc-rpt-chip').forEach((c) => c.classList.remove('is-active'));
            loadReport();
        });
    });

    els.exportBtn?.addEventListener('click', exportReport);
    els.printBtn?.addEventListener('click', () => window.print());
    els.refreshBtn?.addEventListener('click', loadReport);
    document.addEventListener('acc:refresh', loadReport);

    setPeriod('year');
});
