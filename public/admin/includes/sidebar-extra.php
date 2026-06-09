<?php
// Additional sidebar menu items for admin pages
// This file is included in all admin pages
?>
<li class="nav-section">Gestion</li>
<li>
</li>
<?php
// Only show 'Magasins' to super admins
$__role_for_sidebar = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if ($__role_for_sidebar === 'super_admin'): ?>
    <li>
        <a href="stores.php" class="nav-link<?php echo ($activePage === 'stores') ? ' active' : ''; ?>">
            <span class="material-icons-round">store</span>
            <span>Magasins</span>
        </a>
    </li>
<?php endif; ?>
</li>
<li>
    <a href="users.php" class="nav-link<?php echo ($activePage === 'users') ? ' active' : ''; ?>">
        <span class="material-icons-round">group</span>
        <span>Utilisateurs</span>
    </a>
</li>
<li>
    <a href="analytics.php" class="nav-link<?php echo ($activePage === 'analytics') ? ' active' : ''; ?>">
        <span class="material-icons-round">analytics</span>
        <span>Analyses & rapports</span>
    </a>
</li>
<li>
    <a href="inventory_analytics.php" class="nav-link<?php echo ($activePage === 'inventory_analytics') ? ' active' : ''; ?>">
        <span class="material-icons-round">inventory_2</span>
        <span>Analytics inventaire</span>
    </a>
</li>
<li>
    <a href="sync-monitor.php" class="nav-link<?php echo ($activePage === 'sync') ? ' active' : ''; ?>">
        <span class="material-icons-round">cloud_sync</span>
        <span>Sync Monitor</span>
    </a>
</li>