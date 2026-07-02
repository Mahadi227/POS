/**
 * Accounting accounts payable v1 — supplier invoices, aging, charts
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accApRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;
    const PAGE_SIZE = 15;

    const STATUS_LABELS = {
        open: 'ap_status_open',
        partial: 'ap_status_partial',
        overdue: 'ap_status_overdue',
        paid: 'ap_status_paid',
    };

    const STATUS_COLORS = { open: '#2563eb', partial: '#d97706', overdue: '#dc2626', paid: '#059669' };
    const AGING_LABELS = { current: 'ap_aging_current', '1_30': 'ap_aging_30', '31_60': 'ap_aging_60', '60_plus': 'ap_aging_90' };
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
        count: document.getElementById('accApCount'),
        statOutstanding: document.getElementById('accApStatOutstanding'),
        statOpen: document.getElementById('accApStatOpen'),
        statOverdue: document.getElementById('accApStatOverdue'),
        statPaid: document.getElementById('accApStatPaid'),
        stats: document.getElementById('accApStats'),
        suppliers: document.getElementById('accApSuppliers'),
        avgBalance: document.getElementById('accApAvgBalance'),
        overdueRatio: document.getElementById('accApOverdueRatio'),
        paidRatio: document.getElementById('accApPaidRatio'),
        search: document.getElementById('accApSearch'),
        searchClear: document.getElementById('accApSearchClear'),
        dateFrom: document.getElementById('accApDateFrom'),
        dateTo: document.getElementById('accApDateTo'),
        statusFilters: document.getElementById('accApStatusFilters'),
        meta: document.getElementById('accApMeta'),
        pagePrev: document.getElementById('accApPrev'),
        pageNext: document.getElementById('accApNext'),
        pageInfo: document.getElementById('accApPageInfo'),
        exportBtn: document.getElementById('accApExportBtn'),
        printBtn: document.getElementById('accApPrintBtn'),
        refreshBtn: document.getElementById('accApRefreshBtn'),
        detailModal: document.getElementById('accApDetailModal'),
        detailBody: document.getElementById('accApDetailBody'),
        detailClose: document.getElementById('accApDetailClose'),
        statusEmpty: document.getElementById('accApStatusEmpty'),
        agingEmpty: document.getElementById('accApAgingEmpty'),
        suppliersEmpty: document.getElementById('accApSuppliersEmpty'),
        statusLegend: document.getElementById('accApStatusLegend'),
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
        if (status === 'paid') return 'acc-ap-status--paid';
        if (status === 'overdue') return 'acc-ap-status--overdue';
        if (status === 'partial') return 'acc-ap-status--partial';
        return 'acc-ap-status--open';
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
        document.querySelectorAll('.acc-ap-stat__value, .acc-ap-insight__value').forEach((el) => {
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
        const ctx = document.getElementById('accApStatus');
        if (!ctx || !window.Chart) return;
        destroyChart('accApStatus');
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

        state.chartInstances.accApStatus = new Chart(ctx, {
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
        const ctx = document.getElementById('accApAging');
        if (!ctx || !window.Chart) return;
        destroyChart('accApAging');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.agingEmpty) els.agingEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accApAging = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => t(AGING_LABELS[x.key] || x.key)),
                datasets: [{
                    label: t('ap_col_balance'),
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

    function renderSuppliersChart(items) {
        const ctx = document.getElementById('accApSuppliers');
        if (!ctx || !window.Chart) return;
        destroyChart('accApSuppliers');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.suppliersEmpty) els.suppliersEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accApSuppliers = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => x.supplier),
                datasets: [{
                    label: t('ap_col_balance'),
                    data: filtered.map((x) => x.amount),
                    backgroundColor: 'rgba(217,119,6,0.75)',
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
        if (els.statPaid) els.statPaid.textContent = money(stats.paid_amount);

        if (els.suppliers) els.suppliers.textContent = String(insights.supplier_count ?? 0);
        if (els.avgBalance) els.avgBalance.textContent = money(insights.avg_balance);
        if (els.overdueRatio) els.overdueRatio.textContent = pct(insights.overdue_ratio);
        if (els.paidRatio) els.paidRatio.textContent = pct(insights.paid_ratio);

        document.querySelectorAll('.acc-ap-stat__value, .acc-ap-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.count) {
            const scope = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
            els.count.textContent = `${scope} · ${state.rows.length} ${t('records')}`;
        }
    }

    function filteredRows() {
        return state.rows;
    }

    function paginatedRows() {
        const rows = filteredRows();
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
        if (els.meta) {
            els.meta.textContent = total
                ? `${total} ${t('records')}`
                : t('no_data');
        }

        if (!rows.length) {
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">receipt_long</span><p>${esc(t('no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="acc-ap-table-wrap">
                <table class="modern-table acc-table acc-ap-table">
                    <thead><tr>
                        <th>${esc(t('ap_col_invoice'))}</th>
                        <th>${esc(t('ap_col_supplier'))}</th>
                        <th>${esc(t('ap_col_due'))}</th>
                        <th class="acc-ap-num">${esc(t('ap_col_amount'))}</th>
                        <th class="acc-ap-num">${esc(t('ap_col_paid'))}</th>
                        <th class="acc-ap-num">${esc(t('ap_col_balance'))}</th>
                        <th>${esc(t('ap_col_status'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr>
                            <td><code>${esc(r.invoice_no)}</code></td>
                            <td>${esc(r.supplier_name || '—')}</td>
                            <td>${esc(formatDate(r.due_date))}</td>
                            <td class="acc-ap-num">${esc(money(r.amount))}</td>
                            <td class="acc-ap-num">${esc(money(r.amount_paid))}</td>
                            <td class="acc-ap-num">${esc(money(r.balance ?? (r.amount - r.amount_paid)))}</td>
                            <td><span class="acc-ap-status ${statusClass(r.status)}">${esc(statusLabel(r.status))}</span></td>
                            <td><button type="button" class="acc-ap-link" data-id="${esc(String(r.id))}">${esc(t('ap_view_details'))}</button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-ap-cards">${rows.map((r) => `
                <article class="acc-ap-card">
                    <div class="acc-ap-card__head">
                        <code>${esc(r.invoice_no)}</code>
                        <span class="acc-ap-status ${statusClass(r.status)}">${esc(statusLabel(r.status))}</span>
                    </div>
                    <h4>${esc(r.supplier_name || '—')}</h4>
                    <dl>
                        <div><dt>${esc(t('ap_col_due'))}</dt><dd>${esc(formatDate(r.due_date))}</dd></div>
                        <div><dt>${esc(t('ap_col_balance'))}</dt><dd>${esc(money(r.balance ?? (r.amount - r.amount_paid)))}</dd></div>
                    </dl>
                    <button type="button" class="acc-ap-link" data-id="${esc(String(r.id))}">${esc(t('ap_view_details'))}</button>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('.acc-ap-link').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.id)));
        });
    }

    function openDetail(id) {
        const row = state.rows.find((r) => Number(r.id) === id);
        if (!row || !els.detailBody || !els.detailModal) return;
        state.selected = row;
        els.detailBody.innerHTML = `
            <dl class="acc-ap-detail-grid">
                <div><dt>${esc(t('ap_col_invoice'))}</dt><dd><code>${esc(row.invoice_no)}</code></dd></div>
                <div><dt>${esc(t('ap_col_supplier'))}</dt><dd>${esc(row.supplier_name || '—')}</dd></div>
                <div><dt>${esc(t('ap_col_due'))}</dt><dd>${esc(formatDate(row.due_date))}</dd></div>
                <div><dt>${esc(t('ap_col_status'))}</dt><dd><span class="acc-ap-status ${statusClass(row.status)}">${esc(statusLabel(row.status))}</span></dd></div>
                <div><dt>${esc(t('ap_col_amount'))}</dt><dd>${esc(money(row.amount))}</dd></div>
                <div><dt>${esc(t('ap_col_paid'))}</dt><dd>${esc(money(row.amount_paid))}</dd></div>
                <div><dt>${esc(t('ap_col_balance'))}</dt><dd><strong>${esc(money(row.balance ?? (row.amount - row.amount_paid)))}</strong></dd></div>
                ${row.notes ? `<div class="acc-ap-detail-full"><dt>${esc(t('ap_notes'))}</dt><dd>${esc(row.notes)}</dd></div>` : ''}
            </dl>`;
        els.detailModal.hidden = false;
        document.body.classList.add('acc-ap-modal-open');
    }

    function closeDetail() {
        if (!els.detailModal) return;
        els.detailModal.hidden = true;
        document.body.classList.remove('acc-ap-modal-open');
        state.selected = null;
    }

    function renderCharts(charts) {
        renderStatusDonut(charts?.by_status);
        renderAgingChart(charts?.aging);
        renderSuppliersChart(charts?.by_supplier);
    }

    async function load() {
        hideError();
        setLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getAccounting('payables', queryParams());
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
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">receipt_long</span><p>${esc(e.message)}</p></div>`;
        }
    }

    function exportData() {
        const rows = filteredRows();
        if (!rows.length) return;
        exportCsv(`accounts-payable-${els.dateTo?.value || 'export'}.csv`, [
            [t('ap_col_invoice'), t('ap_col_supplier'), t('ap_col_due'), t('ap_col_amount'), t('ap_col_paid'), t('ap_col_balance'), t('ap_col_status')],
            ...rows.map((r) => [
                r.invoice_no,
                r.supplier_name,
                r.due_date,
                r.amount,
                r.amount_paid,
                r.balance ?? (r.amount - r.amount_paid),
                r.status,
            ]),
        ]);
    }

    els.stats?.querySelectorAll('.acc-ap-stat--click').forEach((btn) => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.statFilter || 'all';
            state.status = filter;
            els.statusFilters?.querySelectorAll('.acc-ap-chip').forEach((chip) => {
                const active = chip.dataset.status === filter;
                chip.classList.toggle('is-active', active);
                chip.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            load();
        });
    });

    els.statusFilters?.querySelectorAll('.acc-ap-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            state.status = chip.dataset.status || 'all';
            els.statusFilters.querySelectorAll('.acc-ap-chip').forEach((c) => {
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
