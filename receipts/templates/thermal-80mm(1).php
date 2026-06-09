<?php
// receipts/templates/thermal-80mm.php
// Generates HTML for an 80mm thermal receipt printer

$receiptNo = $_GET['receipt_no'] ?? 'TEST-1234';
$date = date('d/m/Y H:i:s');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt <?php echo htmlspecialchars($receiptNo); ?></title>
    <style>
        @page { margin: 0; size: 80mm 297mm; }
        body {
            font-family: 'Courier New', Courier, monospace;
            padding: 5mm;
            width: 70mm; /* Account for printer margins */
            margin: 0 auto;
            color: #000;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .bold { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { text-align: left; padding: 2px 0; font-size: 13px; }
        .border-bottom { border-bottom: 1px dashed #000; margin: 5px 0; }
        .header { margin-bottom: 10px; }
        h2 { margin: 0; font-size: 18px; text-transform: uppercase; }
        p { margin: 2px 0; font-size: 12px; }
        
        /* Barcode container */
        .barcode-container { margin: 10px 0; display: flex; justify-content: center; }
        .barcode-container svg { max-width: 100%; height: 50px; }
    </style>
    <!-- Include JsBarcode for generating barcode on the fly -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
</head>
<body>
    <div class="header text-center">
        <h2>RETAIL POS</h2>
        <p>123 Avenue du Commerce</p>
        <p>Abidjan, Côte d'Ivoire</p>
        <p>Tel: +225 01020304</p>
        <div class="border-bottom"></div>
        <p class="text-left">Reçu: <span class="bold"><?php echo htmlspecialchars($receiptNo); ?></span></p>
        <p class="text-left">Date: <?php echo $date; ?></p>
        <p class="text-left">Caisse: #01  |  Caissier: Admin</p>
        <div class="border-bottom"></div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Qte</th>
                <th>Désignation</th>
                <th class="text-right">Montant</th>
            </tr>
        </thead>
        <tbody id="items-list">
            <!-- Items injected by JS -->
        </tbody>
    </table>
    
    <div class="border-bottom"></div>
    <table style="margin: 5px 0;">
        <tr>
            <td class="bold">SOUS-TOTAL</td>
            <td class="text-right" id="subtotal">0</td>
        </tr>
        <tr>
            <td>TVA (18%)</td>
            <td class="text-right" id="tax">0</td>
        </tr>
        <tr>
            <td class="bold" style="font-size: 16px;">TOTAL</td>
            <td class="bold text-right" style="font-size: 16px;" id="total">0 FCFA</td>
        </tr>
    </table>
    <div class="border-bottom"></div>
    
    <div class="text-center" style="margin-top: 15px;">
        <p>Paiement: <span class="bold" id="payment-method">ESPÈCES</span></p>
        <p>Merci de votre visite et à très bientôt !</p>
        <p>Les articles soldés ne sont ni repris ni échangés.</p>
        
        <div class="barcode-container">
            <svg id="barcode"></svg>
        </div>
    </div>

    <script>
        // Extract data passed via window.name or URL params in a real app
        // For demonstration, we just generate the barcode
        JsBarcode("#barcode", "<?php echo htmlspecialchars($receiptNo); ?>", {
            format: "CODE128",
            width: 1.5,
            height: 40,
            displayValue: true,
            fontSize: 12,
            margin: 0
        });

        // Auto print
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
