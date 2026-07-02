        </div>
    </main>
</div>
<script>
window.ADMIN_PAGE = <?php echo json_encode([
    'storeId' => $storeId,
    'storeName' => $storeName,
    'currency' => $storeCurrency,
    'canManage' => $canManageRegisters,
], JSON_UNESCAPED_UNICODE); ?>;
window.CR_PAGE = window.ADMIN_PAGE;
window.ADMIN_CONFIG = <?php echo json_encode([
    'lang' => $activeLang,
    'locale' => $locale,
    'api' => ['base' => $apiBase],
    'moduleReady' => !empty($crModuleReady),
], JSON_UNESCAPED_UNICODE); ?>;
window.CR_MODULE_READY = <?php echo !empty($crModuleReady) ? 'true' : 'false'; ?>;
window.ADMIN_I18N = <?php echo json_encode($crI18n, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo $assetsBase; ?>/js/admin/admin-api.js?v=24"></script>
<?php if ($crCanSwitchStore): ?>
<script src="<?php echo $assetsBase; ?>/js/admin/store-switcher.js?v=2"></script>
<?php endif; ?>
<script src="<?php echo $assetsBase; ?>/js/admin/cash-registers-offline.js?v=1"></script>
<?php foreach ($extraScripts as $js): ?>
<script src="<?php echo $assetsBase; ?>/js/admin/<?php echo htmlspecialchars($js); ?>?v=<?php
    echo match ($js) {
        'cash-registers-dashboard.js' => '6',
        'cash-registers-registers.js' => '4',
        'cash-registers-reconciliation.js' => '3',
        'cash-registers-analytics.js' => '3',
        'cash-registers-common.js' => '3',
        default => '2',
    };
?>"></script>
<?php endforeach; ?>
<script src="<?php echo $assetsBase; ?>/js/app-theme.js?v=2"></script>
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
    document.getElementById('crMenuBtn')?.addEventListener('click', () => {
        document.getElementById('crSidebar')?.classList.toggle('open');
        document.getElementById('crSidebarOverlay')?.classList.toggle('open');
    });
    document.getElementById('crSidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('crSidebar')?.classList.remove('open');
        document.getElementById('crSidebarOverlay')?.classList.remove('open');
    });
    if (window.CR_MODULE_READY) {
        document.getElementById('crMigrationHint')?.setAttribute('hidden', '');
    }
})();
</script>
</body>
</html>
