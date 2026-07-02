/**
 * Warehouse — Enterprise Inventory Report
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whIrptTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, pct, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const CACHE_KEY = 'wh_inventory_report_cache_v1';
    const PAGE_SIZE = 50;

    const MOVEMENT_KEYS = {
        receipt_in: 'wms_mov_receipt_in',
        purchase: 'wms_mov_purchase',
        transfer_in: 'wms_mov_transfer_in',
        transfer_out: 'wms_mov_transfer_out',
        sale: 'wms_mov_sale',
        return_in: 'wms_mov_return_in',
        return_out: 'wms_mov_return_out',
        adjustment: 'wms_mov_adjustment',
        damaged: 'wms_mov_damaged',
        expired: 'wms_mov_expired',
        lost: 'wms_mov_lost',
        manual: 'wms_mov_manual',
        dispatch_out: 'wms_mov_dispatch_out',
    };

    const STATUS_KEYS = { ok: 'OK', low: 'Low', out: 'Out', alert: 'Alert', in_stock: 'In stock', low_stock: 'Low stock', out_of_stock: 'Out of stock' };

    const state = {
        tab: 'overview',
        page: 1,
        total: 0,
        items: [],
        summary: null,
        chartData: null,
        filters: {},
        filterOptions: null,
        searchTimer: null,
        offline: false,
    };

    const chartInstances = {};

    const els = {
        search: document.getElementById('whIrptSearch'),
        warehouse: document.getElementById('whIrptWarehouse'),
        store: document.getElementById('whIrptStore'),
        category: document.getElementById('whIrptCategory'),
        supplier: document.getElementById('whIrptSupplier'),
        stockStatus: document.getElementById('whIrptStockStatus'),
        movementType: document.getElementById('whIrptMovementType'),
        dateFrom: document.getElementById('whIrptDateFrom'),
        dateTo: document.getElementById('whIrptDateTo'),
        zone: document.getElementById('whIrptZone'),
        aisle: document.getElementById('whIrptAisle'),
        rack: document.getElementById('whIrptRack'),
        shelf: document.getElementById('whIrptShelf'),
        bin: document.getElementById('whIrptBin'),
        batch: document.getElementById('whIrptBatch'),
        serial: document.getElementById('whIrptSerial'),
        expiryDays: document.getElementById('whIrptExpiryDays'),
        expiryDaysWrap: document.getElementById('whIrptExpiryDaysWrap'),
        valMethod: document.getElementById('whIrptValMethod'),
        valMethodWrap: document.getElementById('whIrptValMethodWrap'),
        applyBtn: document.getElementById('whIrptApplyBtn'),
        resetBtn: document.getElementById('whIrptResetBtn'),
        filtersPanel: document.getElementById('whIrptFilters'),
        filtersToggle: document.getElementById('whIrptFiltersToggle'),
        filtersClose: document.getElementById('whIrptFiltersClose'),
        tabs: document.getElementById('whIrptTabs'),
        charts: document.getElementById('whIrptCharts'),
        valSection: document.getElementById('whIrptValuation'),
        perfSection: document.getElementById('whIrptPerformance'),
        refresh: document.getElementById('whIrptRefreshBtn'),
        exportCsv: document.getElementById('whIrptExportCsv'),
        exportExcel: document.getElementById('whIrptExportExcel'),
        exportPdf: document.getElementById('whIrptExportPdf'),
        printBtn: document.getElementById('whIrptPrintBtn'),
        scheduleBtn: document.getElementById('whIrptScheduleBtn'),
        scheduleModal: document.getElementById('whIrptScheduleModal'),
        scheduleForm: document.getElementById('whIrptScheduleForm'),
        loading: document.getElementById('whIrptLoading'),
        empty: document.getElementById('whIrptEmpty'),
        pagination: document.getElementById('whIrptPagination'),
        prev: document.getElementById('whIrptPrev'),
        next: document.getElementById('whIrptNext'),
        pageMeta: document.getElementById('whIrptPageMeta'),
        heroMeta: document.getElementById('whIrptHeroMeta'),
        alerts: document.getElementById('whIrptAlerts'),
        alertsBody: document.getElementById('whIrptAlertsBody'),
        offlineBadge: document.getElementById('whIrptOfflineBadge'),
        auditUser: document.getElementById('whIrptAuditUser'),
        auditDate: document.getElementById('whIrptAuditDate'),
        auditFilters: document.getElementById('whIrptAuditFilters'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return { grid: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)', text: dark ? '#94a3b8' : '#64748b' };
    }

    function movementLabel(type) {
        return t(MOVEMENT_KEYS[type] || type) || type || '—';
    }

    function formatDate(val, withTime = false) {
        if (!val) return '—';
        try {
            return AdminAPI.formatDate(val, withTime ? { dateStyle: 'short', timeStyle: 'short' } : { dateStyle: 'short' });
        } catch {
            return val;
        }
    }

    function stockStatusBadge(status) {
        const cls = { ok: 'ok', low: 'warn', out: 'off', alert: 'warn', in_stock: 'ok', low_stock: 'warn', out_of_stock: 'off' }[status] || 'idle';
        return `<span class="cr-badge cr-badge--${cls}">${esc(STATUS_KEYS[status] || status || '—')}</span>`;
    }

    function locationCell(row) {
        const parts = [row.location_code, row.zone, row.aisle, row.rack, row.shelf, row.bin].filter(Boolean);
        return esc(parts.join(' · ') || '—');
    }

    function productImage(url, name) {
        if (url) return `<img class="wh-irpt-thumb" src="${esc(url)}" alt="${esc(name || '')}" loading="lazy">`;
        return '<span class="wh-irpt-thumb wh-irpt-thumb--empty material-icons-round">inventory_2</span>';
    }

    function buildFilters() {
        const f = {};
        const wh = els.warehouse?.value;
        const store = els.store?.value;
        const cat = els.category?.value;
        const sup = els.supplier?.value;
        if (wh) f.warehouse_id = wh;
        if (store) f.store_id = store;
        if (cat) f.category_id = cat;
        if (sup) f.supplier_id = sup;
        if (els.stockStatus?.value) f.stock_status = els.stockStatus.value;
        if (els.movementType?.value) f.movement_type = els.movementType.value;
        if (els.dateFrom?.value) f.date_from = els.dateFrom.value;
        if (els.dateTo?.value) f.date_to = els.dateTo.value;
        if (els.zone?.value.trim()) f.zone = els.zone.value.trim();
        if (els.aisle?.value.trim()) f.aisle = els.aisle.value.trim();
        if (els.rack?.value.trim()) f.rack = els.rack.value.trim();
        if (els.shelf?.value.trim()) f.shelf = els.shelf.value.trim();
        if (els.bin?.value.trim()) f.bin = els.bin.value.trim();
        if (els.batch?.value.trim()) f.batch_number = els.batch.value.trim();
        if (els.serial?.value.trim()) f.serial_number = els.serial.value.trim();
        if (els.search?.value.trim()) f.q = els.search.value.trim();
        if (state.tab === 'expiry' && els.expiryDays?.value) f.expiry_days = els.expiryDays.value;
        if (state.tab === 'valuation' && els.valMethod?.value) f.valuation_method = els.valMethod.value;
        return f;
    }

    function filtersSummary(filters) {
        return Object.entries(filters || {})
            .filter(([, v]) => v !== '' && v != null)
            .map(([k, v]) => `${k}: ${v}`)
            .join(' · ') || '—';
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('.wh-irpt-kpi__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderKpis(summary) {
        const s = summary || {};
        const map = {
            whIrptKpiProducts: s.total_products ?? 0,
            whIrptKpiSkus: s.total_skus ?? 0,
            whIrptKpiQty: Number(s.total_qty ?? 0).toLocaleString(),
            whIrptKpiAvailable: Number(s.available_qty ?? 0).toLocaleString(),
            whIrptKpiReserved: Number(s.reserved_qty ?? 0).toLocaleString(),
            whIrptKpiDamaged: Number(s.damaged_qty ?? 0).toLocaleString(),
            whIrptKpiExpired: Number(s.expired_qty ?? 0).toLocaleString(),
            whIrptKpiValue: money(s.inventory_value),
            whIrptKpiAvgCost: money(s.avg_unit_cost),
            whIrptKpiAvgPrice: money(s.avg_selling_price),
            whIrptKpiPotential: money(s.potential_sales_value),
            whIrptKpiCapacity: pct(s.capacity_used_pct),
            whIrptKpiLow: s.low_stock ?? 0,
            whIrptKpiOut: s.out_of_stock ?? 0,
            whIrptKpiTodayMov: s.today_movements ?? 0,
        };
        Object.entries(map).forEach(([id, val]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(val);
        });
        setKpiLoading(false);
        if (els.heroMeta) {
            els.heroMeta.textContent = `${s.total_skus ?? 0} SKUs · ${money(s.inventory_value)} · ${s.low_stock ?? 0} low · ${s.out_of_stock ?? 0} out`;
        }
    }

    function renderAlerts(alerts) {
        if (!els.alerts || !els.alertsBody) return;
        const list = alerts || [];
        if (!list.length) {
            els.alerts.hidden = true;
            return;
        }
        els.alerts.hidden = false;
        const labels = { low_stock: 'wh_irpt_alert_low', out_of_stock: 'wh_irpt_alert_out', expired: 'wh_irpt_alert_expired' };
        els.alertsBody.innerHTML = list.map((a) => `<span class="wh-irpt-alert-chip">${esc(t(labels[a.type] || a.type))}: <strong>${a.count}</strong></span>`).join('');
    }

    function updateAudit(exportType) {
        const user = window.WH_PAGE?.userName || '—';
        const now = new Date().toLocaleString(window.WH_CONFIG?.locale || 'fr-FR');
        if (els.auditUser) els.auditUser.textContent = user;
        if (els.auditDate) els.auditDate.textContent = now + (exportType ? ` (${exportType})` : '');
        if (els.auditFilters) els.auditFilters.textContent = filtersSummary(state.filters);
        AdminAPI.postWmsInventoryReportAudit?.({
            warehouse_id: state.filters.warehouse_id || window.WH_PAGE?.warehouseId || null,
            tab: state.tab,
            filters: state.filters,
            export_type: exportType || 'view',
        }).catch(() => {});
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
        state.summary = cached.summary;
        state.chartData = cached.charts;
        state.items = cached.data || [];
        state.total = cached.total || 0;
        state.offline = true;
        if (els.offlineBadge) els.offlineBadge.hidden = false;
        renderKpis(state.summary);
        renderAlerts(cached.alerts || []);
        if (state.tab === 'overview') renderCharts(state.chartData);
        renderTable();
        updateAudit('offline');
        return true;
    }

    function syncTabUi() {
        const tab = state.tab;
        els.tabs?.querySelectorAll('.wh-irpt-tab').forEach((btn) => {
            const active = btn.dataset.tab === tab;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (els.charts) els.charts.hidden = tab !== 'overview';
        if (els.valSection) els.valSection.hidden = tab !== 'valuation';
        if (els.perfSection) els.perfSection.hidden = tab !== 'performance';
        if (els.expiryDaysWrap) els.expiryDaysWrap.hidden = tab !== 'expiry';
        if (els.valMethodWrap) els.valMethodWrap.hidden = tab !== 'valuation';
        const paginated = !['overview', 'valuation', 'performance'].includes(tab);
        if (els.pagination) els.pagination.hidden = !paginated || state.total <= PAGE_SIZE;
        if (tableWrap) tableWrap.hidden = ['valuation', 'performance'].includes(tab);
        if (els.empty) els.empty.hidden = true;
    }

    function tableHead() {
        switch (state.tab) {
            case 'movements':
                return [
                    t('col_date'), t('wms_col_reference'), t('wms_col_product'), t('wms_col_warehouse'),
                    t('wms_col_movement_type'), t('wms_col_qty'), t('wh_irpt_col_prev_stock'), t('wms_col_qty'),
                    t('wms_col_user'),
                ];
            case 'low_stock':
                return [
                    t('wms_col_product'), t('wms_col_warehouse'), t('wms_col_qty'), t('wh_irpt_col_min'),
                    t('wh_irpt_col_reorder'), t('wms_col_reorder'), 'Supplier',
                ];
            case 'out_of_stock':
                return [t('wms_col_product'), t('wms_col_warehouse'), t('wh_irpt_col_days_oos'), 'Last sale', 'Last purchase', t('wh_irpt_col_reorder')];
            case 'expiry':
                return [t('wms_col_product'), t('wms_col_batch'), t('wms_col_expiry'), t('wms_col_qty'), t('wh_irpt_col_value_at_risk'), t('wms_col_warehouse')];
            case 'damaged':
                return [t('col_date'), t('wms_col_product'), t('wh_irpt_col_damage_type'), t('wms_col_qty'), t('wms_col_warehouse'), t('wh_irpt_col_reported_by'), t('wms_col_value')];
            default:
                return [
                    t('wh_irpt_col_image'), t('wms_col_product'), 'SKU', t('wms_col_barcode'), 'Category', t('wms_col_warehouse'),
                    t('wh_irpt_col_location'), t('wms_col_qty'), 'Reserved', 'Available', t('wh_irpt_col_min'), t('wh_irpt_col_max'),
                    t('wms_unit_cost'), t('wh_irpt_col_selling'), t('wms_col_value'), t('wms_col_batch'), t('wms_col_expiry'),
                    t('col_status'), t('wh_irpt_col_updated'),
                ];
        }
    }

    function tableRow(row) {
        switch (state.tab) {
            case 'movements':
                return [
                    formatDate(row.created_at, true),
                    row.reference_number || row.reference || '—',
                    row.product_name || '—',
                    row.warehouse_name || '—',
                    movementLabel(row.movement_type),
                    row.quantity ?? '—',
                    row.previous_stock ?? '—',
                    row.balance_after ?? '—',
                    row.created_by_name || '—',
                ];
            case 'low_stock':
                return [
                    row.product_name || '—',
                    row.warehouse_name || '—',
                    row.quantity ?? 0,
                    row.min_stock ?? 0,
                    row.reorder_qty ?? 0,
                    row.supplier_name || '—',
                ];
            case 'out_of_stock':
                return [
                    row.product_name || '—',
                    row.warehouse_name || '—',
                    row.days_out_of_stock ?? '—',
                    formatDate(row.last_sale_at),
                    formatDate(row.last_purchase_at),
                    row.reorder_qty ?? 0,
                ];
            case 'expiry':
                return [
                    row.product_name || '—',
                    row.batch_number || '—',
                    formatDate(row.expiry_date),
                    row.batch_qty ?? row.quantity ?? 0,
                    money(row.value_at_risk),
                    row.warehouse_name || '—',
                ];
            case 'damaged':
                return [
                    formatDate(row.created_at, true),
                    row.product_name || '—',
                    row.damage_type || row.notes || '—',
                    Math.abs(Number(row.quantity || 0)),
                    row.warehouse_name || '—',
                    row.reported_by || '—',
                    money(row.estimated_loss),
                ];
            default:
                return [
                    productImage(row.image_url, row.product_name),
                    row.product_name || '—',
                    row.sku || '—',
                    row.barcode || '—',
                    row.category_name || '—',
                    row.warehouse_name || '—',
                    locationCell(row),
                    row.quantity ?? 0,
                    row.reserved_qty ?? 0,
                    row.available_qty ?? 0,
                    row.min_stock ?? 0,
                    row.max_stock ?? 0,
                    money(row.unit_cost || row.cost),
                    money(row.price),
                    money(row.stock_value),
                    row.batch_number || '—',
                    formatDate(row.expiry_date),
                    stockStatusBadge(row.stock_status),
                    formatDate(row.updated_at || row.last_movement_at, true),
                ];
        }
    }

    function renderTable() {
        const rows = state.items || [];
        const paginated = !['overview', 'valuation', 'performance'].includes(state.tab);
        if (!paginated) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
            return;
        }
        if (!rows.length) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        const head = tableHead();
        const htmlRows = rows.map((r) => {
            const cells = tableRow(r).map((c) => `<td>${typeof c === 'string' && c.startsWith('<') ? c : esc(String(c ?? '—'))}</td>`).join('');
            return `<tr>${cells}</tr>`;
        }).join('');
        tableWrap.innerHTML = `<table class="wh-table wh-irpt-table"><thead><tr>${head.map((h) => `<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${htmlRows}</tbody></table>`;
    }

    function renderValuation(summary) {
        const s = summary || {};
        document.getElementById('whIrptValCost').textContent = money(s.inventory_cost);
        document.getElementById('whIrptValSelling').textContent = money(s.selling_value);
        document.getElementById('whIrptValProfit').textContent = money(s.expected_profit);
        document.getElementById('whIrptValTurnover').textContent = String(s.turnover ?? '—');
    }

    function renderPerformance(summary) {
        const s = summary || {};
        document.getElementById('whIrptPerfAccuracy').textContent = pct(s.inventory_accuracy);
        document.getElementById('whIrptPerfReceiving').textContent = pct(s.receiving_efficiency);
        document.getElementById('whIrptPerfDispatch').textContent = pct(s.dispatch_efficiency);
        document.getElementById('whIrptPerfTransfer').textContent = pct(s.transfer_success_rate);
        document.getElementById('whIrptPerfAge').textContent = `${s.avg_inventory_age ?? 0}d`;
        document.getElementById('whIrptPerfUtil').textContent = pct(s.warehouse_utilization);
        document.getElementById('whIrptPerfTurnover').textContent = String(s.inventory_turnover ?? '—');
    }

    function destroyChart(id) {
        if (chartInstances[id]) {
            chartInstances[id].destroy();
            delete chartInstances[id];
        }
    }

    function renderCharts(charts) {
        if (!window.Chart || !charts) return;
        const c = chartColors();

        destroyChart('value');
        const valueData = charts.value_trend || [];
        const valueCtx = document.getElementById('whIrptChartValue');
        if (valueCtx && valueData.length) {
            chartInstances.value = new Chart(valueCtx, {
                type: 'line',
                data: {
                    labels: valueData.map((d) => d.date),
                    datasets: [{ label: t('wh_irpt_kpi_value'), data: valueData.map((d) => d.value), borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,0.1)', fill: true, tension: 0.35 }],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: c.text } }, y: { ticks: { color: c.text }, grid: { color: c.grid } } } },
            });
        }

        destroyChart('movement');
        const movData = charts.movement_trend || [];
        const movCtx = document.getElementById('whIrptChartMovement');
        if (movCtx && movData.length) {
            chartInstances.movement = new Chart(movCtx, {
                type: 'line',
                data: {
                    labels: movData.map((d) => d.d),
                    datasets: [
                        { label: 'In', data: movData.map((d) => d.stock_in), borderColor: '#0d9488', tension: 0.35 },
                        { label: 'Out', data: movData.map((d) => d.stock_out), borderColor: '#dc2626', tension: 0.35 },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { x: { ticks: { color: c.text } }, y: { ticks: { color: c.text }, grid: { color: c.grid } } } },
            });
        }

        destroyChart('category');
        const catData = charts.category_distribution || [];
        const catCtx = document.getElementById('whIrptChartCategory');
        if (catCtx && catData.length) {
            chartInstances.category = new Chart(catCtx, {
                type: 'doughnut',
                data: { labels: catData.map((d) => d.label), datasets: [{ data: catData.map((d) => d.value), backgroundColor: ['#0d9488', '#2563eb', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#65a30d', '#ea580c'] }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: c.text } } } },
            });
        }

        destroyChart('warehouse');
        const whData = charts.warehouse_comparison || [];
        const whCtx = document.getElementById('whIrptChartWarehouse');
        if (whCtx && whData.length) {
            chartInstances.warehouse = new Chart(whCtx, {
                type: 'bar',
                data: { labels: whData.map((d) => d.label), datasets: [{ label: t('wh_irpt_kpi_value'), data: whData.map((d) => d.value), backgroundColor: '#2563eb', borderRadius: 6 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: c.text, maxRotation: 45 } }, y: { ticks: { color: c.text }, grid: { color: c.grid } } } },
            });
        }

        destroyChart('status');
        const stData = charts.stock_status || [];
        const stCtx = document.getElementById('whIrptChartStatus');
        if (stCtx && stData.length) {
            chartInstances.status = new Chart(stCtx, {
                type: 'pie',
                data: { labels: stData.map((d) => STATUS_KEYS[d.status] || d.status), datasets: [{ data: stData.map((d) => d.count), backgroundColor: ['#16a34a', '#d97706', '#dc2626'] }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: c.text } } } },
            });
        }

        destroyChart('top');
        const topData = charts.top_moving || [];
        const topCtx = document.getElementById('whIrptChartTopMoving');
        if (topCtx && topData.length) {
            chartInstances.top = new Chart(topCtx, {
                type: 'bar',
                data: { labels: topData.map((d) => d.label), datasets: [{ data: topData.map((d) => d.qty), backgroundColor: '#0d9488', borderRadius: 6 }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: c.text }, grid: { color: c.grid } }, y: { ticks: { color: c.text } } } },
            });
        }

        destroyChart('low');
        const lowData = charts.lowest_stock || [];
        const lowCtx = document.getElementById('whIrptChartLowStock');
        if (lowCtx && lowData.length) {
            chartInstances.low = new Chart(lowCtx, {
                type: 'bar',
                data: { labels: lowData.map((d) => d.label), datasets: [{ data: lowData.map((d) => d.qty), backgroundColor: '#d97706', borderRadius: 6 }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: c.text }, grid: { color: c.grid } }, y: { ticks: { color: c.text } } } },
            });
        }

        destroyChart('aging');
        const ageData = charts.aging || [];
        const ageCtx = document.getElementById('whIrptChartAging');
        if (ageCtx && ageData.length) {
            chartInstances.aging = new Chart(ageCtx, {
                type: 'bar',
                data: { labels: ageData.map((d) => d.bucket), datasets: [{ data: ageData.map((d) => d.count), backgroundColor: '#7c3aed', borderRadius: 6 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: c.text } }, y: { ticks: { color: c.text }, grid: { color: c.grid } } } },
            });
        }
    }

    function renderPagination() {
        const totalPages = Math.max(1, Math.ceil(state.total / PAGE_SIZE));
        if (els.pageMeta) {
            els.pageMeta.textContent = `${t('records')}: ${state.total} · ${state.page}/${totalPages}`;
        }
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= totalPages;
        if (els.pagination) els.pagination.hidden = state.total <= PAGE_SIZE || ['overview', 'valuation', 'performance'].includes(state.tab);
    }

    async function loadFilterOptions() {
        try {
            const res = await AdminAPI.getWmsInventoryReport({ tab: 'filters' });
            state.filterOptions = res.data || {};
            const opts = state.filterOptions;
            if (els.store && opts.stores) {
                const cur = els.store.value;
                els.store.innerHTML = '<option value="">—</option>' + opts.stores.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
                if (cur) els.store.value = cur;
            }
            if (els.category && opts.categories) {
                const cur = els.category.value;
                els.category.innerHTML = '<option value="">—</option>' + opts.categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
                if (cur) els.category.value = cur;
            }
            if (els.supplier && opts.suppliers) {
                const cur = els.supplier.value;
                els.supplier.innerHTML = '<option value="">—</option>' + opts.suppliers.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
                if (cur) els.supplier.value = cur;
            }
        } catch (_) { /* optional */ }
    }

    async function load() {
        hideError();
        state.offline = false;
        if (els.offlineBadge) els.offlineBadge.hidden = true;
        state.filters = buildFilters();
        syncTabUi();
        setKpiLoading(true);
        if (els.loading) els.loading.hidden = false;

        const params = {
            ...state.filters,
            tab: state.tab,
            limit: PAGE_SIZE,
            offset: (state.page - 1) * PAGE_SIZE,
        };

        try {
            const res = await AdminAPI.getWmsInventoryReport(params);
            setMigrationHint(res.module_ready !== false);
            state.summary = res.summary;
            state.chartData = res.charts;
            state.items = res.data || [];
            state.total = res.total || 0;
            renderKpis(res.summary);
            renderAlerts(res.alerts || []);
            if (state.tab === 'overview') renderCharts(res.charts);
            if (state.tab === 'valuation') renderValuation(res.summary);
            if (state.tab === 'performance') renderPerformance(res.summary);
            renderTable();
            renderPagination();
            updateLastUpdated();
            updateAudit();
            saveCache({
                tab: state.tab,
                filters: state.filters,
                summary: res.summary,
                charts: res.charts,
                data: state.items,
                total: state.total,
                alerts: res.alerts,
            });
        } catch (err) {
            if (!navigator.onLine) {
                const cached = loadCache();
                if (applyCached(cached)) {
                    if (els.loading) els.loading.hidden = true;
                    return;
                }
            }
            showError(err.message || t('load_error'));
            const cached = loadCache();
            if (applyCached(cached)) {
                if (els.loading) els.loading.hidden = true;
                return;
            }
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    function exportRows() {
        const head = tableHead();
        const body = (state.items || []).map((r) => tableRow(r).map((c) => {
            if (typeof c === 'string') return c.replace(/<[^>]+>/g, '').trim();
            return c ?? '';
        }));
        return [head, ...body];
    }

    async function doExport(type) {
        state.filters = buildFilters();
        if (['overview', 'valuation', 'performance'].includes(state.tab) && state.tab !== 'overview') {
            await load();
        }
        const stamp = new Date().toISOString().slice(0, 10);
        const base = `inventory-report-${state.tab}-${stamp}`;

        if (type === 'csv' || type === 'excel') {
            const rows = exportRows();
            if (!rows.length || rows.length === 1) {
                showError(t('wh_irpt_empty'));
                return;
            }
            if (type === 'excel') {
                const bom = '\uFEFF';
                const tsv = bom + rows.map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join('\t')).join('\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([tsv], { type: 'application/vnd.ms-excel;charset=utf-8' }));
                a.download = `${base}.xls`;
                a.click();
            } else {
                exportCsv(`${base}.csv`, rows);
            }
            updateAudit(type);
            return;
        }

        if (type === 'pdf') {
            const rows = exportRows();
            if (rows.length <= 1 && ['inventory', 'movements', 'low_stock', 'out_of_stock', 'expiry', 'damaged'].includes(state.tab)) {
                await load();
            }
            const exportData = exportRows();
            const head = exportData[0] || [];
            const body = exportData.slice(1);
            if (window.WmsReportExport?.exportPdf) {
                await WmsReportExport.exportPdf({
                    title: t('wh_nav_rpt_inventory') || 'Inventory Report',
                    periodLabel: filtersSummary(state.filters),
                    generatedLabel: t('wh_irpt_audit_generated'),
                    locale: window.WH_CONFIG?.locale,
                    head,
                    rows: body,
                    filename: `${base}.pdf`,
                });
                updateAudit('pdf');
            }
            return;
        }

        if (type === 'print') {
            window.print();
            updateAudit('print');
        }
    }

    function resetFilters() {
        [els.stockStatus, els.movementType, els.store, els.category, els.supplier, els.zone, els.aisle, els.rack, els.shelf, els.bin, els.batch, els.serial].forEach((el) => { if (el) el.value = ''; });
        if (els.search) els.search.value = '';
        if (els.valMethod) els.valMethod.value = 'weighted';
        if (els.expiryDays) els.expiryDays.value = '90';
        state.page = 1;
        load();
    }

    els.tabs?.addEventListener('click', (e) => {
        const btn = e.target.closest('.wh-irpt-tab');
        if (!btn) return;
        state.tab = btn.dataset.tab || 'overview';
        state.page = 1;
        load();
    });

    els.applyBtn?.addEventListener('click', () => { state.page = 1; load(); els.filtersPanel?.classList.remove('is-open'); });
    els.resetBtn?.addEventListener('click', resetFilters);
    els.refresh?.addEventListener('click', () => load());
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => { state.page += 1; load(); });
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => { state.page = 1; load(); }, 350);
    });
    els.exportCsv?.addEventListener('click', () => doExport('csv'));
    els.exportExcel?.addEventListener('click', () => doExport('excel'));
    els.exportPdf?.addEventListener('click', () => doExport('pdf'));
    els.printBtn?.addEventListener('click', () => doExport('print'));
    els.filtersToggle?.addEventListener('click', () => els.filtersPanel?.classList.toggle('is-open'));
    els.filtersClose?.addEventListener('click', () => els.filtersPanel?.classList.remove('is-open'));
    els.scheduleForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const freq = document.getElementById('whIrptScheduleFreq')?.value;
        const email = document.getElementById('whIrptScheduleEmail')?.value;
        try {
            await AdminAPI.postWmsInventoryReportSchedule({
                frequency: freq,
                email,
                tab: state.tab,
                filters: buildFilters(),
            });
            els.scheduleModal.hidden = true;
            updateAudit('schedule');
        } catch (err) {
            showError(err.message || t('error'));
        }
    });
    els.scheduleBtn?.addEventListener('click', () => { if (els.scheduleModal) els.scheduleModal.hidden = false; });
    document.querySelectorAll('[data-close="whIrptScheduleModal"]').forEach((el) => {
        el.addEventListener('click', () => { if (els.scheduleModal) els.scheduleModal.hidden = true; });
    });

    document.addEventListener('wh:refresh', () => load());
    window.addEventListener('online', () => load());

    loadWarehouseOptions(els.warehouse).then(() => loadFilterOptions()).then(() => load());
});
