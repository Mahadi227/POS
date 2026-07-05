<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'payments';
$pageTitle = __t('plat_nav_payments', 'platform');
$extraStyles = ['platform-payments.css'];
$extraScripts = ['platform-common.js', 'platform-payments.js'];
$pageI18n = plat_i18n([
    'plat_nav_payments', 'plat_col_name', 'plat_col_plan', 'plat_no_data', 'plat_search',
    'plat_clear_filters', 'loading', 'load_error', 'action_success', 'action_error',
    'plat_payments_subtitle', 'plat_payments_badge', 'plat_payments_count', 'plat_payments_load_error',
    'plat_payments_empty', 'plat_payments_empty_hint', 'plat_payments_kpi_total', 'plat_payments_kpi_confirmed',
    'plat_payments_kpi_pending', 'plat_payments_kpi_collected', 'plat_payments_kpi_failed',
    'plat_payments_filter_all_status', 'plat_payments_filter_all_providers', 'plat_event_amount', 'plat_event_date',
    'plat_payments_col_provider', 'plat_payments_col_reference', 'plat_payments_col_status', 'plat_payments_view_org',
    'plat_payments_status_confirmed', 'plat_payments_status_pending', 'plat_payments_status_failed',
    'plat_payments_status_refund', 'plat_payments_confirm', 'plat_payments_confirm_prompt',
    'plat_sub_provider_manual', 'plat_sub_provider_stripe', 'plat_sub_provider_paystack', 'plat_sub_provider_mobile',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-payments">
    <div class="plat-payments-error" id="platPaymentsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platPaymentsErrorText"></span>
    </div>
    <div class="plat-payments-alert" id="platPaymentsAlert" hidden role="status"></div>

    <section class="plat-payments-hero" aria-labelledby="platPaymentsHeroTitle">
        <div class="plat-payments-hero__intro">
            <div class="plat-payments-badge">
                <span class="material-icons-round" aria-hidden="true">payments</span>
                <?php echo __t('plat_payments_badge', 'platform'); ?>
            </div>
            <h2 class="plat-payments-hero__title" id="platPaymentsHeroTitle"><?php echo __t('plat_nav_payments', 'platform'); ?></h2>
            <p class="plat-payments-hero__desc"><?php echo __t('plat_payments_subtitle', 'platform'); ?></p>
        </div>
        <p class="plat-payments-count" id="platPaymentsCount" aria-live="polite"></p>
    </section>

    <section class="plat-kpi-grid plat-payments-kpi-grid" id="platPaymentsKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">swap_horiz</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_payments_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPayKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_payments_kpi_confirmed', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPayKpiConfirmed">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">hourglass_top</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_payments_kpi_pending', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPayKpiPending">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">account_balance_wallet</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_payments_kpi_collected', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPayKpiCollected">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-payments-panel">
        <div class="plat-payments-toolbar" id="platPaymentsFilters">
            <div class="plat-payments-search-wrap">
                <span class="material-icons-round plat-payments-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platPaymentsSearch" class="plat-search plat-payments-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platPaymentsStatusFilter" class="plat-select" aria-label="<?php echo __t('plat_payments_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_payments_filter_all_status', 'platform'); ?></option>
                <option value="confirmed"><?php echo __t('plat_payments_status_confirmed', 'platform'); ?></option>
                <option value="pending"><?php echo __t('plat_payments_status_pending', 'platform'); ?></option>
                <option value="failed"><?php echo __t('plat_payments_status_failed', 'platform'); ?></option>
                <option value="refund"><?php echo __t('plat_payments_status_refund', 'platform'); ?></option>
            </select>
            <select id="platPaymentsProviderFilter" class="plat-select" aria-label="<?php echo __t('plat_payments_col_provider', 'platform'); ?>">
                <option value=""><?php echo __t('plat_payments_filter_all_providers', 'platform'); ?></option>
                <option value="stripe"><?php echo __t('plat_sub_provider_stripe', 'platform'); ?></option>
                <option value="paystack"><?php echo __t('plat_sub_provider_paystack', 'platform'); ?></option>
                <option value="mobile_money"><?php echo __t('plat_sub_provider_mobile', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-payments-clear-btn" id="platPaymentsClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-table-wrap plat-payments-table-wrap">
            <table class="plat-table plat-payments-table" id="platPaymentsTable">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_event_date', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_name', 'platform'); ?></th>
                        <th><?php echo __t('plat_payments_col_provider', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_plan', 'platform'); ?></th>
                        <th><?php echo __t('plat_event_amount', 'platform'); ?></th>
                        <th><?php echo __t('plat_payments_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_payments_col_reference', 'platform'); ?></th>
                        <th class="plat-col-action"></th>
                    </tr>
                </thead>
                <tbody id="platPaymentsBody">
                    <tr class="plat-payments-loading-row">
                        <td colspan="8">
                            <span class="plat-payments-loading">
                                <span class="plat-payments-spinner" aria-hidden="true"></span>
                                <?php echo __t('loading', 'platform'); ?>…
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="plat-payments-empty" id="platPaymentsEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">payments</span>
            <h3><?php echo __t('plat_payments_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_payments_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
