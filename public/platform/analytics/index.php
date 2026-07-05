<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'analytics';
$pageTitle = __t('plat_nav_analytics', 'platform');
$extraStyles = ['platform-analytics.css'];
$extraScripts = ['platform-common.js', 'platform-analytics.js'];
$pageI18n = plat_i18n([
    'plat_nav_analytics', 'plat_nav_subscriptions', 'plat_nav_plans', 'plat_col_name', 'plat_col_status',
    'plat_col_stores', 'plat_col_users', 'plat_no_data', 'loading', 'load_error', 'plat_view_detail',
    'plat_analytics_subtitle', 'plat_analytics_badge', 'plat_analytics_load_error',
    'plat_analytics_kpi_tenants', 'plat_analytics_kpi_mrr', 'plat_analytics_kpi_revenue',
    'plat_analytics_kpi_subscriptions', 'plat_analytics_kpi_stores', 'plat_analytics_kpi_users',
    'plat_analytics_chart_growth', 'plat_analytics_chart_revenue', 'plat_analytics_plan_breakdown',
    'plat_analytics_sub_status', 'plat_analytics_top_tenants', 'plat_analytics_usage',
    'plat_analytics_view_subscriptions', 'plat_status_trial', 'plat_status_active',
    'plat_sub_status_past_due', 'plat_sub_status_cancelled', 'plat_analytics_metric_api',
    'plat_analytics_metric_stores', 'plat_analytics_metric_users', 'plat_analytics_metric_sales',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-analytics">
    <div class="plat-analytics-error" id="platAnalyticsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platAnalyticsErrorText"></span>
    </div>

    <section class="plat-analytics-hero" aria-labelledby="platAnalyticsHeroTitle">
        <div class="plat-analytics-hero__intro">
            <div class="plat-analytics-badge">
                <span class="material-icons-round" aria-hidden="true">insights</span>
                <?php echo __t('plat_analytics_badge', 'platform'); ?>
            </div>
            <h2 class="plat-analytics-hero__title" id="platAnalyticsHeroTitle"><?php echo __t('plat_nav_analytics', 'platform'); ?></h2>
            <p class="plat-analytics-hero__desc"><?php echo __t('plat_analytics_subtitle', 'platform'); ?></p>
        </div>
        <a href="<?php echo htmlspecialchars(plat_href('subscriptions/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-analytics-link-btn">
            <span class="material-icons-round" aria-hidden="true">autorenew</span>
            <?php echo __t('plat_analytics_view_subscriptions', 'platform'); ?>
        </a>
    </section>

    <section class="plat-kpi-grid plat-analytics-kpi-grid" id="platAnalyticsKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">business</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_analytics_kpi_tenants', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAnKpiTenants">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">trending_up</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_analytics_kpi_mrr', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAnKpiMrr">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">payments</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_analytics_kpi_revenue', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAnKpiRevenue">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">autorenew</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_analytics_kpi_subscriptions', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAnKpiSubs">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">storefront</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_analytics_kpi_stores', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAnKpiStores">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">groups</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_analytics_kpi_users', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAnKpiUsers">—</strong>
        </article>
    </section>

    <div class="plat-analytics-charts">
        <section class="plat-panel plat-analytics-chart-panel">
            <h3><?php echo __t('plat_analytics_chart_growth', 'platform'); ?></h3>
            <div class="plat-analytics-bars" id="platAnGrowthChart" aria-live="polite">
                <p class="plat-analytics-muted"><?php echo __t('loading', 'platform'); ?>…</p>
            </div>
        </section>
        <section class="plat-panel plat-analytics-chart-panel">
            <h3><?php echo __t('plat_analytics_chart_revenue', 'platform'); ?></h3>
            <div class="plat-analytics-bars" id="platAnRevenueChart" aria-live="polite">
                <p class="plat-analytics-muted"><?php echo __t('loading', 'platform'); ?>…</p>
            </div>
        </section>
    </div>

    <div class="plat-analytics-mid">
        <section class="plat-panel plat-analytics-plan-panel">
            <h3><?php echo __t('plat_analytics_plan_breakdown', 'platform'); ?></h3>
            <div id="platAnPlanBreakdown" class="plat-analytics-breakdown" aria-live="polite"></div>
        </section>
        <section class="plat-panel plat-analytics-status-panel">
            <h3><?php echo __t('plat_analytics_sub_status', 'platform'); ?></h3>
            <div class="plat-analytics-status-grid" id="platAnSubStatus" aria-live="polite"></div>
        </section>
        <section class="plat-panel plat-analytics-usage-panel">
            <h3><?php echo __t('plat_analytics_usage', 'platform'); ?></h3>
            <div class="plat-analytics-usage-grid" id="platAnUsage" aria-live="polite"></div>
        </section>
    </div>

    <section class="plat-panel plat-analytics-tenants-panel">
        <header class="plat-analytics-tenants-head">
            <h3><?php echo __t('plat_analytics_top_tenants', 'platform'); ?></h3>
        </header>
        <div class="plat-table-wrap">
            <table class="plat-table plat-analytics-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_col_name', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_stores', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_users', 'platform'); ?></th>
                        <th class="plat-col-action"></th>
                    </tr>
                </thead>
                <tbody id="platAnTopTenants">
                    <tr><td colspan="5" class="plat-analytics-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
