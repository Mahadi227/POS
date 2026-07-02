<?php
/**
 * Port cash register pages to public/cash-registers/ and stub admin redirects.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$adminDir = $root . '/public/admin/cash_registers';
$portalDir = $root . '/public/cash-registers';

$pages = [
    'dashboard.php',
    'registers.php',
    'reconciliation.php',
    'cash_movements.php',
    'cash_transfers.php',
    'shift_management.php',
    'reports.php',
    'analytics.php',
    'logs.php',
    'settings.php',
    'open_register.php',
    'close_register.php',
    'create_register.php',
    'edit_register.php',
    'register_details.php',
];

foreach ($pages as $file) {
    $src = $adminDir . '/' . $file;
    $dest = $portalDir . '/' . $file;
    if (!is_readable($src)) {
        echo "SKIP missing: {$file}\n";
        continue;
    }
    $content = file_get_contents($src);
    if ($content === false) {
        echo "FAIL read: {$file}\n";
        continue;
    }
    file_put_contents($dest, $content);
    echo "Ported: {$file}\n";
}

$redirectMap = [
    'dashboard.php' => '../../cash-registers/dashboard.php',
    'registers.php' => '../../cash-registers/registers.php',
    'reconciliation.php' => '../../cash-registers/reconciliation.php',
    'cash_movements.php' => '../../cash-registers/cash_movements.php',
    'cash_transfers.php' => '../../cash-registers/cash_transfers.php',
    'shift_management.php' => '../../cash-registers/shift_management.php',
    'reports.php' => '../../cash-registers/reports.php',
    'analytics.php' => '../../cash-registers/analytics.php',
    'logs.php' => '../../cash-registers/logs.php',
    'settings.php' => '../../cash-registers/settings.php',
    'open_register.php' => '../../cash-registers/open_register.php',
    'close_register.php' => '../../cash-registers/close_register.php',
    'create_register.php' => '../../cash-registers/create_register.php',
    'register_details.php' => '../../cash-registers/register_details.php',
];

foreach ($redirectMap as $file => $target) {
    if ($file === 'edit_register.php') {
        continue;
    }
    $path = $adminDir . '/' . $file;
    $content = "<?php\n// Legacy admin path — redirects to Cash Registers portal (public/cash-registers/)\nheader('Location: {$target}');\nexit;\n";
    file_put_contents($path, $content);
    echo "Redirect: {$file}\n";
}

$editRedirect = "<?php\n// Legacy admin path — redirects to Cash Registers portal\n\$id = isset(\$_GET['id']) ? '?id=' . urlencode((string) \$_GET['id']) : '';\nheader('Location: ../../cash-registers/edit_register.php' . \$id);\nexit;\n";
file_put_contents($adminDir . '/edit_register.php', $editRedirect);
echo "Redirect: edit_register.php\n";

$whDash = $adminDir . '/warehouse/dashboard.php';
file_put_contents($whDash, "<?php\nheader('Location: ../../../warehouse/dashboard.php');\nexit;\n");
echo "Redirect: warehouse/dashboard.php\n";

file_put_contents($adminDir . '/README.md', <<<'MD'
# Legacy path — use `/public/cash-registers/`

All cash register UI lives in **`public/cash-registers/`** (Caisses portal), same pattern as **`public/warehouse/`** and **`public/accounting/`**.

Files here are **HTTP redirects only** so old bookmarks keep working.

Do not add new features under `public/admin/cash_registers/`.
MD);

echo "Done.\n";
