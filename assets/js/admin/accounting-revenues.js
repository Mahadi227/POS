/**
 * Accounting revenues v1 — GL revenue lines, trends, source breakdown
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accRevRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;
    const PAGE_SIZE = 20;

    const SOURCE_LABELS = {
        manual: 'je_ref_manual',
        sale: 'je_ref_sale',
        expense: 'je_ref_expense',
        payment: 'je_ref_payment',
        purchase: 'je_ref_purchase',
        inventory: 'je_ref_inventory',
    };

    const SOURCE_COLORS = {
        sale: '#059669',
        manual: '#2563eb',
        expense: '#d97706',
        payment: '#7c3aed',
        purchase: '#0891b2',
        inventory: '#64748b',
    };

    const ACCOUNT_COLORS = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#ca8a04', '#64748b'];

    const state = {
        rows: [],
        stats: {},
        insights: {},
        charts: {},
        accounts: [],
        source: 'all',
        accountId: 'all',
        search: '',
        page: 1,
        searchTimer: null,
        chartInstances: {},
    };

    const els = {
        count: document.getElementById('accRevCount'),
        statPeriod: document.getElementById('accRevStatPeriod'),
        statToday: document.getElementById('accRevStatToday'),
        statSales: document.getElementById('accRevStatSales'),
        statManual: document.getElementById('accRevStatManual'),
        stats: document.getElementById('accRevStats'),
        insightLines: document.getElementById('accRevInsightLines'),
        insightAvg: document.getElementById('accRevInsightAvg'),
        insightAuto: document.getElementById('accRevInsightAuto'),
        insightTop: document.getElementById('accRevInsightTop'),
        search: document.getElementById('accRevSearch'),
        searchClear: document.getElementById('accRevSearchClear'),
        dateFrom: document.getElementById('accRevDateFrom'),
        dateTo: document.getElementById('accRevDateTo'),
        sourceFilters: document.getElementById('accRevSourceFilters'),
        accountFilters: document.getElementById('accRevAccountFilters'),
        meta: document.getElementById('accRevMeta'),
        pagePrev: document.getElementById('accRevPrev'),
        pageNext: document.getElementById('accRevNext'),
        pageInfo: document.getElementById('accRevPageInfo'),
        exportBtn: document.getElementById('accRevExportBtn'),
        printBtn: document.getElementById('accRevPrintBtn'),
        refreshBtn: document.getElementById('accRevRefreshBtn'),
        detailModal: document.getElementById('accRevDetailModal'),
        detailBody: document.getElementById('accRevDetailBody'),
        detailClose: document.getElementById('accRevDetailClose'),
        trendEmpty: document.getElementById('accRevTrendEmpty'),
        sourceEmpty: document.getElementById('accRevSourceEmpty'),
        accountsEmpty: document.getElementById('accRevAccountsEmpty'),
        sourceLegend: document.getElementById('accRevSourceLegend'),
    };

    function locale() {
        return window.ADMIN_CONFIG?.locale || 'fr-FR';
    }

    function pct(n) {
        return `${Number(n || 0).toLocaleString(locale(), { maximumFractionDigits: 1 })}%`;
    }

    function sourceLabel(src) {
        const key = SOURCE_LABELS[src || 'manual'];
        return key ? t(key) : (src || '—');
    }

    function sourceClass(src) {
        if (src === 'sale') return 'acc-rev-source--sale';
        if (src === 'manual') return 'acc-rev-source--manual';
        return 'acc-rev-source--other';
    }

    function formatDate(d) {
        if (!d) return '—';
        return new Date(`${d}T12:00:00`).toLocaleDateString(locale(), {
            day: '2-digit', month: 'short', year: 'numeric',
        });
    }

    function formatDayLabel(day) {
        if (!day) return '';
        return new Date(`${day}T12:00:00`).toLocaleDateString(locale(), { day: '2-digit', month: 'short' });
    }

    function queryParams() {
        return {
            source: state.source === 'all' ? '' : state.source,
            account_id: state.accountId === 'all' ? '' : state.accountId,
            search: state.search.trim(),
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
        };
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-rev-stat__value, .acc-rev-insight__value').forEach((el) => {
            el.classList.toggle('is-loading', on);
        });
    }

    function destroyChart(id) {
        if (state.chartInstances[id]) {
            state.chartInstances[id].destroy();
            delete state.chartInstances[id];
        }
    }

    function destroyAllCharts() {
        Object.keys(state.chartInstances).forEach(destroyChart);
    }

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: dark ? 'rgba(148,163,184,0.12)' : 'rgba(0,0,0,0.06)',
            text: dark ? '#9ca3af' : '#6b7280',
        };
    }

    function renderTrendChart(items) {
        const ctx = document.getElementById('accRevTrend');
        if (!ctx || !window.Chart) return;
        destroyChart('accRevTrend');
        const rows = items || [];
        const hasData = rows.some((x) => Number(x.amount) > 0);
        if (els.trendEmpty) els.trendEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accRevTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: rows.map((x) => formatDayLabel(x.day)),
                datasets: [{
                    label: t('rev_stat_period'),
                    data: rows.map((x) => x.amount),
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.08)',
                    fill: true,
                    tension: 0.35,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(ctx) {
                                return money(ctx.parsed?.y ?? 0);
                            },
                        },
                    },
                },
                scales: {
                    x: { grid: { color: c.grid }, ticks: { color: c.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                    y: { grid: { color: c.grid }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderSourceDonut(items) {
        const ctx = document.getElementById('accRevSource');
        if (!ctx || !window.Chart) return;
        destroyChart('accRevSource');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.sourceEmpty) els.sourceEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (els.sourceLegend) {
            els.sourceLegend.innerHTML = hasData
                ? filtered.map((item) => {
                    const color = SOURCE_COLORS[item.source] || '#64748b';
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(sourceLabel(item.source))}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.chartInstances.accRevSource = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map((x) => sourceLabel(x.source)),
                datasets: [{
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((x) => SOURCE_COLORS[x.source] || '#64748b'),
                    borderWidth: 0,
                }],
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { display: false } } },
        });
    }

    function renderAccountsChart(items) {
        const ctx = document.getElementById('accRevAccounts');
        if (!ctx || !window.Chart) return;
        destroyChart('accRevAccounts');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.accountsEmpty) els.accountsEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accRevAccounts = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => `${x.account_code}`),
                datasets: [{
                    label: t('rev_col_amount'),
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((_, i) => ACCOUNT_COLORS[i % ACCOUNT_COLORS.length]),
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title(ctx) {
                                const idx = ctx[0]?.dataIndex ?? 0;
                                const row = filtered[idx];
                                return row ? `${row.account_code} — ${row.account_name}` : '';
                            },
                            label(ctx) {
                                return money(ctx.parsed?.x ?? 0);
                            },
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

    function renderHero(stats) {
        if (els.statPeriod) els.statPeriod.textContent = money(stats.period_total);
        if (els.statToday) els.statToday.textContent = money(stats.today_total);
        if (els.statSales) els.statSales.textContent = money(stats.sale_total);
        if (els.statManual) els.statManual.textContent = money(stats.manual_total);
        document.querySelectorAll('.acc-rev-stat__value, .acc-rev-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.count) {
            const scope = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
            els.count.textContent = scope;
        }
    }

    function renderInsights(insights) {
        if (els.insightLines) els.insightLines.textContent = String(insights.line_count ?? 0);
        if (els.insightAvg) els.insightAvg.textContent = money(insights.avg_daily);
        if (els.insightAuto) els.insightAuto.textContent = pct(insights.auto_pct);
        if (els.insightTop) els.insightTop.textContent = insights.top_account || '—';
    }

    function renderAccountFilters(accounts) {
        if (!els.accountFilters) return;
        const opts = [{ id: 'all', code: '', name: t('rev_filter_all') }, ...(accounts || [])];
        els.accountFilters.innerHTML = opts.map((acc) => {
            const id = acc.id === 'all' ? 'all' : String(acc.id);
            const label = id === 'all' ? t('rev_filter_all') : `${acc.code} — ${acc.name}`;
            const active = state.accountId === id;
            return `<button type="button" class="acc-rev-chip${active ? ' is-active' : ''}" data-account="${esc(id)}" role="tab" aria-selected="${active}">${esc(label)}</button>`;
        }).join('');
        els.accountFilters.querySelectorAll('.acc-rev-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                state.accountId = chip.dataset.account || 'all';
                syncFilterChips();
                load();
            });
        });
    }

    function syncFilterChips() {
        els.sourceFilters?.querySelectorAll('.acc-rev-chip').forEach((chip) => {
            const active = chip.dataset.source === state.source;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        els.accountFilters?.querySelectorAll('.acc-rev-chip').forEach((chip) => {
            const active = chip.dataset.account === state.accountId;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function paginatedRows() {
        const rows = state.rows;
        const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        const start = (state.page - 1) * PAGE_SIZE;
        return { rows: rows.slice(start, start + PAGE_SIZE), totalPages, total: rows.length };
    }

    function openDetail(row) {
        if (!row || !els.detailBody || !els.detailModal) return;
        els.detailBody.innerHTML = `
            <dl class="acc-rev-detail-grid">
                <div><dt>${esc(t('rev_col_date'))}</dt><dd>${esc(formatDate(row.entry_date))}</dd></div>
                <div><dt>${esc(t('rev_col_entry'))}</dt><dd><code>${esc(row.entry_no || '—')}</code></dd></div>
                <div><dt>${esc(t('rev_col_account'))}</dt><dd>${esc(`${row.account_code || ''} — ${row.account_name || ''}`)}</dd></div>
                <div><dt>${esc(t('rev_col_amount'))}</dt><dd><strong>${esc(money(row.amount))}</strong></dd></div>
                <div><dt>${esc(t('rev_col_source'))}</dt><dd><span class="acc-rev-source ${sourceClass(row.reference_type)}">${esc(sourceLabel(row.reference_type))}</span></dd></div>
                <div><dt>${esc(t('rev_col_by'))}</dt><dd>${esc(row.created_by_name || '—')}</dd></div>
                <div class="acc-rev-detail-full"><dt>${esc(t('rev_col_description'))}</dt><dd>${esc(row.description || '—')}</dd></div>
                ${row.memo ? `<div class="acc-rev-detail-full"><dt>${esc(t('rev_memo'))}</dt><dd>${esc(row.memo)}</dd></div>` : ''}
                ${row.reference_id ? `<div><dt>${esc(t('rev_col_source'))} ID</dt><dd><code>#${esc(String(row.reference_id))}</code></dd></div>` : ''}
            </dl>`;
        els.detailModal.hidden = false;
        document.body.classList.add('acc-rev-modal-open');
    }

    function closeDetail() {
        if (!els.detailModal) return;
        els.detailModal.hidden = true;
        document.body.classList.remove('acc-rev-modal-open');
    }

    function renderTable() {
        const { rows, totalPages, total } = paginatedRows();
        if (els.pageInfo) els.pageInfo.textContent = `${state.page} / ${totalPages}`;
        if (els.pagePrev) els.pagePrev.disabled = state.page <= 1;
        if (els.pageNext) els.pageNext.disabled = state.page >= totalPages;
        if (els.meta) els.meta.textContent = total ? `${total} ${t('records')}` : t('no_data');

        if (!rows.length) {
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">payments</span><p>${esc(t('no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="acc-rev-table-wrap">
                <table class="modern-table acc-table acc-rev-table">
                    <thead><tr>
                        <th>${esc(t('rev_col_date'))}</th>
                        <th>${esc(t('rev_col_entry'))}</th>
                        <th>${esc(t('rev_col_account'))}</th>
                        <th class="acc-rev-num">${esc(t('rev_col_amount'))}</th>
                        <th>${esc(t('rev_col_source'))}</th>
                        <th>${esc(t('rev_col_description'))}</th>
                        <th>${esc(t('rev_col_by'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr>
                            <td>${esc(formatDate(r.entry_date))}</td>
                            <td><code>${esc(r.entry_no || '—')}</code></td>
                            <td>${esc(`${r.account_code} — ${r.account_name}`)}</td>
                            <td class="acc-rev-num"><strong>${esc(money(r.amount))}</strong></td>
                            <td><span class="acc-rev-source ${sourceClass(r.reference_type)}">${esc(sourceLabel(r.reference_type))}</span></td>
                            <td class="acc-rev-desc">${esc(r.description || '—')}</td>
                            <td>${esc(r.created_by_name || '—')}</td>
                            <td><button type="button" class="acc-rev-link" data-id="${esc(String(r.id))}">${esc(t('rev_view_details'))}</button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-rev-cards">${rows.map((r) => `
                <article class="acc-rev-card">
                    <div class="acc-rev-card__head">
                        <span>${esc(formatDate(r.entry_date))}</span>
                        <strong>${esc(money(r.amount))}</strong>
                    </div>
                    <h4>${esc(`${r.account_code} — ${r.account_name}`)}</h4>
                    <dl>
                        <div><dt>${esc(t('rev_col_entry'))}</dt><dd><code>${esc(r.entry_no || '—')}</code></dd></div>
                        <div><dt>${esc(t('rev_col_source'))}</dt><dd><span class="acc-rev-source ${sourceClass(r.reference_type)}">${esc(sourceLabel(r.reference_type))}</span></dd></div>
                    </dl>
                    <button type="button" class="acc-rev-link" data-id="${esc(String(r.id))}">${esc(t('rev_view_details'))}</button>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('.acc-rev-link').forEach((btn) => {
            btn.addEventListener('click', () => {
                const row = state.rows.find((r) => String(r.id) === btn.dataset.id);
                openDetail(row);
            });
        });
    }

    function renderCharts(charts) {
        renderTrendChart(charts?.trend);
        renderSourceDonut(charts?.by_source);
        renderAccountsChart(charts?.by_account);
    }

    function applyData(data) {
        state.rows = data.rows || [];
        state.stats = data.stats || {};
        state.insights = data.insights || {};
        state.charts = data.charts || {};
        state.accounts = data.accounts || [];
        state.page = 1;
        renderHero(state.stats);
        renderInsights(state.insights);
        renderAccountFilters(state.accounts);
        renderCharts(state.charts);
        renderTable();
    }

    async function load() {
        hideError();
        setLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getAccounting('revenues', queryParams());
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? res.data?.module_ready ?? true);
            applyData(res.data || {});
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyAllCharts();
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">payments</span><p>${esc(e.message)}</p></div>`;
        }
    }

    function exportData() {
        if (!state.rows.length) return;
        exportCsv(`revenues-${els.dateTo?.value || 'export'}.csv`, [
            [t('rev_col_date'), t('rev_col_entry'), t('rev_col_account'), t('rev_col_amount'), t('rev_col_source'), t('rev_col_description'), t('rev_col_by')],
            ...state.rows.map((r) => [
                r.entry_date,
                r.entry_no,
                `${r.account_code} — ${r.account_name}`,
                r.amount,
                r.reference_type,
                r.description || '',
                r.created_by_name || '',
            ]),
        ]);
    }

    els.stats?.querySelectorAll('.acc-rev-stat--click').forEach((btn) => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.statFilter || 'all';
            if (filter === 'today') {
                const today = new Date().toISOString().slice(0, 10);
                if (els.dateFrom) els.dateFrom.value = today;
                if (els.dateTo) els.dateTo.value = today;
                state.source = 'all';
                state.accountId = 'all';
            } else if (filter === 'sale' || filter === 'manual') {
                state.source = filter;
            } else {
                state.source = 'all';
            }
            syncFilterChips();
            load();
        });
    });

    els.sourceFilters?.querySelectorAll('.acc-rev-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            state.source = chip.dataset.source || 'all';
            syncFilterChips();
            load();
        });
    });

    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => {
            state.search = els.search.value;
            if (els.searchClear) els.searchClear.hidden = !state.search;
            load();
        }, 300);
    });

    els.searchClear?.addEventListener('click', () => {
        if (els.search) els.search.value = '';
        state.search = '';
        els.searchClear.hidden = true;
        load();
    });

    els.dateFrom?.addEventListener('change', load);
    els.dateTo?.addEventListener('change', load);
    els.pagePrev?.addEventListener('click', () => { state.page -= 1; renderTable(); });
    els.pageNext?.addEventListener('click', () => { state.page += 1; renderTable(); });
    els.refreshBtn?.addEventListener('click', load);
    els.exportBtn?.addEventListener('click', exportData);
    els.printBtn?.addEventListener('click', () => window.print());
    els.detailClose?.addEventListener('click', closeDetail);
    els.detailModal?.addEventListener('click', (e) => {
        if (e.target === els.detailModal) closeDetail();
    });

    document.addEventListener('acc:refresh', load);
    document.addEventListener('themechange', () => {
        if (state.charts) renderCharts(state.charts);
    });

    if (els.searchClear) els.searchClear.hidden = true;
    load();
});
