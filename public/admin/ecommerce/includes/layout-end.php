        </div>
    </main>
</div>
<script>
window.ADMIN_PAGE = <?php echo json_encode([
    'tenantId' => $tenantId,
    'storeId' => $storeId,
    'storeName' => $storeName,
    'currency' => $storeCurrency,
    'canManage' => $canManageEcom,
    'storefrontUrl' => $ecomStorefrontUrl,
], JSON_UNESCAPED_UNICODE); ?>;
window.ECOM_PAGE = window.ADMIN_PAGE;
window.ADMIN_CONFIG = <?php echo json_encode([
    'lang' => $activeLang,
    'locale' => $locale,
    'api' => ['base' => $apiBase],
], JSON_UNESCAPED_UNICODE); ?>;
window.ADMIN_I18N = <?php echo json_encode($ecomI18n, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo $assetsBase; ?>/js/admin/admin-api.js?v=25"></script>
<?php if ($ecomCanSwitchStore): ?>
<script src="<?php echo $assetsBase; ?>/js/admin/store-switcher.js?v=2"></script>
<?php endif; ?>
<?php foreach ($extraScripts as $js): ?>
<script src="<?php echo $assetsBase; ?>/js/admin/<?php echo htmlspecialchars($js); ?>?v=1"></script>
<?php endforeach; ?>
<script src="<?php echo $assetsBase; ?>/js/app-theme.js?v=2"></script>
<script>
(function () {
    const locale = window.ADMIN_CONFIG?.locale || 'en-US';
    const d = document.getElementById('ecomHeaderDate');
    if (d) d.textContent = new Date().toLocaleDateString(locale, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('ecomRefreshBtn')?.addEventListener('click', () => {
        document.getElementById('ecomRefreshBtn')?.classList.add('spinning');
        document.dispatchEvent(new CustomEvent('ecom:refresh'));
        setTimeout(() => document.getElementById('ecomRefreshBtn')?.classList.remove('spinning'), 700);
    });
    document.getElementById('ecomMenuBtn')?.addEventListener('click', () => {
        document.getElementById('ecomSidebar')?.classList.toggle('open');
        document.getElementById('ecomSidebarOverlay')?.classList.toggle('open');
    });
    document.getElementById('ecomSidebarOverlay')?.addEventListener('click', () => {
        document.getElementById('ecomSidebar')?.classList.remove('open');
        document.getElementById('ecomSidebarOverlay')?.classList.remove('open');
    });
})();
</script>
</body>
</html>
