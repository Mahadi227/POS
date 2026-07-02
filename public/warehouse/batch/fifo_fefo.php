<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('batch');

$useWmsModules = true;
$activeWhPage = 'fifo_fefo';
$pageTitle = __t('wh_nav_fifo', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-fifo-fefo.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_fifo_subtitle', 'wh_fifo_hint', 'wh_fifo_stat_batches', 'wh_fifo_stat_units', 'wh_fifo_stat_with_expiry',
        'wh_fifo_stat_expiring_7d', 'wh_fifo_hero_meta', 'wh_fifo_strategy_breakdown', 'wh_fifo_strategy_fefo',
        'wh_fifo_strategy_fifo', 'wh_fifo_search', 'wh_fifo_empty', 'wh_fifo_col_rank', 'wh_fifo_col_received',
        'wh_fifo_link_batch', 'wh_fifo_link_serial', 'wh_fifo_link_expiry', 'wh_all_warehouses', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records',
        'close', 'error', 'col_date', 'col_status', 'cancel', 'save',
    ]),
    wms_i18n([
        'wms_col_batch', 'wms_col_product', 'wms_col_expiry', 'wms_col_qty', 'wms_col_value', 'wms_col_mfg',
        'wms_col_barcode', 'wms_col_serial', 'wms_nav_warehouses', 'wms_batch_details', 'wms_view_details',
        'wms_unit_cost', 'wms_days_to_expiry', 'wms_days_short', 'wms_mark_depleted', 'wms_confirm_deplete',
        'wms_status_active',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-fifo-hero" aria-labelledby="whFifoHeroTitle">
    <div class="wh-fifo-hero__intro">
        <h2 class="wh-fifo-hero__title" id="whFifoHeroTitle"><?php echo __t('wh_fifo_subtitle', 'warehouse'); ?></h2>
        <p class="wh-fifo-hero__meta" id="whFifoHeroMeta" aria-live="polite">—</p>
        <p class="wh-fifo-hero__hint"><?php echo __t('wh_fifo_hint', 'warehouse'); ?></p>
        <div class="wh-fifo-hero__links">
            <a class="wh-fifo-hero__link" href="batch_tracking.php"><?php echo __t('wh_fifo_link_batch', 'warehouse'); ?></a>
            <a class="wh-fifo-hero__link" href="serial_numbers.php"><?php echo __t('wh_fifo_link_serial', 'warehouse'); ?></a>
            <a class="wh-fifo-hero__link" href="expiry_management.php"><?php echo __t('wh_fifo_link_expiry', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-fifo-hero__stats" role="group">
        <article class="wh-fifo-stat wh-fifo-stat--primary">
            <span class="wh-fifo-stat__label"><?php echo __t('wh_fifo_stat_batches', 'warehouse'); ?></span>
            <strong class="wh-fifo-stat__value is-loading" id="whFifoStatBatches">—</strong>
        </article>
        <article class="wh-fifo-stat wh-fifo-stat--success">
            <span class="wh-fifo-stat__label"><?php echo __t('wh_fifo_stat_units', 'warehouse'); ?></span>
            <strong class="wh-fifo-stat__value is-loading" id="whFifoStatUnits">—</strong>
        </article>
        <article class="wh-fifo-stat wh-fifo-stat--info">
            <span class="wh-fifo-stat__label"><?php echo __t('wh_fifo_stat_with_expiry', 'warehouse'); ?></span>
            <strong class="wh-fifo-stat__value is-loading" id="whFifoStatExpiry">—</strong>
        </article>
        <article class="wh-fifo-stat wh-fifo-stat--warn">
            <span class="wh-fifo-stat__label"><?php echo __t('wh_fifo_stat_expiring_7d', 'warehouse'); ?></span>
            <strong class="wh-fifo-stat__value is-loading" id="whFifoStatExp7d">—</strong>
        </article>
    </div>
</section>

<section class="wh-fifo-breakdown" id="whFifoBreakdownPanel" hidden aria-labelledby="whFifoBreakdownTitle">
    <div class="wh-fifo-breakdown__head">
        <h3 id="whFifoBreakdownTitle"><?php echo __t('wh_fifo_strategy_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-fifo-strategy-chips" id="whFifoStrategyChips"></div>
</section>

<div class="wh-fifo-toolbar">
    <div class="wh-fifo-toolbar__row">
        <div class="wh-fifo-toolbar__filters">
            <label class="wh-fifo-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whFifoSearch" class="wh-fifo-search" placeholder="<?php echo htmlspecialchars(__t('wh_fifo_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whFifoWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whFifoStrategy" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_fifo_strategy_breakdown', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="fefo"><?php echo __t('wh_fifo_strategy_fefo', 'warehouse'); ?></option>
                <option value="fifo"><?php echo __t('wh_fifo_strategy_fifo', 'warehouse'); ?></option>
            </select>
        </div>
        <div class="wh-fifo-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whFifoExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whFifoRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-fifo-panel" aria-live="polite">
    <div class="wh-fifo-table-wrap" id="whFifoTableWrap"></div>
    <div class="wh-fifo-empty" id="whFifoEmpty" hidden>
        <span class="material-icons-round">sort</span>
        <p><?php echo __t('wh_fifo_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whFifoLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-fifo-pagination" id="whFifoPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whFifoPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-fifo-pagination__meta" id="whFifoPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whFifoNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-fifo-toast" id="whFifoToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whFifoDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--fifo" role="dialog" aria-labelledby="whFifoDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">sort</span></div>
                <div>
                    <h3 id="whFifoDetailTitle"><?php echo __t('wms_batch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whFifoDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whFifoDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whFifoDetailBody" class="wh-fifo-detail-body"></div>
        <footer class="wms-grn-modal__footer" id="whFifoDetailActions" hidden>
            <div class="wms-grn-modal__actions">
                <button type="button" class="wh-btn wh-btn--primary" id="whFifoDepleteBtn"><?php echo __t('wms_mark_depleted', 'wms'); ?></button>
            </div>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';
