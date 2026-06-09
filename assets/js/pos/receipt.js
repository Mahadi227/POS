// assets/js/pos/receipt.js
// Handles sending receipt data to the hidden iframe to print

function printReceipt(receiptNo, saleData) {
    const iframe = document.getElementById('receiptFrame');
    
    // In a real app, you would pass the receiptNo to a PHP template that generates the HTML
    // Example: iframe.src = `../../receipts/templates/thermal-80mm.php?receipt_no=${receiptNo}`;
    
    // For this demonstration, we'll write the HTML directly into the iframe
    const doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`
        <html>
        <head>
            <style>
                @page { margin: 0; size: 80mm 297mm; }
                body { font-family: monospace; padding: 10mm; width: 80mm; margin: 0; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .bold { font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                th, td { text-align: left; padding: 2px 0; font-size: 12px; }
                .border-bottom { border-bottom: 1px dashed #000; }
            </style>
        </head>
        <body>
            <div class="text-center">
                <h2>RETAIL POS</h2>
                <p>123 Rue du Commerce<br>Abidjan, CI<br>Tel: +225 01020304</p>
                <p>--------------------------------</p>
                <p>Reçu: ${receiptNo}<br>Date: ${new Date().toLocaleString()}</p>
                <p>--------------------------------</p>
            </div>
            <table>
                <tr><th>Qte</th><th>Article</th><th class="text-right">Montant</th></tr>
                ${saleData.cart.map(item => `
                    <tr>
                        <td>${item.qty}x</td>
                        <td>${item.name.substring(0,15)}</td>
                        <td class="text-right">${item.price * item.qty}</td>
                    </tr>
                `).join('')}
            </table>
            <div class="text-center border-bottom"></div>
            <p class="text-right bold">TOTAL: ${saleData.totals.total} FCFA</p>
            <div class="text-center border-bottom"></div>
            <p class="text-center">Merci de votre visite !</p>
        </body>
        </html>
    `);
    doc.close();
    
    // Print after rendering
    setTimeout(() => {
        iframe.contentWindow.print();
    }, 500);
}

// Expose globally
window.Receipt = { printReceipt };
