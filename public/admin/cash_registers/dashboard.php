<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'dashboard';
$pageTitle = __t('cr_dashboard_title', 'admin');
$loadChart = true;
$extraScripts = ['cash-registers-common.js', 'cash-registers-dashboard.js'];
$pageI18n = cr_i18n([
    'cr_dashboard_subtitle', 'cr_stat_total_registers', 'cr_stat_open', 'cr_stat_closed',
    'cr_stat_cash_balance', 'cr_stat_expected', 'cr_stat_difference', 'cr_stat_sales_today',
    'cr_stat_cash', 'cr_stat_mobile', 'cr_stat_card', 'cr_stat_pending_recon', 'cr_stat_active_cashiers',
    'cr_chart_collection', 'cr_chart_performance', 'cr_register_status', 'cr_recent_activity',
    'cr_no_registers', 'cr_no_data', 'cr_session_open', 'cr_session_closed',
    'cr_quick_actions', 'cr_section_operations', 'cr_section_cash_flow', 'cr_section_collections',
    'cr_hero_sales_today', 'cr_hero_registers', 'cr_hero_cash_on_hand', 'cr_hero_variance',
    'cr_transactions_today', 'cr_payment_mix', 'cr_variance_alert', 'cr_pending_recon_alert',
    'cr_view_registers', 'cr_view_details', 'cr_assigned_cashier', 'cr_open_register',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-dash-hero" aria-labelledby="crDashHeroTitle">
    <div class="cr-dash-hero__body">
        <p class="cr-dash-hero__eyebrow" id="crDashHeroTitle"><?php echo __t('cr_dashboard_subtitle', 'admin'); ?></p>
        <div class="cr-dash-hero__grid">
            <div class="cr-dash-hero__metric">
                <span class="cr-dash-hero__label"><?php echo __t('cr_hero_sales_today', 'admin'); ?></span>
                <strong class="cr-dash-hero__value is-loading" id="crHeroSales">—</strong>
                <span class="cr-dash-hero__hint" id="crHeroTransactions">—</span>
            </div>
            <div class="cr-dash-hero__metric">
                <span class="cr-dash-hero__label"><?php echo __t('cr_hero_registers', 'admin'); ?></span>
                <strong class="cr-dash-hero__value is-loading" id="crHeroOpen">—</strong>
                <span class="cr-dash-hero__hint" id="crHeroTotalRegisters">—</span>
            </div>
            <div class="cr-dash-hero__metric">
                <span class="cr-dash-hero__label"><?php echo __t('cr_hero_cash_on_hand', 'admin'); ?></span>
                <strong class="cr-dash-hero__value is-loading" id="crHeroBalance">—</strong>
                <span class="cr-dash-hero__hint"><?php echo __t('cr_stat_expected', 'admin'); ?>: <span id="crHeroExpected">—</span></span>
            </div>
            <div class="cr-dash-hero__metric cr-dash-hero__metric--variance">
                <span class="cr-dash-hero__label"><?php echo __t('cr_hero_variance', 'admin'); ?></span>
                <strong class="cr-dash-hero__value is-loading" id="crHeroVariance">—</strong>
                <span class="cr-dash-hero__hint" id="crHeroPendingRecon">—</span>
            </div>
        </div>
    </div>
    <div class="cr-dash-hero__aside" aria-hidden="true">
        <span class="material-icons-round">point_of_sale</span>
    </div>
</section>

<div class="cr-dash-alerts" id="crDashAlerts" hidden></div>

<nav class="ad-quick-nav cr-dash-quick" aria-label="<?php echo htmlspecialchars(__t('cr_quick_actions', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
    <a href="open_register.php" class="ad-quick-nav__item ad-quick-nav__item--accent">
        <span class="material-icons-round">lock_open</span>
        <span><?php echo __t('cr_open_register', 'admin'); ?></span>
    </a>
    <a href="registers.php" class="ad-quick-nav__item">
        <span class="material-icons-round">storefront</span>
        <span><?php echo __t('cr_nav_registers', 'admin'); ?></span>
    </a>
    <a href="reconciliation.php" class="ad-quick-nav__item">
        <span class="material-icons-round">account_balance_wallet</span>
        <span><?php echo __t('cr_nav_reconciliation', 'admin'); ?></span>
    </a>
    <a href="cash_transfers.php" class="ad-quick-nav__item">
        <span class="material-icons-round">sync_alt</span>
        <span><?php echo __t('cr_nav_transfers', 'admin'); ?></span>
    </a>
    <a href="reports.php" class="ad-quick-nav__item">
        <span class="material-icons-round">summarize</span>
        <span><?php echo __t('cr_nav_reports', 'admin'); ?></span>
    </a>
    <a href="analytics.php" class="ad-quick-nav__item">
        <span class="material-icons-round">analytics</span>
        <span><?php echo __t('cr_nav_analytics', 'admin'); ?></span>
    </a>
</nav>

<div class="cr-dash-sections">
    <section class="cr-dash-section" aria-labelledby="crSecOps">
        <header class="cr-dash-section__head">
            <h2 id="crSecOps"><span class="material-icons-round">tune</span><?php echo __t('cr_section_operations', 'admin'); ?></h2>
        </header>
        <div class="cr-dash-kpi-row">
            <?php
            $opsKpis = [
                ['crTotalRegisters', 'cr_stat_total_registers', 'storefront', 'primary'],
                ['crOpenRegisters', 'cr_stat_open', 'lock_open', 'success'],
                ['crClosedRegisters', 'cr_stat_closed', 'lock', ''],
                ['crActiveCashiers', 'cr_stat_active_cashiers', 'badge', 'info'],
            ];
            foreach ($opsKpis as [$id, $label, $icon, $tone]): ?>
            <article class="cr-dash-kpi cr-kpi-card is-loading<?php echo $tone ? ' cr-dash-kpi--' . $tone : ''; ?>">
                <div class="cr-dash-kpi__icon"><span class="material-icons-round"><?php echo $icon; ?></span></div>
                <div class="cr-dash-kpi__body">
                    <span class="cr-dash-kpi__label"><?php echo __t($label, 'admin'); ?></span>
                    <strong class="cr-dash-kpi__value" id="<?php echo $id; ?>">—</strong>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cr-dash-section" aria-labelledby="crSecCash">
        <header class="cr-dash-section__head">
            <h2 id="crSecCash"><span class="material-icons-round">account_balance</span><?php echo __t('cr_section_cash_flow', 'admin'); ?></h2>
        </header>
        <div class="cr-dash-kpi-row">
            <?php
            $cashKpis = [
                ['crCashBalance', 'cr_stat_cash_balance', 'account_balance_wallet', 'primary'],
                ['crExpectedCash', 'cr_stat_expected', 'calculate', ''],
                ['crCashDifference', 'cr_stat_difference', 'difference', 'warn'],
                ['crPendingRecon', 'cr_stat_pending_recon', 'pending_actions', 'warn'],
            ];
            foreach ($cashKpis as [$id, $label, $icon, $tone]): ?>
            <article class="cr-dash-kpi cr-kpi-card is-loading<?php echo $tone ? ' cr-dash-kpi--' . $tone : ''; ?>">
                <div class="cr-dash-kpi__icon"><span class="material-icons-round"><?php echo $icon; ?></span></div>
                <div class="cr-dash-kpi__body">
                    <span class="cr-dash-kpi__label"><?php echo __t($label, 'admin'); ?></span>
                    <strong class="cr-dash-kpi__value" id="<?php echo $id; ?>">—</strong>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cr-dash-section" aria-labelledby="crSecColl">
        <header class="cr-dash-section__head">
            <h2 id="crSecColl"><span class="material-icons-round">payments</span><?php echo __t('cr_section_collections', 'admin'); ?></h2>
        </header>
        <div class="cr-dash-kpi-row">
            <?php
            $collKpis = [
                ['crSalesToday', 'cr_stat_sales_today', 'point_of_sale', 'primary'],
                ['crCashCollected', 'cr_stat_cash', 'payments', 'success'],
                ['crMobileCollected', 'cr_stat_mobile', 'smartphone', ''],
                ['crCardCollected', 'cr_stat_card', 'credit_card', 'info'],
            ];
            foreach ($collKpis as [$id, $label, $icon, $tone]): ?>
            <article class="cr-dash-kpi cr-kpi-card is-loading<?php echo $tone ? ' cr-dash-kpi--' . $tone : ''; ?>">
                <div class="cr-dash-kpi__icon"><span class="material-icons-round"><?php echo $icon; ?></span></div>
                <div class="cr-dash-kpi__body">
                    <span class="cr-dash-kpi__label"><?php echo __t($label, 'admin'); ?></span>
                    <strong class="cr-dash-kpi__value" id="<?php echo $id; ?>">—</strong>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<div class="cr-dash-charts">
    <section class="cr-panel cr-panel--chart">
        <header class="cr-panel__head">
            <h3><span class="material-icons-round">show_chart</span><?php echo __t('cr_chart_collection', 'admin'); ?></h3>
            <span class="cr-panel__badge"><?php echo __t('cr_stat_sales_today', 'admin'); ?></span>
        </header>
        <div class="cr-chart-wrap"><canvas id="crCollectionChart" height="220"></canvas></div>
    </section>
    <section class="cr-panel cr-panel--chart">
        <header class="cr-panel__head">
            <h3><span class="material-icons-round">bar_chart</span><?php echo __t('cr_chart_performance', 'admin'); ?></h3>
            <span class="cr-panel__badge">7d</span>
        </header>
        <div class="cr-chart-wrap"><canvas id="crPerformanceChart" height="220"></canvas></div>
    </section>
    <section class="cr-panel cr-panel--chart cr-panel--compact">
        <header class="cr-panel__head">
            <h3><span class="material-icons-round">donut_large</span><?php echo __t('cr_payment_mix', 'admin'); ?></h3>
        </header>
        <div class="cr-chart-wrap cr-chart-wrap--donut"><canvas id="crPaymentChart" height="200"></canvas></div>
        <ul class="cr-payment-legend" id="crPaymentLegend" aria-live="polite"></ul>
    </section>
</div>

<div class="cr-grid-2 cr-dash-bottom">
    <section class="cr-panel cr-panel--list">
        <header class="cr-panel__head">
            <h3><span class="material-icons-round">sensors</span><?php echo __t('cr_register_status', 'admin'); ?></h3>
            <a href="registers.php" class="cr-panel__link"><?php echo __t('cr_view_registers', 'admin'); ?> <span class="material-icons-round">arrow_forward</span></a>
        </header>
        <div class="cr-status-list" id="crStatusList"></div>
    </section>
    <section class="cr-panel cr-panel--list">
        <header class="cr-panel__head">
            <h3><span class="material-icons-round">history</span><?php echo __t('cr_recent_activity', 'admin'); ?></h3>
            <a href="logs.php" class="cr-panel__link"><?php echo __t('cr_nav_logs', 'admin'); ?> <span class="material-icons-round">arrow_forward</span></a>
        </header>
        <div class="cr-activity-feed" id="crActivityList"></div>
    </section>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
