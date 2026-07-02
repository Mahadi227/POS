<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('batch');

$useWmsModules = true;
$activeWhPage = 'expiry_management';
$pageTitle = __t('wh_nav_expiry', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-expiry-management.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_exp_subtitle', 'wh_exp_hint', 'wh_exp_hero_meta', 'wh_exp_risk_breakdown', 'wh_exp_empty',
        'wh_exp_link_batch', 'wh_exp_link_serial', 'wh_exp_link_fifo', 'wh_all_warehouses', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records',
        'close', 'error', 'col_date', 'col_status', 'cancel',
    ]),
    wms_i18n([
        'wms_expiry_title', 'wms_expiry_subtitle', 'wms_stat_exp_soon', 'wms_stat_exp_past', 'wms_stat_exp_units',
        'wms_stat_exp_value', 'wms_search_expiry', 'wms_expiry_period', 'wms_period_7d', 'wms_period_14d',
        'wms_period_30d', 'wms_period_60d', 'wms_period_90d', 'wms_filter_at_risk', 'wms_filter_expiring_only',
        'wms_filter_expired_only', 'wms_col_batch', 'wms_col_product', 'wms_col_expiry', 'wms_col_qty',
        'wms_col_value', 'wms_days_to_expiry', 'wms_days_short', 'wms_expiry_details', 'wms_view_details',
        'wms_batch_details', 'wms_nav_warehouses', 'wms_unit_cost', 'wms_col_mfg', 'wms_col_barcode',
        'wms_col_serial', 'wms_urgency_critical', 'wms_urgency_warning', 'wms_urgency_expired', 'wms_mark_expired',
        'wms_mark_recalled', 'wms_mark_depleted', 'wms_confirm_mark_expired', 'wms_confirm_recall',
        'wms_confirm_deplete', 'wms_status_active', 'wms_status_expired', 'wms_status_recalled', 'wms_status_depleted',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-exp-hero" aria-labelledby="whExpHeroTitle">
    <div class="wh-exp-hero__intro">
        <h2 class="wh-exp-hero__title" id="whExpHeroTitle"><?php echo __t('wh_exp_subtitle', 'warehouse'); ?></h2>
        <p class="wh-exp-hero__meta" id="whExpHeroMeta" aria-live="polite">—</p>
        <p class="wh-exp-hero__hint"><?php echo __t('wh_exp_hint', 'warehouse'); ?></p>
        <div class="wh-exp-hero__links">
            <a class="wh-exp-hero__link" href="batch_tracking.php"><?php echo __t('wh_exp_link_batch', 'warehouse'); ?></a>
            <a class="wh-exp-hero__link" href="serial_numbers.php"><?php echo __t('wh_exp_link_serial', 'warehouse'); ?></a>
            <a class="wh-exp-hero__link" href="fifo_fefo.php"><?php echo __t('wh_exp_link_fifo', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-exp-hero__stats" role="group">
        <article class="wh-exp-stat wh-exp-stat--warn">
            <span class="wh-exp-stat__label"><?php echo __t('wms_stat_exp_soon', 'wms'); ?></span>
            <strong class="wh-exp-stat__value is-loading" id="whExpStatSoon">—</strong>
        </article>
        <article class="wh-exp-stat wh-exp-stat--danger">
            <span class="wh-exp-stat__label"><?php echo __t('wms_stat_exp_past', 'wms'); ?></span>
            <strong class="wh-exp-stat__value is-loading" id="whExpStatPast">—</strong>
        </article>
        <article class="wh-exp-stat wh-exp-stat--primary">
            <span class="wh-exp-stat__label"><?php echo __t('wms_stat_exp_units', 'wms'); ?></span>
            <strong class="wh-exp-stat__value is-loading" id="whExpStatUnits">—</strong>
        </article>
        <article class="wh-exp-stat wh-exp-stat--danger">
            <span class="wh-exp-stat__label"><?php echo __t('wms_stat_exp_value', 'wms'); ?></span>
            <strong class="wh-exp-stat__value is-loading" id="whExpStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-exp-breakdown" id="whExpBreakdownPanel" hidden aria-labelledby="whExpBreakdownTitle">
    <div class="wh-exp-breakdown__head">
        <h3 id="whExpBreakdownTitle"><?php echo __t('wh_exp_risk_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-exp-status-chips" id="whExpStatusChips"></div>
</section>

<div class="wh-exp-toolbar">
    <div class="wh-exp-toolbar__row">
        <div class="wh-exp-toolbar__filters">
            <label class="wh-exp-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whExpSearch" class="wh-exp-search" placeholder="<?php echo htmlspecialchars(__t('wms_search_expiry', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whExpWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whExpPeriod" class="wh-select" title="<?php echo htmlspecialchars(__t('wms_expiry_period', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(__t('wms_expiry_period', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="7"><?php echo __t('wms_period_7d', 'wms'); ?></option>
                <option value="14"><?php echo __t('wms_period_14d', 'wms'); ?></option>
                <option value="30" selected><?php echo __t('wms_period_30d', 'wms'); ?></option>
                <option value="60"><?php echo __t('wms_period_60d', 'wms'); ?></option>
                <option value="90"><?php echo __t('wms_period_90d', 'wms'); ?></option>
            </select>
            <select id="whExpFilter" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_exp_risk_breakdown', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="at_risk"><?php echo __t('wms_filter_at_risk', 'wms'); ?></option>
                <option value="expiring_soon"><?php echo __t('wms_filter_expiring_only', 'wms'); ?></option>
                <option value="expired"><?php echo __t('wms_filter_expired_only', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-exp-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whExpExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whExpRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-exp-panel" aria-live="polite">
    <div class="wh-exp-table-wrap" id="whExpTableWrap"></div>
    <div class="wh-exp-empty" id="whExpEmpty" hidden>
        <span class="material-icons-round">event_busy</span>
        <p><?php echo __t('wh_exp_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whExpLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-exp-pagination" id="whExpPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whExpPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-exp-pagination__meta" id="whExpPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whExpNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-exp-toast" id="whExpToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whExpDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--exp" role="dialog" aria-labelledby="whExpDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">event_busy</span></div>
                <div>
                    <h3 id="whExpDetailTitle"><?php echo __t('wms_expiry_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whExpDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whExpDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whExpDetailBody" class="wh-exp-detail-body"></div>
        <footer class="wms-grn-modal__footer" id="whExpDetailActions" hidden>
            <div class="wms-grn-modal__actions">
                <button type="button" class="wh-btn" id="whExpExpiredBtn"><?php echo __t('wms_mark_expired', 'wms'); ?></button>
                <button type="button" class="wh-btn wh-btn--warn" id="whExpRecallBtn"><?php echo __t('wms_mark_recalled', 'wms'); ?></button>
                <button type="button" class="wh-btn wh-btn--ghost" id="whExpDepleteBtn"><?php echo __t('wms_mark_depleted', 'wms'); ?></button>
            </div>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';
