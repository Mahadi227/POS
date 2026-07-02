<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/WarehouseSettingsRepository.php';
require_once __DIR__ . '/../WarehouseSettingsSchema.php';
require_once __DIR__ . '/../../Wms/Repositories/WarehouseRepository.php';
require_once __DIR__ . '/../../Helpers/WarehousePortalAuth.php';
require_once __DIR__ . '/../../Helpers/RbacGuard.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';
require_once __DIR__ . '/../../Helpers/CurrencyHelper.php';
require_once __DIR__ . '/../../Database/Database.php';

class WarehouseSettingsService
{
    private WarehouseSettingsRepository $repo;
    private WarehouseRepository $warehouses;

    public function __construct(?PDO $db = null)
    {
        $this->repo = new WarehouseSettingsRepository($db);
        $this->warehouses = new WarehouseRepository($db);
    }

    public function moduleReady(): bool
    {
        return WarehouseSettingsSchema::ready(Database::getInstance()->getConnection());
    }

    public function canView(int $userId, int $warehouseId): bool
    {
        if (!WarehousePortalAuth::canSettingsView()) {
            return false;
        }
        try {
            RbacGuard::assertWarehouseAccess($warehouseId);
            return true;
        } catch (Throwable) {
            return WarehousePortalAuth::canManage() && in_array(WarehousePortalAuth::roleSlug(), ['super_admin', 'admin'], true);
        }
    }

    public function canEdit(int $userId, int $warehouseId): bool
    {
        if (!WarehousePortalAuth::canSettingsEdit()) {
            return false;
        }
        $role = WarehousePortalAuth::roleSlug();
        if (in_array($role, ['super_admin', 'admin'], true)) {
            return true;
        }
        if ($role === 'warehouse_manager') {
            return (int) ($_SESSION['warehouse_id'] ?? 0) === $warehouseId;
        }
        return false;
    }

    public function defaults(): array
    {
        return [
            'general' => [
                'working_hours' => '08:00-18:00',
                'timezone' => 'UTC',
                'language' => 'en',
                'currency' => 'FCFA',
            ],
            'inventory' => [
                'default_reorder_level' => 10,
                'allow_negative_stock' => false,
                'require_adjustment_approval' => true,
                'automatic_inventory_updates' => true,
                'valuation_method' => 'fifo',
                'enable_batch_tracking' => true,
                'enable_serial_tracking' => false,
                'enable_expiry_tracking' => true,
                'automatic_low_stock_alerts' => true,
            ],
            'transfers' => [
                'require_approval' => true,
                'auto_approve_internal' => false,
                'allow_partial' => true,
                'require_notes' => false,
                'auto_generate_number' => true,
                'transfer_prefix' => 'TRF',
                'default_status' => 'pending',
            ],
            'receiving' => [
                'require_purchase_order' => true,
                'require_quality_inspection' => false,
                'require_barcode_scan' => false,
                'auto_generate_grn' => true,
                'auto_update_inventory' => true,
                'require_manager_approval' => false,
            ],
            'dispatch' => [
                'require_picking' => true,
                'require_packing' => true,
                'require_final_verification' => true,
                'generate_dispatch_note' => true,
                'generate_delivery_note' => true,
                'require_delivery_signature' => false,
            ],
            'barcode' => [
                'default_type' => 'code128',
                'auto_generate' => true,
                'barcode_prefix' => 'WH',
                'print_labels' => true,
                'print_qr_codes' => false,
            ],
            'notifications' => [
                'low_stock' => true,
                'out_of_stock' => true,
                'expired_products' => true,
                'damaged_products' => true,
                'transfer_requests' => true,
                'transfer_approved' => true,
                'receiving_completed' => true,
                'dispatch_completed' => true,
                'inventory_count_due' => true,
                'warehouse_full' => true,
                'channel_dashboard' => true,
                'channel_email' => true,
                'channel_sms' => false,
                'channel_push' => true,
                'channel_whatsapp' => false,
            ],
            'security' => [
                'require_password_critical' => true,
                'enable_audit_logs' => true,
                'enable_activity_logs' => true,
                'max_failed_attempts' => 5,
                'session_timeout_minutes' => 30,
                'ip_restrictions' => '',
            ],
            'offline' => [
                'enable_offline_mode' => true,
                'automatic_sync' => true,
                'conflict_strategy' => 'server_wins',
                'sync_frequency_minutes' => 5,
                'local_storage_limit_mb' => 50,
            ],
            'reports' => [
                'default_format' => 'pdf',
                'default_date_range' => '30d',
                'automatic_scheduled_reports' => false,
            ],
        ];
    }

    public function load(int $userId, int $warehouseId): array
    {
        if (!$this->canView($userId, $warehouseId)) {
            throw new RuntimeException('Access denied');
        }

        $warehouse = $this->warehouses->findById($warehouseId);
        if (!$warehouse) {
            throw new RuntimeException('Warehouse not found');
        }

        $stored = $this->repo->getSettingsJson($warehouseId) ?? [];
        $settings = $this->mergeDefaults($stored);
        $storeId = (int) ($warehouse['store_id'] ?? 0) ?: null;
        $currency = $settings['general']['currency'] ?? 'FCFA';
        if ($storeId) {
            $ctx = CurrencyHelper::portalContext(Database::getInstance()->getConnection(), $storeId, false);
            $currency = $ctx['currency'];
        }

        return [
            'warehouse_id' => $warehouseId,
            'warehouse' => [
                'id' => (int) $warehouse['id'],
                'name' => $warehouse['name'],
                'warehouse_code' => $warehouse['warehouse_code'],
                'warehouse_type' => $warehouse['warehouse_type'],
                'manager_id' => $warehouse['manager_id'] ? (int) $warehouse['manager_id'] : null,
                'manager_name' => $warehouse['manager_name'] ?? null,
                'phone' => $warehouse['phone'] ?? null,
                'email' => $warehouse['email'] ?? null,
                'address' => $warehouse['address'] ?? null,
                'city' => $warehouse['city'] ?? null,
                'country' => $warehouse['country'] ?? null,
                'status' => $warehouse['status'] ?? 'active',
                'store_id' => $storeId,
                'store_name' => $warehouse['store_name'] ?? null,
            ],
            'settings' => $settings,
            'general' => array_merge($settings['general'], ['currency' => $currency]),
            'managers' => $this->repo->listManagers($warehouseId),
            'permissions' => [
                'can_edit' => $this->canEdit($userId, $warehouseId),
                'can_edit_general' => $this->canEdit($userId, $warehouseId),
            ],
        ];
    }

    public function save(int $userId, int $warehouseId, array $input, ?string $userName = null): array
    {
        if (!$this->canEdit($userId, $warehouseId)) {
            throw new RuntimeException('Access denied');
        }

        $current = $this->load($userId, $warehouseId);
        $oldFlat = $this->flatten($current['settings']);
        $newSettings = $this->parseInput($input);
        $merged = $this->mergeDefaults(array_replace_recursive($current['settings'], $newSettings));
        $this->validate($merged);

        if (!empty($input['warehouse']) && is_array($input['warehouse'])) {
            $this->updateWarehouse($warehouseId, $input['warehouse']);
        }

        $this->repo->saveSettingsJson($warehouseId, $merged, $userId);
        $newFlat = $this->flatten($merged);
        $this->auditChanges($warehouseId, $userId, $userName, $oldFlat, $newFlat);

        return ['settings' => $merged];
    }

    public function reset(int $userId, int $warehouseId, ?string $section = null, ?string $userName = null): array
    {
        if (!$this->canEdit($userId, $warehouseId)) {
            throw new RuntimeException('Access denied');
        }

        $current = $this->load($userId, $warehouseId);
        $defaults = $this->defaults();
        $oldFlat = $this->flatten($current['settings']);

        if ($section && isset($defaults[$section])) {
            $merged = $current['settings'];
            $merged[$section] = $defaults[$section];
        } else {
            $merged = $defaults;
            $this->repo->deleteSettings($warehouseId);
        }

        $this->repo->saveSettingsJson($warehouseId, $merged, $userId);
        $newFlat = $this->flatten($merged);
        $this->auditChanges($warehouseId, $userId, $userName, $oldFlat, $newFlat, $section ? "reset:{$section}" : 'reset:all');

        return ['settings' => $merged];
    }

    public function validateSettings(array $input): array
    {
        $parsed = $this->parseInput($input);
        $merged = $this->mergeDefaults($parsed);
        $this->validate($merged);
        return ['valid' => true, 'settings' => $merged];
    }

    public function auditLog(int $userId, int $warehouseId, ?string $search, int $limit, int $offset): array
    {
        if (!$this->canView($userId, $warehouseId)) {
            throw new RuntimeException('Access denied');
        }
        return [
            'data' => $this->repo->listAudit($warehouseId, $search, $limit, $offset),
            'total' => $this->repo->countAudit($warehouseId, $search),
        ];
    }

    private function updateWarehouse(int $warehouseId, array $data): void
    {
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'warehouse_type' => (string) ($data['warehouse_type'] ?? 'central'),
            'manager_id' => !empty($data['manager_id']) ? (int) $data['manager_id'] : null,
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'country' => trim((string) ($data['country'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'status' => in_array($data['status'] ?? 'active', ['active', 'inactive'], true) ? $data['status'] : 'active',
            'capacity_units' => (int) ($data['capacity_units'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ];
        if ($payload['name'] === '') {
            throw new InvalidArgumentException('Warehouse name is required');
        }
        $this->warehouses->update($warehouseId, $payload);
    }

    private function parseInput(array $input): array
    {
        $out = [];
        foreach (['general', 'inventory', 'transfers', 'receiving', 'dispatch', 'barcode', 'notifications', 'security', 'offline', 'reports'] as $section) {
            if (isset($input[$section]) && is_array($input[$section])) {
                $out[$section] = $input[$section];
            }
        }
        if (isset($input['settings']) && is_array($input['settings'])) {
            $out = array_replace_recursive($out, $input['settings']);
        }
        return $out;
    }

    private function mergeDefaults(array $stored): array
    {
        return array_replace_recursive($this->defaults(), $stored);
    }

    private function validate(array $settings): void
    {
        $inv = $settings['inventory'] ?? [];
        if ((int) ($inv['default_reorder_level'] ?? 0) < 0) {
            throw new InvalidArgumentException('Reorder level must be zero or greater');
        }
        $method = $inv['valuation_method'] ?? 'fifo';
        if (!in_array($method, ['fifo', 'lifo', 'weighted_average'], true)) {
            throw new InvalidArgumentException('Invalid valuation method');
        }
        $barcode = $settings['barcode']['default_type'] ?? 'code128';
        if (!in_array($barcode, ['code128', 'ean13', 'ean8', 'upc', 'qr'], true)) {
            throw new InvalidArgumentException('Invalid barcode type');
        }
        $sec = $settings['security'] ?? [];
        if ((int) ($sec['max_failed_attempts'] ?? 5) < 3 || (int) ($sec['max_failed_attempts'] ?? 5) > 20) {
            throw new InvalidArgumentException('Max failed attempts must be between 3 and 20');
        }
        if ((int) ($sec['session_timeout_minutes'] ?? 30) < 5 || (int) ($sec['session_timeout_minutes'] ?? 30) > 480) {
            throw new InvalidArgumentException('Session timeout must be between 5 and 480 minutes');
        }
    }

    /** @return array<string, string> */
    private function flatten(array $settings, string $prefix = ''): array
    {
        $flat = [];
        foreach ($settings as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $flat = array_merge($flat, $this->flatten($value, $path));
            } else {
                $flat[$path] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            }
        }
        return $flat;
    }

    private function auditChanges(
        int $warehouseId,
        int $userId,
        ?string $userName,
        array $oldFlat,
        array $newFlat,
        ?string $bulkLabel = null
    ): void {
        if ($bulkLabel) {
            $this->repo->logChange($warehouseId, $userId, $userName, $bulkLabel, null, 'reset');
            return;
        }
        $keys = array_unique(array_merge(array_keys($oldFlat), array_keys($newFlat)));
        foreach ($keys as $key) {
            $old = $oldFlat[$key] ?? null;
            $new = $newFlat[$key] ?? null;
            if ($old !== $new) {
                $this->repo->logChange($warehouseId, $userId, $userName, $key, $old, $new);
            }
        }
    }
}
