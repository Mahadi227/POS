        </div>
    </main>
</div>
<script>
window.WH_PAGE = <?php echo json_encode([
    'userId' => (int) ($_SESSION['user_id'] ?? 0),
    'userName' => $_SESSION['name'] ?? '',
    'role' => $roleSlug,
    'storeId' => $storeId,
    'storeName' => $storeName,
    'warehouseId' => $warehouseId,
    'warehouseName' => $warehouseName,
    'currency' => $storeCurrency,
    'isGlobalView' => $whIsGlobalView,
    'canSwitchStore' => $whCanSwitchStore,
    'canManage' => $whCanManage,
    'canReceive' => $whCanReceive,
    'canDispatch' => $whCanDispatch,
    'canInventory' => $whCanInventory,
    'canTransfer' => $whCanTransfer,
    'canReports' => $whCanReports,
    'readOnly' => $whReadOnly,
    'csrfToken' => $_SESSION['csrf_token'] ?? '',
], JSON_UNESCAPED_UNICODE); ?>;
window.WH_CONFIG = <?php echo json_encode([
    'lang' => $activeLang,
    'locale' => $locale,
    'currency' => $storeCurrency,
    'currencies' => $whCurrencyCatalog,
    'isGlobalView' => $whIsGlobalView,
    'api' => ['base' => $apiBase],
], JSON_UNESCAPED_UNICODE); ?>;
window.WH_I18N = <?php echo json_encode($whI18n, JSON_UNESCAPED_UNICODE); ?>;
window.ADMIN_I18N = window.WH_I18N;
window.ADMIN_PAGE = window.WH_PAGE;
window.ADMIN_CONFIG = window.WH_CONFIG;
window.ADMIN_PAGE.currency = <?php echo json_encode($storeCurrency, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo $assetsBase; ?>/js/admin/admin-api.js?v=25"></script>
<script src="<?php echo $assetsBase; ?>/js/warehouse/warehouse-offline.js?v=1"></script>
<?php if ($whCanSwitchStore): ?>
<script src="<?php echo $assetsBase; ?>/js/admin/store-switcher.js?v=2"></script>
<?php endif; ?>
<?php foreach ($extraAdminScripts as $js): ?>
<script src="<?php echo $assetsBase; ?>/js/admin/<?php echo htmlspecialchars($js); ?>?v=1"></script>
<?php endforeach; ?>
<?php foreach ($extraScripts as $js): ?>
<script src="<?php echo $assetsBase; ?>/js/warehouse/<?php echo htmlspecialchars($js); ?>?v=<?php
    echo match ($js) {
        'warehouse-dashboard.js' => '6',
        'warehouse-notifications.js' => '5',
        'warehouse-calendar.js' => '3',
        'warehouse-products.js' => '2',
        'warehouse-inventory.js' => '2',
        'warehouse-stock-levels.js' => '2',
        'warehouse-stock-adjustments.js' => '1',
        'warehouse-stores.js' => '1',
        'warehouse-warehouses.js' => '1',
        'warehouse-warehouse-form.js' => '1',
        'warehouse-locations.js' => '1',
        'warehouse-logs.js' => '1',
        'warehouse-sync-monitor.js' => '1',
        'warehouse-goods-receipts.js' => '5',
        'warehouse-supplier-deliveries.js' => '1',
        'warehouse-receive-scan.js' => '1',
        'warehouse-receive-stock.js' => '3',
        'warehouse-quality-inspection.js' => '1',
        'warehouse-receiving-history.js' => '1',
        'warehouse-barcode-scanner.js' => '3',
        'warehouse-stock-ledger.js' => '1',
        'warehouse-inventory-history.js' => '2',
        'warehouse-purchase-orders.js' => '1',
        'warehouse-stock-count.js' => '1',
        'warehouse-dispatch-orders.js' => '1',
        'warehouse-pick-list.js' => '1',
        'warehouse-transfer-requests.js' => '1',
        'warehouse-incoming-transfers.js' => '1',
        'warehouse-outgoing-transfers.js' => '1',
        'warehouse-transfers.js' => '1',
        'warehouse-branch-transfers.js' => '1',
        'warehouse-approve-transfers.js' => '1',
        'warehouse-transfer-history.js' => '1',
        'warehouse-batch-tracking.js' => '1',
        'warehouse-serial-numbers.js' => '1',
        'warehouse-expiry-management.js' => '1',
        'warehouse-fifo-fefo.js' => '1',
        'warehouse-inventory-report.js' => '1',
        'warehouse-stock-movement-report.js' => '1',
        'warehouse-receiving-report.js' => '1',
        'warehouse-dispatch-report.js' => '1',
        'warehouse-transfer-report.js' => '1',
        'warehouse-performance-report.js' => '1',
        'warehouse-inventory-valuation-report.js' => '1',
        'warehouse-damage-report.js' => '1',
        'warehouse-expiry-report.js' => '1',
        'warehouse-profile.js' => '3',
        'warehouse-settings.js' => '3',
        'warehouse-help.js' => '1',
        'warehouse-packing.js' => '1',
        'warehouse-shipping.js' => '1',
        'warehouse-delivery-confirmation.js' => '1',
        'warehouse-dispatch-history.js' => '1',
        'warehouse-search.js' => '1',
        'warehouse-common.js' => '6',
        'theme-settings.js' => '1',
        default => '1',
    };
?>"></script>
<?php endforeach; ?>
<script src="<?php echo $assetsBase; ?>/js/app-theme.js?v=2"></script>
<script>
(function () {
    const locale = window.WH_CONFIG?.locale || 'en-US';
    document.getElementById('whRefreshBtn')?.addEventListener('click', () => {
        document.dispatchEvent(new CustomEvent('wh:refresh'));
    });
    document.getElementById('whMenuBtn')?.addEventListener('click', () => {
        document.getElementById('whSidebar')?.classList.toggle('open');
        document.getElementById('whSidebarOverlay')?.classList.toggle('open');
    });
    document.getElementById('whSidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('whSidebar')?.classList.remove('open');
        document.getElementById('whSidebarOverlay')?.classList.remove('open');
    });
})();
</script>
</body>
</html>
