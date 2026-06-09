<?php
// public/cashier/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <span class="material-icons-round">point_of_sale</span>
            <h2>Caisse<span class="dot">.</span></h2>
        </div>
    </div>
    
    <ul class="nav-menu">
        <li class="nav-section">Principal</li>
        <li>
            <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <span class="material-icons-round">dashboard</span>
                <span>Tableau de bord</span>
            </a>
        </li>
        <li>
            <a href="pos.php" class="nav-link <?php echo $current_page == 'pos.php' ? 'active' : ''; ?>">
                <span class="material-icons-round">shopping_cart</span>
                <span>Aller en Caisse</span>
            </a>
        </li>
        
        <li class="nav-section">Ventes & Clients</li>
        <li>
            <a href="sales_history.php" class="nav-link <?php echo ($current_page == 'sales_history.php' || $current_page == 'view_sale.php') ? 'active' : ''; ?>">
                <span class="material-icons-round">receipt_long</span>
                <span>Historique Ventes</span>
            </a>
        </li>
        <li>
            <a href="returns.php" class="nav-link <?php echo $current_page == 'returns.php' ? 'active' : ''; ?>">
                <span class="material-icons-round">assignment_return</span>
                <span>Retours & Remboursements</span>
            </a>
        </li>
        <li>
            <a href="customers.php" class="nav-link <?php echo $current_page == 'customers.php' ? 'active' : ''; ?>">
                <span class="material-icons-round">people</span>
                <span>Clients</span>
            </a>
        </li>

        <li class="nav-section">Paramètres</li>
        <li>
            <a href="profile.php" class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                <span class="material-icons-round">person</span>
                <span>Mon Profil</span>
            </a>
        </li>
        <li>
            <a href="../logout.php" class="nav-link" style="color: var(--danger);">
                <span class="material-icons-round">logout</span>
                <span>Déconnexion</span>
            </a>
        </li>
    </ul>
</aside>
