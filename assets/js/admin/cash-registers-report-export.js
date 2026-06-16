/**
 * Cash register reports — PDF export (jsPDF + autoTable)
 */
window.CashRegisterReportExport = (() => {
    const BRAND = 'RetailPOS';
    const BRAND_COLOR = [37, 99, 235];

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
            console.warn('PDF libs failed', e);
            return false;
        }
    }

    function money(amount) {
        return AdminAPI.formatCurrency(amount);
    }

    /**
     * @param {object} ctx
     * @param {string} ctx.title
     * @param {string} ctx.periodLabel
     * @param {string[]} ctx.head
     * @param {Array<Array<string|number>>} ctx.rows
     */
    async function exportPdf(ctx) {
        const ok = await ensurePdfLibs();
        if (!ok) {
            return { fallback: true };
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const fileDate = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

        doc.setFillColor(...BRAND_COLOR);
        doc.rect(0, 0, 297, 22, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(14);
        doc.text(BRAND, 14, 14);
        doc.setFontSize(10);
        doc.text(ctx.title || 'Cash Register Report', 14, 19);

        doc.setTextColor(60, 60, 60);
        doc.setFontSize(9);
        const metaY = 30;
        const storeName = window.ADMIN_PAGE?.storeName || '—';
        doc.text(`${ctx.t?.('cr_branch') || 'Branch'}: ${storeName}`, 14, metaY);
        doc.text(`${ctx.t?.('col_date') || 'Period'}: ${ctx.periodLabel || '—'}`, 14, metaY + 5);
        doc.text(`${ctx.t?.('last_updated') || 'Generated'}: ${now.toLocaleString(ctx.locale || 'fr-FR')}`, 14, metaY + 10);

        doc.autoTable({
            startY: 42,
            head: [ctx.head],
            body: ctx.rows.map((row) => row.map((c) => String(c ?? '—'))),
            styles: { fontSize: 8, cellPadding: 2 },
            headStyles: { fillColor: BRAND_COLOR, textColor: 255 },
            alternateRowStyles: { fillColor: [248, 250, 252] },
            margin: { left: 14, right: 14 },
        });

        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(120, 120, 120);
            doc.text(`${ctx.t?.('doc_page') || 'Page'} ${i} / ${pageCount}`, 270, 200, { align: 'right' });
        }

        doc.save(`RetailPOS_CashRegister_${fileDate}.pdf`);
        return { fallback: false };
    }

    function buildHistoryContext(items, dateFrom, dateTo, t, locale) {
        return {
            title: t('cr_reports_title'),
            periodLabel: `${dateFrom} — ${dateTo}`,
            t,
            locale,
            head: [
                t('col_date'),
                t('cr_col_register'),
                t('cr_branch'),
                t('cr_col_cashier'),
                t('cr_opening_balance'),
                t('cr_col_expected'),
                t('cr_counted_cash'),
                t('cr_col_difference'),
            ],
            rows: items.map((s) => [
                AdminAPI.formatDate(s.opened_at),
                s.register_name,
                s.store_name,
                s.cashier_name,
                money(s.opening_balance),
                money(s.expected_cash),
                money(s.counted_cash),
                money(s.variance),
            ]),
        };
    }

    return { exportPdf, buildHistoryContext };
})();
