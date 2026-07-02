        </div>
    </main>
</div>
<script>
window.ADMIN_PAGE = <?php echo json_encode([
    'storeId' => $storeId,
    'storeName' => $storeName,
    'currency' => $storeCurrency,
    'canManage' => $canManageAccounting,
    'canApprove' => $canApproveExpenses,
], JSON_UNESCAPED_UNICODE); ?>;
window.ADMIN_CONFIG = <?php echo json_encode(['lang' => $activeLang, 'locale' => $locale, 'api' => ['base' => $apiBase], 'moduleReady' => !empty($accModuleReady)], JSON_UNESCAPED_UNICODE); ?>;
window.ACC_MODULE_READY = <?php echo !empty($accModuleReady) ? 'true' : 'false'; ?>;
window.ADMIN_I18N = <?php echo json_encode($accI18n, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo $assetsBase; ?>/js/admin/admin-api.js?v=17"></script>
<script src="<?php echo $assetsBase; ?>/js/admin/store-switcher.js?v=2"></script>
<script src="<?php echo $assetsBase; ?>/js/admin/accounting-offline.js?v=1"></script>
<?php foreach ($extraScripts as $js): ?>
<script src="<?php echo $assetsBase; ?>/js/admin/<?php echo htmlspecialchars($js); ?>?v=<?php
    echo match ($js) {
        'accounting-dashboard.js' => '4',
        'accounting-common.js' => '3',
        'accounting-expenses.js' => '1',
        'accounting-journal.js' => '1',
        'accounting-reports.js' => '1',
        'accounting-analytics.js' => '2',
        'accounting-cashflow.js' => '1',
        'accounting-balance-sheet.js' => '1',
        'accounting-profit-loss.js' => '1',
        'accounting-inventory.js' => '1',
        'accounting-payables.js' => '1',
        'accounting-receivables.js' => '1',
        'accounting-mobile-money.js' => '1',
        'accounting-banks.js' => '1',
        'accounting-cash.js' => '1',
        'accounting-chart-of-accounts.js' => '1',
        'accounting-audit-logs.js' => '1',
        'accounting-revenues.js' => '1',
        'accounting-page.js' => '1',
        default => '1',
    };
?>"></script>
<?php endforeach; ?>
<script src="<?php echo $assetsBase; ?>/js/app-theme.js?v=2"></script>
<script src="<?php echo $assetsBase; ?>/js/admin/admin-sidebar.js?v=2"></script>
<script>
(function () {
    const locale = window.ADMIN_CONFIG?.locale || 'en-US';
    const d = document.getElementById('accHeaderDate');
    if (d) d.textContent = new Date().toLocaleDateString(locale, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('accRefreshBtn')?.addEventListener('click', () => {
        document.dispatchEvent(new CustomEvent('acc:refresh'));
    });
})();
</script>
</body>
</html>
