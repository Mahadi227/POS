/**
 * Accounting balance sheet v1 — assets, liabilities, equity as-of snapshot
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('accBsHeroStats')) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;

    const COMPOSITION_COLORS = { asset: '#2563eb', liability: '#d97706', equity: '#059669' };
    const TYPE_COLORS = { asset: 'rgba(37,99,235,0.75)', liability: 'rgba(217,119,6,0.75)', equity: 'rgba(5,150,105,0.75)' };

    const state = { preset: 'today', data: null, charts: {} };

    const els = {
        periodLabel: document.getElementById('accBsPeriodLabel'),
        storeScope: document.getElementById('accBsStoreScope'),
        statAssets: document.getElementById('accBsStatAssets'),
        statLiabilities: document.getElementById('accBsStatLiabilities'),
        statEquity: document.getElementById('accBsStatEquity'),
        statNetWorth: document.getElementById('accBsStatNetWorth'),
        deRatio: document.getElementById('accBsDeRatio'),
        equityRatio: document.getElementById('accBsEquityRatio'),
        liabilityRatio: document.getElementById('accBsLiabilityRatio'),
        balanced: document.getElementById('accBsBalanced'),
        asOf: document.getElementById('accBsAsOf'),
        periodTabs: document.getElementById('accBsPeriod'),
        exportBtn: document.getElementById('accBsExportBtn'),
        printBtn: document.getElementById('accBsPrintBtn'),
        refreshBtn: document.getElementById('accBsRefreshBtn'),
        detailRoot: document.getElementById('accBsDetailRoot'),
        compositionEmpty: document.getElementById('accBsCompositionEmpty'),
        topAccountsEmpty: document.getElementById('accBsTopAccountsEmpty'),
        compositionLegend: document.getElementById('accBsCompositionLegend'),
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

    function compositionLabel(key) {
        const map = { asset: t('report_assets'), liability: t('report_liabilities'), equity: t('report_equity') };
        return map[key] || key;
    }

    function asOfLabel() {
        const date = els.asOf?.value;
        if (!date) return '—';
        const formatted = new Date(`${date}T12:00:00`).toLocaleDateString(locale(), { day: 'numeric', month: 'short', year: 'numeric' });
        return `${t('rpt_as_of_label')} ${formatted}`;
    }

    function presetDate(preset) {
        const now = new Date();
        let d = new Date(now);
        if (preset === 'month') {
            d = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        } else if (preset === 'quarter') {
            const qm = Math.floor(now.getMonth() / 3) * 3 + 2;
            d = new Date(now.getFullYear(), qm + 1, 0);
        } else if (preset === 'year') {
            d = new Date(now.getFullYear(), 11, 31);
        }
        if (d > now) d = now;
        return d.toISOString().slice(0, 10);
    }

    function pct(n) {
        return `${Number(n || 0).toLocaleString(locale(), { maximumFractionDigits: 1 })}%`;
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-bs-stat__value, .acc-bs-insight__value').forEach((el) => {
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

    function sectionTable(title, rows, total) {
        if (!rows?.length) {
            return `<section class="acc-bs-section">
                <h4 class="acc-bs-section__title">${esc(title)}</h4>
                <p class="acc-bs-empty">${esc(t('no_data'))}</p>
            </section>`;
        }
        return `<section class="acc-bs-section">
            <h4 class="acc-bs-section__title">${esc(title)}</h4>
            <div class="acc-bs-table-wrap">
                <table class="modern-table acc-table acc-bs-table">
                    <thead><tr>
                        <th>${esc(t('rpt_code'))}</th>
                        <th>${esc(t('rpt_account'))}</th>
                        <th class="acc-bs-num">${esc(t('rpt_col_balance'))}</th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr>
                            <td><code>${esc(r.code)}</code></td>
                            <td>${esc(r.name)}</td>
                            <td class="acc-bs-num">${esc(money(r.balance))}</td>
                        </tr>`).join('')}
                    </tbody>
                    <tfoot><tr>
                        <th colspan="2">${esc(t('rpt_total'))}</th>
                        <th class="acc-bs-num">${esc(money(total))}</th>
                    </tr></tfoot>
                </table>
            </div>
            <div class="acc-bs-cards">${rows.map((r) => `
                <article class="acc-bs-row-card">
                    <div class="acc-bs-row-card__head">
                        <code>${esc(r.code)}</code>
                        <strong>${esc(money(r.balance))}</strong>
                    </div>
                    <p>${esc(r.name)}</p>
                </article>`).join('')}
            </div>
        </section>`;
    }

    function renderCompositionDonut(items) {
        const ctx = document.getElementById('accBsComposition');
        if (!ctx || !window.Chart) return;
        destroyChart('accBsComposition');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.compositionEmpty) els.compositionEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (els.compositionLegend) {
            els.compositionLegend.innerHTML = hasData
                ? filtered.map((item) => {
                    const color = COMPOSITION_COLORS[item.key] || '#64748b';
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(compositionLabel(item.key))}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.charts.accBsComposition = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map((x) => compositionLabel(x.key)),
                datasets: [{
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((x) => COMPOSITION_COLORS[x.key] || '#64748b'),
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

    function renderTopAccountsChart(rows) {
        const ctx = document.getElementById('accBsTopAccounts');
        if (!ctx || !window.Chart) return;
        destroyChart('accBsTopAccounts');
        const filtered = (rows || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.topAccountsEmpty) els.topAccountsEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.charts.accBsTopAccounts = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => x.label),
                datasets: [{
                    label: t('rpt_col_balance'),
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((x) => TYPE_COLORS[x.type] || 'rgba(100,116,139,0.75)'),
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(ctx) { return money(ctx.parsed.x); },
                        },
                    },
                },
                scales: {
                    x: { grid: { color: c.grid }, ticks: { color: c.text } },
                    y: { grid: { display: false }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderHero(data) {
        const totals = data.totals || {};
        if (els.statAssets) els.statAssets.textContent = money(totals.asset);
        if (els.statLiabilities) els.statLiabilities.textContent = money(totals.liability);
        if (els.statEquity) els.statEquity.textContent = money(totals.equity);
        if (els.statNetWorth) els.statNetWorth.textContent = money(data.net_worth ?? (totals.asset - totals.liability));

        const ins = data.insights || {};
        if (els.deRatio) {
            els.deRatio.textContent = ins.debt_to_equity != null
                ? `${Number(ins.debt_to_equity).toLocaleString(locale(), { maximumFractionDigits: 2 })}×`
                : '—';
        }
        if (els.equityRatio) els.equityRatio.textContent = pct(ins.equity_ratio);
        if (els.liabilityRatio) els.liabilityRatio.textContent = pct(ins.liability_ratio);
        if (els.balanced) {
            els.balanced.textContent = ins.is_balanced ? t('bs_balanced_yes') : t('bs_balanced_no');
            els.balanced.classList.toggle('acc-cell--pos', !!ins.is_balanced);
            els.balanced.classList.toggle('acc-cell--neg', !ins.is_balanced);
        }

        document.querySelectorAll('.acc-bs-stat__value, .acc-bs-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.periodLabel) els.periodLabel.textContent = asOfLabel();
        if (els.storeScope) {
            const count = ins.account_count ?? 0;
            const scope = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
            els.storeScope.textContent = `${scope} · ${count} ${t('bs_insight_accounts')}`;
        }
    }

    function renderDetail(data) {
        if (!els.detailRoot) return;
        const totals = data.totals || {};
        els.detailRoot.innerHTML = `
            <div class="acc-bs-grid">
                ${sectionTable(t('rpt_section_assets'), data.assets, totals.asset)}
                ${sectionTable(t('rpt_section_liabilities'), data.liabilities, totals.liability)}
                ${sectionTable(t('rpt_section_equity'), data.equity, totals.equity)}
            </div>`;
    }

    function renderCharts(data) {
        renderCompositionDonut(data.composition);
        renderTopAccountsChart(data.charts?.top_accounts);
    }

    function queryParams() {
        return { to: els.asOf?.value || '' };
    }

    async function load() {
        hideError();
        setLoading(true);
        if (els.detailRoot) {
            els.detailRoot.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        }
        try {
            const res = await AdminAPI.getAccounting('balance-sheet', queryParams());
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
                els.detailRoot.innerHTML = `<div class="acc-empty"><span class="material-icons-round">balance</span><p>${esc(e.message)}</p></div>`;
            }
        }
    }

    function exportData() {
        if (!state.data) return;
        const d = state.data;
        const rows = [
            [t('balance_sheet'), asOfLabel()],
            [],
            [t('rpt_code'), t('rpt_account'), t('rpt_col_balance')],
        ];
        [
            ['assets', t('report_assets')],
            ['liabilities', t('report_liabilities')],
            ['equity', t('report_equity')],
        ].forEach(([key, label]) => {
            rows.push([]);
            rows.push([label]);
            (d[key] || []).forEach((r) => rows.push([r.code, r.name, r.balance]));
            rows.push([t('rpt_total'), '', d.totals?.[key === 'assets' ? 'asset' : key === 'liabilities' ? 'liability' : 'equity']]);
        });
        exportCsv(`balance-sheet-${els.asOf?.value || 'export'}.csv`, rows);
    }

    function applyPreset(preset) {
        state.preset = preset;
        if (els.asOf) els.asOf.value = presetDate(preset);
        els.periodTabs?.querySelectorAll('.acc-bs-chip').forEach((chip) => {
            const active = chip.dataset.preset === preset;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        load();
    }

    els.periodTabs?.querySelectorAll('.acc-bs-chip').forEach((chip) => {
        chip.addEventListener('click', () => applyPreset(chip.dataset.preset || 'today'));
    });

    els.asOf?.addEventListener('change', () => {
        els.periodTabs?.querySelectorAll('.acc-bs-chip').forEach((c) => c.classList.remove('is-active'));
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
