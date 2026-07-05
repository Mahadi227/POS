<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'billing';
$pageTitle = __t('plat_nav_billing', 'platform');
$extraStyles = ['platform-billing.css'];
$extraScripts = ['platform-common.js', 'platform-billing.js'];
$pageI18n = plat_i18n([
    'plat_nav_billing', 'plat_col_name', 'plat_col_status', 'plat_no_data', 'plat_search',
    'plat_clear_filters', 'loading', 'load_error', 'plat_view_detail',
    'plat_billing_subtitle', 'plat_billing_badge', 'plat_billing_count', 'plat_billing_load_error',
    'plat_billing_empty', 'plat_billing_empty_hint', 'plat_billing_kpi_total', 'plat_billing_kpi_payments',
    'plat_billing_kpi_collected', 'plat_billing_kpi_failed', 'plat_billing_kpi_refunds',
    'plat_billing_filter_all_types', 'plat_event_type', 'plat_event_amount', 'plat_event_date',
    'plat_billing_col_org', 'plat_billing_col_reference', 'plat_billing_view_org',
    'plat_billing_type_invoice', 'plat_billing_type_payment', 'plat_billing_type_refund',
    'plat_billing_type_failed', 'plat_billing_type_checkout', 'plat_billing_type_subscription_updated',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-billing">
    <div class="plat-billing-error" id="platBillingError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platBillingErrorText"></span>
    </div>

    <section class="plat-billing-hero" aria-labelledby="platBillingHeroTitle">
        <div class="plat-billing-hero__intro">
            <div class="plat-billing-badge">
                <span class="material-icons-round" aria-hidden="true">receipt_long</span>
                <?php echo __t('plat_billing_badge', 'platform'); ?>
            </div>
            <h2 class="plat-billing-hero__title" id="platBillingHeroTitle"><?php echo __t('plat_nav_billing', 'platform'); ?></h2>
            <p class="plat-billing-hero__desc"><?php echo __t('plat_billing_subtitle', 'platform'); ?></p>
        </div>
        <p class="plat-billing-count" id="platBillingCount" aria-live="polite"></p>
    </section>

    <section class="plat-kpi-grid plat-billing-kpi-grid" id="platBillingKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">receipt</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_billing_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platBillKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">payments</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_billing_kpi_payments', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platBillKpiPayments">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">account_balance_wallet</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_billing_kpi_collected', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platBillKpiCollected">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">error</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_billing_kpi_failed', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platBillKpiFailed">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-billing-panel">
        <div class="plat-billing-toolbar" id="platBillingFilters">
            <div class="plat-billing-search-wrap">
                <span class="material-icons-round plat-billing-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platBillingSearch" class="plat-search plat-billing-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platBillingTypeFilter" class="plat-select" aria-label="<?php echo __t('plat_event_type', 'platform'); ?>">
                <option value=""><?php echo __t('plat_billing_filter_all_types', 'platform'); ?></option>
                <option value="payment"><?php echo __t('plat_billing_type_payment', 'platform'); ?></option>
                <option value="invoice"><?php echo __t('plat_billing_type_invoice', 'platform'); ?></option>
                <option value="refund"><?php echo __t('plat_billing_type_refund', 'platform'); ?></option>
                <option value="failed"><?php echo __t('plat_billing_type_failed', 'platform'); ?></option>
                <option value="checkout"><?php echo __t('plat_billing_type_checkout', 'platform'); ?></option>
                <option value="subscription_updated"><?php echo __t('plat_billing_type_subscription_updated', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-billing-clear-btn" id="platBillingClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-table-wrap plat-billing-table-wrap">
            <table class="plat-table plat-billing-table" id="platBillingTable">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_event_date', 'platform'); ?></th>
                        <th><?php echo __t('plat_billing_col_org', 'platform'); ?></th>
                        <th><?php echo __t('plat_event_type', 'platform'); ?></th>
                        <th><?php echo __t('plat_event_amount', 'platform'); ?></th>
                        <th><?php echo __t('plat_billing_col_reference', 'platform'); ?></th>
                        <th class="plat-col-action"></th>
                    </tr>
                </thead>
                <tbody id="platBillingBody">
                    <tr class="plat-billing-loading-row">
                        <td colspan="6">
                            <span class="plat-billing-loading">
                                <span class="plat-billing-spinner" aria-hidden="true"></span>
                                <?php echo __t('loading', 'platform'); ?>…
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="plat-billing-empty" id="platBillingEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">receipt_long</span>
            <h3><?php echo __t('plat_billing_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_billing_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
