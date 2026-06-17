<?php

/**
 * Configuration dynamique POS — magasin, taxes, utilisateur (depuis la BDD).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Database/Database.php';
require_once __DIR__ . '/../../../includes/Database/CustomerSchemaMigrator.php';
require_once __DIR__ . '/../../../includes/Config/config.php';
require_once __DIR__ . '/../../../includes/Helpers/StoreScope.php';

$db = Database::getInstance()->getConnection();
CustomerSchemaMigrator::ensure($db);
$storeId = StoreScope::resolveStoreId($db);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? 'cashier'));

$store = [
    'id'       => $storeId,
    'name'     => 'RetailPOS',
    'location' => '',
    'tax_rate' => 18.0,
    'currency' => 'FCFA',
];

$customers = [];

try {
    $stmt = $db->prepare(
        'SELECT id, name, location, tax_rate, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$storeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $store = [
            'id'       => (int) $row['id'],
            'name'     => $row['name'],
            'location' => $row['location'] ?? '',
            'tax_rate' => (float) $row['tax_rate'],
            'currency' => $row['currency'] ?? 'FCFA',
        ];
    }

    [$custStoreSql, $custStoreParams] = StoreScope::sqlFilter($db, 'store_id', 'customers');
    $custStmt = $db->prepare(
        "SELECT id, name, phone, store_id FROM customers
         WHERE deleted_at IS NULL{$custStoreSql}
         ORDER BY name ASC LIMIT 200"
    );
    $custStmt->execute($custStoreParams);
    $customers = $custStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    // Valeurs par défaut si la BDD est indisponible
}

$taxPercent = $store['tax_rate'] > 0 ? (float) $store['tax_rate'] : 18.0;

$posConfig = [
    'user' => [
        'id'   => $userId,
        'name' => $_SESSION['name'] ?? 'Caissier',
        'role' => $_SESSION['role'] ?? 'Cashier',
        'role_slug' => $roleSlug,
    ],
    'store' => $store,
    'customers' => $customers,
    'settings' => [
        'tax_percent' => $taxPercent,
        'tax_rate'    => $taxPercent / 100,
        'currency'    => $store['currency'],
        'currency_symbol' => $store['currency'],
        'low_stock_threshold' => 5,
    ],
    'api' => [
        'base' => '../../api/v1/index.php',
    ],
    'receipt' => [
        'template' => '../../receipts/templates/thermal-80mm.php',
    ],
    'appUrl' => request_app_base_url(),
];
