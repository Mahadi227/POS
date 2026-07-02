<?php
declare(strict_types=1);

require_once __DIR__ . '/../Auth/RoleRedirect.php';
require_once __DIR__ . '/../Auth/PermissionService.php';

/**
 * Warehouse portal navigation and permission matrix.
 */
class WarehousePortalAuth
{
    public static function roleSlug(): string
    {
        return RoleRedirect::slug($_SESSION['role_slug'] ?? $_SESSION['role'] ?? '');
    }

    public static function isReadOnly(): bool
    {
        return self::roleSlug() === 'warehouse_auditor';
    }

    public static function canManage(): bool
    {
        $role = self::roleSlug();
        return in_array($role, ['super_admin', 'admin', 'warehouse_manager'], true)
            || PermissionService::has('manage_warehouse');
    }

    public static function canReceive(): bool
    {
        if (self::isReadOnly()) {
            return false;
        }
        return self::canManage()
            || in_array(self::roleSlug(), ['receiving_officer', 'storekeeper'], true)
            || PermissionService::has('warehouse.receive');
    }

    public static function canDispatch(): bool
    {
        if (self::isReadOnly()) {
            return false;
        }
        return self::canManage()
            || self::roleSlug() === 'dispatch_officer'
            || PermissionService::has('warehouse.dispatch');
    }

    public static function canInventory(): bool
    {
        return self::canManage()
            || in_array(self::roleSlug(), ['inventory_officer', 'storekeeper', 'receiving_officer', 'dispatch_officer', 'warehouse_auditor'], true)
            || PermissionService::hasAny(['warehouse.inventory', 'manage_inventory', 'inventory.view']);
    }

    public static function canTransfer(): bool
    {
        if (self::isReadOnly()) {
            return false;
        }
        return self::canManage()
            || in_array(self::roleSlug(), ['inventory_officer', 'storekeeper'], true)
            || PermissionService::has('approve_transfers');
    }

    public static function canReports(): bool
    {
        return self::canManage()
            || self::roleSlug() === 'warehouse_auditor'
            || in_array(self::roleSlug(), ['receiving_officer', 'dispatch_officer', 'inventory_officer'], true)
            || PermissionService::has('reports.view');
    }

    public static function canSettingsView(): bool
    {
        if (self::canManage()) {
            return true;
        }
        return in_array(self::roleSlug(), [
            'inventory_officer', 'receiving_officer', 'dispatch_officer', 'warehouse_auditor',
        ], true);
    }

    public static function canSettingsEdit(): bool
    {
        if (self::isReadOnly()) {
            return false;
        }
        return self::canManage();
    }

    public static function canModule(string $module): bool
    {
        return match ($module) {
            'dashboard', 'profile', 'notifications', 'calendar', 'help' => true,
            'settings' => self::canSettingsView(),
            'inventory' => self::canInventory(),
            'receiving' => self::canReceive(),
            'dispatch' => self::canDispatch(),
            'transfers' => self::canTransfer(),
            'batch' => self::canInventory(),
            'reports' => self::canReports(),
            'locations' => self::canManage() || self::roleSlug() === 'inventory_officer',
            'stores' => self::roleSlug() === 'super_admin',
            default => self::canManage(),
        };
    }

    /** @return list<array{id:string,label:string,icon:string,items:list<array{href:string,label:string,icon:string,module:string,id:string}>}> */
    public static function navigation(): array
    {
        $sections = [
            [
                'id' => 'main',
                'label' => 'wh_section_main',
                'icon' => 'home',
                'items' => [
                    ['id' => 'dashboard', 'href' => 'dashboard.php', 'label' => 'wh_nav_dashboard', 'icon' => 'dashboard', 'module' => 'dashboard'],
                    ['id' => 'notifications', 'href' => 'notifications.php', 'label' => 'wh_nav_notifications', 'icon' => 'notifications', 'module' => 'dashboard'],
                    ['id' => 'calendar', 'href' => 'calendar.php', 'label' => 'wh_nav_calendar', 'icon' => 'calendar_month', 'module' => 'dashboard'],
                ],
            ],
            [
                'id' => 'inventory',
                'label' => 'wh_section_inventory',
                'icon' => 'inventory_2',
                'items' => [
                    ['id' => 'products', 'href' => 'inventory/products.php', 'label' => 'wh_nav_products', 'icon' => 'category', 'module' => 'inventory'],
                    ['id' => 'warehouse_inventory', 'href' => 'inventory/warehouse_inventory.php', 'label' => 'wh_nav_warehouse_inventory', 'icon' => 'inventory', 'module' => 'inventory'],
                    ['id' => 'stock_levels', 'href' => 'inventory/stock_levels.php', 'label' => 'wh_nav_stock_levels', 'icon' => 'stacked_bar_chart', 'module' => 'inventory'],
                    ['id' => 'stock_adjustments', 'href' => 'inventory/stock_adjustments.php', 'label' => 'wh_nav_adjustments', 'icon' => 'tune', 'module' => 'inventory'],
                    ['id' => 'stock_count', 'href' => 'inventory/stock_count.php', 'label' => 'wh_nav_stock_count', 'icon' => 'fact_check', 'module' => 'inventory'],
                    ['id' => 'inventory_history', 'href' => 'inventory/inventory_history.php', 'label' => 'wh_nav_history', 'icon' => 'history', 'module' => 'inventory'],
                    ['id' => 'stock_ledger', 'href' => 'inventory/stock_ledger.php', 'label' => 'wh_nav_ledger', 'icon' => 'menu_book', 'module' => 'inventory'],
                    ['id' => 'barcode_scanner', 'href' => 'inventory/barcode_scanner.php', 'label' => 'wh_nav_scanner', 'icon' => 'qr_code_scanner', 'module' => 'inventory'],
                ],
            ],
            [
                'id' => 'receiving',
                'label' => 'wh_section_receiving',
                'icon' => 'move_to_inbox',
                'items' => [
                    ['id' => 'purchase_orders', 'href' => 'receiving/purchase_orders.php', 'label' => 'wh_nav_purchase_orders', 'icon' => 'shopping_cart', 'module' => 'receiving'],
                    ['id' => 'supplier_deliveries', 'href' => 'receiving/supplier_deliveries.php', 'label' => 'wh_nav_deliveries', 'icon' => 'local_shipping', 'module' => 'receiving'],
                    ['id' => 'goods_receipts', 'href' => 'receiving/goods_receipts.php', 'label' => 'wh_nav_goods_receipts', 'icon' => 'inbox', 'module' => 'receiving'],
                    ['id' => 'receive_stock', 'href' => 'receiving/receive_stock.php', 'label' => 'wh_nav_receive_stock', 'icon' => 'add_box', 'module' => 'receiving'],
                    ['id' => 'quality_inspection', 'href' => 'receiving/quality_inspection.php', 'label' => 'wh_nav_inspection', 'icon' => 'verified', 'module' => 'receiving'],
                    ['id' => 'receiving_history', 'href' => 'receiving/receiving_history.php', 'label' => 'wh_nav_receiving_history', 'icon' => 'history', 'module' => 'receiving'],
                ],
            ],
            [
                'id' => 'dispatch',
                'label' => 'wh_section_dispatch',
                'icon' => 'local_shipping',
                'items' => [
                    ['id' => 'dispatch_orders', 'href' => 'dispatch/dispatch_orders.php', 'label' => 'wh_nav_dispatch_orders', 'icon' => 'outbound', 'module' => 'dispatch'],
                    ['id' => 'pick_list', 'href' => 'dispatch/pick_list.php', 'label' => 'wh_nav_pick_list', 'icon' => 'checklist', 'module' => 'dispatch'],
                    ['id' => 'packing', 'href' => 'dispatch/packing.php', 'label' => 'wh_nav_packing', 'icon' => 'inventory_2', 'module' => 'dispatch'],
                    ['id' => 'shipping', 'href' => 'dispatch/shipping.php', 'label' => 'wh_nav_shipping', 'icon' => 'flight_takeoff', 'module' => 'dispatch'],
                    ['id' => 'delivery_confirmation', 'href' => 'dispatch/delivery_confirmation.php', 'label' => 'wh_nav_delivery', 'icon' => 'done_all', 'module' => 'dispatch'],
                    ['id' => 'dispatch_history', 'href' => 'dispatch/dispatch_history.php', 'label' => 'wh_nav_dispatch_history', 'icon' => 'history', 'module' => 'dispatch'],
                ],
            ],
            [
                'id' => 'transfers',
                'label' => 'wh_section_transfers',
                'icon' => 'sync_alt',
                'items' => [
                    ['id' => 'transfer_requests', 'href' => 'transfers/transfer_requests.php', 'label' => 'wh_nav_transfer_requests', 'icon' => 'assignment', 'module' => 'transfers'],
                    ['id' => 'incoming_transfers', 'href' => 'transfers/incoming_transfers.php', 'label' => 'wh_nav_incoming', 'icon' => 'call_received', 'module' => 'transfers'],
                    ['id' => 'outgoing_transfers', 'href' => 'transfers/outgoing_transfers.php', 'label' => 'wh_nav_outgoing', 'icon' => 'call_made', 'module' => 'transfers'],
                    ['id' => 'warehouse_transfer', 'href' => 'transfers/warehouse_transfer.php', 'label' => 'wh_nav_wh_transfer', 'icon' => 'warehouse', 'module' => 'transfers'],
                    ['id' => 'branch_transfer', 'href' => 'transfers/branch_transfer.php', 'label' => 'wh_nav_branch_transfer', 'icon' => 'store', 'module' => 'transfers'],
                    ['id' => 'approve_transfer', 'href' => 'transfers/approve_transfer.php', 'label' => 'wh_nav_approve_transfer', 'icon' => 'thumb_up', 'module' => 'transfers'],
                    ['id' => 'transfer_history', 'href' => 'transfers/transfer_history.php', 'label' => 'wh_nav_transfer_history', 'icon' => 'history', 'module' => 'transfers'],
                ],
            ],
            [
                'id' => 'batch',
                'label' => 'wh_section_batch',
                'icon' => 'qr_code_2',
                'items' => [
                    ['id' => 'batch_tracking', 'href' => 'batch/batch_tracking.php', 'label' => 'wh_nav_batch', 'icon' => 'qr_code', 'module' => 'batch'],
                    ['id' => 'serial_numbers', 'href' => 'batch/serial_numbers.php', 'label' => 'wh_nav_serial', 'icon' => 'pin', 'module' => 'batch'],
                    ['id' => 'expiry_management', 'href' => 'batch/expiry_management.php', 'label' => 'wh_nav_expiry', 'icon' => 'event_busy', 'module' => 'batch'],
                    ['id' => 'fifo_fefo', 'href' => 'batch/fifo_fefo.php', 'label' => 'wh_nav_fifo', 'icon' => 'sort', 'module' => 'batch'],
                ],
            ],
            [
                'id' => 'reports',
                'label' => 'wh_section_reports',
                'icon' => 'summarize',
                'items' => [
                    ['id' => 'inventory_report', 'href' => 'reports/inventory_report.php', 'label' => 'wh_nav_rpt_inventory', 'icon' => 'inventory_2', 'module' => 'reports'],
                    ['id' => 'stock_movement_report', 'href' => 'reports/stock_movement_report.php', 'label' => 'wh_nav_rpt_movements', 'icon' => 'swap_horiz', 'module' => 'reports'],
                    ['id' => 'receiving_report', 'href' => 'reports/receiving_report.php', 'label' => 'wh_nav_rpt_receiving', 'icon' => 'move_to_inbox', 'module' => 'reports'],
                    ['id' => 'dispatch_report', 'href' => 'reports/dispatch_report.php', 'label' => 'wh_nav_rpt_dispatch', 'icon' => 'local_shipping', 'module' => 'reports'],
                    ['id' => 'transfer_report', 'href' => 'reports/transfer_report.php', 'label' => 'wh_nav_rpt_transfer', 'icon' => 'sync_alt', 'module' => 'reports'],
                    ['id' => 'warehouse_performance', 'href' => 'reports/warehouse_performance.php', 'label' => 'wh_nav_rpt_performance', 'icon' => 'analytics', 'module' => 'reports'],
                    ['id' => 'inventory_valuation', 'href' => 'reports/inventory_valuation.php', 'label' => 'wh_nav_rpt_valuation', 'icon' => 'payments', 'module' => 'reports'],
                    ['id' => 'damage_report', 'href' => 'reports/damage_report.php', 'label' => 'wh_nav_rpt_damage', 'icon' => 'broken_image', 'module' => 'reports'],
                    ['id' => 'expiry_report', 'href' => 'reports/expiry_report.php', 'label' => 'wh_nav_rpt_expiry', 'icon' => 'event_busy', 'module' => 'reports'],
                ],
            ],
            [
                'id' => 'management',
                'label' => 'wh_section_management',
                'icon' => 'settings_suggest',
                'items' => [
                    ['id' => 'warehouses', 'href' => 'management/warehouses.php', 'label' => 'wh_nav_manage_warehouses', 'icon' => 'warehouse', 'module' => 'settings'],
                    ['id' => 'locations', 'href' => 'management/locations.php', 'label' => 'wh_nav_manage_locations', 'icon' => 'place', 'module' => 'locations'],
                    ['id' => 'logs', 'href' => 'management/logs.php', 'label' => 'wh_nav_audit_logs', 'icon' => 'history', 'module' => 'settings'],
                    ['id' => 'sync', 'href' => 'management/sync-monitor.php', 'label' => 'wh_nav_sync', 'icon' => 'cloud_sync', 'module' => 'settings'],
                    ['id' => 'stores_mgmt', 'href' => 'management/stores.php', 'label' => 'wh_nav_stores', 'icon' => 'storefront', 'module' => 'stores'],
                ],
            ],
            [
                'id' => 'account',
                'label' => 'wh_section_account',
                'icon' => 'person',
                'items' => [
                    ['id' => 'profile', 'href' => 'profile.php', 'label' => 'wh_nav_profile', 'icon' => 'person', 'module' => 'dashboard'],
                    ['id' => 'settings', 'href' => 'settings.php', 'label' => 'wh_nav_settings', 'icon' => 'settings', 'module' => 'settings'],
                    ['id' => 'help', 'href' => 'help.php', 'label' => 'wh_nav_help', 'icon' => 'help', 'module' => 'dashboard'],
                ],
            ],
        ];

        return array_values(array_filter(array_map(function (array $section) {
            $items = array_values(array_filter($section['items'], static fn (array $item) => self::canModule($item['module'])));
            if (!$items) {
                return null;
            }
            $section['items'] = $items;
            return $section;
        }, $sections)));
    }

    public static function assertModule(string $module): void
    {
        if (!self::canModule($module)) {
            http_response_code(403);
            echo '<!DOCTYPE html><html><head><title>403</title></head><body><h1>403 Forbidden</h1>'
                . '<p>Warehouse access denied for this module.</p><a href="dashboard.php">Dashboard</a></body></html>';
            exit;
        }
    }
}
