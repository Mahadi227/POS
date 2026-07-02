/**
 * Accounting accounts receivable v1 — customer invoices, aging, charts
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accArRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;
    const PAGE_SIZE = 15;

    const STATUS_LABELS = {
        open: 'ar_status_open',
        partial: 'ar_status_partial',
        overdue: 'ar_status_overdue',
        paid: 'ar_status_paid',
        written_off: 'ar_status_written_off',
    };

    const STATUS_COLORS = {
        open: '#2563eb',
        partial: '#d97706',
        overdue: '#dc2626',
        paid: '#059669',
        written_off: '#64748b',
    };
    const AGING_LABELS = { current: 'ar_aging_current', '1_30': 'ar_aging_30', '31_60': 'ar_aging_60', '60_plus': 'ar_aging_90' };
    const AGING_COLORS = ['#059669', '#2563eb', '#d97706', '#dc2626'];

    const state = {
        rows: [],
        stats: {},
        insights: {},
        charts: {},
        status: 'all',
        search: '',
        page: 1,
        selected: null,
        searchTimer: null,
        chartInstances: {},
    };

    const els = {
        count: document.getElementById('accArCount'),
        statOutstanding: document.getElementById('accArStatOutstanding'),
        statOpen: document.getElementById('accArStatOpen'),
        statOverdue: document.getElementById('accArStatOverdue'),
        statCollected: document.getElementById('accArStatCollected'),
        stats: document.getElementById('accArStats'),
        customers: document.getElementById('accArCustomers'),
        avgBalance: document.getElementById('accArAvgBalance'),
        overdueRatio: document.getElementById('accArOverdueRatio'),
        collectedRatio: document.getElementById('accArCollectedRatio'),
        search: document.getElementById('accArSearch'),
        searchClear: document.getElementById('accArSearchClear'),
        dateFrom: document.getElementById('accArDateFrom'),
        dateTo: document.getElementById('accArDateTo'),
        statusFilters: document.getElementById('accArStatusFilters'),
        meta: document.getElementById('accArMeta'),
        pagePrev: document.getElementById('accArPrev'),
        pageNext: document.getElementById('accArNext'),
        pageInfo: document.getElementById('accArPageInfo'),
        exportBtn: document.getElementById('accArExportBtn'),
        printBtn: document.getElementById('accArPrintBtn'),
        refreshBtn: document.getElementById('accArRefreshBtn'),
        detailModal: document.getElementById('accArDetailModal'),
        detailBody: document.getElementById('accArDetailBody'),
        detailClose: document.getElementById('accArDetailClose'),
        statusEmpty: document.getElementById('accArStatusEmpty'),
        agingEmpty: document.getElementById('accArAgingEmpty'),
        customersEmpty: document.getElementById('accArCustomersEmpty'),
        statusLegend: document.getElementById('accArStatusLegend'),
    };

    function locale() {
        return window.ADMIN_CONFIG?.locale || 'fr-FR';
    }

    function pct(n) {
        return `${Number(n || 0).toLocaleString(locale(), { maximumFractionDigits: 1 })}%`;
    }

    function statusLabel(status) {
        const key = STATUS_LABELS[status];
        return key ? t(key) : (status || '—');
    }

    function statusClass(status) {
        if (status === 'paid') return 'acc-ar-status--paid';
        if (status === 'overdue') return 'acc-ar-status--overdue';
        if (status === 'partial') return 'acc-ar-status--partial';
        if (status === 'written_off') return 'acc-ar-status--written';
        return 'acc-ar-status--open';
    }

    function formatDate(d) {
        if (!d) return '—';
        return new Date(`${d}T12:00:00`).toLocaleDateString(locale(), { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function queryParams() {
        return {
            status: state.status === 'all' ? '' : state.status,
            search: state.search.trim(),
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
        };
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-ar-stat__value, .acc-ar-insight__value').forEach((el) => {
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

    function renderStatusDonut(items) {
        const ctx = document.getElementById('accArStatus');
        if (!ctx || !window.Chart) return;
        destroyChart('accArStatus');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.statusEmpty) els.statusEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (els.statusLegend) {
            els.statusLegend.innerHTML = hasData
                ? filtered.map((item) => {
                    const color = STATUS_COLORS[item.key] || '#64748b';
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(statusLabel(item.key))}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.chartInstances.accArStatus = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map((x) => statusLabel(x.key)),
                datasets: [{
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((x) => STATUS_COLORS[x.key] || '#64748b'),
                    borderWidth: 0,
                }],
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { display: false } } },
        });
    }

    function renderAgingChart(items) {
        const ctx = document.getElementById('accArAging');
        if (!ctx || !window.Chart) return;
        destroyChart('accArAging');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.agingEmpty) els.agingEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accArAging = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => t(AGING_LABELS[x.key] || x.key)),
                datasets: [{
                    label: t('ar_col_balance'),
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((_, i) => AGING_COLORS[i % AGING_COLORS.length]),
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: c.text } },
                    y: { grid: { color: c.grid }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderCustomersChart(items) {
        const ctx = document.getElementById('accArCustomers');
        if (!ctx || !window.Chart) return;
        destroyChart('accArCustomers');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.customersEmpty) els.customersEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accArCustomers = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => x.customer),
                datasets: [{
                    label: t('ar_col_balance'),
                    data: filtered.map((x) => x.amount),
                    backgroundColor: 'rgba(5,150,105,0.75)',
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: c.grid }, ticks: { color: c.text } },
                    y: { grid: { display: false }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderHero(stats, insights) {
        if (els.statOutstanding) els.statOutstanding.textContent = money(stats.outstanding);
        if (els.statOpen) els.statOpen.textContent = money(stats.open_amount);
        if (els.statOverdue) els.statOverdue.textContent = money(stats.overdue_amount);
        if (els.statCollected) els.statCollected.textContent = money(stats.paid_amount);

        if (els.customers) els.customers.textContent = String(insights.customer_count ?? 0);
        if (els.avgBalance) els.avgBalance.textContent = money(insights.avg_balance);
        if (els.overdueRatio) els.overdueRatio.textContent = pct(insights.overdue_ratio);
        if (els.collectedRatio) els.collectedRatio.textContent = pct(insights.collected_ratio);

        document.querySelectorAll('.acc-ar-stat__value, .acc-ar-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.count) {
            const scope = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
            els.count.textContent = `${scope} · ${state.rows.length} ${t('records')}`;
        }
    }

    function paginatedRows() {
        const rows = state.rows;
        const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        const start = (state.page - 1) * PAGE_SIZE;
        return { rows: rows.slice(start, start + PAGE_SIZE), totalPages, total: rows.length };
    }

    function renderTable() {
        const { rows, totalPages, total } = paginatedRows();
        if (els.pageInfo) els.pageInfo.textContent = `${state.page} / ${totalPages}`;
        if (els.pagePrev) els.pagePrev.disabled = state.page <= 1;
        if (els.pageNext) els.pageNext.disabled = state.page >= totalPages;
        if (els.meta) els.meta.textContent = total ? `${total} ${t('records')}` : t('no_data');

        if (!rows.length) {
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">account_balance_wallet</span><p>${esc(t('no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="acc-ar-table-wrap">
                <table class="modern-table acc-table acc-ar-table">
                    <thead><tr>
                        <th>${esc(t('ar_col_invoice'))}</th>
                        <th>${esc(t('ar_col_customer'))}</th>
                        <th>${esc(t('ar_col_due'))}</th>
                        <th class="acc-ar-num">${esc(t('ar_col_amount'))}</th>
                        <th class="acc-ar-num">${esc(t('ar_col_paid'))}</th>
                        <th class="acc-ar-num">${esc(t('ar_col_balance'))}</th>
                        <th>${esc(t('ar_col_status'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr>
                            <td><code>${esc(r.invoice_no)}</code></td>
                            <td>${esc(r.customer_name || '—')}</td>
                            <td>${esc(formatDate(r.due_date))}</td>
                            <td class="acc-ar-num">${esc(money(r.amount))}</td>
                            <td class="acc-ar-num">${esc(money(r.amount_paid))}</td>
                            <td class="acc-ar-num">${esc(money(r.balance ?? (r.amount - r.amount_paid)))}</td>
                            <td><span class="acc-ar-status ${statusClass(r.status)}">${esc(statusLabel(r.status))}</span></td>
                            <td><button type="button" class="acc-ar-link" data-id="${esc(String(r.id))}">${esc(t('ar_view_details'))}</button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-ar-cards">${rows.map((r) => `
                <article class="acc-ar-card">
                    <div class="acc-ar-card__head">
                        <code>${esc(r.invoice_no)}</code>
                        <span class="acc-ar-status ${statusClass(r.status)}">${esc(statusLabel(r.status))}</span>
                    </div>
                    <h4>${esc(r.customer_name || '—')}</h4>
                    <dl>
                        <div><dt>${esc(t('ar_col_due'))}</dt><dd>${esc(formatDate(r.due_date))}</dd></div>
                        <div><dt>${esc(t('ar_col_balance'))}</dt><dd>${esc(money(r.balance ?? (r.amount - r.amount_paid)))}</dd></div>
                    </dl>
                    <button type="button" class="acc-ar-link" data-id="${esc(String(r.id))}">${esc(t('ar_view_details'))}</button>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('.acc-ar-link').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.id)));
        });
    }

    function openDetail(id) {
        const row = state.rows.find((r) => Number(r.id) === id);
        if (!row || !els.detailBody || !els.detailModal) return;
        state.selected = row;
        els.detailBody.innerHTML = `
            <dl class="acc-ar-detail-grid">
                <div><dt>${esc(t('ar_col_invoice'))}</dt><dd><code>${esc(row.invoice_no)}</code></dd></div>
                <div><dt>${esc(t('ar_col_customer'))}</dt><dd>${esc(row.customer_name || '—')}</dd></div>
                <div><dt>${esc(t('ar_col_due'))}</dt><dd>${esc(formatDate(row.due_date))}</dd></div>
                <div><dt>${esc(t('ar_col_status'))}</dt><dd><span class="acc-ar-status ${statusClass(row.status)}">${esc(statusLabel(row.status))}</span></dd></div>
                <div><dt>${esc(t('ar_col_amount'))}</dt><dd>${esc(money(row.amount))}</dd></div>
                <div><dt>${esc(t('ar_col_paid'))}</dt><dd>${esc(money(row.amount_paid))}</dd></div>
                <div><dt>${esc(t('ar_col_balance'))}</dt><dd><strong>${esc(money(row.balance ?? (row.amount - row.amount_paid)))}</strong></dd></div>
                ${row.notes ? `<div class="acc-ar-detail-full"><dt>${esc(t('ar_notes'))}</dt><dd>${esc(row.notes)}</dd></div>` : ''}
            </dl>`;
        els.detailModal.hidden = false;
        document.body.classList.add('acc-ar-modal-open');
    }

    function closeDetail() {
        if (!els.detailModal) return;
        els.detailModal.hidden = true;
        document.body.classList.remove('acc-ar-modal-open');
        state.selected = null;
    }

    function renderCharts(charts) {
        renderStatusDonut(charts?.by_status);
        renderAgingChart(charts?.aging);
        renderCustomersChart(charts?.by_customer);
    }

    async function load() {
        hideError();
        setLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getAccounting('receivables', queryParams());
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            const data = res.data || {};
            state.rows = data.rows || [];
            state.stats = data.stats || {};
            state.insights = data.insights || {};
            state.charts = data.charts || {};
            state.page = 1;
            renderHero(state.stats, state.insights);
            renderCharts(state.charts);
            renderTable();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyAllCharts();
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">account_balance_wallet</span><p>${esc(e.message)}</p></div>`;
        }
    }

    function exportData() {
        if (!state.rows.length) return;
        exportCsv(`accounts-receivable-${els.dateTo?.value || 'export'}.csv`, [
            [t('ar_col_invoice'), t('ar_col_customer'), t('ar_col_due'), t('ar_col_amount'), t('ar_col_paid'), t('ar_col_balance'), t('ar_col_status')],
            ...state.rows.map((r) => [
                r.invoice_no,
                r.customer_name,
                r.due_date,
                r.amount,
                r.amount_paid,
                r.balance ?? (r.amount - r.amount_paid),
                r.status,
            ]),
        ]);
    }

    els.stats?.querySelectorAll('.acc-ar-stat--click').forEach((btn) => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.statFilter || 'all';
            state.status = filter;
            els.statusFilters?.querySelectorAll('.acc-ar-chip').forEach((chip) => {
                const active = chip.dataset.status === filter;
                chip.classList.toggle('is-active', active);
                chip.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            load();
        });
    });

    els.statusFilters?.querySelectorAll('.acc-ar-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            state.status = chip.dataset.status || 'all';
            els.statusFilters.querySelectorAll('.acc-ar-chip').forEach((c) => {
                const active = c.dataset.status === state.status;
                c.classList.toggle('is-active', active);
                c.setAttribute('aria-selected', active ? 'true' : 'false');
            });
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
