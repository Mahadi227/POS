/**
 * Warehouse — Performance Report
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whWperfTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const CACHE_KEY = 'wh_warehouse_performance_v1';

    const STOCK_STATUS_KEYS = {
        in_stock: 'wh_wperf_stock_in_stock',
        low_stock: 'wh_wperf_stock_low',
        out_of_stock: 'wh_wperf_stock_out',
    };
    const STOCK_COLORS = ['#059669', '#d97706', '#dc2626'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        charts: null,
        searchTimer: null,
    };

    const chartInstances = {};

    const els = {
        loading: document.getElementById('whWperfLoading'),
        empty: document.getElementById('whWperfEmpty'),
        period: document.getElementById('whWperfPeriod'),
        warehouse: document.getElementById('whWperfWarehouse'),
        search: document.getElementById('whWperfSearch'),
        dateFrom: document.getElementById('whWperfDateFrom'),
        dateTo: document.getElementById('whWperfDateTo'),
        dateFromWrap: document.getElementById('whWperfDateFromWrap'),
        dateToWrap: document.getElementById('whWperfDateToWrap'),
        refresh: document.getElementById('whWperfRefreshBtn'),
        exportCsv: document.getElementById('whWperfExportCsv'),
        exportExcel: document.getElementById('whWperfExportExcel'),
        exportPdf: document.getElementById('whWperfExportPdf'),
        printBtn: document.getElementById('whWperfPrintBtn'),
        heroMeta: document.getElementById('whWperfHeroMeta'),
        statMovements: document.getElementById('whWperfStatMovements'),
        statIn: document.getElementById('whWperfStatIn'),
        statOut: document.getElementById('whWperfStatOut'),
        statMovValue: document.getElementById('whWperfStatMovValue'),
        statInvValue: document.getElementById('whWperfStatInvValue'),
        statExpiring: document.getElementById('whWperfStatExpiring'),
        scoreUtil: document.getElementById('whWperfScoreUtil'),
        scoreTurnover: document.getElementById('whWperfScoreTurnover'),
        scoreReceiving: document.getElementById('whWperfScoreReceiving'),
        scoreDispatch: document.getElementById('whWperfScoreDispatch'),
        pagination: document.getElementById('whWperfPagination'),
        prev: document.getElementById('whWperfPrev'),
        next: document.getElementById('whWperfNext'),
        pageMeta: document.getElementById('whWperfPageMeta'),
        offlineBadge: document.getElementById('whWperfOfflineBadge'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return { grid: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)', text: dark ? '#94a3b8' : '#64748b' };
    }

    function pct(v) {
        const n = Number(v);
        return Number.isFinite(n) ? `${n.toFixed(1)}%` : '—';
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-wperf-stat__value, .wh-wperf-score__value').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statMovements) els.statMovements.textContent = String(s.movements ?? 0);
        if (els.statIn) els.statIn.textContent = String(s.stock_in ?? 0);
        if (els.statOut) els.statOut.textContent = String(s.stock_out ?? 0);
        if (els.statMovValue) els.statMovValue.textContent = money(s.movement_value ?? 0);
        if (els.statInvValue) els.statInvValue.textContent = money(s.inventory_value ?? 0);
        if (els.statExpiring) els.statExpiring.textContent = String(s.expiring_soon ?? 0);
        if (els.scoreUtil) els.scoreUtil.textContent = pct(s.warehouse_utilization ?? s.capacity_used_pct ?? 0);
        if (els.scoreTurnover) els.scoreTurnover.textContent = String(s.inventory_turnover ?? 0);
        if (els.scoreReceiving) els.scoreReceiving.textContent = pct(s.receiving_efficiency ?? 0);
        if (els.scoreDispatch) els.scoreDispatch.textContent = pct(s.dispatch_efficiency ?? 0);
        setStatsLoading(false);
    }

    function destroyChart(id) {
        if (chartInstances[id]) {
            chartInstances[id].destroy();
            delete chartInstances[id];
        }
    }

    function renderCharts(charts) {
        if (!window.Chart) return;
        const c = chartColors();
        const data = charts || {};

        destroyChart('throughput');
        const throughputCtx = document.getElementById('whWperfChartThroughput');
        const trend = data.movement_trend || [];
        if (throughputCtx && trend.length) {
            chartInstances.throughput = new Chart(throughputCtx, {
                type: 'line',
                data: {
                    labels: trend.map((d) => d.d),
                    datasets: [
                        { label: t('wh_wperf_stat_stock_in'), data: trend.map((d) => d.stock_in), borderColor: '#059669', tension: 0.35 },
                        { label: t('wh_wperf_stat_stock_out'), data: trend.map((d) => d.stock_out), borderColor: '#d97706', tension: 0.35 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: c.text } } },
                    scales: {
                        x: { ticks: { color: c.text }, grid: { color: c.grid } },
                        y: { ticks: { color: c.text }, grid: { color: c.grid } },
                    },
                },
            });
        }

        destroyChart('comparison');
        const compCtx = document.getElementById('whWperfChartComparison');
        const comp = data.warehouse_comparison || [];
        if (compCtx && comp.length) {
            chartInstances.comparison = new Chart(compCtx, {
                type: 'bar',
                data: {
                    labels: comp.map((d) => d.label),
                    datasets: [{ data: comp.map((d) => d.value), backgroundColor: '#2563eb', borderRadius: 6 }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: c.text, maxRotation: 45 }, grid: { display: false } },
                        y: { ticks: { color: c.text }, grid: { color: c.grid } },
                    },
                },
            });
        }

        destroyChart('top');
        const topCtx = document.getElementById('whWperfChartTop');
        const top = data.top_moving || [];
        if (topCtx && top.length) {
            chartInstances.top = new Chart(topCtx, {
                type: 'doughnut',
                data: {
                    labels: top.map((d) => d.label),
                    datasets: [{ data: top.map((d) => d.qty), backgroundColor: ['#2563eb', '#0d9488', '#d97706', '#7c3aed', '#dc2626', '#059669', '#ea580c', '#4f46e5', '#0891b2', '#64748b'] }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: c.text, boxWidth: 12 } } },
                },
            });
        }

        destroyChart('status');
        const statusCtx = document.getElementById('whWperfChartStatus');
        const statusData = (data.stock_status || []).filter((d) => Number(d.count) > 0);
        if (statusCtx && statusData.length) {
            chartInstances.status = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusData.map((d) => t(STOCK_STATUS_KEYS[d.status] || d.status)),
                    datasets: [{ data: statusData.map((d) => d.count), backgroundColor: STOCK_COLORS }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: c.text, boxWidth: 12 } } },
                },
            });
        }
    }

    function syncPeriodUi() {
        const custom = els.period?.value === 'custom';
        if (els.dateFrom) els.dateFrom.disabled = !custom;
        if (els.dateTo) els.dateTo.disabled = !custom;
        if (els.dateFromWrap) els.dateFromWrap.hidden = !custom;
        if (els.dateToWrap) els.dateToWrap.hidden = !custom;
    }

    function buildParams(forExport = false) {
        const params = {
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
        };
        const period = els.period?.value?.trim();
        if (period && period !== 'custom') {
            params.period = period;
        } else {
            const from = els.dateFrom?.value?.trim();
            const to = els.dateTo?.value?.trim();
            if (from) params.date_from = from;
            if (to) params.date_to = to;
        }
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        const q = els.search?.value?.trim();
        if (q) params.q = q;
        return params;
    }

    function renderTable(items) {
        if (!items.length) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        tableWrap.innerHTML = `<table class="wh-table wh-wperf-table"><thead><tr>
            <th>${esc(t('wms_nav_warehouses'))}</th>
            <th>${esc(t('wh_wperf_col_code'))}</th>
            <th>${esc(t('wh_wperf_col_products'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('wh_wperf_col_capacity'))}</th>
            <th>${esc(t('wh_wperf_stat_stock_in'))}</th>
            <th>${esc(t('wh_wperf_stat_stock_out'))}</th>
            <th>${esc(t('wh_wperf_stat_mov_value'))}</th>
            <th>${esc(t('wh_wperf_col_turnover'))}</th>
            <th>${esc(t('wh_wperf_stock_low'))}</th>
            <th>${esc(t('wh_wperf_stock_out'))}</th>
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td><strong>${esc(r.warehouse_name)}</strong></td>
            <td>${esc(r.warehouse_code || '—')}</td>
            <td>${Number(r.product_count || 0)}</td>
            <td>${esc(money(r.stock_value))}</td>
            <td>${esc(pct(r.capacity_used_pct))}</td>
            <td>${Number(r.stock_in || 0)}</td>
            <td>${Number(r.stock_out || 0)}</td>
            <td>${esc(money(r.movement_value))}</td>
            <td>${Number(r.turnover || 0)}</td>
            <td>${Number(r.low_stock || 0)}</td>
            <td>${Number(r.out_of_stock || 0)}</td>
        </tr>`).join('')}</tbody></table>`;
    }

    function renderPagination() {
        const pages = Math.max(1, Math.ceil(state.total / state.limit));
        if (els.pagination) els.pagination.hidden = state.total <= state.limit;
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= pages;
        if (els.pageMeta) {
            const from = state.total ? (state.page - 1) * state.limit + 1 : 0;
            const to = Math.min(state.page * state.limit, state.total);
            els.pageMeta.textContent = `${from}–${to} / ${state.total} ${t('records')}`;
        }
    }

    function saveCache(payload) {
        try {
            localStorage.setItem(CACHE_KEY, JSON.stringify({ saved_at: Date.now(), ...payload }));
        } catch (_) { /* quota */ }
    }

    function loadCache() {
        try {
            const raw = localStorage.getItem(CACHE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    }

    function applyCached(cached) {
        if (!cached) return false;
        state.items = cached.data || [];
        state.total = cached.total || 0;
        state.summary = cached.summary;
        state.charts = cached.charts;
        if (els.offlineBadge) els.offlineBadge.hidden = false;
        renderStats(state.summary);
        renderCharts(state.charts);
        renderTable(state.items);
        renderPagination();
        return true;
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        syncPeriodUi();
        if (els.offlineBadge) els.offlineBadge.hidden = true;
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getWmsWarehousePerformance(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            state.charts = res.charts || null;
            renderStats(state.summary);
            renderCharts(state.charts);
            renderTable(state.items);
            renderPagination();
            const whName = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
            const whCount = state.summary?.warehouse_count ?? state.total;
            if (els.heroMeta) {
                els.heroMeta.textContent = `${whName} · ${whCount} ${t('wms_nav_warehouses').toLowerCase()} · ${pct(state.summary?.capacity_used_pct ?? 0)} ${t('wh_wperf_stat_utilization').toLowerCase()}`;
            }
            updateLastUpdated();
            saveCache({
                data: state.items,
                total: state.total,
                summary: state.summary,
                charts: state.charts,
            });
        } catch (err) {
            const cached = loadCache();
            if (applyCached(cached)) return;
            showError(err.message || t('load_error'));
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    function exportHeaders() {
        return [
            t('wms_nav_warehouses'), t('wh_wperf_col_code'), t('wh_wperf_col_products'), t('wms_col_value'),
            t('wh_wperf_col_capacity'), t('wh_wperf_stat_stock_in'), t('wh_wperf_stat_stock_out'),
            t('wh_wperf_stat_mov_value'), t('wh_wperf_col_turnover'), t('wh_wperf_stock_low'), t('wh_wperf_stock_out'),
        ];
    }

    function exportRow(r) {
        return [
            r.warehouse_name,
            r.warehouse_code,
            r.product_count,
            r.stock_value,
            r.capacity_used_pct,
            r.stock_in,
            r.stock_out,
            r.movement_value,
            r.turnover,
            r.low_stock,
            r.out_of_stock,
        ];
    }

    async function doExport(type) {
        try {
            const res = await AdminAPI.getWmsWarehousePerformance(buildParams(true));
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            const rows = res.data || [];
            if (!rows.length) {
                showError(t('wh_wperf_empty'));
                return;
            }
            const stamp = new Date().toISOString().slice(0, 10);
            const head = exportHeaders();
            const body = rows.map(exportRow);

            if (type === 'csv') {
                exportCsv(`warehouse-performance-${stamp}.csv`, [head, ...body]);
                return;
            }
            if (type === 'excel') {
                const bom = '\uFEFF';
                const tsv = bom + [head, ...body].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join('\t')).join('\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([tsv], { type: 'application/vnd.ms-excel;charset=utf-8' }));
                a.download = `warehouse-performance-${stamp}.xls`;
                a.click();
                return;
            }
            if (type === 'pdf' && window.WmsReportExport?.exportPdf) {
                await WmsReportExport.exportPdf({
                    title: t('wh_nav_rpt_performance') || 'Warehouse Performance',
                    periodLabel: els.period?.value !== 'custom'
                        ? (els.period?.selectedOptions?.[0]?.text || '')
                        : [els.dateFrom?.value, els.dateTo?.value].filter(Boolean).join(' → '),
                    generatedLabel: t('last_updated'),
                    locale: window.WH_CONFIG?.locale,
                    head,
                    rows: body,
                    filename: `warehouse-performance-${stamp}.pdf`,
                });
                return;
            }
            if (type === 'print') window.print();
        } catch (err) {
            showError(err.message || t('load_error'));
        }
    }

    els.refresh?.addEventListener('click', () => load());
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => { state.page += 1; load(); });
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => load(true), 350);
    });
    els.period?.addEventListener('change', () => load(true));
    [els.warehouse, els.dateFrom, els.dateTo].forEach((el) => {
        el?.addEventListener('change', () => load(true));
    });
    els.exportCsv?.addEventListener('click', () => doExport('csv'));
    els.exportExcel?.addEventListener('click', () => doExport('excel'));
    els.exportPdf?.addEventListener('click', () => doExport('pdf'));
    els.printBtn?.addEventListener('click', () => doExport('print'));
    document.addEventListener('wh:refresh', () => load());
    window.addEventListener('online', () => load());

    syncPeriodUi();
    loadWarehouseOptions(els.warehouse).then(() => load());
});
