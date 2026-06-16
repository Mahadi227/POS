/**
 * Professional analytics report export — styled Excel template + CSV fallback
 */
window.AnalyticsReportExport = (() => {
    const BRAND = 'RetailPOS';
    const COLS = 6;
    const BRAND_COLOR = 'FF2563EB';
    const INK = 'FF0F172A';
    const MUTED = 'FF64748B';
    const HEADER_BG = 'FFF1F5F9';
    const TOTAL_BG = 'FFE2E8F0';
    const META_LABEL_BG = 'FFF8FAFC';
    const BORDER = 'FFCBD5E1';

    const STYLES = {
        brand: { name: 'Segoe UI', size: 20, bold: true, color: { argb: BRAND_COLOR } },
        title: { name: 'Segoe UI', size: 13, bold: true, color: { argb: INK } },
        subtitle: { name: 'Segoe UI', size: 10, color: { argb: MUTED } },
        metaLabel: { name: 'Segoe UI', size: 10, bold: true, color: { argb: MUTED } },
        metaValue: { name: 'Segoe UI', size: 10, color: { argb: INK } },
        section: { name: 'Segoe UI', size: 11, bold: true, color: { argb: 'FFFFFFFF' } },
        th: { name: 'Segoe UI', size: 10, bold: true, color: { argb: 'FF334155' } },
        td: { name: 'Segoe UI', size: 10, color: { argb: INK } },
        total: { name: 'Segoe UI', size: 10, bold: true, color: { argb: INK } },
        footer: { name: 'Segoe UI', size: 9, italic: true, color: { argb: MUTED } },
        kpiLabel: { name: 'Segoe UI', size: 9, bold: true, color: { argb: MUTED } },
        kpiValue: { name: 'Segoe UI', size: 14, bold: true, color: { argb: BRAND_COLOR } },
    };

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

    async function ensureExcelJs() {
        if (window.ExcelJS) return true;
        try {
            await loadScript('https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js');
            return !!window.ExcelJS;
        } catch (e) {
            console.warn('ExcelJS failed to load', e);
            return false;
        }
    }

    function csvSep(locale) {
        return (locale || '').startsWith('fr') ? ';' : ',';
    }

    function csvCell(value) {
        const str = String(value ?? '');
        if (/[",;\n\r]/.test(str)) return `"${str.replace(/"/g, '""')}"`;
        return str;
    }

    function csvRow(cells, sep) {
        return cells.map(csvCell).join(sep);
    }

    function fmtRaw(n, decimals = 2) {
        return Number(n || 0).toFixed(decimals);
    }

    function sum(rows, key) {
        return rows.reduce((s, r) => s + (parseFloat(r[key]) || 0), 0);
    }

    function sumArray(arr) {
        return (arr || []).reduce((s, v) => s + (parseFloat(v) || 0), 0);
    }

    function downloadBlob(filename, content, mime) {
        const blob = content instanceof Blob ? content : new Blob([content], { type: mime });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename;
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function formatRangeDate(iso, locale) {
        if (!iso) return '—';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return String(iso).slice(0, 10);
        return d.toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function buildMeta(ctx) {
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const ref = `RPT-${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`;
        const dateStr = now.toLocaleString(ctx.locale, { dateStyle: 'long', timeStyle: 'short' });
        const fileDate = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
        const d = ctx.reportData || {};

        let storeName = d.store_name || ctx.cfg.storeName || '—';
        if (!d.store_name && d.is_global) storeName = ctx.t('global_view');

        return {
            ref,
            dateStr,
            fileDate,
            storeName,
            userName: ctx.cfg.userName || '—',
            currency: ctx.cfg.currency || 'FCFA',
            period: ctx.periodLabel(d.period || ctx.periodKey || 'month'),
            periodKey: d.period || ctx.periodKey || 'month',
            from: formatRangeDate(d.from, ctx.locale),
            to: formatRangeDate(d.to, ctx.locale),
        };
    }

    function safeSlug(str) {
        return String(str || 'All')
            .replace(/[^\w\s-]/g, '')
            .trim()
            .replace(/\s+/g, '_')
            .slice(0, 32) || 'All';
    }

    function buildFilename(meta, ext) {
        return `${BRAND}_Analytics_${safeSlug(meta.storeName)}_${meta.fileDate}_${meta.periodKey}.${ext}`;
    }

    function extractReport(ctx) {
        const d = ctx.reportData || {};
        const summary = d.summary || {};
        const cust = d.customer_analytics || {};
        const inv = d.inventory_analytics || {};
        const daily = d.daily_sales || {};
        const pm = daily.payment_mix || {};
        const pmAmounts = pm.amounts || [];
        const pmTotal = sumArray(pmAmounts);

        return {
            summary,
            cust,
            inv,
            daily: {
                labels: daily.labels || [],
                revenues: daily.revenues || [],
                counts: daily.counts || [],
            },
            paymentMix: {
                labels: pm.labels || [],
                amounts: pmAmounts,
                total: pmTotal,
                shares: pmAmounts.map((amt) => (pmTotal > 0 ? `${((amt / pmTotal) * 100).toFixed(1)}%` : '0%')),
            },
            branches: d.branch_analytics?.stores || [],
            cashiers: d.cashier_performance?.cashiers || [],
            stockCounts: inv.stock_status?.counts || [],
            topMoving: inv.top_moving || [],
            loyaltyCounts: cust.loyalty_split?.counts || [],
            topCustomers: cust.top_customers || [],
        };
    }

    function cellBorder(cell) {
        const b = { style: 'thin', color: { argb: BORDER } };
        cell.border = { top: b, left: b, bottom: b, right: b };
    }

    function fillCell(cell, argb) {
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb } };
    }

    function mergeRow(ws, row, fromCol, toCol) {
        if (toCol > fromCol) ws.mergeCells(row, fromCol, row, toCol);
    }

    async function buildFullXlsx(ctx) {
        const ok = await ensureExcelJs();
        if (!ok) throw new Error('excel_lib_unavailable');

        const { t, paymentLabel } = ctx;
        const meta = buildMeta(ctx);
        const data = extractReport(ctx);
        const currency = meta.currency;

        const wb = new ExcelJS.Workbook();
        wb.creator = BRAND;
        wb.created = new Date();
        wb.company = BRAND;

        const ws = wb.addWorksheet(t('doc_title').slice(0, 31), {
            views: [{ showGridLines: false, state: 'frozen', ySplit: 0 }],
            properties: { defaultRowHeight: 18 },
        });

        ws.columns = [
            { width: 24 }, { width: 18 }, { width: 16 }, { width: 14 }, { width: 14 }, { width: 14 },
        ];

        let r = 1;

        // ── Cover header ──
        mergeRow(ws, r, 1, COLS);
        const brandCell = ws.getCell(r, 1);
        brandCell.value = BRAND;
        brandCell.font = STYLES.brand;
        brandCell.alignment = { vertical: 'middle' };
        ws.getRow(r).height = 30;
        r++;

        mergeRow(ws, r, 1, COLS);
        ws.getCell(r, 1).value = t('doc_title');
        ws.getCell(r, 1).font = STYLES.title;
        r++;

        mergeRow(ws, r, 1, COLS);
        ws.getCell(r, 1).value = t('doc_subtitle');
        ws.getCell(r, 1).font = STYLES.subtitle;
        r += 2;

        // ── Meta info grid (2 columns x 4 rows) ──
        const metaRows = [
            [t('doc_reference'), meta.ref, t('doc_store'), meta.storeName],
            [t('doc_period'), meta.period, t('doc_currency'), currency],
            [t('doc_date_range'), `${meta.from} – ${meta.to}`, t('doc_prepared_by'), meta.userName],
            [t('doc_generated_on'), meta.dateStr, '', ''],
        ];
        metaRows.forEach((row) => {
            for (let c = 0; c < 4; c += 2) {
                const labelCell = ws.getCell(r, c + 1);
                const valueCell = ws.getCell(r, c + 2);
                labelCell.value = row[c] || '';
                valueCell.value = row[c + 1] || '';
                labelCell.font = STYLES.metaLabel;
                valueCell.font = STYLES.metaValue;
                fillCell(labelCell, META_LABEL_BG);
                cellBorder(labelCell);
                cellBorder(valueCell);
            }
            ws.getRow(r).height = 20;
            r++;
        });
        r++;

        const addSection = (title) => {
            mergeRow(ws, r, 1, COLS);
            const cell = ws.getCell(r, 1);
            cell.value = String(title).toUpperCase();
            cell.font = STYLES.section;
            fillCell(cell, BRAND_COLOR);
            cell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
            ws.getRow(r).height = 24;
            r++;
        };

        const addTableHeader = (headers) => {
            headers.forEach((h, i) => {
                const cell = ws.getCell(r, i + 1);
                cell.value = h;
                cell.font = STYLES.th;
                fillCell(cell, HEADER_BG);
                cellBorder(cell);
                cell.alignment = { vertical: 'middle', horizontal: 'left', indent: 1 };
            });
            ws.getRow(r).height = 20;
            r++;
        };

        const addDataRow = (values, { bold = false, fill = null } = {}) => {
            values.forEach((v, i) => {
                const cell = ws.getCell(r, i + 1);
                cell.value = v;
                cell.font = bold ? STYLES.total : STYLES.td;
                if (fill) fillCell(cell, fill);
                cellBorder(cell);
                cell.alignment = { vertical: 'middle', horizontal: typeof v === 'number' ? 'right' : 'left', indent: 1 };
            });
            r++;
        };

        const addKpiRow = (items) => {
            items.forEach((item, i) => {
                const col = i * 2 + 1;
                mergeRow(ws, r, col, col + 1);
                const labelCell = ws.getCell(r, col);
                labelCell.value = item.label;
                labelCell.font = STYLES.kpiLabel;
                labelCell.alignment = { horizontal: 'center' };
                fillCell(labelCell, META_LABEL_BG);
                cellBorder(labelCell);

                mergeRow(ws, r + 1, col, col + 1);
                const valCell = ws.getCell(r + 1, col);
                valCell.value = item.value;
                valCell.font = STYLES.kpiValue;
                valCell.alignment = { horizontal: 'center' };
                cellBorder(valCell);
            });
            ws.getRow(r).height = 18;
            ws.getRow(r + 1).height = 26;
            r += 3;
        };

        // ── Executive summary KPIs ──
        addSection(t('doc_executive_summary'));
        addKpiRow([
            { label: t('stat_revenue'), value: `${fmtRaw(data.summary.revenue)} ${currency}` },
            { label: t('col_transactions'), value: data.summary.transactions ?? 0 },
            { label: t('stat_avg_ticket'), value: `${fmtRaw(data.summary.avg_ticket)} ${currency}` },
        ]);
        addKpiRow([
            { label: t('active_customers'), value: data.cust.active_customers ?? 0 },
            { label: t('stat_new_customers'), value: data.cust.new_customers ?? 0 },
            { label: t('stat_stock_value'), value: `${fmtRaw(data.inv.inventory_value)} ${currency}` },
        ]);

        // ── Daily sales ──
        addSection(t('report_section_daily'));
        addTableHeader([t('col_date'), `${t('col_revenue')} (${currency})`, t('col_transactions')]);
        if (data.daily.labels.length) {
            data.daily.labels.forEach((lbl, i) => {
                addDataRow([lbl, parseFloat(fmtRaw(data.daily.revenues[i])), data.daily.counts[i] ?? 0]);
            });
            addDataRow([t('doc_total'), parseFloat(fmtRaw(sumArray(data.daily.revenues))), sumArray(data.daily.counts)], { bold: true, fill: TOTAL_BG });
        } else {
            addDataRow([t('no_sales'), '', '']);
        }
        r++;

        // ── Payment mix ──
        addSection(t('report_payment_mix'));
        addTableHeader([t('col_payment_method'), `${t('col_amount')} (${currency})`, t('col_share')]);
        if (data.paymentMix.labels.length) {
            data.paymentMix.labels.forEach((method, i) => {
                addDataRow([paymentLabel(method), parseFloat(fmtRaw(data.paymentMix.amounts[i])), data.paymentMix.shares[i]]);
            });
            addDataRow([t('doc_total'), parseFloat(fmtRaw(data.paymentMix.total)), '100%'], { bold: true, fill: TOTAL_BG });
        }
        r++;

        // ── Branches ──
        addSection(t('report_section_branches'));
        addTableHeader([t('col_branch'), t('col_code'), `${t('col_revenue')} (${currency})`, t('col_transactions'), `${t('col_avg_ticket')} (${currency})`]);
        if (data.branches.length) {
            data.branches.forEach((s) => {
                addDataRow([s.name, s.code || '—', parseFloat(fmtRaw(s.revenue)), s.transactions ?? 0, parseFloat(fmtRaw(s.avg_ticket))]);
            });
            const brRev = sum(data.branches, 'revenue');
            const brTx = sum(data.branches, 'transactions');
            addDataRow([t('doc_total'), '', parseFloat(fmtRaw(brRev)), brTx, parseFloat(fmtRaw(brTx ? brRev / brTx : 0))], { bold: true, fill: TOTAL_BG });
        } else {
            addDataRow([t('no_branch_data'), '', '', '', '']);
        }
        r++;

        // ── Cashiers ──
        addSection(t('report_section_cashiers'));
        addTableHeader([t('col_rank'), t('col_cashier'), `${t('col_revenue')} (${currency})`, t('col_transactions'), `${t('col_avg_ticket')} (${currency})`]);
        if (data.cashiers.length) {
            data.cashiers.forEach((c, i) => {
                addDataRow([i + 1, c.name, parseFloat(fmtRaw(c.revenue)), c.transactions ?? 0, parseFloat(fmtRaw(c.avg_ticket))]);
            });
            const caRev = sum(data.cashiers, 'revenue');
            const caTx = sum(data.cashiers, 'transactions');
            addDataRow([t('doc_total'), '', parseFloat(fmtRaw(caRev)), caTx, parseFloat(fmtRaw(caTx ? caRev / caTx : 0))], { bold: true, fill: TOTAL_BG });
        } else {
            addDataRow([t('no_cashier_sales'), '', '', '', '']);
        }
        r++;

        // ── Inventory ──
        addSection(t('report_inventory_snapshot'));
        addTableHeader([t('stat_products'), t('stat_out_of_stock'), t('low_stock'), `${t('stat_stock_value')} (${currency})`]);
        addDataRow([data.inv.total_products ?? 0, data.inv.out_of_stock ?? 0, data.inv.low_stock ?? 0, parseFloat(fmtRaw(data.inv.inventory_value))]);
        if (data.stockCounts.length >= 3) {
            r++;
            addTableHeader([t('stock_in_stock'), t('low_stock'), t('stock_out')]);
            addDataRow([data.stockCounts[0] ?? 0, data.stockCounts[1] ?? 0, data.stockCounts[2] ?? 0]);
        }
        r++;

        addSection(t('report_top_products_section'));
        addTableHeader([t('col_product'), t('col_qty_sold'), `${t('col_revenue_generated')} (${currency})`]);
        if (data.topMoving.length) {
            data.topMoving.forEach((p) => addDataRow([p.name, p.qty_sold ?? 0, parseFloat(fmtRaw(p.revenue))]));
            addDataRow([t('doc_total'), sum(data.topMoving, 'qty_sold'), parseFloat(fmtRaw(sum(data.topMoving, 'revenue')))], { bold: true, fill: TOTAL_BG });
        } else {
            addDataRow([t('no_product_sales'), '', '']);
        }
        r++;

        // ── Customers ──
        addSection(t('report_section_customers'));
        addTableHeader([t('active_customers'), t('stat_new_customers'), t('stat_total_customers')]);
        addDataRow([data.cust.active_customers ?? 0, data.cust.new_customers ?? 0, data.cust.total_customers ?? 0]);
        if (data.loyaltyCounts.length >= 2) {
            r++;
            addTableHeader([t('customer_identified'), t('customer_anonymous')]);
            addDataRow([data.loyaltyCounts[0] ?? 0, data.loyaltyCounts[1] ?? 0]);
        }
        r++;
        addTableHeader([t('col_customer'), t('col_phone'), t('col_visits'), `${t('col_total_spent')} (${currency})`]);
        if (data.topCustomers.length) {
            data.topCustomers.forEach((c) => addDataRow([c.name, c.phone || '—', c.visits ?? 0, parseFloat(fmtRaw(c.spent))]));
            addDataRow([t('doc_total'), '', sum(data.topCustomers, 'visits'), parseFloat(fmtRaw(sum(data.topCustomers, 'spent')))], { bold: true, fill: TOTAL_BG });
        } else {
            addDataRow([t('no_identified_customers'), '', '', '']);
        }

        r += 2;
        mergeRow(ws, r, 1, COLS);
        ws.getCell(r, 1).value = t('doc_confidential');
        ws.getCell(r, 1).font = STYLES.footer;
        r++;
        mergeRow(ws, r, 1, COLS);
        ws.getCell(r, 1).value = t('doc_footer');
        ws.getCell(r, 1).font = STYLES.footer;

        const buffer = await wb.xlsx.writeBuffer();
        return {
            content: buffer,
            filename: buildFilename(meta, 'xlsx'),
            mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }

    function buildFullCsv(ctx) {
        const { reportData, t, paymentLabel } = ctx;
        const meta = buildMeta(ctx);
        const sep = csvSep(ctx.locale);
        const currency = meta.currency;
        const data = extractReport(ctx);
        const lines = [];

        const push = (...cells) => lines.push(csvRow(cells, sep));
        const blank = () => lines.push('');
        const section = (title) => {
            blank();
            push('='.repeat(72));
            push(String(title).toUpperCase());
            push('='.repeat(72));
        };

        push(BRAND, t('doc_title'), t('doc_subtitle'));
        blank();
        push(t('doc_reference'), meta.ref, t('doc_store'), meta.storeName);
        push(t('doc_period'), meta.period, t('doc_date_range'), `${meta.from} – ${meta.to}`);
        push(t('doc_currency'), currency, t('doc_prepared_by'), meta.userName);
        push(t('doc_generated_on'), meta.dateStr);
        blank();

        section(t('doc_executive_summary'));
        push(t('stat_revenue'), fmtRaw(data.summary.revenue));
        push(t('col_transactions'), data.summary.transactions ?? 0);
        push(t('stat_avg_ticket'), fmtRaw(data.summary.avg_ticket));
        push(t('active_customers'), data.cust.active_customers ?? 0);
        push(t('stat_new_customers'), data.cust.new_customers ?? 0);
        push(t('stat_stock_value'), fmtRaw(data.inv.inventory_value));

        section(t('report_section_daily'));
        push(t('col_date'), `${t('col_revenue')} (${currency})`, t('col_transactions'));
        if (data.daily.labels.length) {
            data.daily.labels.forEach((lbl, i) => push(lbl, fmtRaw(data.daily.revenues[i]), data.daily.counts[i] ?? 0));
            push(t('doc_total'), fmtRaw(sumArray(data.daily.revenues)), sumArray(data.daily.counts));
        } else push(t('no_sales'));

        section(t('report_payment_mix'));
        push(t('col_payment_method'), `${t('col_amount')} (${currency})`, t('col_share'));
        data.paymentMix.labels.forEach((method, i) => {
            push(paymentLabel(method), fmtRaw(data.paymentMix.amounts[i]), data.paymentMix.shares[i]);
        });
        if (data.paymentMix.labels.length) push(t('doc_total'), fmtRaw(data.paymentMix.total), '100%');

        section(t('report_section_branches'));
        push(t('col_branch'), t('col_code'), `${t('col_revenue')} (${currency})`, t('col_transactions'), `${t('col_avg_ticket')} (${currency})`);
        data.branches.forEach((s) => push(s.name, s.code || '—', fmtRaw(s.revenue), s.transactions ?? 0, fmtRaw(s.avg_ticket)));
        if (!data.branches.length) push(t('no_branch_data'));

        section(t('report_section_cashiers'));
        push(t('col_rank'), t('col_cashier'), `${t('col_revenue')} (${currency})`, t('col_transactions'), `${t('col_avg_ticket')} (${currency})`);
        data.cashiers.forEach((c, i) => push(i + 1, c.name, fmtRaw(c.revenue), c.transactions ?? 0, fmtRaw(c.avg_ticket)));
        if (!data.cashiers.length) push(t('no_cashier_sales'));

        section(t('report_top_products_section'));
        push(t('col_product'), t('col_qty_sold'), `${t('col_revenue_generated')} (${currency})`);
        data.topMoving.forEach((p) => push(p.name, p.qty_sold ?? 0, fmtRaw(p.revenue)));

        blank();
        push(t('doc_confidential'));
        push(t('doc_footer'));

        return {
            content: `\uFEFF${lines.join('\r\n')}`,
            filename: buildFilename(meta, 'csv'),
            mime: 'text/csv;charset=utf-8;',
        };
    }

    function buildExportCtx(ctx) {
        return {
            reportData: ctx.reportData,
            locale: ctx.locale,
            periodKey: ctx.periodKey,
            cfg: ctx.cfg,
            t: ctx.t,
            periodLabel: ctx.periodLabel,
            paymentLabel: ctx.paymentLabel,
        };
    }

    async function exportFullExcel(ctx) {
        try {
            const built = await buildFullXlsx(buildExportCtx(ctx));
            downloadBlob(built.filename, built.content, built.mime);
            return built.filename;
        } catch (e) {
            if (e.message === 'excel_lib_unavailable') {
                const built = buildFullCsv(buildExportCtx(ctx));
                downloadBlob(built.filename, built.content, built.mime);
                return built.filename;
            }
            throw e;
        }
    }

    function exportFullCsv(ctx) {
        const built = buildFullCsv(buildExportCtx(ctx));
        downloadBlob(built.filename, built.content, built.mime);
        return built.filename;
    }

    return { exportFullExcel, exportFullCsv, buildFullXlsx, buildFullCsv };
})();
