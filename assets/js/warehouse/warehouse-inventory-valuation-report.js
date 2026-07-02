/**
 * Warehouse — Inventory Valuation Report
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whIvalTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const CACHE_KEY = 'wh_inventory_valuation_v1';

    const METHOD_KEYS = {
        fifo: 'wh_irpt_val_fifo',
        weighted: 'wh_irpt_val_weighted',
        lifo: 'wh_irpt_val_lifo',
    };

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        charts: null,
        categories: [],
        searchTimer: null,
    };

    const chartInstances = {};

    const els = {
        loading: document.getElementById('whIvalLoading'),
        empty: document.getElementById('whIvalEmpty'),
        warehouse: document.getElementById('whIvalWarehouse'),
        category: document.getElementById('whIvalCategory'),
        method: document.getElementById('whIvalMethod'),
        search: document.getElementById('whIvalSearch'),
        refresh: document.getElementById('whIvalRefreshBtn'),
        exportCsv: document.getElementById('whIvalExportCsv'),
        exportExcel: document.getElementById('whIvalExportExcel'),
        exportPdf: document.getElementById('whIvalExportPdf'),
        printBtn: document.getElementById('whIvalPrintBtn'),
        heroMeta: document.getElementById('whIvalHeroMeta'),
        methodLabel: document.getElementById('whIvalMethodLabel'),
        statCost: document.getElementById('whIvalStatCost'),
        statSelling: document.getElementById('whIvalStatSelling'),
        statProfit: document.getElementById('whIvalStatProfit'),
        statTurnover: document.getElementById('whIvalStatTurnover'),
        statLines: document.getElementById('whIvalStatLines'),
        statQty: document.getElementById('whIvalStatQty'),
        breakdownPanel: document.getElementById('whIvalBreakdownPanel'),
        categoryChips: document.getElementById('whIvalCategoryChips'),
        pagination: document.getElementById('whIvalPagination'),
        prev: document.getElementById('whIvalPrev'),
        next: document.getElementById('whIvalNext'),
        pageMeta: document.getElementById('whIvalPageMeta'),
        offlineBadge: document.getElementById('whIvalOfflineBadge'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return { grid: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)', text: dark ? '#94a3b8' : '#64748b' };
    }

    function methodLabel(method) {
        return t(METHOD_KEYS[method] || method) || method || '—';
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-ival-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statCost) els.statCost.textContent = money(s.inventory_cost ?? 0);
        if (els.statSelling) els.statSelling.textContent = money(s.selling_value ?? 0);
        if (els.statProfit) els.statProfit.textContent = money(s.expected_profit ?? 0);
        if (els.statTurnover) els.statTurnover.textContent = String(s.turnover ?? 0);
        if (els.statLines) els.statLines.textContent = String(s.line_count ?? 0);
        if (els.statQty) els.statQty.textContent = String(s.total_qty ?? 0);
        if (els.methodLabel) els.methodLabel.textContent = methodLabel(s.method || els.method?.value);
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.categoryChips) return;
        const list = (items || []).filter((r) => Number(r.value) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const activeCat = els.category?.value || '';
        els.breakdownPanel.hidden = false;
        els.categoryChips.innerHTML = list.map((r) => {
            const catId = state.categories.find((c) => c.name === r.label)?.id;
            const isActive = activeCat && String(catId) === activeCat;
            return `<button type="button" class="wh-ival-category-chip${isActive ? ' is-active' : ''}" data-category-id="${esc(catId || '')}" data-label="${esc(r.label)}">
                <span>${esc(r.label)}</span>
                <strong>${money(r.value)}</strong>
            </button>`;
        }).join('');
        els.categoryChips.querySelectorAll('.wh-ival-category-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                if (els.category) {
                    els.category.value = chip.dataset.categoryId || '';
                    if (!chip.dataset.categoryId) {
                        const opt = [...els.category.options].find((o) => o.textContent === chip.dataset.label);
                        if (opt) els.category.value = opt.value;
                    }
                }
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

    function renderCharts(charts) {
        if (!window.Chart) return;
        const c = chartColors();
        const data = charts || {};

        destroyChart('value');
        const valueCtx = document.getElementById('whIvalChartValue');
        const valueTrend = data.value_trend || [];
        if (valueCtx && valueTrend.length) {
            chartInstances.value = new Chart(valueCtx, {
                type: 'line',
                data: {
                    labels: valueTrend.map((d) => d.date),
                    datasets: [{ label: t('wh_ival_stat_cost'), data: valueTrend.map((d) => d.value), borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,0.08)', fill: true, tension: 0.35 }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { ticks: { color: c.text }, grid: { color: c.grid } }, y: { ticks: { color: c.text }, grid: { color: c.grid } } },
                },
            });
        }

        destroyChart('category');
        const catCtx = document.getElementById('whIvalChartCategory');
        const cats = data.category_distribution || [];
        if (catCtx && cats.length) {
            chartInstances.category = new Chart(catCtx, {
                type: 'bar',
                data: {
                    labels: cats.map((d) => d.label),
                    datasets: [{ data: cats.map((d) => d.value), backgroundColor: '#2563eb', borderRadius: 6 }],
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

        destroyChart('warehouse');
        const whCtx = document.getElementById('whIvalChartWarehouse');
        const whData = data.warehouse_comparison || [];
        if (whCtx && whData.length) {
            chartInstances.warehouse = new Chart(whCtx, {
                type: 'bar',
                data: {
                    labels: whData.map((d) => d.label),
                    datasets: [{ data: whData.map((d) => d.value), backgroundColor: '#059669', borderRadius: 6 }],
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
    }

    function buildParams(forExport = false) {
        const params = {
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        const cat = els.category?.value?.trim();
        if (cat) params.category_id = cat;
        const method = els.method?.value?.trim();
        if (method) params.valuation_method = method;
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
        tableWrap.innerHTML = `<table class="wh-table wh-ival-table"><thead><tr>
            <th>${esc(t('wms_col_product'))}</th>
            <th>SKU</th>
            <th>${esc(t('wh_prod_col_category'))}</th>
            ${whCol}
            <th>${esc(t('wms_col_qty'))}</th>
            <th>${esc(t('wh_ival_col_unit_cost'))}</th>
            <th>${esc(t('wh_ival_col_unit_price'))}</th>
            <th>${esc(t('wh_ival_stat_cost'))}</th>
            <th>${esc(t('wh_ival_stat_selling'))}</th>
            <th>${esc(t('wh_ival_col_margin'))}</th>
            <th>${esc(t('wh_ival_col_margin_pct'))}</th>
        </tr></thead><tbody>${items.map((r) => {
            const whCell = showWarehouseCol() ? `<td>${esc(r.warehouse_name || '—')}</td>` : '';
            return `<tr>
            <td><strong>${esc(r.product_name)}</strong></td>
            <td>${esc(r.sku || '—')}</td>
            <td>${esc(r.category_name || '—')}</td>
            ${whCell}
            <td>${Number(r.stock_quantity || 0)}</td>
            <td>${esc(money(r.unit_cost))}</td>
            <td>${esc(money(r.unit_price))}</td>
            <td>${esc(money(r.cost_value))}</td>
            <td>${esc(money(r.retail_value))}</td>
            <td>${esc(money(r.margin))}</td>
            <td>${Number(r.margin_pct || 0)}%</td>
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
        state.breakdown = cached.breakdown || [];
        state.charts = cached.charts;
        if (els.offlineBadge) els.offlineBadge.hidden = false;
        renderStats(state.summary);
        renderBreakdown(state.breakdown);
        renderCharts(state.charts);
        renderTable(state.items);
        renderPagination();
        return true;
    }

    async function loadFilterOptions() {
        try {
            const res = await AdminAPI.getWmsInventoryReport({ tab: 'filters' });
            state.categories = res.data?.categories || [];
            if (els.category && state.categories.length) {
                const cur = els.category.value;
                els.category.innerHTML = `<option value="">${esc(t('wh_ledger_filter_all'))}</option>`
                    + state.categories.map((c) => `<option value="${esc(c.id)}">${esc(c.name)}</option>`).join('');
                if (cur) els.category.value = cur;
            }
        } catch (_) { /* optional */ }
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        if (els.offlineBadge) els.offlineBadge.hidden = true;
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getWmsInventoryValuation(buildParams());
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            state.breakdown = res.breakdown || [];
            state.charts = res.charts || null;
            renderStats(state.summary);
            renderBreakdown(state.breakdown);
            renderCharts(state.charts);
            renderTable(state.items);
            renderPagination();
            const whName = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
            if (els.heroMeta) {
                els.heroMeta.textContent = `${whName} · ${methodLabel(state.summary?.method)} · ${money(state.summary?.inventory_cost ?? 0)} → ${money(state.summary?.selling_value ?? 0)}`;
            }
            updateLastUpdated();
            saveCache({
                data: state.items,
                total: state.total,
                summary: state.summary,
                breakdown: state.breakdown,
                charts: state.charts,
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
        const cols = [t('wms_col_product'), 'SKU', t('wh_prod_col_category')];
        if (showWarehouseCol()) cols.push(t('wms_nav_warehouses'));
        cols.push(t('wms_col_qty'), t('wh_ival_col_unit_cost'), t('wh_ival_col_unit_price'),
            t('wh_ival_stat_cost'), t('wh_ival_stat_selling'), t('wh_ival_col_margin'), t('wh_ival_col_margin_pct'));
        return cols;
    }

    function exportRow(r) {
        const row = [r.product_name, r.sku, r.category_name];
        if (showWarehouseCol()) row.push(r.warehouse_name);
        row.push(r.stock_quantity, r.unit_cost, r.unit_price, r.cost_value, r.retail_value, r.margin, `${r.margin_pct}%`);
        return row;
    }

    async function doExport(type) {
        try {
            const res = await AdminAPI.getWmsInventoryValuation(buildParams(true));
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            const rows = res.data || [];
            if (!rows.length) {
                showError(t('wh_ival_empty'));
                return;
            }
            const stamp = new Date().toISOString().slice(0, 10);
            const head = exportHeaders();
            const body = rows.map(exportRow);

            if (type === 'csv') {
                exportCsv(`inventory-valuation-${stamp}.csv`, [head, ...body]);
                return;
            }
            if (type === 'excel') {
                const bom = '\uFEFF';
                const tsv = bom + [head, ...body].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join('\t')).join('\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([tsv], { type: 'application/vnd.ms-excel;charset=utf-8' }));
                a.download = `inventory-valuation-${stamp}.xls`;
                a.click();
                return;
            }
            if (type === 'pdf' && window.WmsReportExport?.exportPdf) {
                await WmsReportExport.exportPdf({
                    title: t('wh_nav_rpt_valuation') || 'Inventory Valuation',
                    periodLabel: methodLabel(state.summary?.method || els.method?.value),
                    generatedLabel: t('last_updated'),
                    locale: window.WH_CONFIG?.locale,
                    head,
                    rows: body,
                    filename: `inventory-valuation-${stamp}.pdf`,
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
    [els.warehouse, els.category, els.method].forEach((el) => {
        el?.addEventListener('change', () => load(true));
    });
    els.exportCsv?.addEventListener('click', () => doExport('csv'));
    els.exportExcel?.addEventListener('click', () => doExport('excel'));
    els.exportPdf?.addEventListener('click', () => doExport('pdf'));
    els.printBtn?.addEventListener('click', () => doExport('print'));
    document.addEventListener('wh:refresh', () => load());
    window.addEventListener('online', () => load());

    Promise.all([loadWarehouseOptions(els.warehouse), loadFilterOptions()]).then(() => load());
});
