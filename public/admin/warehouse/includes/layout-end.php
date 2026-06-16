        </div>
    </main>
</div>
<script>
window.ADMIN_PAGE = <?php echo json_encode(['storeId' => $storeId, 'storeName' => $storeName, 'currency' => $storeCurrency, 'canManage' => $canManageWms], JSON_UNESCAPED_UNICODE); ?>;
window.ADMIN_CONFIG = <?php echo json_encode(['lang' => $activeLang, 'locale' => $locale, 'api' => ['base' => $apiBase]], JSON_UNESCAPED_UNICODE); ?>;
window.ADMIN_I18N = <?php echo json_encode($wmsI18n, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo $assetsBase; ?>/js/admin/admin-api.js?v=22"></script>
<script src="<?php echo $assetsBase; ?>/js/admin/store-switcher.js?v=3"></script>
<script src="<?php echo $assetsBase; ?>/js/admin/wms-offline.js?v=1"></script>
<?php foreach ($extraScripts as $js): ?>
<script src="<?php echo $assetsBase; ?>/js/admin/<?php echo htmlspecialchars($js); ?>?v=1"></script>
<?php endforeach; ?>
<script src="<?php echo $assetsBase; ?>/js/app-theme.js?v=1"></script>
<script src="<?php echo $assetsBase; ?>/js/admin/admin-sidebar.js?v=2"></script>
<script>
(function () {
    const locale = window.ADMIN_CONFIG?.locale || 'fr-FR';
    const d = document.getElementById('wmsHeaderDate');
    if (d) d.textContent = new Date().toLocaleDateString(locale, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('wmsRefreshBtn')?.addEventListener('click', () => {
        document.getElementById('wmsRefreshBtn')?.classList.add('spinning');
        document.dispatchEvent(new CustomEvent('wms:refresh'));
        setTimeout(() => document.getElementById('wmsRefreshBtn')?.classList.remove('spinning'), 700);
    });
})();
</script>
</body>
</html>
