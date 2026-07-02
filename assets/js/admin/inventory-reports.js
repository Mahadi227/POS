/**
 * Admin inventory reports — valuation, alerts, exports, i18n
 */
(() => {
    const CFG = window.INVENTORY_CONFIG || {};
    const i18n = window.INVENTORY_I18N || {};
    const locale = CFG.locale || (CFG.lang === 'fr' ? 'fr-FR' : 'en-US');
    const PAGE_SIZE = 25;

    const PERIOD_KEYS = {
        today: 'period_today',
        week: 'period_week',
        month: 'period_month',
        '90d': 'period_90d',
        all: 'period_all',
    };

    const $ = (id) => document.getElementById(id);
    let reportData = null;
    let valuationRows = [];
    let valuationPage = 1;
    let activePeriod = 'month';
    let lastFetchAt = null;

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function escapeAttr(s) {
        return String(s ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function categoryLabels() {
        return {
            category: t('col_category'),
            products: t('stat_total_products'),
            stock: t('stock'),
            cost: t('col_cost_value'),
            retail: t('col_retail_value'),
        };
    }

    function productLabels() {
        return {
            product: t('col_product'),
            sku: t('col_sku'),
            category: t('col_category'),
            stock: t('stock'),
            minStock: t('col_min_stock'),
            cost: t('col_cost_value'),
            retail: t('col_retail_value'),
            qtySold: t('col_qty_sold'),
            revenue: t('col_revenue'),
        };
    }

    function toast(msg, type = 'success') {
        const el = $('invToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast show ${type === 'error' ? 'error' : ''}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('#irSummaryCards .ad-kpi').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function clearKpiLoading() {
        document.querySelectorAll('#irSummaryCards .ad-kpi').forEach((el) => {
            el.classList.remove('is-loading');
        });
    }

    function syncPeriodChips(period) {
        document.querySelectorAll('.ir-chips .inv-chip').forEach((chip) => {
            const active = chip.dataset.period === period;
            chip.classList.toggle('active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function updateHeroMeta() {
        const periodEl = $('irHeroPeriod');
        if (periodEl) {
            periodEl.textContent = new Date().toLocaleDateString(locale, {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            });
        }
        const scopeEl = $('irHeroScope');
        if (scopeEl) {
            const store = CFG.storeName || t('store_fallback');
            scopeEl.textContent = `${store} · ${t('report_generated', periodLabel(activePeriod))}`;
        }
    }

    function updateLowStockAlert() {
        const alert = $('irLowStockAlert');
        const text = $('irLowStockAlertText');
        if (!alert || !text) return;

        const status = reportData?.stock_status || {};
        const count = (status.low_stock ?? 0) + (status.out_of_stock ?? 0);
        let msg = '';
        if (count > 0) {
            const raw = t('low_stock_alert');
            if (raw && raw !== 'low_stock_alert') {
                msg = raw.includes('%s') ? raw.replace('%s', String(count)) : `${count} — ${raw}`;
                text.textContent = msg;
            }
        } else {
            text.textContent = '';
        }
        alert.hidden = !(count > 0 && msg.trim());
    }

    function showError(msg) {
        const banner = $('reportsError');
        if (!banner) return;
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        banner.classList.add('is-visible');
    }

    function hideError() {
        $('reportsError')?.classList.remove('is-visible');
    }

    function updateDateHeader() {
        const header = $('reportsDate');
        if (!header) return;
        header.textContent = new Date().toLocaleDateString(locale, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }

    function updateLastUpdated() {
        const el = $('lastUpdated');
        if (!el || !lastFetchAt) return;
        const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        el.textContent = t('last_updated', time);
    }

    function periodLabel(period) {
        const key = PERIOD_KEYS[period] || 'period_month';
        return t(key);
    }

    function categoryLabel(name) {
        if (!name || name === 'Uncategorized') return t('uncategorized');
        return name;
    }

    function stockClass(qty, min) {
        const q = parseInt(qty, 10) || 0;
        const m = parseInt(min, 10) || 5;
        if (q <= 0) return 'ir-stock-warn';
        if (q <= m) return 'ir-stock-low';
        return '';
    }

    function renderSummary(summary) {
        clearKpiLoading();
        if (!summary) return;

        const units = summary.total_units ?? 0;
        if ($('stat-products-val')) $('stat-products-val').textContent = String(summary.total_products ?? 0);
        if ($('stat-units-val')) $('stat-units-val').textContent = units.toLocaleString(locale);
        if ($('stat-cost-val')) $('stat-cost-val').textContent = AdminAPI.formatCurrency(summary.cost_value ?? 0);
        if ($('stat-retail-val')) $('stat-retail-val').textContent = AdminAPI.formatCurrency(summary.retail_value ?? 0);

        const status = reportData?.stock_status || {};
        if ($('ir-kpi-products-meta')) {
            $('ir-kpi-products-meta').textContent = `${t('stock_status_in_stock')}: ${status.in_stock ?? 0}`;
        }
        if ($('ir-kpi-units-meta')) {
            $('ir-kpi-units-meta').textContent = t('units_in_stock', units.toLocaleString(locale));
        }

        if ($('pill-in-stock')) {
            $('pill-in-stock').textContent = `${t('stock_status_in_stock')}: ${status.in_stock ?? 0}`;
        }
        if ($('pill-low-stock')) {
            $('pill-low-stock').textContent = `${t('stock_status_low')}: ${status.low_stock ?? 0}`;
        }
        if ($('pill-out-stock')) {
            $('pill-out-stock').textContent = `${t('stock_status_out')}: ${status.out_of_stock ?? 0}`;
        }

        updateLowStockAlert();

        const ledger = reportData?.ledger_summary || {};
        if ($('ledger-in')) $('ledger-in').textContent = (ledger.total_in ?? 0).toLocaleString(locale);
        if ($('ledger-out')) $('ledger-out').textContent = (ledger.total_out ?? 0).toLocaleString(locale);
        if ($('ledger-entries')) {
            $('ledger-entries').textContent = t('ledger_entries', ledger.entries ?? 0);
        }
    }

    function renderCategoryTable(rows) {
        const body = $('categoryBreakdownBody');
        if (!body) return;

        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="5" class="ad-empty-row">${t('no_report_data')}</td></tr>`;
            return;
        }

        const lbl = categoryLabels();
        body.innerHTML = rows.map((row) => `
            <tr>
                <td data-label="${escapeAttr(lbl.category)}"><strong>${escapeHtml(categoryLabel(row.name))}</strong></td>
                <td data-label="${escapeAttr(lbl.products)}">${escapeHtml(String(row.product_count ?? 0))}</td>
                <td data-label="${escapeAttr(lbl.stock)}">${escapeHtml(String(row.units ?? 0))}</td>
                <td data-label="${escapeAttr(lbl.cost)}">${escapeHtml(AdminAPI.formatCurrency(row.cost_value ?? 0))}</td>
                <td data-label="${escapeAttr(lbl.retail)}">${escapeHtml(AdminAPI.formatCurrency(row.retail_value ?? 0))}</td>
            </tr>
        `).join('');
    }

    function renderLowStockTable(rows) {
        const body = $('lowStockBody');
        if (!body) return;

        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${t('no_low_stock')}</td></tr>`;
            return;
        }

        const lbl = productLabels();
        body.innerHTML = rows.map((row) => {
            const cls = stockClass(row.stock_quantity, row.min_stock_level);
            return `
                <tr>
                    <td data-label="${escapeAttr(lbl.product)}"><strong>${escapeHtml(row.name)}</strong></td>
                    <td data-label="${escapeAttr(lbl.sku)}">${escapeHtml(row.sku || '—')}</td>
                    <td data-label="${escapeAttr(lbl.category)}">${escapeHtml(categoryLabel(row.category_name))}</td>
                    <td class="${cls}" data-label="${escapeAttr(lbl.stock)}">${escapeHtml(String(row.stock_quantity ?? 0))}</td>
                    <td data-label="${escapeAttr(lbl.minStock)}">${escapeHtml(String(row.min_stock_level ?? 5))}</td>
                    <td data-label="${escapeAttr(lbl.cost)}">${escapeHtml(AdminAPI.formatCurrency((row.cost ?? 0) * (row.stock_quantity ?? 0)))}</td>
                </tr>
            `;
        }).join('');
    }

    function renderTopMovingTable(rows) {
        const body = $('topMovingBody');
        if (!body) return;

        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="4" class="ad-empty-row">${t('no_top_moving')}</td></tr>`;
            return;
        }

        const lbl = productLabels();
        body.innerHTML = rows.map((row) => `
            <tr>
                <td data-label="${escapeAttr(lbl.product)}"><strong>${escapeHtml(row.name)}</strong></td>
                <td data-label="${escapeAttr(lbl.sku)}">${escapeHtml(row.sku || '—')}</td>
                <td data-label="${escapeAttr(lbl.qtySold)}" style="font-weight:600;">${escapeHtml(String(row.qty_sold ?? 0))}</td>
                <td data-label="${escapeAttr(lbl.revenue)}">${escapeHtml(AdminAPI.formatCurrency(row.revenue ?? 0))}</td>
            </tr>
        `).join('');
    }

    function buildValuationRows(products) {
        return (products || []).map((p) => {
            const qty = parseInt(p.stock_quantity, 10) || 0;
            const cost = parseFloat(p.cost) || 0;
            const price = parseFloat(p.price) || 0;
            return {
                id: p.id,
                name: p.name,
                sku: p.sku,
                category_name: p.category_name,
                stock_quantity: qty,
                min_stock_level: p.min_stock_level ?? 5,
                cost_value: qty * cost,
                retail_value: qty * price,
            };
        }).sort((a, b) => b.retail_value - a.retail_value);
    }

    function renderValuationTable() {
        const body = $('valuationBody');
        if (!body) return;

        if (!valuationRows.length) {
            body.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${t('no_report_data')}</td></tr>`;
            if ($('valuationSummary')) $('valuationSummary').textContent = t('no_report_data');
            if ($('valPageInfo')) $('valPageInfo').textContent = '1 / 1';
            if ($('valPagePrev')) $('valPagePrev').disabled = true;
            if ($('valPageNext')) $('valPageNext').disabled = true;
            return;
        }

        const totalPages = Math.max(1, Math.ceil(valuationRows.length / PAGE_SIZE));
        if (valuationPage > totalPages) valuationPage = totalPages;
        const start = (valuationPage - 1) * PAGE_SIZE;
        const pageItems = valuationRows.slice(start, start + PAGE_SIZE);

        if ($('valuationSummary')) {
            $('valuationSummary').textContent = t('valuation_table_summary', valuationRows.length, valuationPage, totalPages);
        }
        if ($('valPageInfo')) $('valPageInfo').textContent = `${valuationPage} / ${totalPages}`;
        if ($('valPagePrev')) $('valPagePrev').disabled = valuationPage <= 1;
        if ($('valPageNext')) $('valPageNext').disabled = valuationPage >= totalPages;

        const lbl = productLabels();
        body.innerHTML = pageItems.map((row) => {
            const cls = stockClass(row.stock_quantity, row.min_stock_level);
            return `
                <tr>
                    <td data-label="${escapeAttr(lbl.product)}"><strong>${escapeHtml(row.name)}</strong></td>
                    <td data-label="${escapeAttr(lbl.sku)}">${escapeHtml(row.sku || '—')}</td>
                    <td data-label="${escapeAttr(lbl.category)}">${escapeHtml(categoryLabel(row.category_name))}</td>
                    <td class="${cls}" data-label="${escapeAttr(lbl.stock)}">${escapeHtml(String(row.stock_quantity))}</td>
                    <td data-label="${escapeAttr(lbl.cost)}">${escapeHtml(AdminAPI.formatCurrency(row.cost_value))}</td>
                    <td data-label="${escapeAttr(lbl.retail)}">${escapeHtml(AdminAPI.formatCurrency(row.retail_value))}</td>
                </tr>
            `;
        }).join('');
    }

    function applyPeriod(period) {
        activePeriod = period;
        syncPeriodChips(period);
        loadReports();
    }

    async function loadReports() {
        const btn = $('refreshReportsBtn');
        setStatsLoading(true);
        btn?.classList.add('spinning');

        try {
            const [reportRes, productsRes] = await Promise.all([
                AdminAPI.getInventoryReports({ period: activePeriod }),
                AdminAPI.getInventoryProducts(),
            ]);

            if (reportRes.status !== 'success') {
                showError(reportRes.message || t('load_error'));
                reportData = null;
            } else {
                hideError();
                reportData = reportRes.data || {};
            }

            valuationRows = productsRes.status === 'success'
                ? buildValuationRows(productsRes.data)
                : [];

            lastFetchAt = new Date();
            updateLastUpdated();
            updateHeroMeta();
            valuationPage = 1;

            renderSummary(reportData?.summary);
            renderCategoryTable(reportData?.category_breakdown);
            renderLowStockTable(reportData?.low_stock_products);
            renderTopMovingTable(reportData?.top_moving);
            renderValuationTable();
        } catch (e) {
            console.error(e);
            showError(t('connection_error'));
            clearKpiLoading();
        } finally {
            btn?.classList.remove('spinning');
        }
    }

    function exportContext() {
        return {
            cfg: CFG,
            locale,
            i18n,
            reportData,
            valuationRows,
            activePeriod,
            t,
            categoryLabel,
            periodLabel,
        };
    }

    function exportFullCsv() {
        if (!reportData && !valuationRows.length) {
            toast(t('no_report_data'), 'error');
            return;
        }
        InventoryReportExport.exportFullCsv(exportContext());
        toast(t('export_success'));
    }

    function exportValuationCsv() {
        if (!valuationRows.length) {
            toast(t('no_report_data'), 'error');
            return;
        }
        InventoryReportExport.exportValuationCsv(exportContext());
        toast(t('export_success'));
    }

    function exportAlertsCsv() {
        const rows = reportData?.low_stock_products || [];
        if (!rows.length) {
            toast(t('no_low_stock'), 'error');
            return;
        }
        InventoryReportExport.exportAlertsCsv(exportContext());
        toast(t('export_success'));
    }

    async function exportPdf() {
        if (!reportData && !valuationRows.length) {
            toast(t('no_report_data'), 'error');
            return;
        }
        toast(t('exporting_pdf'));
        try {
            const result = await InventoryReportExport.exportPdf(exportContext());
            toast(result?.fallback ? t('pdf_fallback_print') : t('export_success'));
        } catch (e) {
            console.error(e);
            toast(t('export_error'), 'error');
        }
    }

    function printReport() {
        if (!reportData && !valuationRows.length) {
            toast(t('no_report_data'), 'error');
            return;
        }
        if (!InventoryReportExport.printReport(exportContext())) {
            toast(t('export_error'), 'error');
        }
    }

    function bindEvents() {
        $('refreshReportsBtn')?.addEventListener('click', loadReports);

        document.addEventListener('store-switched', () => loadReports());

        document.querySelectorAll('.ir-chips .inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => applyPeriod(chip.dataset.period || 'month'));
        });

        $('exportCsvBtn')?.addEventListener('click', exportFullCsv);
        $('exportAlertsCsvBtn')?.addEventListener('click', exportAlertsCsv);
        $('exportPdfBtn')?.addEventListener('click', exportPdf);
        $('printReportBtn')?.addEventListener('click', printReport);

        $('valPagePrev')?.addEventListener('click', () => {
            if (valuationPage > 1) {
                valuationPage -= 1;
                renderValuationTable();
            }
        });
        $('valPageNext')?.addEventListener('click', () => {
            const totalPages = Math.max(1, Math.ceil(valuationRows.length / PAGE_SIZE));
            if (valuationPage < totalPages) {
                valuationPage += 1;
                renderValuationTable();
            }
        });
    }

    async function init() {
        updateDateHeader();
        updateHeroMeta();
        syncPeriodChips(activePeriod);
        bindEvents();
        await loadReports();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
