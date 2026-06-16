<?php

/**
 * Thermal 80 mm receipt — DB load (id / receipt_no) or local preview (offline).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/Config/session.php';
requireLogin('../../public/login.php');

require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';
require_once __DIR__ . '/../../includes/Database/Database.php';

$activeLang = defined('ACTIVE_LANG') ? ACTIVE_LANG : ($_SESSION['lang'] ?? 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';

$receiptI18nKeys = [
    'receipt', 'product', 'quantity', 'amount', 'subtotal', 'discount', 'total',
    'tax_percent', 'thank_you_visit', 'return_policy', 'date', 'cashier', 'cashier_default',
    'customer', 'payment', 'payment_ref', 'amount_tendered', 'change',
    'pay_cash', 'pay_mobile_money', 'pay_card', 'missing_sale_id', 'sale_not_found',
    'access_denied', 'load_error', 'invalid_params', 'close', 'loading',
    'offline_unavailable', 'offline_sync_notice', 'title_receipt',
];
$receiptI18n = [];
foreach ($receiptI18nKeys as $key) {
    $receiptI18n[$key] = __t($key, 'receipt');
}

function receipt_fmt_money(float $n, string $currency = 'FCFA', string $lang = 'fr'): string
{
    $safeCurrency = htmlspecialchars($currency, ENT_QUOTES, 'UTF-8');
    if ($lang === 'en') {
        return number_format($n, 0, '.', ',') . ' ' . $safeCurrency;
    }
    return number_format($n, 0, ',', ' ') . ' ' . $safeCurrency;
}

function receipt_format_date(string $datetime, string $lang): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    if ($lang === 'fr') {
        return date('d/m/Y H:i:s', $ts);
    }
    return date('m/d/Y H:i:s', $ts);
}

function receipt_payment_label(?string $method, ?string $provider = null): string
{
    $labels = [
        'cash'         => __t('pay_cash', 'receipt'),
        'mobile_money' => __t('pay_mobile_money', 'receipt'),
        'card'         => __t('pay_card', 'receipt'),
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
            $error = __t('missing_sale_id', 'receipt');
        }

        if (!$error) {
            $stmt = $db->prepare($sql . ' LIMIT 1');
            $stmt->execute($params);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                $error = __t('sale_not_found', 'receipt');
            } else {
                if ($roleSlug === 'cashier') {
                    if ((int) $sale['user_id'] !== $userId) {
                        $error = __t('access_denied', 'receipt');
                    } elseif ($storeId && (int) $sale['store_id'] !== $storeId) {
                        $error = __t('access_denied', 'receipt');
                    }
                } elseif (in_array($roleSlug, ['admin', 'manager', 'staff'], true) && $storeId) {
                    if ((int) $sale['store_id'] !== $storeId) {
                        $error = __t('access_denied', 'receipt');
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
                        'receipt_no'       => $sale['receipt_no'],
                        'date'             => $sale['created_at'] ?? date('Y-m-d H:i:s'),
                        'store_name'       => $sale['store_name'] ?? 'RetailPOS',
                        'store_location'   => $sale['store_location'] ?? '',
                        'store_phone'      => $sale['store_phone'] ?? '',
                        'cashier_name'     => $sale['cashier_name'] ?? __t('cashier_default', 'receipt'),
                        'customer_name'    => $sale['customer_name'] ?? null,
                        'items'            => $items,
                        'subtotal'         => $subtotal,
                        'tax'              => (float) ($sale['tax'] ?? 0),
                        'discount'         => (float) ($sale['discount'] ?? 0),
                        'total'            => (float) ($sale['total'] ?? 0),
                        'tax_percent'      => $taxRate,
                        'payment_method'   => $sale['payment_method'] ?? 'cash',
                        'payment_provider' => $sale['payment_provider'] ?? null,
                        'payment_ref'      => $sale['payment_ref'] ?? null,
                        'tendered'         => $tendered,
                        'change'           => $change,
                        'currency'         => $sale['store_currency'] ?? 'FCFA',
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        $error = __t('load_error', 'receipt');
    }
}

$dateFormatted = $receipt
    ? receipt_format_date((string) $receipt['date'], $activeLang)
    : receipt_format_date(date('Y-m-d H:i:s'), $activeLang);

$pageTitle = __t('title_receipt', 'receipt');
if ($receipt) {
    $pageTitle .= ' — ' . $receipt['receipt_no'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>">

<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
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
    <p class="text-center no-print"><button type="button" onclick="window.close()"><?php echo htmlspecialchars(__t('close', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></button></p>
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
    <p class="meta-row"><span><?php echo htmlspecialchars(__t('receipt', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></span><span
            class="bold"><?php echo htmlspecialchars($receipt['receipt_no'], ENT_QUOTES, 'UTF-8'); ?></span></p>
    <p class="meta-row">
        <span><?php echo htmlspecialchars(__t('date', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo htmlspecialchars($dateFormatted, ENT_QUOTES, 'UTF-8'); ?></span>
    </p>
    <p class="meta-row">
        <span><?php echo htmlspecialchars(__t('cashier', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo htmlspecialchars($receipt['cashier_name'], ENT_QUOTES, 'UTF-8'); ?></span>
    </p>
    <?php if ($receipt['customer_name']): ?>
    <p class="meta-row">
        <span><?php echo htmlspecialchars(__t('customer', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo htmlspecialchars($receipt['customer_name'], ENT_QUOTES, 'UTF-8'); ?></span>
    </p>
    <?php endif; ?>
    <div class="border-dash"></div>

    <table>
        <thead>
            <tr>
                <th style="width:18%"><?php echo htmlspecialchars(__t('quantity', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th><?php echo htmlspecialchars(__t('product', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></th>
                <th class="text-right" style="width:32%"><?php echo htmlspecialchars(__t('amount', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($receipt['items'] as $item): ?>
            <tr>
                <td><?php echo (int) $item['quantity']; ?>x</td>
                <td class="item-name"><?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="text-right"><?php echo receipt_fmt_money((float) $item['subtotal'], $receipt['currency'], $activeLang); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="border-dash"></div>
    <table class="totals">
        <tr>
            <td><?php echo htmlspecialchars(__t('subtotal', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="text-right"><?php echo receipt_fmt_money((float) $receipt['subtotal'], $receipt['currency'], $activeLang); ?>
            </td>
        </tr>
        <?php if ((float) $receipt['discount'] > 0): ?>
        <tr>
            <td><?php echo htmlspecialchars(__t('discount', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="text-right">-
                <?php echo receipt_fmt_money((float) $receipt['discount'], $receipt['currency'], $activeLang); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td><?php echo htmlspecialchars(sprintf(__t('tax_percent', 'receipt'), (string) $receipt['tax_percent']), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="text-right"><?php echo receipt_fmt_money((float) $receipt['tax'], $receipt['currency'], $activeLang); ?></td>
        </tr>
        <tr class="grand">
            <td><?php echo htmlspecialchars(__t('total', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="text-right"><?php echo receipt_fmt_money((float) $receipt['total'], $receipt['currency'], $activeLang); ?>
            </td>
        </tr>
    </table>

    <div class="border-dash"></div>
    <p class="text-center">
        <?php echo htmlspecialchars(__t('payment', 'receipt'), ENT_QUOTES, 'UTF-8'); ?> : <span class="bold"><?php echo htmlspecialchars(
            receipt_payment_label($receipt['payment_method'], $receipt['payment_provider']),
            ENT_QUOTES,
            'UTF-8'
        ); ?></span>
    </p>
    <?php if (!empty($receipt['payment_ref'])): ?>
    <p class="text-center"><?php echo htmlspecialchars(__t('payment_ref', 'receipt'), ENT_QUOTES, 'UTF-8'); ?> : <?php echo htmlspecialchars($receipt['payment_ref'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if ($receipt['payment_method'] === 'cash' && $receipt['tendered'] !== null && $receipt['tendered'] > 0): ?>
    <p class="meta-row"><span><?php echo htmlspecialchars(__t('amount_tendered', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo receipt_fmt_money((float) $receipt['tendered'], $receipt['currency'], $activeLang); ?></span>
    </p>
    <p class="meta-row"><span><?php echo htmlspecialchars(__t('change', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></span><span
            class="bold"><?php echo receipt_fmt_money((float) ($receipt['change'] ?? 0), $receipt['currency'], $activeLang); ?></span>
    </p>
    <?php endif; ?>

    <div class="border-dash"></div>
    <p class="text-center"><?php echo htmlspecialchars(__t('thank_you_visit', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></p>
    <p class="text-center" style="font-size:10px;"><?php echo htmlspecialchars(__t('return_policy', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></p>

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
        <p class="text-center"><?php echo htmlspecialchars(__t('loading', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <script>
    window.RECEIPT_I18N = <?php echo json_encode($receiptI18n, JSON_UNESCAPED_UNICODE); ?>;
    window.RECEIPT_LOCALE = <?php echo json_encode($locale, JSON_UNESCAPED_UNICODE); ?>;
    (function() {
        const i18n = window.RECEIPT_I18N || {};
        const locale = window.RECEIPT_LOCALE || 'en-US';

        function t(key, ...args) {
            let str = i18n[key] || key;
            args.forEach((val) => {
                str = str.replace('%s', val);
            });
            return str;
        }

        const payLabels = {
            cash: t('pay_cash'),
            mobile_money: t('pay_mobile_money'),
            card: t('pay_card'),
        };
        const providers = {
            orange_money: 'Orange',
            mtn_momo: 'MTN',
            wave: 'Wave',
            moov: 'Moov',
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
            root.innerHTML = '<p class="err">' + t('offline_unavailable').replace(/</g, '&lt;') + '</p>';
            return;
        }

        const currencySymbol = (data.store && data.store.currency) || data.currency || 'FCFA';
        const fmt = (n) => Number(n || 0).toLocaleString(locale, { maximumFractionDigits: 0 }) + ' ' + currencySymbol;

        const pay = payLabels[data.payment_method] || (data.payment_method || '').toUpperCase();
        const payLine = data.payment_method === 'mobile_money' && data.payment_provider
            ? pay + ' — ' + (providers[data.payment_provider] || data.payment_provider)
            : pay;

        const itemsHtml = (data.items || []).map((it) => `
                <tr>
                    <td>${it.quantity}x</td>
                    <td class="item-name">${(it.product_name || '').replace(/</g, '&lt;')}</td>
                    <td class="text-right">${fmt(it.subtotal ?? it.quantity * it.unit_price)}</td>
                </tr>
            `).join('');

        const dateStr = data.created_at
            ? new Date(data.created_at).toLocaleString(locale)
            : new Date().toLocaleString(locale);

        const taxPercent = data.tax_percent ?? data.store?.tax_rate ?? 18;

        root.innerHTML = `
                <div class="text-center">
                    <h2>${(data.store?.name || 'RetailPOS').replace(/</g, '&lt;')}</h2>
                    ${data.store?.location ? `<p>${String(data.store.location).replace(/</g, '&lt;')}</p>` : ''}
                    ${data.store?.phone ? `<p>${String(data.store.phone).replace(/</g, '&lt;')}</p>` : ''}
                </div>
                <div class="border-dash"></div>
                <p class="meta-row"><span>${t('receipt').replace(/</g, '&lt;')}</span><span class="bold">${data.receipt_no}</span></p>
                <p class="meta-row"><span>${t('date').replace(/</g, '&lt;')}</span><span>${dateStr}</span></p>
                <p class="meta-row"><span>${t('cashier').replace(/</g, '&lt;')}</span><span>${(data.cashier_name || t('cashier_default')).replace(/</g, '&lt;')}</span></p>
                ${data.customer_name ? `<p class="meta-row"><span>${t('customer').replace(/</g, '&lt;')}</span><span>${String(data.customer_name).replace(/</g, '&lt;')}</span></p>` : ''}
                <div class="border-dash"></div>
                <table><thead><tr><th>${t('quantity').replace(/</g, '&lt;')}</th><th>${t('product').replace(/</g, '&lt;')}</th><th class="text-right">${t('amount').replace(/</g, '&lt;')}</th></tr></thead><tbody>${itemsHtml}</tbody></table>
                <div class="border-dash"></div>
                <table class="totals">
                    <tr><td>${t('subtotal').replace(/</g, '&lt;')}</td><td class="text-right">${fmt(data.subtotal)}</td></tr>
                    ${data.discount > 0 ? `<tr><td>${t('discount').replace(/</g, '&lt;')}</td><td class="text-right">- ${fmt(data.discount)}</td></tr>` : ''}
                    <tr><td>${t('tax_percent', String(taxPercent)).replace(/</g, '&lt;')}</td><td class="text-right">${fmt(data.tax)}</td></tr>
                    <tr class="grand"><td>${t('total').replace(/</g, '&lt;')}</td><td class="text-right">${fmt(data.total)}</td></tr>
                </table>
                <div class="border-dash"></div>
                <p class="text-center">${t('payment').replace(/</g, '&lt;')} : <span class="bold">${payLine.replace(/</g, '&lt;')}</span></p>
                ${data.payment_ref ? `<p class="text-center">${t('payment_ref').replace(/</g, '&lt;')} : ${String(data.payment_ref).replace(/</g, '&lt;')}</p>` : ''}
                ${data.tendered > 0 ? `<p class="meta-row"><span>${t('amount_tendered').replace(/</g, '&lt;')}</span><span>${fmt(data.tendered)}</span></p>
                <p class="meta-row"><span>${t('change').replace(/</g, '&lt;')}</span><span class="bold">${fmt(data.change)}</span></p>` : ''}
                <div class="border-dash"></div>
                <p class="text-center">${t('thank_you_visit').replace(/</g, '&lt;')}</p>
                <p class="text-center" style="font-size:10px;">${t('offline_sync_notice').replace(/</g, '&lt;')}</p>
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
    <p class="err"><?php echo htmlspecialchars(__t('invalid_params', 'receipt'), ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
</body>

</html>
