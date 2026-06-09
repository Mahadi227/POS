<?php
/**
 * Manager sidebar — supervision workflow navigation.
 * Expects: $activePage (string), $managerConfig (array), $roleSlug (string)
 */
$activePage = $activePage ?? '';
$roleSlug   = $managerConfig['user']['role_slug'] ?? 'manager';
$canAdmin   = !empty($managerConfig['permissions']['can_access_admin']);
$mgr        = $mgrPrefix ?? '';

function mgr_nav_active(string $page, string $active): string
{
    return $page === $active ? ' active' : '';
}

function mgr_nav_active_group(array $pages, string $active): string
{
    return in_array($active, $pages, true) ? ' active' : '';
}
?>
<aside class="sidebar mgr-sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <span class="material-icons-round">supervisor_account</span>
            <h2>Supervision<span class="dot">.</span></h2>
        </div>
    </div>

    <ul class="nav-menu">
        <li class="nav-section">Vue d'ensemble</li>
        <li>
            <a href="<?php echo $canAdmin ? $mgr . '../admin/index.php' : $mgr . 'index.php'; ?>"
               class="nav-link<?php echo mgr_nav_active('dashboard', $activePage); ?>">
                <span class="material-icons-round">dashboard</span>
                <span>Tableau de bord</span>
            </a>
        </li>

        <li class="nav-section">Supervision</li>
        <li>
            <a href="<?php echo $mgr; ?>supervision/live-registers.php"
               class="nav-link<?php echo mgr_nav_active('live-registers', $activePage); ?>">
                <span class="material-icons-round">sensors</span>
                <span>Caisse en direct</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $mgr; ?>supervision/shifts.php"
               class="nav-link<?php echo mgr_nav_active('shifts', $activePage); ?>">
                <span class="material-icons-round">schedule</span>
                <span>Quarts / Shifts</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $mgr; ?>supervision/team-performance.php"
               class="nav-link<?php echo mgr_nav_active('team-performance', $activePage); ?>">
                <span class="material-icons-round">groups</span>
                <span>Performance équipe</span>
            </a>
        </li>

        <li class="nav-section">Approbations</li>
        <li>
            <a href="<?php echo $mgr; ?>approvals/index.php"
               class="nav-link<?php echo mgr_nav_active_group(['approvals', 'returns', 'discounts', 'voids'], $activePage); ?>">
                <span class="material-icons-round">pending_actions</span>
                <span>File d'attente</span>
                <span class="badge warning hidden" id="sidebar-pending-approvals">0</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $mgr; ?>approvals/returns.php"
               class="nav-link<?php echo mgr_nav_active('returns', $activePage); ?>">
                <span class="material-icons-round">assignment_return</span>
                <span>Retours</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $mgr; ?>approvals/discounts.php"
               class="nav-link<?php echo mgr_nav_active('discounts', $activePage); ?>">
                <span class="material-icons-round">percent</span>
                <span>Remises</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $mgr; ?>approvals/voids.php"
               class="nav-link<?php echo mgr_nav_active('voids', $activePage); ?>">
                <span class="material-icons-round">block</span>
                <span>Annulations</span>
            </a>
        </li>

        <li class="nav-section">Opérations</li>
        <li>
            <a href="<?php echo $mgr; ?>operations/inventory-alerts.php"
               class="nav-link<?php echo mgr_nav_active('inventory-alerts', $activePage); ?>">
                <span class="material-icons-round">inventory</span>
                <span>Alertes stock</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $mgr; ?>operations/cash-reconciliation.php"
               class="nav-link<?php echo mgr_nav_active('cash-reconciliation', $activePage); ?>">
                <span class="material-icons-round">account_balance_wallet</span>
                <span>Réconciliation caisse</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $mgr; ?>operations/sales-review.php"
               class="nav-link<?php echo mgr_nav_active('sales-review', $activePage); ?>">
                <span class="material-icons-round">fact_check</span>
                <span>Revue ventes</span>
            </a>
        </li>

        <li class="nav-section">Rapports</li>
        <li>
            <a href="<?php echo $mgr; ?>reports/daily-summary.php"
               class="nav-link<?php echo mgr_nav_active('daily-summary', $activePage); ?>">
                <span class="material-icons-round">summarize</span>
                <span>Résumé journalier</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $mgr; ?>reports/audit-trail.php"
               class="nav-link<?php echo mgr_nav_active('audit-trail', $activePage); ?>">
                <span class="material-icons-round">history</span>
                <span>Journal d'audit</span>
            </a>
        </li>

        <li class="nav-section">Accès rapide</li>
        <?php if ($canAdmin): ?>
        <li>
            <a href="<?php echo $mgr; ?>../admin/index.php" class="nav-link">
                <span class="material-icons-round">admin_panel_settings</span>
                <span>Administration</span>
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="<?php echo $mgr; ?>../cashier/pos.php" class="nav-link">
                <span class="material-icons-round">point_of_sale</span>
                <span>Terminal caisse</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $mgr; ?>../logout.php" class="nav-link" style="color: var(--danger); margin-top: 8px;">
                <span class="material-icons-round">logout</span>
                <span>Déconnexion</span>
            </a>
        </li>
    </ul>

    <div class="user-profile-widget">
        <span class="avatar-initial"><?php echo htmlspecialchars($initial ?? 'M'); ?></span>
        <div class="user-info">
            <p class="name"><?php echo htmlspecialchars($managerConfig['user']['name'] ?? ''); ?></p>
            <p class="role"><?php echo htmlspecialchars($managerConfig['user']['role'] ?? ''); ?></p>
        </div>
    </div>
</aside>
