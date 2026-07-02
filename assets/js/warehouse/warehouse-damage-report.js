/**
 * Warehouse — Damage Report
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whDmgrTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const CACHE_KEY = 'wh_damage_report_v1';

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
        loading: document.getElementById('whDmgrLoading'),
        empty: document.getElementById('whDmgrEmpty'),
        warehouse: document.getElementById('whDmgrWarehouse'),
        search: document.getElementById('whDmgrSearch'),
        dateFrom: document.getElementById('whDmgrDateFrom'),
        dateTo: document.getElementById('whDmgrDateTo'),
        refresh: document.getElementById('whDmgrRefreshBtn'),
        exportCsv: document.getElementById('whDmgrExportCsv'),
        exportExcel: document.getElementById('whDmgrExportExcel'),
        exportPdf: document.getElementById('whDmgrExportPdf'),
        printBtn: document.getElementById('whDmgrPrintBtn'),
        heroMeta: document.getElementById('whDmgrHeroMeta'),
        statIncidents: document.getElementById('whDmgrStatIncidents'),
        statUnits: document.getElementById('whDmgrStatUnits'),
        statLoss: document.getElementById('whDmgrStatLoss'),
        statProducts: document.getElementById('whDmgrStatProducts'),
        statWarehouses: document.getElementById('whDmgrStatWarehouses'),
        statOnHand: document.getElementById('whDmgrStatOnHand'),
        breakdownPanel: document.getElementById('whDmgrBreakdownPanel'),
        warehouseChips: document.getElementById('whDmgrWarehouseChips'),
        pagination: document.getElementById('whDmgrPagination'),
        prev: document.getElementById('whDmgrPrev'),
        next: document.getElementById('whDmgrNext'),
        pageMeta: document.getElementById('whDmgrPageMeta'),
        offlineBadge: document.getElementById('whDmgrOfflineBadge'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return { grid: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)', text: dark ? '#94a3b8' : '#64748b' };
    }

    function damageTypeLabel(type) {
        if (!type || type === 'unspecified') return t('wh_dmgr_type_unspecified');
        return type;
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return AdminAPI.formatDate(iso, { dateStyle: 'short', timeStyle: 'short' });
        } catch {
            return iso;
        }
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-dmgr-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statIncidents) els.statIncidents.textContent = String(s.total_incidents ?? 0);
        if (els.statUnits) els.statUnits.textContent = String(s.damaged_units ?? 0);
        if (els.statLoss) els.statLoss.textContent = money(s.total_loss ?? 0);
        if (els.statProducts) els.statProducts.textContent = String(s.unique_products ?? 0);
        if (els.statWarehouses) els.statWarehouses.textContent = String(s.warehouses_affected ?? 0);
        if (els.statOnHand) els.statOnHand.textContent = String(s.on_hand_damaged ?? 0);
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.warehouseChips) return;
        const list = (items || []).filter((r) => Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        els.breakdownPanel.hidden = false;
        els.warehouseChips.innerHTML = list.map((r) => {
            const isActive = els.warehouse?.value === String(r.warehouse_id);
            return `<button type="button" class="wh-dmgr-status-chip${isActive ? ' is-active' : ''}" data-warehouse-id="${esc(r.warehouse_id)}">
                <span>${esc(r.label)}</span>
                <strong>${money(r.total_loss)}</strong>
            </button>`;
        }).join('');
        els.warehouseChips.querySelectorAll('.wh-dmgr-status-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                if (els.warehouse) els.warehouse.value = chip.dataset.warehouseId || '';
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

    function renderCharts(chart, breakdown) {
        if (!window.Chart) return;
        const c = chartColors();
        const trend = chart?.trend || [];

        destroyChart('trend');
        const trendCtx = document.getElementById('whDmgrChartTrend');
        if (trendCtx && trend.length) {
            chartInstances.trend = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trend.map((d) => d.d),
                    datasets: [
                        { label: t('wh_dmgr_stat_incidents'), data: trend.map((d) => d.incident_count), borderColor: '#dc2626', tension: 0.35, yAxisID: 'y' },
                        { label: t('wh_dmgr_stat_loss'), data: trend.map((d) => d.total_loss), borderColor: '#d97706', tension: 0.35, yAxisID: 'y1' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: c.text } } },
                    scales: {
                        x: { ticks: { color: c.text }, grid: { color: c.grid } },
                        y: { ticks: { color: c.text }, grid: { color: c.grid }, position: 'left' },
                        y1: { ticks: { color: c.text }, grid: { drawOnChartArea: false }, position: 'right' },
                    },
                },
            });
        }

        destroyChart('warehouse');
        const whCtx = document.getElementById('whDmgrChartWarehouse');
        const whData = breakdown?.warehouse || [];
        if (whCtx && whData.length) {
            chartInstances.warehouse = new Chart(whCtx, {
                type: 'bar',
                data: {
                    labels: whData.map((d) => d.label),
                    datasets: [{ data: whData.map((d) => d.total_loss), backgroundColor: '#dc2626', borderRadius: 6 }],
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

        destroyChart('type');
        const typeCtx = document.getElementById('whDmgrChartType');
        const typeData = (breakdown?.type || []).filter((d) => Number(d.count) > 0);
        if (typeCtx && typeData.length) {
            chartInstances.type = new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: typeData.map((d) => damageTypeLabel(d.damage_type)),
                    datasets: [{ data: typeData.map((d) => d.count), backgroundColor: ['#dc2626', '#d97706', '#7c3aed', '#2563eb', '#64748b', '#059669'] }],
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
            days: 30,
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        const q = els.search?.value?.trim();
        if (q) params.q = q;
        const from = els.dateFrom?.value?.trim();
        if (from) params.date_from = from;
        const to = els.dateTo?.value?.trim();
        if (to) params.date_to = to;
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
        tableWrap.innerHTML = `<table class="wh-table wh-dmgr-table"><thead><tr>
            <th>${esc(t('col_date'))}</th>
            <th>${esc(t('wms_col_product'))}</th>
            <th>SKU</th>
            ${whCol}
            <th>${esc(t('wh_dmgr_col_damage_type'))}</th>
            <th>${esc(t('wms_col_qty'))}</th>
            <th>${esc(t('wh_dmgr_col_loss'))}</th>
            <th>${esc(t('wh_dmgr_col_reported_by'))}</th>
        </tr></thead><tbody>${items.map((r) => {
            const whCell = showWarehouseCol() ? `<td>${esc(r.warehouse_name || '—')}</td>` : '';
            return `<tr>
            <td>${esc(formatDate(r.created_at))}</td>
            <td><strong>${esc(r.product_name)}</strong></td>
            <td>${esc(r.sku || '—')}</td>
            ${whCell}
            <td>${esc(damageTypeLabel(r.damage_type))}</td>
            <td>${Number(r.quantity_damaged ?? r.quantity ?? 0)}</td>
            <td>${esc(money(r.estimated_loss))}</td>
            <td>${esc(r.reported_by || '—')}</td>
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
        renderBreakdown(state.breakdown?.warehouse);
        renderCharts(state.chart, state.breakdown);
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
            const res = await AdminAPI.getWmsDamageReport(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            state.breakdown = res.breakdown || null;
            state.chart = res.chart || null;
            renderStats(state.summary);
            renderBreakdown(state.breakdown?.warehouse);
            renderCharts(state.chart, state.breakdown);
            renderTable(state.items);
            renderPagination();
            const whName = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
            if (els.heroMeta) {
                els.heroMeta.textContent = `${whName} · ${state.summary?.total_incidents ?? 0} ${t('wh_dmgr_stat_incidents').toLowerCase()} · ${money(state.summary?.total_loss ?? 0)} ${t('wh_dmgr_stat_loss').toLowerCase()}`;
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
        const cols = [t('col_date'), t('wms_col_product'), 'SKU'];
        if (showWarehouseCol()) cols.push(t('wms_nav_warehouses'));
        cols.push(t('wh_dmgr_col_damage_type'), t('wms_col_qty'), t('wh_dmgr_col_loss'), t('wh_dmgr_col_reported_by'));
        return cols;
    }

    function exportRow(r) {
        const row = [formatDate(r.created_at), r.product_name, r.sku];
        if (showWarehouseCol()) row.push(r.warehouse_name);
        row.push(damageTypeLabel(r.damage_type), r.quantity_damaged ?? r.quantity, r.estimated_loss, r.reported_by);
        return row;
    }

    async function doExport(type) {
        try {
            const res = await AdminAPI.getWmsDamageReport(buildParams(true));
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            const rows = res.data || [];
            if (!rows.length) {
                showError(t('wh_dmgr_empty'));
                return;
            }
            const stamp = new Date().toISOString().slice(0, 10);
            const head = exportHeaders();
            const body = rows.map(exportRow);

            if (type === 'csv') {
                exportCsv(`damage-report-${stamp}.csv`, [head, ...body]);
                return;
            }
            if (type === 'excel') {
                const bom = '\uFEFF';
                const tsv = bom + [head, ...body].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join('\t')).join('\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([tsv], { type: 'application/vnd.ms-excel;charset=utf-8' }));
                a.download = `damage-report-${stamp}.xls`;
                a.click();
                return;
            }
            if (type === 'pdf' && window.WmsReportExport?.exportPdf) {
                await WmsReportExport.exportPdf({
                    title: t('wh_nav_rpt_damage') || 'Damage Report',
                    periodLabel: [els.dateFrom?.value, els.dateTo?.value].filter(Boolean).join(' → '),
                    generatedLabel: t('col_date'),
                    locale: window.WH_CONFIG?.locale,
                    head,
                    rows: body,
                    filename: `damage-report-${stamp}.pdf`,
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
    [els.warehouse, els.dateFrom, els.dateTo].forEach((el) => {
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
