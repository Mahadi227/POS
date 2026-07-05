<?php
// Additional sidebar menu items for admin pages
if (!function_exists('__t')) {
    require_once __DIR__ . '/../../../languages/LanguageMiddleware.php';
    require_once __DIR__ . '/../../../languages/helpers.php';
}
if (!isset($adminAccent)) {
    require __DIR__ . '/admin-branding.php';
}
$__role_for_sidebar = strtolower(str_replace(' ', '_', $_SESSION['role_slug'] ?? $_SESSION['role'] ?? ''));
$__saas_modules = [];
if (file_exists(__DIR__ . '/../../../includes/Helpers/EntitlementGuard.php')) {
    require_once __DIR__ . '/../../../includes/Helpers/EntitlementGuard.php';
    $__saas_modules = EntitlementGuard::modulesForCurrentTenant();
}
$__has_mod = static fn (string $m): bool => empty($__saas_modules) || !empty($__saas_modules[$m]);
?>
<li class="nav-section"><?php echo __t('nav_management', 'admin'); ?></li>
<?php if ($__has_mod('api_access') && in_array($__role_for_sidebar, ['super_admin', 'admin'], true)): ?>
<li>
    <a href="../api-keys.php" class="nav-link<?php echo ($activePage ?? '') === 'api_keys' ? ' active' : ''; ?>">
        <span class="material-icons-round">vpn_key</span>
        <span><?php echo __t('apikeys_title', 'saas'); ?></span>
    </a>
</li>
<li>
    <a href="../developers/index.php" class="nav-link<?php echo ($activePage ?? '') === 'developers' ? ' active' : ''; ?>">
        <span class="material-icons-round">code</span>
        <span><?php echo __t('dev_title', 'saas'); ?></span>
    </a>
</li>
<li>
    <a href="../webhooks.php" class="nav-link<?php echo ($activePage ?? '') === 'webhooks' ? ' active' : ''; ?>">
        <span class="material-icons-round">webhook</span>
        <span><?php echo __t('webhooks_title', 'saas'); ?></span>
    </a>
</li>
<?php endif; ?>
<?php if ($__has_mod('white_label') && in_array($__role_for_sidebar, ['super_admin', 'admin'], true)): ?>
<li>
    <a href="../branding.php" class="nav-link<?php echo ($activePage ?? '') === 'branding' ? ' active' : ''; ?>">
        <span class="material-icons-round">palette</span>
        <span><?php echo __t('branding_title', 'saas'); ?></span>
    </a>
</li>
<?php endif; ?>
<?php if (in_array($__role_for_sidebar, ['super_admin', 'admin'], true)): ?>
<li>
    <a href="../billing.php" class="nav-link<?php echo ($activePage ?? '') === 'billing' ? ' active' : ''; ?>">
        <span class="material-icons-round">credit_card</span>
        <span><?php echo __t('billing_title', 'saas'); ?></span>
    </a>
</li>
<?php endif; ?>
<?php if ($__role_for_sidebar === 'super_admin'): ?>
<li>
    <a href="stores.php" class="nav-link<?php echo ($activePage === 'stores') ? ' active' : ''; ?>">
        <span class="material-icons-round">store</span>
        <span><?php echo __t('nav_stores', 'admin'); ?></span>
    </a>
</li>
<?php endif; ?>
<?php if ($__has_mod('cash_registers')): ?>
<li>
    <a href="../cash-registers/dashboard.php" class="nav-link<?php echo ($activePage === 'cash_registers') ? ' active' : ''; ?>">
        <span class="material-icons-round">point_of_sale</span>
        <span><?php echo __t('nav_cash_registers', 'admin'); ?></span>
    </a>
</li>
<?php endif; ?>
<?php if ($__has_mod('accounting') && in_array($__role_for_sidebar, ['super_admin', 'admin', 'accountant', 'manager'], true)): ?>
<li>
    <a href="../accounting/dashboard.php" class="nav-link<?php echo ($activePage === 'accounting') ? ' active' : ''; ?>">
        <span class="material-icons-round">account_balance</span>
        <span><?php echo __t('nav_accounting', 'admin'); ?></span>
    </a>
</li>
<?php endif; ?>
<?php if ($__has_mod('warehouse')): ?>
<li>
    <a href="../warehouse/dashboard.php" class="nav-link<?php echo ($activePage === 'warehouse') ? ' active' : ''; ?>">
        <span class="material-icons-round">warehouse</span>
        <span><?php echo __t('nav_warehouses', 'admin'); ?></span>
    </a>
</li>
<?php endif; ?>
<?php if ($__has_mod('ecommerce') && in_array($__role_for_sidebar, ['super_admin', 'admin', 'manager'], true)): ?>
<li>
    <a href="ecommerce/dashboard.php" class="nav-link nav-link--ecom<?php echo ($activePage ?? '') === 'ecommerce' ? ' active' : ''; ?>">
        <span class="material-icons-round">storefront</span>
        <span><?php echo __t('nav_ecommerce', 'admin'); ?></span>
    </a>
</li>
<?php if (($ecomStorefrontUrl ?? '') !== ''): ?>
<li>
    <a href="<?php echo htmlspecialchars($ecomStorefrontUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link nav-link--sub nav-link--storefront" target="_blank" rel="noopener">
        <span class="material-icons-round">open_in_new</span>
        <span><?php echo __t('ecom_open_storefront', 'admin'); ?></span>
    </a>
</li>
<?php endif; ?>
<?php endif; ?>
<?php if (in_array($__role_for_sidebar, ['super_admin', 'admin'], true)): ?>
<li>
    <a href="users.php" class="nav-link<?php echo ($activePage === 'users') ? ' active' : ''; ?>">
        <span class="material-icons-round">group</span>
        <span><?php echo __t('nav_users', 'admin'); ?></span>
    </a>
</li>
<?php endif; ?>
<li>
    <a href="analytics.php" class="nav-link<?php echo ($activePage === 'analytics') ? ' active' : ''; ?>">
        <span class="material-icons-round">analytics</span>
        <span><?php echo __t('nav_analytics', 'admin'); ?></span>
    </a>
</li>
<li>
    <a href="inventory_analytics.php" class="nav-link<?php echo ($activePage === 'inventory_analytics') ? ' active' : ''; ?>">
        <span class="material-icons-round">inventory_2</span>
        <span><?php echo __t('nav_inventory_analytics', 'admin'); ?></span>
    </a>
</li>
<li>
    <a href="../notifications/notification_center.php" class="nav-link<?php echo ($activePage === 'notifications') ? ' active' : ''; ?>">
        <span class="material-icons-round">notifications</span>
        <span><?php echo __t('notif_title', 'notifications'); ?></span>
    </a>
</li>
<li>
    <a href="sync-monitor.php" class="nav-link<?php echo ($activePage === 'sync') ? ' active' : ''; ?>">
        <span class="material-icons-round">cloud_sync</span>
        <span><?php echo __t('nav_sync', 'admin'); ?></span>
    </a>
</li>
