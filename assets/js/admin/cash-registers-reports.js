/**
 * Register session reports — history table, KPIs, CSV / PDF / print export
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crRptRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = CashRegistersUI;
    const PAGE_SIZE = 20;
    const VARIANCE_TOLERANCE = 500;

    const state = { items: [], search: '', page: 1 };

    const els = {
        search: document.getElementById('crRptSearch'),
        searchClear: document.getElementById('crRptSearchClear'),
        dateFrom: document.getElementById('crRptDateFrom'),
        dateTo: document.getElementById('crRptDateTo'),
        count: document.getElementById('crRptCount'),
        statSessions: document.getElementById('crRptStatSessions'),
        statSales: document.getElementById('crRptStatSales'),
        statVariance: document.getElementById('crRptStatVariance'),
        statToday: document.getElementById('crRptStatToday'),
        meta: document.getElementById('crRptMeta'),
        pagePrev: document.getElementById('crRptPrev'),
        pageNext: document.getElementById('crRptNext'),
        pageInfo: document.getElementById('crRptPageInfo'),
        exportCsvBtn: document.getElementById('crRptExportCsvBtn'),
        exportPdfBtn: document.getElementById('crRptExportPdfBtn'),
        printBtn: document.getElementById('crRptPrintBtn'),
        refreshBtn: document.getElementById('crRptRefreshBtn'),
    };

    function isToday(dateStr) {
        if (!dateStr) return false;
        const d = new Date(dateStr);
        const now = new Date();
        return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
    }

    function varianceClass(v) {
        const n = Math.abs(Number(v || 0));
        if (n >= VARIANCE_TOLERANCE) return 'is-danger';
        if (n > 0) return 'is-warn';
        return 'is-ok';
    }

    function filteredItems() {
        let list = [...state.items];
        const q = state.search.trim().toLowerCase();
        if (q) {
            list = list.filter((s) => {
                const hay = [s.register_name, s.register_code, s.cashier_name, s.store_name, s.status]
                    .map((v) => String(v ?? '').toLowerCase()).join(' ');
                return hay.includes(q);
            });
        }
        return list.sort((a, b) => new Date(b.opened_at || 0) - new Date(a.opened_at || 0));
    }

    function paginated() {
        const all = filteredItems();
        const totalPages = Math.max(1, Math.ceil(all.length / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        const start = (state.page - 1) * PAGE_SIZE;
        return { all, pageItems: all.slice(start, start + PAGE_SIZE), totalPages };
    }

    function sessionVariance(s) {
        if (s.variance != null && s.variance !== '') return Number(s.variance);
        const expected = Number(s.expected_cash ?? 0);
        const counted = Number(s.counted_cash ?? 0);
        if (s.status === 'closed' && counted) return counted - expected;
        return 0;
    }

    function updateStats() {
        const list = filteredItems();
        const sales = list.reduce((sum, s) => sum + Number(s.total_sales || 0), 0);
        const variance = list.reduce((sum, s) => sum + Math.abs(sessionVariance(s)), 0);
        const today = list.filter((s) => isToday(s.opened_at)).length;

        const set = (el, val) => { if (el) { el.textContent = val; el.classList.remove('is-loading'); } };
        set(els.statSessions, String(list.length));
        set(els.statSales, money(sales));
        set(els.statVariance, money(variance));
        set(els.statToday, String(today));
        if (els.count) els.count.textContent = list.length ? `${list.length}` : t('cr_no_data');
    }

    function renderTable() {
        const { all, pageItems, totalPages } = paginated();
        updateStats();
        window.__crHistoryItems = all;

        if (els.meta) els.meta.textContent = t('cr_rpt_table_summary', all.length, state.page, totalPages);
        if (els.pageInfo) els.pageInfo.textContent = `${state.page} / ${totalPages}`;
        if (els.pagePrev) els.pagePrev.disabled = state.page <= 1;
        if (els.pageNext) els.pageNext.disabled = state.page >= totalPages;

        if (!pageItems.length) {
            root.innerHTML = `<div class="cr-data-empty"><span class="material-icons-round">summarize</span><p>${esc(t('cr_no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="cr-data-table-wrap">
                <table class="modern-table cr-data-table" id="crReportTable">
                    <thead><tr>
                        <th>${esc(t('col_date'))}</th>
                        <th>${esc(t('cr_col_register'))}</th>
                        <th>${esc(t('cr_branch'))}</th>
                        <th>${esc(t('cr_col_cashier'))}</th>
                        <th>${esc(t('cr_opening_balance'))}</th>
                        <th>${esc(t('cr_col_expected'))}</th>
                        <th>${esc(t('cr_counted_cash'))}</th>
                        <th>${esc(t('cr_col_difference'))}</th>
                    </tr></thead>
                    <tbody>${pageItems.map((s) => {
                        const v = sessionVariance(s);
                        return `<tr>
                            <td>${esc(AdminAPI.formatDate(s.opened_at))}</td>
                            <td>${esc(s.register_name)}</td>
                            <td>${esc(s.store_name || '—')}</td>
                            <td>${esc(s.cashier_name || '—')}</td>
                            <td>${esc(money(s.opening_balance))}</td>
                            <td>${esc(money(s.expected_cash))}</td>
                            <td>${esc(s.counted_cash != null ? money(s.counted_cash) : '—')}</td>
                            <td class="${varianceClass(v)}"><strong>${esc(money(v))}</strong></td>
                        </tr>`;
                    }).join('')}</tbody>
                </table>
            </div>
            <div class="cr-data-list">${pageItems.map((s) => {
                const v = sessionVariance(s);
                return `<article class="cr-data-list__item cr-data-list__item--stack">
                    <div class="cr-data-list__main">
                        <strong>${esc(s.register_name)}</strong>
                        <span>${esc(s.cashier_name || '—')} · ${esc(AdminAPI.formatDate(s.opened_at))}</span>
                        <span>${esc(s.store_name || '—')}</span>
                    </div>
                    <div class="cr-data-list__side">
                        <span class="${varianceClass(v)}">${esc(t('cr_col_difference'))}: <strong>${esc(money(v))}</strong></span>
                        <span>${esc(t('cr_rpt_stat_sales'))}: ${esc(money(s.total_sales))}</span>
                    </div>
                </article>`;
            }).join('')}
            </div>`;
    }

    function exportData() {
        const list = filteredItems();
        if (!list.length) return;
        exportCsv(`cash-register-report-${new Date().toISOString().slice(0, 10)}.csv`, [
            [t('col_date'), t('cr_col_register'), t('cr_branch'), t('cr_col_cashier'), t('cr_opening_balance'), t('cr_col_expected'), t('cr_counted_cash'), t('cr_col_difference')],
            ...list.map((s) => [
                AdminAPI.formatDate(s.opened_at), s.register_name, s.store_name, s.cashier_name,
                s.opening_balance, s.expected_cash, s.counted_cash, sessionVariance(s),
            ]),
        ]);
    }

    async function exportPdf() {
        const list = filteredItems();
        if (!list.length) { showError(t('cr_no_data')); return; }
        if (!window.CashRegisterReportExport) return;
        els.exportPdfBtn?.setAttribute('disabled', 'disabled');
        try {
            const ctx = CashRegisterReportExport.buildHistoryContext(
                list,
                els.dateFrom?.value || '',
                els.dateTo?.value || '',
                t,
                window.ADMIN_CONFIG?.locale || 'fr-FR'
            );
            const result = await CashRegisterReportExport.exportPdf(ctx);
            if (result?.fallback) showError(t('pdf_fallback_print'));
        } catch (e) {
            showError(e.message || t('error'));
        } finally {
            els.exportPdfBtn?.removeAttribute('disabled');
        }
    }

    function printTable() {
        const table = document.getElementById('crReportTable');
        if (!table) return;
        const w = window.open('', '_blank');
        w.document.write(`<html><head><title>${document.title}</title><style>body{font-family:sans-serif;padding:16px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:6px;font-size:12px}th{background:#2563eb;color:#fff}</style></head><body><h2>${document.title}</h2>${table.outerHTML}</body></html>`);
        w.document.close();
        w.print();
    }

    function initToolbar() {
        els.search?.addEventListener('input', () => { state.search = els.search.value; els.searchClear?.classList.toggle('visible', !!els.search.value); state.page = 1; renderTable(); });
        els.searchClear?.addEventListener('click', () => {
            if (els.search) els.search.value = '';
            els.searchClear?.classList.remove('visible');
            state.search = '';
            state.page = 1;
            renderTable();
        });
        els.dateFrom?.addEventListener('change', () => load());
        els.dateTo?.addEventListener('change', () => load());
        els.exportCsvBtn?.addEventListener('click', exportData);
        els.exportPdfBtn?.addEventListener('click', exportPdf);
        els.printBtn?.addEventListener('click', printTable);
        els.refreshBtn?.addEventListener('click', () => load());
        els.pagePrev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; renderTable(); } });
        els.pageNext?.addEventListener('click', () => { state.page += 1; renderTable(); });
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('#crRptStats .cr-data-stat__value').forEach((el) => el.classList.add('is-loading'));
        try {
            const res = await AdminAPI.getCashRegisterHistory({
                from: els.dateFrom?.value || undefined,
                to: els.dateTo?.value || undefined,
            });
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            state.items = res.data || [];
            state.page = 1;
            renderTable();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<div class="cr-data-empty"><span class="material-icons-round">error_outline</span><p>${esc(e.message)}</p></div>`;
        }
    }

    initToolbar();
    load();
    document.addEventListener('cr:refresh', load);
});
