        </div>
    </main>
</div>
<script>
window.ADMIN_PAGE = <?php echo json_encode(['storeId' => $storeId, 'storeName' => $storeName, 'currency' => $storeCurrency, 'canManage' => $canManageRegisters], JSON_UNESCAPED_UNICODE); ?>;
window.ADMIN_CONFIG = <?php echo json_encode(['lang' => $activeLang, 'locale' => $locale, 'api' => ['base' => $apiBase]], JSON_UNESCAPED_UNICODE); ?>;
window.ADMIN_I18N = <?php echo json_encode($crI18n, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo $assetsBase; ?>/js/admin/admin-api.js?v=16"></script>
<script src="<?php echo $assetsBase; ?>/js/admin/store-switcher.js?v=2"></script>
<script src="<?php echo $assetsBase; ?>/js/admin/cash-registers-offline.js?v=1"></script>
<?php foreach ($extraScripts as $js): ?>
<script src="<?php echo $assetsBase; ?>/js/admin/<?php echo htmlspecialchars($js); ?>?v=<?php
    if (in_array($js, ['cash-registers-dashboard.js', 'cash-registers-registers.js', 'cash-registers-reconciliation.js', 'cash-registers-analytics.js', 'cash-registers-movements.js', 'cash-registers-transfers.js', 'cash-registers-reports.js', 'cash-registers-shifts.js', 'cash-registers-open.js', 'cash-registers-close.js', 'cash-registers-create.js', 'cash-registers-edit.js', 'cash-registers-logs.js'], true)) {
        echo match ($js) {
            'cash-registers-dashboard.js' => '3',
            'cash-registers-logs.js' => '1',
            'cash-registers-reconciliation.js' => '2',
            'cash-registers-analytics.js' => '2',
            'cash-registers-movements.js' => '1',
            'cash-registers-transfers.js' => '1',
            'cash-registers-reports.js' => '1',
            'cash-registers-shifts.js' => '1',
            'cash-registers-registers.js' => '3',
            default => '2',
        };
    } else {
        echo '1';
    }
?>"></script>
<?php endforeach; ?>
<script src="<?php echo $assetsBase; ?>/js/app-theme.js?v=2"></script>
<script src="<?php echo $assetsBase; ?>/js/admin/admin-sidebar.js?v=2"></script>
<script>
(function () {
    const locale = window.ADMIN_CONFIG?.locale || 'en-US';
    const d = document.getElementById('crHeaderDate');
    if (d) d.textContent = new Date().toLocaleDateString(locale, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('crRefreshBtn')?.addEventListener('click', () => {
        document.getElementById('crRefreshBtn')?.classList.add('spinning');
        document.dispatchEvent(new CustomEvent('cr:refresh'));
        setTimeout(() => document.getElementById('crRefreshBtn')?.classList.remove('spinning'), 700);
    });
})();
</script>
</body>
</html>
