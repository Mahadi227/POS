<?php
// Example bilingual receipt template. Include languages/LanguageMiddleware.php earlier in bootstrap.
require_once __DIR__ . '\..\..\languages\helpers.php';

$items = $items ?? [['name' => 'Coca-Cola', 'qty' => 2, 'price' => 1.50], ['name' => 'Bread', 'qty' => 1, 'price' => 0.90]];
$total = 0;
?>
<div class="receipt">
    <h2><?php echo __t('receipt', 'receipt'); ?></h2>
    <table>
        <thead>
            <tr>
                <th><?php echo __t('product', 'receipt'); ?></th>
                <th><?php echo __t('quantity', 'receipt'); ?></th>
                <th><?php echo __t('price', 'receipt'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it): $total += $it['qty'] * $it['price']; ?>
                <tr>
                    <td><?php echo htmlspecialchars($it['name']); ?></td>
                    <td><?php echo $it['qty']; ?></td>
                    <td><?php echo number_format($it['price'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="total"><?php echo __t('total', 'receipt'); ?>: <?php echo number_format($total, 2); ?></div>
    <p><?php echo __t('thank_you', 'receipt'); ?></p>
</div>