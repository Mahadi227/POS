<?php

/**
 * Reçu thermique 80 mm — chargement BDD (id / receipt_no) ou aperçu local (hors ligne).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/Config/session.php';
requireLogin('../../public/login.php');

require_once __DIR__ . '/../../includes/Database/Database.php';

function receipt_fmt_money(float $n, string $currency = 'FCFA'): string
{
    return number_format($n, 0, ',', ' ') . ' ' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8');
}

function receipt_payment_label(?string $method, ?string $provider = null): string
{
    $labels = [
        'cash'         => 'ESPÈCES',
        'mobile_money' => 'MOBILE MONEY',
        'card'         => 'CARTE BANCAIRE',
    ];
    $base = $labels[$method] ?? strtoupper((string) $method);

    if ($method === 'mobile_money' && $provider) {
        $providers = [
            'orange_money' => 'Orange',
            'mtn_momo'     => 'MTN',
            'wave'         => 'Wave',
            'moov'         => 'Moov',
        ];
        $p = $providers[$provider] ?? $provider;
        return $base . ' — ' . $p;
    }

    return $base;
}

$localMode = isset($_GET['local']) && $_GET['local'] === '1';
$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$receiptNo = trim((string) ($_GET['receipt_no'] ?? ''));
$tendered = isset($_GET['tendered']) ? (float) $_GET['tendered'] : null;
$change = isset($_GET['change']) ? (float) $_GET['change'] : null;

$receipt = null;
$error = null;

if (!$localMode) {
    try {
        $db = Database::getInstance()->getConnection();
        $roleSlug = strtolower(str_replace(' ', '_', trim($_SESSION['role'] ?? '')));
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $storeId = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : 0;

        $sql = "SELECT s.*, u.name AS cashier_name, c.name AS customer_name,
                       st.name AS store_name, st.location AS store_location, st.currency AS store_currency,
                       st.tax_rate AS store_tax_rate, st.phone AS store_phone,
                       p.method AS payment_method, p.provider AS payment_provider, p.transaction_ref AS payment_ref
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN stores st ON s.store_id = st.id
                LEFT JOIN payments p ON p.sale_id = s.id
                WHERE s.deleted_at IS NULL";

        $params = [];

        if ($saleId > 0) {
            $sql .= ' AND s.id = ?';
            $params[] = $saleId;
        } elseif ($receiptNo !== '') {
            $sql .= ' AND s.receipt_no = ?';
            $params[] = $receiptNo;
        } else {
            $error = 'Identifiant de vente manquant.';
        }

        if (!$error) {
            $stmt = $db->prepare($sql . ' LIMIT 1');
            $stmt->execute($params);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                $error = 'Vente introuvable.';
            } else {
                if ($roleSlug === 'cashier') {
                    if ((int) $sale['user_id'] !== $userId) {
                        $error = 'Accès refusé à ce ticket.';
                    } elseif ($storeId && (int) $sale['store_id'] !== $storeId) {
                        $error = 'Accès refusé à ce ticket.';
                    }
                } elseif (in_array($roleSlug, ['admin', 'manager', 'staff'], true) && $storeId) {
                    if ((int) $sale['store_id'] !== $storeId) {
                        $error = 'Accès refusé à ce ticket.';
                    }
                }

                if (!$error) {
                    $itemsStmt = $db->prepare(
                        "SELECT si.quantity, si.unit_price, si.subtotal, p.name AS product_name
                         FROM sale_items si
                         JOIN products p ON si.product_id = p.id
                         WHERE si.sale_id = ?
                         ORDER BY si.id ASC"
                    );
                    $itemsStmt->execute([(int) $sale['id']]);
                    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                    $subtotal = 0.0;
                    foreach ($items as $row) {
                        $subtotal += (float) ($row['subtotal'] ?? 0);
                    }

                    $taxRate = (float) ($sale['store_tax_rate'] ?? 18);
                    if ($taxRate <= 0) {
                        $taxRate = 18;
                    }

                    $receipt = [
                        'receipt_no'      => $sale['receipt_no'],
                        'date'            => $sale['created_at'] ?? date('Y-m-d H:i:s'),
                        'store_name'      => $sale['store_name'] ?? 'RetailPOS',
                        'store_location'  => $sale['store_location'] ?? '',
                        'store_phone'     => $sale['store_phone'] ?? '',
                        'cashier_name'    => $sale['cashier_name'] ?? 'Caissier',
                        'customer_name'   => $sale['customer_name'] ?? null,
                        'items'           => $items,
                        'subtotal'        => $subtotal,
                        'tax'             => (float) ($sale['tax'] ?? 0),
                        'discount'        => (float) ($sale['discount'] ?? 0),
                        'total'           => (float) ($sale['total'] ?? 0),
                        'tax_percent'     => $taxRate,
                        'payment_method'  => $sale['payment_method'] ?? 'cash',
                        'payment_provider' => $sale['payment_provider'] ?? null,
                        'payment_ref'     => $sale['payment_ref'] ?? null,
                        'tendered'        => $tendered,
                        'change'          => $change,
                        'currency'        => $sale['store_currency'] ?? 'FCFA',
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        $error = 'Erreur lors du chargement du reçu.';
    }
}

$dateFormatted = $receipt
    ? date('d/m/Y H:i:s', strtotime((string) $receipt['date']))
    : date('d/m/Y H:i:s');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <title>Reçu<?php echo $receipt ? ' — ' . htmlspecialchars($receipt['receipt_no'], ENT_QUOTES, 'UTF-8') : ''; ?>
    </title>
    <style>
    @page {
        margin: 0;
        size: 80mm auto;
    }

    body {
        font-family: 'Courier New', Courier, monospace;
        padding: 4mm;
        width: 72mm;
        margin: 0 auto;
        color: #000;
        font-size: 12px;
    }

    .text-center {
        text-align: center;
    }

    .text-right {
        text-align: right;
    }

    .bold {
        font-weight: bold;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin: 8px 0;
    }

    th,
    td {
        padding: 2px 0;
        font-size: 11px;
        vertical-align: top;
    }

    th {
        border-bottom: 1px solid #000;
    }

    .border-dash {
        border-top: 1px dashed #000;
        margin: 6px 0;
    }

    h2 {
        margin: 0;
        font-size: 16px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    p {
        margin: 2px 0;
    }

    .item-name {
        max-width: 38mm;
        word-break: break-word;
    }

    .totals td {
        padding: 3px 0;
    }

    .totals .grand td {
        font-size: 14px;
        font-weight: bold;
        padding-top: 6px;
        border-top: 1px solid #000;
    }

    .barcode-wrap {
        margin-top: 12px;
        display: flex;
        justify-content: center;
    }

    .barcode-wrap svg {
        max-width: 100%;
        height: 48px;
    }

    .err {
        color: #b91c1c;
        text-align: center;
        padding: 20px 0;
    }

    .meta-row {
        display: flex;
        justify-content: space-between;
        gap: 4px;
    }

    @media print {
        body {
            padding: 2mm;
        }

        .no-print {
            display: none !important;
        }
    }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
</head>

<body>
    <?php if ($error && !$localMode): ?>
    <p class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="text-center no-print"><button type="button" onclick="window.close()">Fermer</button></p>
    <?php elseif ($receipt): ?>
    <div class="text-center">
        <h2><?php echo htmlspecialchars($receipt['store_name'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <?php if ($receipt['store_location']): ?>
        <p><?php echo htmlspecialchars($receipt['store_location'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($receipt['store_phone'])): ?>
        <p><?php echo htmlspecialchars($receipt['store_phone'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
    <div class="border-dash"></div>
    <p class="meta-row"><span>Reçu</span><span
            class="bold"><?php echo htmlspecialchars($receipt['receipt_no'], ENT_QUOTES, 'UTF-8'); ?></span></p>
    <p class="meta-row">
        <span>Date</span><span><?php echo htmlspecialchars($dateFormatted, ENT_QUOTES, 'UTF-8'); ?></span>
    </p>
    <p class="meta-row">
        <span>Caissier</span><span><?php echo htmlspecialchars($receipt['cashier_name'], ENT_QUOTES, 'UTF-8'); ?></span>
    </p>
    <?php if ($receipt['customer_name']): ?>
    <p class="meta-row">
        <span>Client</span><span><?php echo htmlspecialchars($receipt['customer_name'], ENT_QUOTES, 'UTF-8'); ?></span>
    </p>
    <?php endif; ?>
    <div class="border-dash"></div>

    <table>
        <thead>
            <tr>
                <th style="width:18%">Qté</th>
                <th>Article</th>
                <th class="text-right" style="width:32%">Montant</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($receipt['items'] as $item): ?>
            <tr>
                <td><?php echo (int) $item['quantity']; ?>x</td>
                <td class="item-name"><?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-right"><?php echo receipt_fmt_money((float) $item['subtotal'], $receipt['currency']); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="border-dash"></div>
    <table class="totals">
        <tr>
            <td>Sous-total</td>
            <td class="text-right"><?php echo receipt_fmt_money((float) $receipt['subtotal'], $receipt['currency']); ?>
            </td>
        </tr>
        <?php if ((float) $receipt['discount'] > 0): ?>
        <tr>
            <td>Remise</td>
            <td class="text-right">-
                <?php echo receipt_fmt_money((float) $receipt['discount'], $receipt['currency']); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>TVA (<?php echo htmlspecialchars((string) $receipt['tax_percent'], ENT_QUOTES, 'UTF-8'); ?>%)</td>
            <td class="text-right"><?php echo receipt_fmt_money((float) $receipt['tax'], $receipt['currency']); ?></td>
        </tr>
        <tr class="grand">
            <td>TOTAL</td>
            <td class="text-right"><?php echo receipt_fmt_money((float) $receipt['total'], $receipt['currency']); ?>
            </td>
        </tr>
    </table>

    <div class="border-dash"></div>
    <p class="text-center">
        Paiement : <span class="bold"><?php echo htmlspecialchars(
                                                receipt_payment_label($receipt['payment_method'], $receipt['payment_provider']),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?></span>
    </p>
    <?php if (!empty($receipt['payment_ref'])): ?>
    <p class="text-center">Réf. : <?php echo htmlspecialchars($receipt['payment_ref'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if ($receipt['payment_method'] === 'cash' && $receipt['tendered'] !== null && $receipt['tendered'] > 0): ?>
    <p class="meta-row"><span>Reçu
            client</span><span><?php echo receipt_fmt_money((float) $receipt['tendered'], $receipt['currency']); ?></span>
    </p>
    <p class="meta-row"><span>Monnaie</span><span
            class="bold"><?php echo receipt_fmt_money((float) ($receipt['change'] ?? 0), $receipt['currency']); ?></span>
    </p>
    <?php endif; ?>

    <div class="border-dash"></div>
    <p class="text-center">Merci de votre visite !</p>
    <p class="text-center" style="font-size:10px;">Articles soldés : ni repris ni échangés.</p>

    <div class="barcode-wrap">
        <svg id="barcode"></svg>
    </div>

    <script>
    JsBarcode('#barcode', <?php echo json_encode($receipt['receipt_no'], JSON_UNESCAPED_UNICODE); ?>, {
        format: 'CODE128',
        width: 1.4,
        height: 40,
        displayValue: true,
        fontSize: 11,
        margin: 0,
    });
    window.addEventListener('load', () => {
        setTimeout(() => window.print(), 400);
    });
    </script>
    <?php elseif ($localMode): ?>
    <div id="receipt-root">
        <p class="text-center">Chargement du reçu…</p>
    </div>
    <script>
    (function() {
        const payLabels = {
            cash: 'ESPÈCES',
            mobile_money: 'MOBILE MONEY',
            card: 'CARTE BANCAIRE'
        };
        const providers = {
            orange_money: 'Orange',
            mtn_momo: 'MTN',
            wave: 'Wave',
            moov: 'Moov'
        };

        let data;
        try {
            const raw = sessionStorage.getItem('pos_receipt_print');
            data = raw ? JSON.parse(raw) : null;
            sessionStorage.removeItem('pos_receipt_print');
        } catch (e) {
            data = null;
        }

        const root = document.getElementById('receipt-root');
        if (!data) {
            root.innerHTML = '<p class="err">Données de reçu indisponibles (hors ligne).</p>';
            return;
        }

        const currencySymbol = (data.store && data.store.currency) || data.currency || 'FCFA';
        const fmt = (n) => Number(n || 0).toLocaleString('fr-FR') + ' ' + currencySymbol;

        if (!data) {
            root.innerHTML = '<p class="err">Données de reçu indisponibles (hors ligne).</p>';
            return;
        }

        const pay = payLabels[data.payment_method] || (data.payment_method || '').toUpperCase();
        const payLine = data.payment_method === 'mobile_money' && data.payment_provider ?
            pay + ' — ' + (providers[data.payment_provider] || data.payment_provider) :
            pay;

        const itemsHtml = (data.items || []).map((it) => `
                <tr>
                    <td>${it.quantity}x</td>
                    <td class="item-name">${(it.product_name || '').replace(/</g, '&lt;')}</td>
                    <td class="text-right">${fmt(it.subtotal ?? it.quantity * it.unit_price)}</td>
                </tr>
            `).join('');

        const dateStr = data.created_at ?
            new Date(data.created_at).toLocaleString('fr-FR') :
            new Date().toLocaleString('fr-FR');

        root.innerHTML = `
                <div class="text-center">
                    <h2>${(data.store?.name || 'RetailPOS').replace(/</g, '&lt;')}</h2>
                    ${data.store?.location ? `<p>${String(data.store.location).replace(/</g, '&lt;')}</p>` : ''}
                    ${data.store?.phone ? `<p>${String(data.store.phone).replace(/</g, '&lt;')}</p>` : ''}
                </div>
                <div class="border-dash"></div>
                <p class="meta-row"><span>Reçu</span><span class="bold">${data.receipt_no}</span></p>
                <p class="meta-row"><span>Date</span><span>${dateStr}</span></p>
                <p class="meta-row"><span>Caissier</span><span>${(data.cashier_name || 'Caissier').replace(/</g, '&lt;')}</span></p>
                ${data.customer_name ? `<p class="meta-row"><span>Client</span><span>${String(data.customer_name).replace(/</g, '&lt;')}</span></p>` : ''}
                <div class="border-dash"></div>
                <table><thead><tr><th>Qté</th><th>Article</th><th class="text-right">Montant</th></tr></thead><tbody>${itemsHtml}</tbody></table>
                <div class="border-dash"></div>
                <table class="totals">
                    <tr><td>Sous-total</td><td class="text-right">${fmt(data.subtotal)}</td></tr>
                    ${data.discount > 0 ? `<tr><td>Remise</td><td class="text-right">- ${fmt(data.discount)}</td></tr>` : ''}
                    <tr><td>TVA</td><td class="text-right">${fmt(data.tax)}</td></tr>
                    <tr class="grand"><td>TOTAL</td><td class="text-right">${fmt(data.total)}</td></tr>
                </table>
                <div class="border-dash"></div>
                <p class="text-center">Paiement : <span class="bold">${payLine}</span></p>
                ${data.payment_ref ? `<p class="text-center">Réf. : ${String(data.payment_ref).replace(/</g, '&lt;')}</p>` : ''}
                ${data.tendered > 0 ? `<p class="meta-row"><span>Reçu client</span><span>${fmt(data.tendered)}</span></p>
                <p class="meta-row"><span>Monnaie</span><span class="bold">${fmt(data.change)}</span></p>` : ''}
                <div class="border-dash"></div>
                <p class="text-center">Merci de votre visite !</p>
                <p class="text-center" style="font-size:10px;">[HORS LIGNE — à synchroniser]</p>
                <div class="barcode-wrap"><svg id="barcode"></svg></div>
            `;

        JsBarcode('#barcode', data.receipt_no, {
            format: 'CODE128',
            width: 1.4,
            height: 40,
            displayValue: true,
            fontSize: 11,
            margin: 0,
        });
        setTimeout(() => window.print(), 400);
    })();
    </script>
    <?php else: ?>
    <p class="err">Paramètres de reçu invalides.</p>
    <?php endif; ?>
</body>

</html>