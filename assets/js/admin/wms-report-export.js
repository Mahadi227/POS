/**
 * WMS reports — PDF export (jsPDF + autoTable)
 */
window.WmsReportExport = (() => {
    const BRAND = 'RetailPOS WMS';
    const BRAND_COLOR = [13, 148, 136];

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

    async function exportPdf(ctx) {
        const ok = await ensurePdfLibs();
        if (!ok) return { fallback: true };

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
        doc.text(ctx.title || 'WMS Report', 14, 19);

        doc.setTextColor(60, 60, 60);
        doc.setFontSize(9);
        doc.text(`${ctx.periodLabel || '—'}`, 14, 30);
        doc.text(`${ctx.generatedLabel || 'Generated'}: ${now.toLocaleString(ctx.locale || 'fr-FR')}`, 14, 35);

        doc.autoTable({
            startY: 42,
            head: [ctx.head],
            body: ctx.rows.map((row) => row.map((c) => String(c ?? '—'))),
            styles: { fontSize: 8, cellPadding: 2 },
            headStyles: { fillColor: BRAND_COLOR, textColor: 255 },
            alternateRowStyles: { fillColor: [248, 250, 252] },
            margin: { left: 14, right: 14 },
        });

        doc.save(ctx.filename || `wms-report-${fileDate}.pdf`);
        return { fallback: false };
    }

    return { exportPdf };
})();
