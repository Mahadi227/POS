<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'subscriptions';
$pageTitle = __t('plat_nav_subscriptions', 'platform');
$extraStyles = ['platform-subscriptions.css'];
$extraScripts = ['platform-common.js', 'platform-subscriptions.js'];
$pageI18n = plat_i18n([
    'plat_col_name', 'plat_col_plan', 'plat_col_status', 'plat_view_detail', 'plat_no_data',
    'plat_subscriptions_subtitle', 'plat_subscriptions_badge', 'plat_subscriptions_count',
    'plat_subscriptions_load_error', 'plat_subscriptions_empty', 'plat_subscriptions_empty_hint',
    'plat_clear_filters', 'plat_search', 'loading', 'load_error', 'action_success', 'action_error',
    'plat_sub_kpi_total', 'plat_sub_kpi_active', 'plat_sub_kpi_trial', 'plat_sub_kpi_past_due', 'plat_sub_kpi_mrr',
    'plat_sub_status_all', 'plat_sub_status_active', 'plat_sub_status_trial', 'plat_sub_status_past_due',
    'plat_sub_status_cancelled', 'plat_sub_col_period', 'plat_sub_col_provider', 'plat_sub_change_plan',
    'plat_sub_apply_plan', 'plat_sub_filter_plan_all', 'plat_sub_provider_manual', 'plat_sub_provider_stripe',
    'plat_sub_provider_paystack', 'plat_sub_provider_mobile',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-subs">
    <div class="plat-subs-error" id="platSubsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platSubsErrorText"></span>
    </div>
    <div class="plat-subs-alert" id="platSubsAlert" hidden role="status"></div>

    <section class="plat-subs-hero" aria-labelledby="platSubsHeroTitle">
        <div class="plat-subs-hero__intro">
            <div class="plat-subs-badge">
                <span class="material-icons-round" aria-hidden="true">autorenew</span>
                <?php echo __t('plat_subscriptions_badge', 'platform'); ?>
            </div>
            <h2 class="plat-subs-hero__title" id="platSubsHeroTitle"><?php echo __t('plat_nav_subscriptions', 'platform'); ?></h2>
            <p class="plat-subs-hero__desc"><?php echo __t('plat_subscriptions_subtitle', 'platform'); ?></p>
        </div>
        <p class="plat-subs-count" id="platSubsCount" aria-live="polite"></p>
    </section>

    <section class="plat-kpi-grid plat-subs-kpi-grid" id="platSubsKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">layers</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_sub_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSubKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_sub_kpi_active', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSubKpiActive">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">hourglass_top</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_sub_kpi_trial', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSubKpiTrial">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">payments</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_sub_kpi_mrr', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSubKpiMrr">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-subs-panel">
        <div class="plat-subs-toolbar" id="platSubsFilters">
            <div class="plat-subs-search-wrap">
                <span class="material-icons-round plat-subs-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platSubsSearch" class="plat-search plat-subs-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platSubsStatusFilter" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_sub_status_all', 'platform'); ?></option>
                <option value="active"><?php echo __t('plat_sub_status_active', 'platform'); ?></option>
                <option value="trial"><?php echo __t('plat_sub_status_trial', 'platform'); ?></option>
                <option value="past_due"><?php echo __t('plat_sub_status_past_due', 'platform'); ?></option>
                <option value="cancelled"><?php echo __t('plat_sub_status_cancelled', 'platform'); ?></option>
            </select>
            <select id="platSubsPlanFilter" class="plat-select" aria-label="<?php echo __t('plat_col_plan', 'platform'); ?>">
                <option value=""><?php echo __t('plat_sub_filter_plan_all', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-subs-clear-btn" id="platSubsClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-table-wrap plat-subs-table-wrap">
            <table class="plat-table plat-subs-table" id="platSubsTable">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_col_name', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_plan', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_sub_col_period', 'platform'); ?></th>
                        <th><?php echo __t('plat_sub_col_provider', 'platform'); ?></th>
                        <th><?php echo __t('plat_sub_change_plan', 'platform'); ?></th>
                        <th class="plat-col-action"></th>
                    </tr>
                </thead>
                <tbody id="platSubsBody">
                    <tr class="plat-subs-loading-row">
                        <td colspan="7">
                            <span class="plat-subs-loading">
                                <span class="plat-subs-spinner" aria-hidden="true"></span>
                                <?php echo __t('loading', 'platform'); ?>…
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="plat-subs-empty" id="platSubsEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">credit_card_off</span>
            <h3><?php echo __t('plat_subscriptions_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_subscriptions_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
