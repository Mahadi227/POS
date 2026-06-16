<?php
require_once __DIR__ . '/../includes/Config/config.php';
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Helpers/InventoryLedgerHelper.php';

$db = Database::getInstance()->getConnection();

echo "=== Products with logs ===\n";
$rows = $db->query("
    SELECT p.id, p.name, p.stock_quantity AS current_stock,
           COUNT(il.id) AS log_count,
           COALESCE(SUM(il.change_amount), 0) AS total_change,
           p.stock_quantity - COALESCE(SUM(il.change_amount), 0) AS implied_opening
    FROM products p
    LEFT JOIN inventory_logs il ON il.product_id = p.id
    WHERE p.deleted_at IS NULL
    GROUP BY p.id
    HAVING log_count > 0
    ORDER BY log_count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    echo sprintf(
        "Product #%d %s | current=%d logs=%d sum_change=%d implied_opening=%d\n",
        $r['id'],
        $r['name'],
        $r['current_stock'],
        $r['log_count'],
        $r['total_change'],
        $r['implied_opening']
    );

    $logs = $db->prepare('SELECT id, change_amount, reason, created_at FROM inventory_logs WHERE product_id = ? ORDER BY created_at ASC, id ASC');
    $logs->execute([$r['id']]);
    $logRows = $logs->fetchAll(PDO::FETCH_ASSOC);

    $snapshots = InventoryLedgerHelper::computeStockSnapshots($db, [(int) $r['id']]);
    foreach ($logRows as $log) {
        $key = 'log:' . $log['id'];
        $s = $snapshots[$key] ?? null;
        echo sprintf(
            "  log #%d %s change=%+d at %s => open=%s in=%s out=%s cur=%s\n",
            $log['id'],
            $log['reason'],
            $log['change_amount'],
            $log['created_at'],
            $s['opening_stock'] ?? '?',
            $s['stock_in'] ?? '?',
            $s['stock_out'] ?? '?',
            $s['current_stock'] ?? '?'
        );
    }
    echo "\n";
}

echo "=== Verify backward replay (product #17 Chargeur) ===\n";
$pid = 17;
$snap = InventoryLedgerHelper::computeStockSnapshots($db, [$pid]);
$stock = (int) $db->query("SELECT stock_quantity FROM products WHERE id=$pid")->fetchColumn();
$last = $db->query("SELECT id, change_amount FROM inventory_logs WHERE product_id=$pid ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$s = $snap['log:' . $last['id']];
echo "Product current=$stock | last log #{$last['id']} change={$last['change_amount']} => cur={$s['current_stock']} (should match product current)\n";
$ledgerCount = (int) $db->query('SELECT COUNT(*) FROM inventory_ledger')->fetchColumn();
$logCount = (int) $db->query('SELECT COUNT(*) FROM inventory_logs')->fetchColumn();
echo "ledger=$ledgerCount logs=$logCount\n";

$dupes = $db->query("
    SELECT il.id AS log_id, COUNT(l.id) AS ledger_rows
    FROM inventory_logs il
    LEFT JOIN inventory_ledger l ON l.reference_type = 'inventory_log' AND l.reference_id = CAST(il.id AS CHAR)
    GROUP BY il.id
    HAVING ledger_rows > 1
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
echo "Duplicate ledger per log: " . count($dupes) . "\n";

$missingLedger = (int) $db->query("
    SELECT COUNT(*) FROM inventory_logs il
    WHERE NOT EXISTS (
        SELECT 1 FROM inventory_ledger l
        WHERE l.reference_type = 'inventory_log' AND l.reference_id = CAST(il.id AS CHAR)
    )
")->fetchColumn();
echo "Logs without ledger row: $missingLedger\n";

$orphanLedger = (int) $db->query("
    SELECT COUNT(*) FROM inventory_ledger l
    WHERE l.reference_type = 'inventory_log'
      AND NOT EXISTS (
        SELECT 1 FROM inventory_logs il WHERE il.id = CAST(l.reference_id AS UNSIGNED)
      )
")->fetchColumn();
echo "Ledger refs missing log: $orphanLedger\n";
