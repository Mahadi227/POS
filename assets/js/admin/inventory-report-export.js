/**
 * Professional inventory report export — PDF, CSV, print documents
 */
window.InventoryReportExport = (() => {
    const BRAND = 'RetailPOS';
    const BRAND_COLOR = [37, 99, 235];
    const MUTED = [100, 116, 139];

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector(`script[src="${src}"]`)) {
                resolve();
                return;
            }
            const s = document.createElement('script');
            s.src = src;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    async function ensurePdfLibs() {
        if (window.jspdf?.jsPDF) return true;
        try {
            await loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.2/jspdf.umd.min.js');
            await loadScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js');
            return !!(window.jspdf?.jsPDF);
        } catch (e) {
            console.warn('PDF libs failed to load', e);
            return false;
        }
    }

    function buildMeta(ctx) {
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const ref = `INV-${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`;
        const dateStr = now.toLocaleString(ctx.locale, { dateStyle: 'long', timeStyle: 'short' });
        const fileDate = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        return {
            ref,
            dateStr,
            fileDate,
            storeName: ctx.cfg.storeName || ctx.t('store_fallback', ctx.cfg.storeId || ''),
            userName: ctx.cfg.userName || '—',
            currency: ctx.cfg.currency || 'FCFA',
            period: ctx.periodLabel(ctx.activePeriod),
            periodKey: ctx.activePeriod,
        };
    }

    function fmtMoney(ctx, amount) {
        return AdminAPI.formatCurrency(amount);
    }

    function fmtNum(ctx, n) {
        return Number(n || 0).toLocaleString(ctx.locale);
    }

    function fmtRaw(n, decimals = 2) {
        return Number(n || 0).toFixed(decimals);
    }

    function csvSep(ctx) {
        return ctx.locale.startsWith('fr') ? ';' : ',';
    }

    function csvCell(value) {
        const str = String(value ?? '');
        if (/[",;\n\r]/.test(str)) return `"${str.replace(/"/g, '""')}"`;
        return str;
    }

    function csvRow(cells, sep) {
        return cells.map(csvCell).join(sep);
    }

    function downloadBlob(filename, content, mime) {
        const blob = new Blob([content], { type: mime });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function sum(rows, key) {
        return rows.reduce((s, r) => s + (parseFloat(r[key]) || 0), 0);
    }

    function buildFullCsv(ctx) {
        const { reportData, valuationRows, t, categoryLabel } = ctx;
        const meta = buildMeta(ctx);
        const sep = csvSep(ctx);
        const summary = reportData?.summary || {};
        const status = reportData?.stock_status || {};
        const ledger = reportData?.ledger_summary || {};
        const lines = [];

        const push = (...cells) => lines.push(csvRow(cells, sep));
        const blank = () => lines.push('');
        const section = (title) => {
            blank();
            push('='.repeat(72));
            push(title.toUpperCase());
            push('='.repeat(72));
        };

        push(BRAND);
        push(t('doc_title'));
        push(t('doc_subtitle'));
        blank();
        push(t('doc_reference'), meta.ref);
        push(t('doc_store'), meta.storeName);
        push(t('doc_period'), meta.period);
        push(t('doc_currency'), meta.currency);
        push(t('doc_prepared_by'), meta.userName);
        push(t('doc_generated_on'), meta.dateStr);
        if (reportData?.from && reportData?.to && ctx.activePeriod !== 'all') {
            push('From', reportData.from, 'To', reportData.to);
        }

        section(t('doc_executive_summary'));
        push(t('stat_total_products'), summary.total_products ?? 0);
        push(t('stock'), summary.total_units ?? 0);
        push(t('stat_cost_value'), fmtRaw(summary.cost_value));
        push(t('stat_retail_value'), fmtRaw(summary.retail_value));
        push(t('stock_status_in_stock'), status.in_stock ?? 0);
        push(t('stock_status_low'), status.low_stock ?? 0);
        push(t('stock_status_out'), status.out_of_stock ?? 0);

        section(t('report_ledger_summary'));
        push(t('stat_total_in'), ledger.total_in ?? 0);
        push(t('stat_total_out'), ledger.total_out ?? 0);
        push(t('ledger_entries').replace('%s', ''), ledger.entries ?? 0);

        const categories = reportData?.category_breakdown || [];
        section(t('report_category_breakdown'));
        push(t('col_category'), t('stat_total_products'), t('stock'), t('col_cost_value'), t('col_retail_value'));
        categories.forEach((row) => {
            push(categoryLabel(row.name), row.product_count ?? 0, row.units ?? 0, fmtRaw(row.cost_value), fmtRaw(row.retail_value));
        });
        if (categories.length) {
            push(t('doc_total'), sum(categories, 'product_count'), sum(categories, 'units'), fmtRaw(sum(categories, 'cost_value')), fmtRaw(sum(categories, 'retail_value')));
        }

        const topMoving = reportData?.top_moving || [];
        section(t('report_top_moving'));
        push(t('col_product'), t('col_sku'), t('col_qty_sold'), t('col_revenue'));
        topMoving.forEach((row) => push(row.name, row.sku || '', row.qty_sold ?? 0, fmtRaw(row.revenue)));
        if (topMoving.length) push(t('doc_total'), '', sum(topMoving, 'qty_sold'), fmtRaw(sum(topMoving, 'revenue')));

        const lowStock = reportData?.low_stock_products || [];
        section(t('report_low_stock'));
        push(t('col_product'), t('col_sku'), t('col_category'), t('stock'), t('col_min_stock'), t('col_cost_value'));
        lowStock.forEach((row) => {
            const costVal = (parseFloat(row.cost) || 0) * (parseInt(row.stock_quantity, 10) || 0);
            push(row.name, row.sku || '', categoryLabel(row.category_name), row.stock_quantity ?? 0, row.min_stock_level ?? 5, fmtRaw(costVal));
        });

        section(t('report_valuation'));
        push(t('col_product'), t('col_sku'), t('col_category'), t('stock'), t('col_cost_value'), t('col_retail_value'));
        valuationRows.forEach((row) => {
            push(row.name, row.sku || '', categoryLabel(row.category_name), row.stock_quantity, fmtRaw(row.cost_value), fmtRaw(row.retail_value));
        });
        if (valuationRows.length) {
            push(t('doc_grand_total'), '', '', sum(valuationRows, 'stock_quantity'), fmtRaw(sum(valuationRows, 'cost_value')), fmtRaw(sum(valuationRows, 'retail_value')));
        }

        blank();
        push(t('doc_confidential'));
        push(t('doc_footer'));

        return { content: '\uFEFF' + lines.join('\r\n'), filename: `${BRAND}_Inventory_Report_${meta.fileDate}_${meta.periodKey}.csv` };
    }

    function buildValuationCsv(ctx) {
        const { valuationRows, t, categoryLabel } = ctx;
        const meta = buildMeta(ctx);
        const sep = csvSep(ctx);
        const lines = [];
        const push = (...cells) => lines.push(csvRow(cells, sep));

        push(BRAND, t('report_valuation'));
        push(t('doc_reference'), meta.ref);
        push(t('doc_store'), meta.storeName);
        push(t('doc_generated_on'), meta.dateStr);
        lines.push('');
        push(t('col_product'), t('col_sku'), t('col_category'), t('stock'), t('col_cost_value'), t('col_retail_value'));
        valuationRows.forEach((row) => push(row.name, row.sku || '', categoryLabel(row.category_name), row.stock_quantity, fmtRaw(row.cost_value), fmtRaw(row.retail_value)));
        if (valuationRows.length) {
            push(t('doc_grand_total'), '', '', sum(valuationRows, 'stock_quantity'), fmtRaw(sum(valuationRows, 'cost_value')), fmtRaw(sum(valuationRows, 'retail_value')));
        }

        return { content: '\uFEFF' + lines.join('\r\n'), filename: `${BRAND}_Stock_Valuation_${meta.fileDate}.csv` };
    }

    function buildAlertsCsv(ctx) {
        const { reportData, t, categoryLabel } = ctx;
        const meta = buildMeta(ctx);
        const sep = csvSep(ctx);
        const rows = reportData?.low_stock_products || [];
        const lines = [];
        const push = (...cells) => lines.push(csvRow(cells, sep));

        push(BRAND, t('report_low_stock'));
        push(t('doc_reference'), meta.ref);
        push(t('doc_store'), meta.storeName);
        push(t('doc_generated_on'), meta.dateStr);
        lines.push('');
        push(t('col_product'), t('col_sku'), t('col_category'), t('stock'), t('col_min_stock'), t('col_cost_value'));
        rows.forEach((row) => {
            const costVal = (parseFloat(row.cost) || 0) * (parseInt(row.stock_quantity, 10) || 0);
            push(row.name, row.sku || '', categoryLabel(row.category_name), row.stock_quantity ?? 0, row.min_stock_level ?? 5, fmtRaw(costVal));
        });

        return { content: '\uFEFF' + lines.join('\r\n'), filename: `${BRAND}_Stock_Alerts_${meta.fileDate}.csv` };
    }

    function docStyles() {
        return `
            @page { size: A4; margin: 14mm 12mm 18mm; }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Segoe UI', system-ui, sans-serif; font-size: 10pt; color: #0f172a; line-height: 1.45; background: #fff; }
            .doc { max-width: 210mm; margin: 0 auto; padding: 8mm 0; }
            .doc-header { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 16px; border-bottom: 3px solid #2563eb; margin-bottom: 20px; }
            .brand { font-size: 22pt; font-weight: 700; color: #2563eb; }
            .brand span { color: #0f172a; }
            .doc-title { font-size: 13pt; font-weight: 600; color: #334155; margin-top: 4px; }
            .doc-sub { font-size: 9pt; color: #64748b; margin-top: 2px; }
            .meta-grid { display: grid; grid-template-columns: auto 1fr; gap: 4px 14px; font-size: 9pt; min-width: 220px; }
            .meta-grid dt { color: #64748b; font-weight: 500; }
            .meta-grid dd { color: #0f172a; font-weight: 600; margin: 0; }
            .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
            .kpi { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; background: linear-gradient(180deg, #f8fafc 0%, #fff 100%); }
            .kpi-label { font-size: 8pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
            .kpi-value { font-size: 14pt; font-weight: 700; margin-top: 4px; }
            .status-row { display: flex; gap: 10px; margin-bottom: 22px; flex-wrap: wrap; }
            .status-pill { padding: 6px 12px; border-radius: 999px; font-size: 8.5pt; font-weight: 600; border: 1px solid #e2e8f0; }
            .status-pill.in { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
            .status-pill.low { background: #fffbeb; color: #b45309; border-color: #fde68a; }
            .status-pill.out { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
            .section { margin-bottom: 22px; page-break-inside: avoid; }
            .section-title { font-size: 10pt; font-weight: 700; color: #2563eb; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e2e8f0; }
            table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
            thead th { background: #1e293b; color: #fff; font-weight: 600; text-align: left; padding: 7px 8px; }
            thead th.num, tbody td.num { text-align: right; }
            tbody td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
            tbody tr:nth-child(even) td { background: #f8fafc; }
            tbody tr.total td { font-weight: 700; background: #eff6ff !important; border-top: 2px solid #2563eb; }
            .warn { color: #dc2626; font-weight: 600; }
            .low { color: #d97706; font-weight: 600; }
            .empty { color: #94a3b8; font-style: italic; padding: 12px 0; }
            .ledger-mini { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 8px; }
            .ledger-mini div { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; text-align: center; }
            .ledger-mini strong { display: block; font-size: 13pt; margin-top: 4px; }
            .doc-footer { margin-top: 28px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 8pt; color: #64748b; display: flex; justify-content: space-between; gap: 12px; }
            .page-break { page-break-before: always; }
            @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } .doc { padding: 0; } }
        `;
    }

    function renderTable(headers, rows, numericCols = []) {
        if (!rows.length) return '<p class="empty">—</p>';
        const head = headers.map((h, i) => `<th${numericCols.includes(i) ? ' class="num"' : ''}>${h}</th>`).join('');
        const body = rows.map((row) => {
            const cells = row.cells.map((c, i) => {
                const cls = [numericCols.includes(i) ? 'num' : '', c.class || ''].filter(Boolean).join(' ');
                return `<td${cls ? ` class="${cls}"` : ''}>${c.text}</td>`;
            }).join('');
            return `<tr${row.total ? ' class="total"' : ''}>${cells}</tr>`;
        }).join('');
        return `<table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>`;
    }

    function buildPrintHtml(ctx) {
        const { reportData, valuationRows, t, categoryLabel } = ctx;
        const meta = buildMeta(ctx);
        const summary = reportData?.summary || {};
        const status = reportData?.stock_status || {};
        const ledger = reportData?.ledger_summary || {};
        const categories = reportData?.category_breakdown || [];
        const topMoving = reportData?.top_moving || [];
        const lowStock = reportData?.low_stock_products || [];
        const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        const money = (n) => fmtMoney(ctx, n);
        const num = (n) => fmtNum(ctx, n);

        const catRows = categories.map((r) => ({
            cells: [
                { text: esc(categoryLabel(r.name)) }, { text: num(r.product_count) }, { text: num(r.units) },
                { text: money(r.cost_value) }, { text: money(r.retail_value) },
            ],
        }));
        if (categories.length) {
            catRows.push({
                total: true,
                cells: [
                    { text: esc(t('doc_total')) }, { text: num(sum(categories, 'product_count')) }, { text: num(sum(categories, 'units')) },
                    { text: money(sum(categories, 'cost_value')) }, { text: money(sum(categories, 'retail_value')) },
                ],
            });
        }

        const topRows = topMoving.map((r) => ({
            cells: [{ text: esc(r.name) }, { text: esc(r.sku || '—') }, { text: num(r.qty_sold) }, { text: money(r.revenue) }],
        }));

        const alertRows = lowStock.map((r) => {
            const q = parseInt(r.stock_quantity, 10) || 0;
            const m = parseInt(r.min_stock_level, 10) || 5;
            const cls = q <= 0 ? 'warn' : (q <= m ? 'low' : '');
            return {
                cells: [
                    { text: esc(r.name) }, { text: esc(r.sku || '—') }, { text: esc(categoryLabel(r.category_name)) },
                    { text: num(q), class: cls }, { text: num(r.min_stock_level ?? 5) },
                    { text: money((parseFloat(r.cost) || 0) * q) },
                ],
            };
        });

        const valRows = valuationRows.map((r) => {
            const cls = r.stock_quantity <= 0 ? 'warn' : (r.stock_quantity <= (r.min_stock_level || 5) ? 'low' : '');
            return {
                cells: [
                    { text: esc(r.name) }, { text: esc(r.sku || '—') }, { text: esc(categoryLabel(r.category_name)) },
                    { text: num(r.stock_quantity), class: cls }, { text: money(r.cost_value) }, { text: money(r.retail_value) },
                ],
            };
        });
        if (valuationRows.length) {
            valRows.push({
                total: true,
                cells: [
                    { text: esc(t('doc_grand_total')) }, { text: '' }, { text: '' },
                    { text: num(sum(valuationRows, 'stock_quantity')) }, { text: money(sum(valuationRows, 'cost_value')) }, { text: money(sum(valuationRows, 'retail_value')) },
                ],
            });
        }

        return `<!DOCTYPE html><html lang="${ctx.cfg.lang || 'en'}"><head><meta charset="UTF-8"><title>${esc(t('doc_title'))} — ${esc(meta.ref)}</title><style>${docStyles()}</style></head><body>
<div class="doc">
<header class="doc-header">
<div><div class="brand">${BRAND}<span>.</span></div><div class="doc-title">${esc(t('doc_title'))}</div><div class="doc-sub">${esc(t('doc_subtitle'))}</div></div>
<dl class="meta-grid">
<dt>${esc(t('doc_reference'))}</dt><dd>${esc(meta.ref)}</dd>
<dt>${esc(t('doc_store'))}</dt><dd>${esc(meta.storeName)}</dd>
<dt>${esc(t('doc_period'))}</dt><dd>${esc(meta.period)}</dd>
<dt>${esc(t('doc_currency'))}</dt><dd>${esc(meta.currency)}</dd>
<dt>${esc(t('doc_prepared_by'))}</dt><dd>${esc(meta.userName)}</dd>
<dt>${esc(t('doc_generated_on'))}</dt><dd>${esc(meta.dateStr)}</dd>
</dl></header>
<div class="section"><div class="section-title">${esc(t('doc_executive_summary'))}</div>
<div class="kpi-row">
<div class="kpi"><div class="kpi-label">${esc(t('stat_total_products'))}</div><div class="kpi-value">${num(summary.total_products)}</div></div>
<div class="kpi"><div class="kpi-label">${esc(t('stock'))}</div><div class="kpi-value">${num(summary.total_units)}</div></div>
<div class="kpi"><div class="kpi-label">${esc(t('stat_cost_value'))}</div><div class="kpi-value">${money(summary.cost_value)}</div></div>
<div class="kpi"><div class="kpi-label">${esc(t('stat_retail_value'))}</div><div class="kpi-value">${money(summary.retail_value)}</div></div>
</div>
<div class="status-row">
<span class="status-pill in">${esc(t('stock_status_in_stock'))}: ${num(status.in_stock)}</span>
<span class="status-pill low">${esc(t('stock_status_low'))}: ${num(status.low_stock)}</span>
<span class="status-pill out">${esc(t('stock_status_out'))}: ${num(status.out_of_stock)}</span>
</div></div>
<div class="section"><div class="section-title">${esc(t('report_ledger_summary'))}</div>
<div class="ledger-mini">
<div><span>${esc(t('stat_total_in'))}</span><strong>${num(ledger.total_in)}</strong></div>
<div><span>${esc(t('stat_total_out'))}</span><strong>${num(ledger.total_out)}</strong></div>
<div><span>${esc(t('ledger_entries').replace('%s', ''))}</span><strong>${num(ledger.entries)}</strong></div>
</div></div>
<div class="section"><div class="section-title">${esc(t('report_category_breakdown'))}</div>
${renderTable([t('col_category'), t('stat_total_products'), t('stock'), t('col_cost_value'), t('col_retail_value')], catRows, [1, 2, 3, 4])}</div>
<div class="section"><div class="section-title">${esc(t('report_top_moving'))}</div>
${topRows.length ? renderTable([t('col_product'), t('col_sku'), t('col_qty_sold'), t('col_revenue')], topRows, [2, 3]) : `<p class="empty">${esc(t('no_top_moving'))}</p>`}</div>
<div class="section page-break"><div class="section-title">${esc(t('report_low_stock'))}</div>
${alertRows.length ? renderTable([t('col_product'), t('col_sku'), t('col_category'), t('stock'), t('col_min_stock'), t('col_cost_value')], alertRows, [3, 4, 5]) : `<p class="empty">${esc(t('no_low_stock'))}</p>`}</div>
<div class="section"><div class="section-title">${esc(t('report_valuation'))}</div>
${valRows.length ? renderTable([t('col_product'), t('col_sku'), t('col_category'), t('stock'), t('col_cost_value'), t('col_retail_value')], valRows, [3, 4, 5]) : `<p class="empty">${esc(t('no_report_data'))}</p>`}</div>
<footer class="doc-footer"><span>${esc(t('doc_confidential'))}</span><span>${esc(t('doc_footer'))} · ${esc(meta.ref)}</span></footer>
</div></body></html>`;
    }

    function openPrintWindow(ctx, autoPrint = false) {
        const html = buildPrintHtml(ctx);
        const win = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
        if (!win) return false;
        win.document.open();
        win.document.write(html);
        win.document.close();
        if (autoPrint) setTimeout(() => { win.focus(); win.print(); }, 500);
        return true;
    }

    function pdfTableOptions(head, body, startY) {
        return {
            startY,
            head: [head],
            body,
            theme: 'grid',
            styles: { fontSize: 8, cellPadding: 2.5, lineColor: [226, 232, 240], lineWidth: 0.1 },
            headStyles: { fillColor: BRAND_COLOR, textColor: 255, fontStyle: 'bold' },
            alternateRowStyles: { fillColor: [248, 250, 252] },
            margin: { left: 14, right: 14 },
        };
    }

    async function exportPdf(ctx) {
        const ok = await ensurePdfLibs();
        if (!ok) {
            openPrintWindow(ctx, true);
            return { fallback: true };
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
        const meta = buildMeta(ctx);
        const { reportData, valuationRows, t, categoryLabel } = ctx;
        const summary = reportData?.summary || {};
        const status = reportData?.stock_status || {};
        const pageW = doc.internal.pageSize.getWidth();

        const addHeader = () => {
            doc.setFillColor(...BRAND_COLOR);
            doc.rect(0, 0, pageW, 22, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(16);
            doc.setFont('helvetica', 'bold');
            doc.text(BRAND, 14, 10);
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            doc.text(t('doc_title'), 14, 16);
            doc.setFontSize(8);
            doc.text(`${meta.ref} · ${meta.storeName}`, pageW - 14, 10, { align: 'right' });
            doc.text(meta.dateStr, pageW - 14, 16, { align: 'right' });
            doc.setTextColor(15, 23, 42);
        };

        const addFooter = (pageNum) => {
            const h = doc.internal.pageSize.getHeight();
            doc.setFontSize(7);
            doc.setTextColor(...MUTED);
            doc.text(t('doc_confidential'), 14, h - 8);
            doc.text(`${t('doc_page')} ${pageNum}`, pageW - 14, h - 8, { align: 'right' });
            doc.text(t('doc_footer'), pageW / 2, h - 8, { align: 'center' });
        };

        addHeader();
        let y = 30;
        doc.setFontSize(11);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(...BRAND_COLOR);
        doc.text(t('doc_executive_summary').toUpperCase(), 14, y);
        y += 6;

        doc.autoTable({
            startY: y,
            body: [
                [t('stat_total_products'), fmtNum(ctx, summary.total_products)],
                [t('stock'), fmtNum(ctx, summary.total_units)],
                [t('stat_cost_value'), fmtMoney(ctx, summary.cost_value)],
                [t('stat_retail_value'), fmtMoney(ctx, summary.retail_value)],
                [t('stock_status_in_stock'), fmtNum(ctx, status.in_stock)],
                [t('stock_status_low'), fmtNum(ctx, status.low_stock)],
                [t('stock_status_out'), fmtNum(ctx, status.out_of_stock)],
            ],
            theme: 'plain',
            styles: { fontSize: 9, cellPadding: 3 },
            columnStyles: { 0: { fontStyle: 'bold', cellWidth: 70 }, 1: { halign: 'right' } },
            margin: { left: 14, right: 14 },
        });
        y = doc.lastAutoTable.finalY + 8;

        const addSectionTable = (title, head, body) => {
            if (y > 250) {
                addFooter(doc.internal.getNumberOfPages());
                doc.addPage();
                addHeader();
                y = 30;
            }
            doc.setFontSize(10);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(...BRAND_COLOR);
            doc.text(title.toUpperCase(), 14, y);
            y += 4;
            doc.autoTable(pdfTableOptions(head, body, y));
            y = doc.lastAutoTable.finalY + 8;
        };

        const categories = reportData?.category_breakdown || [];
        if (categories.length) {
            const catBody = categories.map((r) => [categoryLabel(r.name), String(r.product_count ?? 0), String(r.units ?? 0), fmtMoney(ctx, r.cost_value), fmtMoney(ctx, r.retail_value)]);
            catBody.push([t('doc_total'), String(sum(categories, 'product_count')), String(sum(categories, 'units')), fmtMoney(ctx, sum(categories, 'cost_value')), fmtMoney(ctx, sum(categories, 'retail_value'))]);
            addSectionTable(t('report_category_breakdown'), [t('col_category'), t('stat_total_products'), t('stock'), t('col_cost_value'), t('col_retail_value')], catBody);
        }

        const topMoving = reportData?.top_moving || [];
        if (topMoving.length) {
            addSectionTable(t('report_top_moving'), [t('col_product'), t('col_sku'), t('col_qty_sold'), t('col_revenue')], topMoving.map((r) => [r.name, r.sku || '—', String(r.qty_sold ?? 0), fmtMoney(ctx, r.revenue)]));
        }

        const lowStock = reportData?.low_stock_products || [];
        if (lowStock.length) {
            addSectionTable(t('report_low_stock'), [t('col_product'), t('col_sku'), t('stock'), t('col_min_stock'), t('col_cost_value')], lowStock.map((r) => [r.name, r.sku || '—', String(r.stock_quantity ?? 0), String(r.min_stock_level ?? 5), fmtMoney(ctx, (parseFloat(r.cost) || 0) * (parseInt(r.stock_quantity, 10) || 0))]));
        }

        if (valuationRows.length) {
            const valBody = valuationRows.map((r) => [r.name, r.sku || '—', categoryLabel(r.category_name), String(r.stock_quantity), fmtMoney(ctx, r.cost_value), fmtMoney(ctx, r.retail_value)]);
            valBody.push([t('doc_grand_total'), '', '', String(sum(valuationRows, 'stock_quantity')), fmtMoney(ctx, sum(valuationRows, 'cost_value')), fmtMoney(ctx, sum(valuationRows, 'retail_value'))]);
            addSectionTable(t('report_valuation'), [t('col_product'), t('col_sku'), t('col_category'), t('stock'), t('col_cost_value'), t('col_retail_value')], valBody);
        }

        const totalPages = doc.internal.getNumberOfPages();
        for (let i = 1; i <= totalPages; i++) { doc.setPage(i); addFooter(i); }

        doc.save(`${BRAND}_Inventory_Report_${meta.fileDate}_${meta.periodKey}.pdf`);
        return { fallback: false };
    }

    return {
        exportFullCsv(ctx) {
            const { content, filename } = buildFullCsv(ctx);
            downloadBlob(filename, content, 'text/csv;charset=utf-8;');
        },
        exportValuationCsv(ctx) {
            const { content, filename } = buildValuationCsv(ctx);
            downloadBlob(filename, content, 'text/csv;charset=utf-8;');
        },
        exportAlertsCsv(ctx) {
            const { content, filename } = buildAlertsCsv(ctx);
            downloadBlob(filename, content, 'text/csv;charset=utf-8;');
        },
        printReport(ctx) { return openPrintWindow(ctx, true); },
        previewReport(ctx) { return openPrintWindow(ctx, false); },
        exportPdf,
    };
})();
