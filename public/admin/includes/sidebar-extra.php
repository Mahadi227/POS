<?php
// Additional sidebar menu items for admin pages
if (!function_exists('__t')) {
    require_once __DIR__ . '/../../../languages/LanguageMiddleware.php';
    require_once __DIR__ . '/../../../languages/helpers.php';
}
$__role_for_sidebar = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
?>
<li class="nav-section"><?php echo __t('nav_management', 'admin'); ?></li>
<?php if ($__role_for_sidebar === 'super_admin'): ?>
<li>
    <a href="stores.php" class="nav-link<?php echo ($activePage === 'stores') ? ' active' : ''; ?>">
        <span class="material-icons-round">store</span>
        <span><?php echo __t('nav_stores', 'admin'); ?></span>
    </a>
</li>
<?php endif; ?>
<li>
    <a href="cash_registers/dashboard.php" class="nav-link<?php echo ($activePage === 'cash_registers') ? ' active' : ''; ?>">
        <span class="material-icons-round">point_of_sale</span>
        <span><?php echo __t('nav_cash_registers', 'admin'); ?></span>
    </a>
</li>
<li>
    <a href="warehouse/dashboard.php" class="nav-link<?php echo ($activePage === 'warehouse') ? ' active' : ''; ?>">
        <span class="material-icons-round">warehouse</span>
        <span><?php echo __t('nav_warehouses', 'admin'); ?></span>
    </a>
</li>
<li>
    <a href="users.php" class="nav-link<?php echo ($activePage === 'users') ? ' active' : ''; ?>">
        <span class="material-icons-round">group</span>
        <span><?php echo __t('nav_users', 'admin'); ?></span>
    </a>
</li>
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
    <a href="sync-monitor.php" class="nav-link<?php echo ($activePage === 'sync') ? ' active' : ''; ?>">
        <span class="material-icons-round">cloud_sync</span>
        <span><?php echo __t('nav_sync', 'admin'); ?></span>
    </a>
</li>
