/**
 * Warehouse — Expiry Report
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whExprTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const CACHE_KEY = 'wh_expiry_report_v1';

    const FILTER_KEYS = {
        at_risk: 'wms_filter_at_risk',
        expiring_soon: 'wms_filter_expiring_only',
        expired: 'wms_filter_expired_only',
    };
    const STATUS_KEYS = {
        active: 'wms_status_active',
        expired: 'wms_status_expired',
        recalled: 'wms_status_recalled',
        depleted: 'wms_status_depleted',
    };
    const URGENCY_KEYS = {
        expired: 'wh_expr_urgency_expired',
        critical: 'wh_expr_urgency_critical',
        warning: 'wh_expr_urgency_warning',
        upcoming: 'wh_expr_urgency_upcoming',
    };

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: null,
        chart: null,
        searchTimer: null,
    };

    const chartInstances = {};

    const els = {
        loading: document.getElementById('whExprLoading'),
        empty: document.getElementById('whExprEmpty'),
        warehouse: document.getElementById('whExprWarehouse'),
        period: document.getElementById('whExprPeriod'),
        status: document.getElementById('whExprStatus'),
        search: document.getElementById('whExprSearch'),
        refresh: document.getElementById('whExprRefreshBtn'),
        exportCsv: document.getElementById('whExprExportCsv'),
        exportExcel: document.getElementById('whExprExportExcel'),
        exportPdf: document.getElementById('whExprExportPdf'),
        printBtn: document.getElementById('whExprPrintBtn'),
        heroMeta: document.getElementById('whExprHeroMeta'),
        statSoon: document.getElementById('whExprStatSoon'),
        statPast: document.getElementById('whExprStatPast'),
        statUnits: document.getElementById('whExprStatUnits'),
        statValue: document.getElementById('whExprStatValue'),
        statBatches: document.getElementById('whExprStatBatches'),
        statDays: document.getElementById('whExprStatDays'),
        breakdownPanel: document.getElementById('whExprBreakdownPanel'),
        statusChips: document.getElementById('whExprStatusChips'),
        pagination: document.getElementById('whExprPagination'),
        prev: document.getElementById('whExprPrev'),
        next: document.getElementById('whExprNext'),
        pageMeta: document.getElementById('whExprPageMeta'),
        offlineBadge: document.getElementById('whExprOfflineBadge'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return { grid: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)', text: dark ? '#94a3b8' : '#64748b' };
    }

    function filterLabel(filter) {
        return t(FILTER_KEYS[filter] || filter) || filter || '—';
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function urgencyLabel(key) {
        return t(URGENCY_KEYS[key] || key) || key || '—';
    }

    function formatDate(val) {
        if (!val) return '—';
        try {
            return AdminAPI.formatDate(val, { dateStyle: 'short' });
        } catch {
            return val;
        }
    }

    function urgencyBadge(row) {
        const days = row.days_to_expiry != null ? Number(row.days_to_expiry) : null;
        if (days == null) return '—';
        if (days < 0 || row.status === 'expired') {
            return `<span class="cr-badge cr-badge--off">${esc(t('wms_urgency_expired'))}</span>`;
        }
        if (days <= 7) {
            return `<span class="cr-badge cr-badge--off">${esc(t('wms_urgency_critical'))}</span>`;
        }
        if (days <= 30) {
            return `<span class="cr-badge cr-badge--warn">${esc(t('wms_urgency_warning'))}</span>`;
        }
        return `<span class="cr-badge cr-badge--ok">${days}${esc(t('wms_days_short') || 'd')}</span>`;
    }

    function statusBadge(status) {
        const cls = status === 'active' ? 'ok' : (status === 'recalled' || status === 'expired' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-expr-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statSoon) els.statSoon.textContent = String(s.expiring_soon ?? 0);
        if (els.statPast) els.statPast.textContent = String(s.past_expiry ?? 0);
        if (els.statUnits) els.statUnits.textContent = Number(s.units_at_risk ?? 0).toLocaleString();
        if (els.statValue) els.statValue.textContent = money(s.value_at_risk ?? 0);
        if (els.statBatches) els.statBatches.textContent = String(s.total_batches ?? 0);
        if (els.statDays) els.statDays.textContent = String(s.days ?? els.period?.value ?? 30);
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const list = (items || []).filter((r) => Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const order = ['expiring_soon', 'expired', 'at_risk'];
        const sorted = [...list].sort((a, b) => {
            const ai = order.indexOf(a.status);
            const bi = order.indexOf(b.status);
            return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
        });
        const activeStatus = els.status?.value || '';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const isActive = activeStatus === r.status;
            return `<button type="button" class="wh-expr-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(filterLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-expr-status-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                if (els.status) els.status.value = chip.dataset.status || '';
                load(true);
            });
        });
    }

    function destroyChart(id) {
        if (chartInstances[id]) {
            chartInstances[id].destroy();
            delete chartInstances[id];
        }
    }

    function renderCharts(chart) {
        if (!window.Chart) return;
        const c = chartColors();
        const trend = chart?.trend || [];

        destroyChart('trend');
        const trendCtx = document.getElementById('whExprChartTrend');
        if (trendCtx && trend.length) {
            chartInstances.trend = new Chart(trendCtx, {
                type: 'bar',
                data: {
                    labels: trend.map((d) => d.d),
                    datasets: [
                        {
                            label: t('wh_expr_stat_batches'),
                            data: trend.map((d) => d.batch_count),
                            backgroundColor: '#d97706',
                            borderRadius: 4,
                            yAxisID: 'y',
                        },
                        {
                            label: t('wh_expr_stat_value'),
                            data: trend.map((d) => d.value_at_risk),
                            type: 'line',
                            borderColor: '#dc2626',
                            tension: 0.35,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: c.text, boxWidth: 12 } } },
                    scales: {
                        x: { ticks: { color: c.text, maxRotation: 45 }, grid: { color: c.grid } },
                        y: { ticks: { color: c.text }, grid: { color: c.grid }, position: 'left' },
                        y1: { ticks: { color: c.text }, grid: { drawOnChartArea: false }, position: 'right' },
                    },
                },
            });
        }

        destroyChart('warehouse');
        const whCtx = document.getElementById('whExprChartWarehouse');
        const whData = chart?.warehouse || [];
        if (whCtx && whData.length) {
            chartInstances.warehouse = new Chart(whCtx, {
                type: 'bar',
                data: {
                    labels: whData.map((d) => d.label),
                    datasets: [{ data: whData.map((d) => d.value_at_risk), backgroundColor: '#d97706', borderRadius: 6 }],
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

        destroyChart('urgency');
        const urgCtx = document.getElementById('whExprChartUrgency');
        const urgData = (chart?.urgency || []).filter((d) => Number(d.count) > 0);
        if (urgCtx && urgData.length) {
            chartInstances.urgency = new Chart(urgCtx, {
                type: 'doughnut',
                data: {
                    labels: urgData.map((d) => urgencyLabel(d.urgency)),
                    datasets: [{
                        data: urgData.map((d) => d.count),
                        backgroundColor: ['#dc2626', '#ea580c', '#d97706', '#059669'],
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: c.text, boxWidth: 12 } } },
                },
            });
        }
    }

    function buildParams(forExport = false) {
        const params = {
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
            days: Number(els.period?.value || 30),
            status: els.status?.value?.trim() || 'at_risk',
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        const q = els.search?.value?.trim();
        if (q) params.q = q;
        return params;
    }

    function showWarehouseCol() {
        return !els.warehouse?.value?.trim();
    }

    function renderTable(items) {
        if (!items.length) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        const whCol = showWarehouseCol() ? `<th>${esc(t('wms_nav_warehouses'))}</th>` : '';
        tableWrap.innerHTML = `<table class="wh-table wh-expr-table"><thead><tr>
            <th>${esc(t('wms_col_batch'))}</th>
            <th>${esc(t('wms_col_product'))}</th>
            <th>SKU</th>
            ${whCol}
            <th>${esc(t('wms_col_qty'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('wms_col_expiry'))}</th>
            <th>${esc(t('wms_days_to_expiry'))}</th>
            <th>${esc(t('col_status'))}</th>
        </tr></thead><tbody>${items.map((r) => {
            const whCell = showWarehouseCol() ? `<td>${esc(r.warehouse_name || '—')}</td>` : '';
            return `<tr>
            <td><strong>${esc(r.batch_number)}</strong></td>
            <td>${esc(r.product_name)}</td>
            <td>${esc(r.sku || '—')}</td>
            ${whCell}
            <td>${Number(r.quantity ?? 0)}</td>
            <td>${esc(money(r.stock_value))}</td>
            <td>${esc(formatDate(r.expiry_date))}</td>
            <td>${urgencyBadge(r)}</td>
            <td>${statusBadge(r.status)}</td>
        </tr>`;
        }).join('')}</tbody></table>`;
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
        state.breakdown = cached.breakdown;
        state.chart = cached.chart;
        if (els.offlineBadge) els.offlineBadge.hidden = false;
        renderStats(state.summary);
        renderBreakdown(state.breakdown);
        renderCharts(state.chart);
        renderTable(state.items);
        renderPagination();
        return true;
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        if (els.offlineBadge) els.offlineBadge.hidden = true;
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getWmsExpiryReport(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            state.breakdown = res.breakdown || null;
            state.chart = res.chart || null;
            renderStats(state.summary);
            renderBreakdown(state.breakdown);
            renderCharts(state.chart);
            renderTable(state.items);
            renderPagination();
            const whName = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
            const days = state.summary?.days ?? els.period?.value ?? 30;
            if (els.heroMeta) {
                els.heroMeta.textContent = `${whName} · ${days}d · ${state.summary?.total_batches ?? 0} ${t('wh_expr_stat_batches').toLowerCase()} · ${money(state.summary?.value_at_risk ?? 0)} ${t('wh_expr_stat_value').toLowerCase()}`;
            }
            updateLastUpdated();
            saveCache({
                data: state.items,
                total: state.total,
                summary: state.summary,
                breakdown: state.breakdown,
                chart: state.chart,
            });
        } catch (err) {
            const cached = loadCache();
            if (applyCached(cached)) return;
            showError(err.message || t('load_error'));
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
            if (els.breakdownPanel) els.breakdownPanel.hidden = true;
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    function exportHeaders() {
        const cols = [t('wms_col_batch'), t('wms_col_product'), 'SKU'];
        if (showWarehouseCol()) cols.push(t('wms_nav_warehouses'));
        cols.push(t('wms_col_qty'), t('wms_col_value'), t('wms_col_expiry'), t('wms_days_to_expiry'), t('col_status'));
        return cols;
    }

    function exportRow(r) {
        const days = r.days_to_expiry != null ? Number(r.days_to_expiry) : '';
        const row = [r.batch_number, r.product_name, r.sku];
        if (showWarehouseCol()) row.push(r.warehouse_name);
        row.push(r.quantity, r.stock_value, r.expiry_date, days, statusLabel(r.status));
        return row;
    }

    async function doExport(type) {
        try {
            const res = await AdminAPI.getWmsExpiryReport(buildParams(true));
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            const rows = res.data || [];
            if (!rows.length) {
                showError(t('wh_expr_empty'));
                return;
            }
            const stamp = new Date().toISOString().slice(0, 10);
            const head = exportHeaders();
            const body = rows.map(exportRow);

            if (type === 'csv') {
                exportCsv(`expiry-report-${stamp}.csv`, [head, ...body]);
                return;
            }
            if (type === 'excel') {
                const bom = '\uFEFF';
                const tsv = bom + [head, ...body].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join('\t')).join('\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([tsv], { type: 'application/vnd.ms-excel;charset=utf-8' }));
                a.download = `expiry-report-${stamp}.xls`;
                a.click();
                return;
            }
            if (type === 'pdf' && window.WmsReportExport?.exportPdf) {
                await WmsReportExport.exportPdf({
                    title: t('wh_nav_rpt_expiry') || 'Expiry Report',
                    periodLabel: `${els.period?.value || 30}d · ${filterLabel(els.status?.value || 'at_risk')}`,
                    generatedLabel: t('col_date'),
                    locale: window.WH_CONFIG?.locale,
                    head,
                    rows: body,
                    filename: `expiry-report-${stamp}.pdf`,
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
    [els.warehouse, els.period, els.status].forEach((el) => {
        el?.addEventListener('change', () => load(true));
    });
    els.exportCsv?.addEventListener('click', () => doExport('csv'));
    els.exportExcel?.addEventListener('click', () => doExport('excel'));
    els.exportPdf?.addEventListener('click', () => doExport('pdf'));
    els.printBtn?.addEventListener('click', () => doExport('print'));
    document.addEventListener('wh:refresh', () => load());
    window.addEventListener('online', () => load());

    loadWarehouseOptions(els.warehouse).then(() => load());
});
