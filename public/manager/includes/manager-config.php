<?php

/**
 * Manager module — runtime config (injected into JS as MANAGER_CONFIG).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Database/Database.php';

$storeId = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : 1;
$userId  = (int) ($_SESSION['user_id'] ?? 0);
$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? 'manager'));

$store = [
    'id'   => $storeId,
    'name' => 'RetailPOS',
    'currency' => 'FCFA',
];

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'SELECT id, name, location, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$storeId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $store = [
            'id'       => (int) $row['id'],
            'name'     => $row['name'],
            'location' => $row['location'] ?? '',
            'currency' => $row['currency'] ?? 'FCFA',
        ];
    }
} catch (Throwable $e) {
    // defaults
}

return [
    'user' => [
        'id'        => $userId,
        'name'      => $_SESSION['name'] ?? 'Manager',
        'role'      => $_SESSION['role'] ?? 'Manager',
        'role_slug' => $roleSlug,
    ],
    'store' => $store,
    'api'   => [
        'base' => '../../api/v1/index.php',
    ],
    'appUrl' => rtrim(APP_URL, '/'),
    'permissions' => [
        'can_approve_returns'   => in_array($roleSlug, ['manager', 'admin', 'super_admin'], true),
        'can_approve_voids'     => in_array($roleSlug, ['manager', 'admin', 'super_admin'], true),
        'can_manage_shifts'     => in_array($roleSlug, ['manager', 'admin', 'super_admin'], true),
        'can_view_audit'        => in_array($roleSlug, ['manager', 'admin', 'super_admin'], true),
        'can_access_admin'      => in_array($roleSlug, ['admin', 'super_admin'], true),
    ],
];
