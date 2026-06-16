<?php
// public/cashier/includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$nav = static function (string $key) {
    return function_exists('__t') ? __t($key, 'cashier') : $key;
};
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <span class="material-icons-round">point_of_sale</span>
            <h2><?php echo $nav('brand'); ?><span class="dot">.</span></h2>
        </div>
    </div>

    <ul class="nav-menu">
        <li class="nav-section"><?php echo $nav('nav_main'); ?></li>
        <li>
            <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="material-icons-round">dashboard</span>
                <span><?php echo $nav('nav_dashboard'); ?></span>
            </a>
        </li>
        <li>
            <a href="pos.php" class="nav-link <?php echo $current_page === 'pos.php' ? 'active' : ''; ?>">
                <span class="material-icons-round">shopping_cart</span>
                <span><?php echo $nav('nav_pos'); ?></span>
            </a>
        </li>

        <li class="nav-section"><?php echo $nav('nav_sales_clients'); ?></li>
        <li>
            <a href="sales_history.php" class="nav-link <?php echo in_array($current_page, ['sales_history.php', 'view_sale.php'], true) ? 'active' : ''; ?>">
                <span class="material-icons-round">receipt_long</span>
                <span><?php echo $nav('nav_sales_history'); ?></span>
            </a>
        </li>
        <li>
            <a href="returns.php" class="nav-link <?php echo $current_page === 'returns.php' ? 'active' : ''; ?>">
                <span class="material-icons-round">assignment_return</span>
                <span><?php echo $nav('nav_returns'); ?></span>
            </a>
        </li>
        <li>
            <a href="customers.php" class="nav-link <?php echo $current_page === 'customers.php' ? 'active' : ''; ?>">
                <span class="material-icons-round">people</span>
                <span><?php echo $nav('nav_customers'); ?></span>
            </a>
        </li>

        <li class="nav-section"><?php echo $nav('nav_settings'); ?></li>
        <li>
            <a href="profile.php" class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                <span class="material-icons-round">person</span>
                <span><?php echo $nav('nav_profile'); ?></span>
            </a>
        </li>
        <li>
            <a href="../logout.php" class="nav-link nav-link--logout">
                <span class="material-icons-round">logout</span>
                <span><?php echo $nav('nav_logout'); ?></span>
            </a>
        </li>
    </ul>
</aside>
