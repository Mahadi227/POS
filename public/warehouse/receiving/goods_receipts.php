<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('receiving');

$useWmsModules = true;
$activeWhPage = 'goods_receipts';
$pageTitle = __t('wms_receipts_title', 'wms');
$whCanGrn = $whCanReceive && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-goods-receipts.js'];
$extraCss = ['wh-grn-create.css'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_grn_subtitle', 'wh_grn_stat_total', 'wh_grn_stat_pending', 'wh_grn_stat_completed',
        'wh_grn_stat_value', 'wh_grn_search', 'wh_grn_empty', 'wh_grn_hero_meta',
        'wh_grn_link_inventory', 'wh_grn_link_history', 'wh_grn_complete_hint',
        'wh_grn_status_breakdown', 'wh_grn_toast_created', 'wh_grn_toast_completed', 'wh_grn_lines_empty',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh',
        'last_updated', 'export_csv', 'prev_page', 'next_page', 'records', 'close',
        'cancel', 'save', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_receipts_title', 'wms_receipts_subtitle', 'wms_stat_grn_total', 'wms_stat_grn_pending',
        'wms_stat_grn_value', 'wms_filter_all_status', 'wms_status_pending', 'wms_status_inspecting',
        'wms_status_accepted', 'wms_status_completed', 'wms_status_rejected', 'wms_col_grn',
        'wms_col_supplier', 'wms_col_value', 'wms_col_items', 'wms_col_received_by',
        'wms_receipt_notes', 'wms_receipt_details', 'wms_add_line', 'wms_select_product',
        'wms_qty_received', 'wms_unit_cost', 'wms_batch_optional', 'wms_expiry_optional',
        'wms_search_grn', 'wms_confirm_complete', 'wms_view_details', 'wms_complete',
        'wms_new_receipt', 'wms_nav_warehouses', 'wms_col_product', 'wms_grn_form_subtitle',
        'wms_grn_section_info', 'wms_grn_section_lines', 'wms_grn_estimated_total',
        'wms_grn_lines_count', 'wms_supplier_placeholder', 'wms_grn_lines_hint',
        'wms_line_subtotal', 'wms_line_tracking', 'wms_product_filter', 'wms_qty_short',
        'wms_remove_line', 'wms_col_sku', 'wms_select_warehouse',
        'wms_product_search_placeholder', 'wms_product_new_badge', 'wms_product_create_hint',
        'wms_tracking_close', 'wms_tracking_has_data', 'wms_product_duplicate',
        'wms_po_col_number',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-grn-hero" aria-labelledby="whGrnHeroTitle">
    <div class="wh-grn-hero__intro">
        <h2 class="wh-grn-hero__title" id="whGrnHeroTitle"><?php echo __t('wh_grn_subtitle', 'warehouse'); ?></h2>
        <p class="wh-grn-hero__meta" id="whGrnHeroMeta" aria-live="polite">—</p>
        <p class="wh-grn-hero__hint"><?php echo __t('wh_grn_complete_hint', 'warehouse'); ?></p>
        <div class="wh-grn-hero__links">
            <a class="wh-grn-hero__link" href="../inventory/warehouse_inventory.php"><?php echo __t('wh_grn_link_inventory', 'warehouse'); ?></a>
            <a class="wh-grn-hero__link" href="receiving_history.php"><?php echo __t('wh_grn_link_history', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-grn-hero__stats" role="group">
        <article class="wh-grn-stat wh-grn-stat--primary">
            <span class="wh-grn-stat__label"><?php echo __t('wh_grn_stat_total', 'warehouse'); ?></span>
            <strong class="wh-grn-stat__value is-loading" id="whGrnStatTotal">—</strong>
        </article>
        <article class="wh-grn-stat wh-grn-stat--warn">
            <span class="wh-grn-stat__label"><?php echo __t('wh_grn_stat_pending', 'warehouse'); ?></span>
            <strong class="wh-grn-stat__value is-loading" id="whGrnStatPending">—</strong>
        </article>
        <article class="wh-grn-stat wh-grn-stat--success">
            <span class="wh-grn-stat__label"><?php echo __t('wh_grn_stat_completed', 'warehouse'); ?></span>
            <strong class="wh-grn-stat__value is-loading" id="whGrnStatCompleted">—</strong>
        </article>
        <article class="wh-grn-stat">
            <span class="wh-grn-stat__label"><?php echo __t('wh_grn_stat_value', 'warehouse'); ?></span>
            <strong class="wh-grn-stat__value is-loading" id="whGrnStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-grn-breakdown" id="whGrnBreakdownPanel" hidden aria-labelledby="whGrnBreakdownTitle">
    <div class="wh-grn-breakdown__head">
        <h3 id="whGrnBreakdownTitle"><?php echo __t('wh_grn_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-grn-status-chips" id="whGrnStatusChips"></div>
</section>

<div class="wh-grn-toolbar">
    <div class="wh-grn-toolbar__row">
        <div class="wh-grn-toolbar__filters">
            <label class="wh-grn-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whGrnSearch" class="wh-grn-search" placeholder="<?php echo htmlspecialchars(__t('wh_grn_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whGrnWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whGrnStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="pending"><?php echo __t('wms_status_pending', 'wms'); ?></option>
                <option value="inspecting"><?php echo __t('wms_status_inspecting', 'wms'); ?></option>
                <option value="accepted"><?php echo __t('wms_status_accepted', 'wms'); ?></option>
                <option value="completed"><?php echo __t('wms_status_completed', 'wms'); ?></option>
                <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-grn-toolbar__actions">
            <?php if ($whCanGrn): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whGrnNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wms_new_receipt', 'wms'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whGrnExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whGrnRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-grn-panel" aria-live="polite">
    <div class="wh-grn-table-wrap" id="whGrnTableWrap"></div>
    <div class="wh-grn-empty" id="whGrnEmpty" hidden>
        <span class="material-icons-round">move_to_inbox</span>
        <p><?php echo __t('wh_grn_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whGrnLoading" hidden><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-grn-pagination" id="whGrnPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whGrnPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-grn-pagination__meta" id="whGrnPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whGrnNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-grn-toast" id="whGrnToast" role="status" aria-live="polite"></div>

<?php if ($whCanGrn): ?>
<div class="wms-modal-overlay" id="whGrnCreateModal" aria-hidden="true">
    <div class="wh-grn-modal" role="dialog" aria-labelledby="whGrnCreateTitle" aria-modal="true">
        <div class="wh-grn-modal__swipe-zone">
            <div class="wh-grn-modal__handle" aria-hidden="true"></div>
            <header class="wh-grn-modal__header">
            <div class="wh-grn-modal__brand">
                <div class="wh-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">inventory_2</span></div>
                <div class="wh-grn-modal__titles">
                    <p class="wh-grn-modal__eyebrow"><?php echo __t('wms_grn_section_info', 'wms'); ?></p>
                    <h3 class="wh-grn-modal__title" id="whGrnCreateTitle"><?php echo __t('wms_new_receipt', 'wms'); ?></h3>
                </div>
            </div>
            <button type="button" class="wh-grn-modal__close" id="whGrnCreateClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
            </header>
        </div>

        <form id="whGrnCreateForm" class="wh-grn-modal__form" novalidate>
            <div class="wh-grn-modal__body">
                <details class="wh-grn-meta-wrap" id="whGrnMetaWrap">
                    <summary class="wh-grn-meta-wrap__toggle">
                        <span><?php echo __t('wms_grn_section_info', 'wms'); ?></span>
                        <span class="material-icons-round" aria-hidden="true">expand_more</span>
                    </summary>
                    <fieldset class="wh-grn-meta" aria-labelledby="whGrnMetaTitle">
                    <legend class="wh-grn-sr-only" id="whGrnMetaTitle"><?php echo __t('wms_grn_section_info', 'wms'); ?></legend>
                    <label class="wh-grn-field wh-grn-field--warehouse">
                        <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                        <select name="warehouse_id" id="whGrnFormWarehouse" required></select>
                    </label>
                    <label class="wh-grn-field">
                        <span><?php echo __t('wms_col_supplier', 'wms'); ?></span>
                        <input type="text" name="supplier_name" placeholder="<?php echo htmlspecialchars(__t('wms_supplier_placeholder', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <label class="wh-grn-field wh-grn-field--notes">
                        <span><?php echo __t('wms_receipt_notes', 'wms'); ?></span>
                        <input type="text" name="notes" placeholder="—">
                    </label>
                    </fieldset>
                </details>

                <section class="wh-grn-workspace" aria-labelledby="whGrnLinesTitle">
                    <div class="wh-grn-workspace__toolbar">
                        <div class="wh-grn-workspace__heading">
                            <h4 class="wh-grn-workspace__title" id="whGrnLinesTitle"><?php echo __t('wms_grn_section_lines', 'wms'); ?></h4>
                            <p class="wh-grn-workspace__hint"><?php echo __t('wms_grn_lines_hint', 'wms'); ?></p>
                        </div>
                        <div class="wh-grn-workspace__tools">
                            <label class="wh-grn-search">
                                <span class="material-icons-round" aria-hidden="true">search</span>
                                <input type="search" id="whGrnProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                            </label>
                            <button type="button" class="wh-btn wh-btn--primary wh-btn--sm wh-grn-btn-add" id="whGrnAddLine">
                                <span class="material-icons-round">add</span>
                                <span class="wh-btn-label"><?php echo __t('wms_add_line', 'wms'); ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="wh-grn-workspace__panel">
                        <div class="wh-grn-workspace__scroll" id="whGrnLinesScroll">
                            <table class="wh-grn-table wh-grn-table--dense">
                                <thead class="wh-grn-table__head">
                                    <tr>
                                        <th class="wh-grn-th--num">#</th>
                                        <th><?php echo __t('wms_col_product', 'wms'); ?></th>
                                        <th class="wh-grn-th--qty"><?php echo __t('wms_qty_short', 'wms'); ?></th>
                                        <th class="wh-grn-th--cost"><?php echo __t('wms_unit_cost', 'wms'); ?></th>
                                        <th class="wh-grn-th--sub"><?php echo __t('wms_line_subtotal', 'wms'); ?></th>
                                        <th class="wh-grn-th--act" title="<?php echo htmlspecialchars(__t('wms_line_tracking', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="material-icons-round wh-grn-th-icon">inventory_2</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="whGrnLineItems"></tbody>
                            </table>
                            <div class="wh-grn-empty" id="whGrnLinesEmpty" hidden>
                                <div class="wh-grn-empty__icon" aria-hidden="true"><span class="material-icons-round">playlist_add</span></div>
                                <p class="wh-grn-empty__title"><?php echo __t('wh_grn_lines_empty', 'warehouse'); ?></p>
                                <p class="wh-grn-empty__hint"><?php echo __t('wms_grn_lines_hint', 'wms'); ?></p>
                                <button type="button" class="wh-btn wh-btn--primary wh-btn--sm" data-trigger-add-line>
                                    <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="wh-grn-modal__footer">
                <p class="wh-grn-modal__error" id="whGrnFormError" hidden role="alert"></p>
                <div class="wh-grn-footer-bar">
                    <div class="wh-grn-footer-bar__metrics">
                        <div class="wh-grn-metric">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_items', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whGrnLineCount">0</strong>
                        </div>
                        <div class="wh-grn-metric wh-grn-metric--total">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_grn_estimated_total', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whGrnEstTotal">0</strong>
                        </div>
                    </div>
                    <div class="wh-grn-footer-bar__actions">
                        <button type="button" class="wh-btn wh-btn--ghost" id="whGrnCreateCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
                        <button type="submit" class="wh-btn wh-btn--primary wh-grn-btn-save" id="whGrnCreateSubmit">
                            <span class="material-icons-round">check_circle</span>
                            <?php echo __t('save', 'warehouse'); ?>
                        </button>
                    </div>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="whGrnDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="whGrnDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="whGrnDetailTitle"><?php echo __t('wms_receipt_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_receipts_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whGrnDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whGrnDetailBody" class="wms-detail-body wh-grn-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
