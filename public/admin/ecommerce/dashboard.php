<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$activeEcomPage = 'dashboard';
$pageTitle = __t('ecom_dashboard_title', 'admin');
$loadChart = true;
$extraScripts = ['ecommerce-common.js', 'ecommerce-dashboard.js'];
$pageI18n = ecom_i18n([
    'ecom_dashboard_subtitle', 'ecom_kpi_online_products', 'ecom_kpi_total_products',
    'ecom_kpi_orders_today', 'ecom_kpi_revenue_today', 'ecom_kpi_orders_total',
    'ecom_kpi_revenue_total', 'ecom_kpi_accounts', 'ecom_kpi_brands', 'ecom_kpi_blog',
    'ecom_quick_products', 'ecom_quick_orders', 'ecom_quick_settings', 'ecom_chart_orders',
    'ecom_recent_orders', 'ecom_no_orders', 'col_receipt', 'col_date', 'col_amount', 'col_status',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<section class="ecom-dash-hero">
    <div class="ecom-dash-hero__intro">
        <h2 class="ecom-dash-hero__title"><?php echo __t('ecom_dashboard_subtitle', 'admin'); ?></h2>
        <p class="ecom-dash-hero__scope" id="ecomDashScope"><?php echo htmlspecialchars($storeName ?: __t('dash_all_stores', 'admin'), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="ecom-dash-hero__actions">
        <a href="products.php" class="ecom-quick-action"><span class="material-icons-round">inventory_2</span><span><?php echo __t('ecom_quick_products', 'admin'); ?></span></a>
        <a href="orders.php" class="ecom-quick-action"><span class="material-icons-round">shopping_bag</span><span><?php echo __t('ecom_quick_orders', 'admin'); ?></span></a>
        <?php if ($canManageEcom): ?>
        <a href="settings.php" class="ecom-quick-action"><span class="material-icons-round">settings</span><span><?php echo __t('ecom_quick_settings', 'admin'); ?></span></a>
        <?php endif; ?>
    </div>
</section>

<div class="ecom-kpi-grid">
    <?php foreach ([
        ['ecomKpiOnline', 'ecom_kpi_online_products', 'visibility', 'primary'],
        ['ecomKpiOrdersToday', 'ecom_kpi_orders_today', 'shopping_bag', 'success'],
        ['ecomKpiRevenueToday', 'ecom_kpi_revenue_today', 'payments', 'success'],
        ['ecomKpiOrdersTotal', 'ecom_kpi_orders_total', 'receipt_long', ''],
        ['ecomKpiRevenueTotal', 'ecom_kpi_revenue_total', 'account_balance_wallet', ''],
        ['ecomKpiAccounts', 'ecom_kpi_accounts', 'group', ''],
        ['ecomKpiBrands', 'ecom_kpi_brands', 'sell', ''],
        ['ecomKpiBlog', 'ecom_kpi_blog', 'article', ''],
    ] as [$id, $label, $icon, $mod]): ?>
    <article class="ecom-kpi ecom-kpi--<?php echo $mod ?: 'default'; ?>">
        <span class="material-icons-round ecom-kpi__icon"><?php echo $icon; ?></span>
        <span class="ecom-kpi__label"><?php echo __t($label, 'admin'); ?></span>
        <strong class="ecom-kpi__value is-loading" id="<?php echo $id; ?>">—</strong>
    </article>
    <?php endforeach; ?>
</div>

<div class="ecom-dash-grid">
    <section class="ecom-panel">
        <h3 class="ecom-panel__title"><?php echo __t('ecom_chart_orders', 'admin'); ?></h3>
        <div class="ecom-chart-wrap"><canvas id="ecomOrdersChart" height="220"></canvas></div>
    </section>
    <section class="ecom-panel">
        <h3 class="ecom-panel__title"><?php echo __t('ecom_recent_orders', 'admin'); ?></h3>
        <div class="ecom-table-wrap">
            <table class="ecom-table" id="ecomRecentOrders">
                <thead>
                    <tr>
                        <th><?php echo __t('col_receipt', 'admin'); ?></th>
                        <th><?php echo __t('col_date', 'admin'); ?></th>
                        <th><?php echo __t('col_amount', 'admin'); ?></th>
                        <th><?php echo __t('col_status', 'admin'); ?></th>
                    </tr>
                </thead>
                <tbody><tr><td colspan="4"><?php echo __t('loading', 'admin'); ?></td></tr></tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
