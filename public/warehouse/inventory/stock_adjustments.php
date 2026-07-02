<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('inventory');
$useWmsModules = true;
$activeWhPage = 'stock_adjustments';
$pageTitle = __t('wh_nav_adjustments', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-stock-adjustments.js'];
$extraCss = ['wh-grn-create.css', 'wh-adj-create.css'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_adj_subtitle', 'wh_adj_stat_total', 'wh_adj_stat_in', 'wh_adj_stat_out', 'wh_adj_stat_net',
        'wh_adj_stat_value', 'wh_adj_search', 'wh_adj_filter_all', 'wh_adj_col_date', 'wh_adj_col_product',
        'wh_adj_col_warehouse', 'wh_adj_col_type', 'wh_adj_col_qty', 'wh_adj_col_balance', 'wh_adj_col_notes',
        'wh_adj_col_user', 'wh_adj_empty', 'wh_adj_new', 'wh_adj_form_title', 'wh_adj_form_subtitle',
        'wh_adj_direction', 'wh_adj_dir_in', 'wh_adj_dir_out', 'wh_adj_quantity', 'wh_adj_type', 'wh_adj_notes',
        'wh_adj_select_product', 'wh_adj_product_search', 'wh_adj_on_hand', 'wh_adj_confirm', 'wh_adj_success',
        'wh_adj_section_where', 'wh_adj_section_change', 'wh_adj_form_hint',
        'wh_adj_link_ledger', 'wh_adj_link_count', 'wh_adj_date_from', 'wh_adj_date_to',
        'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint', 'loading', 'load_error',
        'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records', 'close', 'cancel', 'save', 'error',
    ]),
    wms_i18n([
        'wms_mov_adjustment', 'wms_mov_manual', 'wms_mov_damaged', 'wms_mov_expired', 'wms_mov_lost',
        'wms_nav_warehouses', 'wms_col_product', 'wms_select_warehouse', 'wms_select_product',
    ])
);
$whCanAdjust = !$whReadOnly;
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-adj-hero" aria-labelledby="whAdjHeroTitle">
    <div class="wh-adj-hero__intro">
        <h2 class="wh-adj-hero__title" id="whAdjHeroTitle"><?php echo __t('wh_adj_subtitle', 'warehouse'); ?></h2>
        <p class="wh-adj-hero__meta" id="whAdjHeroMeta" aria-live="polite">—</p>
        <div class="wh-adj-hero__links">
            <a class="wh-adj-hero__link" href="stock_ledger.php"><?php echo __t('wh_adj_link_ledger', 'warehouse'); ?></a>
            <a class="wh-adj-hero__link" href="stock_count.php"><?php echo __t('wh_adj_link_count', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-adj-hero__stats" role="group">
        <article class="wh-adj-stat wh-adj-stat--primary">
            <span class="wh-adj-stat__label"><?php echo __t('wh_adj_stat_total', 'warehouse'); ?></span>
            <strong class="wh-adj-stat__value is-loading" id="whAdjStatTotal">—</strong>
        </article>
        <article class="wh-adj-stat wh-adj-stat--success">
            <span class="wh-adj-stat__label"><?php echo __t('wh_adj_stat_in', 'warehouse'); ?></span>
            <strong class="wh-adj-stat__value is-loading" id="whAdjStatIn">—</strong>
        </article>
        <article class="wh-adj-stat wh-adj-stat--danger">
            <span class="wh-adj-stat__label"><?php echo __t('wh_adj_stat_out', 'warehouse'); ?></span>
            <strong class="wh-adj-stat__value is-loading" id="whAdjStatOut">—</strong>
        </article>
        <article class="wh-adj-stat">
            <span class="wh-adj-stat__label"><?php echo __t('wh_adj_stat_net', 'warehouse'); ?></span>
            <strong class="wh-adj-stat__value is-loading" id="whAdjStatNet">—</strong>
        </article>
        <article class="wh-adj-stat">
            <span class="wh-adj-stat__label"><?php echo __t('wh_adj_stat_value', 'warehouse'); ?></span>
            <strong class="wh-adj-stat__value is-loading" id="whAdjStatValue">—</strong>
        </article>
    </div>
</section>

<div class="wh-adj-toolbar">
    <div class="wh-adj-toolbar__row">
        <div class="wh-adj-toolbar__filters">
            <select id="whAdjWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <label class="wh-adj-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whAdjSearch" class="wh-adj-search" placeholder="<?php echo htmlspecialchars(__t('wh_adj_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whAdjType" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_adj_col_type', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_adj_filter_all', 'warehouse'); ?></option>
                <option value="adjustment"><?php echo __t('wms_mov_adjustment', 'wms'); ?></option>
                <option value="manual"><?php echo __t('wms_mov_manual', 'wms'); ?></option>
                <option value="damaged"><?php echo __t('wms_mov_damaged', 'wms'); ?></option>
                <option value="expired"><?php echo __t('wms_mov_expired', 'wms'); ?></option>
                <option value="lost"><?php echo __t('wms_mov_lost', 'wms'); ?></option>
            </select>
            <label class="wh-adj-date-wrap">
                <span><?php echo __t('wh_adj_date_from', 'warehouse'); ?></span>
                <input type="date" id="whAdjDateFrom" class="wh-input wh-adj-date">
            </label>
            <label class="wh-adj-date-wrap">
                <span><?php echo __t('wh_adj_date_to', 'warehouse'); ?></span>
                <input type="date" id="whAdjDateTo" class="wh-input wh-adj-date">
            </label>
        </div>
        <div class="wh-adj-toolbar__actions">
            <?php if ($whCanAdjust): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whAdjNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wh_adj_new', 'warehouse'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whAdjExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whAdjRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-adj-panel" aria-live="polite">
    <div class="wh-adj-table-wrap" id="whAdjTableWrap"></div>
    <div class="wh-adj-empty" id="whAdjEmpty" hidden>
        <span class="material-icons-round">tune</span>
        <p><?php echo __t('wh_adj_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whAdjLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-adj-pagination" id="whAdjPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whAdjPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-adj-pagination__meta" id="whAdjPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whAdjNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<?php if ($whCanAdjust): ?>
<div class="wms-modal-overlay" id="whAdjModal" aria-hidden="true">
    <div class="wh-grn-modal wh-grn-modal--adj" role="dialog" aria-labelledby="whAdjModalTitle" aria-modal="true">
        <div class="wh-grn-modal__swipe-zone">
            <div class="wh-grn-modal__handle" aria-hidden="true"></div>
            <header class="wh-grn-modal__header">
                <div class="wh-grn-modal__brand">
                    <div class="wh-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">tune</span></div>
                    <div class="wh-grn-modal__titles">
                        <p class="wh-grn-modal__eyebrow"><?php echo __t('wh_adj_section_where', 'warehouse'); ?></p>
                        <h3 class="wh-grn-modal__title" id="whAdjModalTitle"><?php echo __t('wh_adj_form_title', 'warehouse'); ?></h3>
                    </div>
                </div>
                <button type="button" class="wh-grn-modal__close" id="whAdjModalClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </header>
        </div>

        <form id="whAdjForm" class="wh-grn-modal__form" novalidate>
            <div class="wh-grn-modal__body">
                <details class="wh-grn-meta-wrap" id="whAdjMetaWrap">
                    <summary class="wh-grn-meta-wrap__toggle">
                        <span><?php echo __t('wh_adj_section_where', 'warehouse'); ?></span>
                        <span class="material-icons-round" aria-hidden="true">expand_more</span>
                    </summary>
                    <fieldset class="wh-grn-meta" aria-labelledby="whAdjMetaTitle">
                        <legend class="wh-grn-sr-only" id="whAdjMetaTitle"><?php echo __t('wh_adj_section_where', 'warehouse'); ?></legend>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="warehouse_id" id="whAdjFormWarehouse" required></select>
                        </label>
                        <label class="wh-grn-field wh-grn-field--product">
                            <span><?php echo __t('wms_col_product', 'wms'); ?></span>
                            <div class="wh-grn-product-picker" id="whAdjProductPicker">
                                <div class="wh-grn-product-input-wrap">
                                    <input type="text" class="wh-grn-product-input" id="whAdjProductInput" placeholder="<?php echo htmlspecialchars(__t('wh_adj_product_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" required>
                                    <input type="hidden" name="product_id" id="whAdjProductId" value="">
                                </div>
                                <div class="wh-grn-product-dropdown" hidden></div>
                            </div>
                        </label>
                        <p class="wh-adj-on-hand" id="whAdjOnHand" hidden aria-live="polite"></p>
                    </fieldset>
                </details>

                <section class="wh-grn-workspace" aria-labelledby="whAdjChangeTitle">
                    <div class="wh-grn-workspace__toolbar">
                        <div class="wh-grn-workspace__heading">
                            <h4 class="wh-grn-workspace__title" id="whAdjChangeTitle"><?php echo __t('wh_adj_section_change', 'warehouse'); ?></h4>
                            <p class="wh-grn-workspace__hint"><?php echo __t('wh_adj_form_hint', 'warehouse'); ?></p>
                        </div>
                    </div>
                    <div class="wh-adj-change-grid">
                        <label class="wh-grn-field">
                            <span><?php echo __t('wh_adj_type', 'warehouse'); ?></span>
                            <select name="movement_type" required>
                                <option value="adjustment"><?php echo __t('wms_mov_adjustment', 'wms'); ?></option>
                                <option value="manual"><?php echo __t('wms_mov_manual', 'wms'); ?></option>
                                <option value="damaged"><?php echo __t('wms_mov_damaged', 'wms'); ?></option>
                                <option value="expired"><?php echo __t('wms_mov_expired', 'wms'); ?></option>
                                <option value="lost"><?php echo __t('wms_mov_lost', 'wms'); ?></option>
                            </select>
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wh_adj_direction', 'warehouse'); ?></span>
                            <select name="direction" id="whAdjDirection" required>
                                <option value="in"><?php echo __t('wh_adj_dir_in', 'warehouse'); ?></option>
                                <option value="out"><?php echo __t('wh_adj_dir_out', 'warehouse'); ?></option>
                            </select>
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wh_adj_quantity', 'warehouse'); ?></span>
                            <input type="number" name="quantity_abs" id="whAdjQtyAbs" min="1" step="1" required placeholder="0" inputmode="numeric">
                        </label>
                        <label class="wh-grn-field wh-grn-field--notes">
                            <span><?php echo __t('wh_adj_notes', 'warehouse'); ?></span>
                            <input type="text" name="notes" placeholder="<?php echo htmlspecialchars(__t('wh_adj_notes', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </div>
                </section>
            </div>

            <footer class="wh-grn-modal__footer">
                <p class="wh-grn-modal__error" id="whAdjFormError" hidden role="alert"></p>
                <div class="wh-grn-footer-bar">
                    <div class="wh-grn-footer-bar__metrics">
                        <div class="wh-grn-metric">
                            <span class="wh-grn-metric__label"><?php echo __t('wh_adj_on_hand', 'warehouse'); ?></span>
                            <strong class="wh-grn-metric__value" id="whAdjMetricOnHand">—</strong>
                        </div>
                        <div class="wh-grn-metric wh-grn-metric--total" id="whAdjMetricBalanceWrap">
                            <span class="wh-grn-metric__label"><?php echo __t('wh_adj_col_balance', 'warehouse'); ?></span>
                            <strong class="wh-grn-metric__value" id="whAdjMetricBalance">—</strong>
                        </div>
                    </div>
                    <div class="wh-grn-footer-bar__actions">
                        <button type="button" class="wh-btn wh-btn--ghost" id="whAdjFormCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
                        <button type="submit" class="wh-btn wh-btn--primary wh-grn-btn-submit">
                            <span class="material-icons-round">check_circle</span>
                            <?php echo __t('wh_adj_confirm', 'warehouse'); ?>
                        </button>
                    </div>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wh-grn-toast" id="whAdjToast" role="status" aria-live="polite"></div>

<?php require __DIR__ . '/../includes/layout-end.php';
