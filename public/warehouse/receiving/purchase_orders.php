<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('receiving');

$useWmsModules = true;
$activeWhPage = 'purchase_orders';
$pageTitle = __t('wh_nav_purchase_orders', 'warehouse');
$whCanPo = $whCanReceive && !$whReadOnly;
$whCanApprovePo = $whCanManage && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-purchase-orders.js'];
$extraCss = ['wh-grn-create.css', 'wh-po-create.css'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_po_subtitle', 'wh_po_hint', 'wh_po_stat_total', 'wh_po_stat_open', 'wh_po_stat_received',
        'wh_po_stat_value', 'wh_po_search', 'wh_po_empty', 'wh_po_status_breakdown',
        'wh_po_link_grn', 'wh_po_link_deliveries', 'wh_po_link_receive', 'wh_po_filter_open',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'cancel', 'save', 'error', 'col_status',
    ]),
    wms_i18n([
        'wms_po_title', 'wms_po_subtitle', 'wms_col_po', 'wms_col_supplier', 'wms_nav_warehouses',
        'wms_po_status_draft', 'wms_po_status_pending', 'wms_po_status_approved', 'wms_po_status_partial',
        'wms_po_status_received', 'wms_po_status_cancelled', 'wms_po_expected_date', 'wms_po_qty_ordered',
        'wms_po_qty_received', 'wms_po_new', 'wms_po_submit', 'wms_po_approve', 'wms_po_cancel', 'wms_po_receive',
        'wms_po_details', 'wms_po_save_draft', 'wms_po_toast_created', 'wms_po_toast_submitted',
        'wms_po_toast_approved', 'wms_po_toast_cancelled', 'wms_po_toast_grn', 'wms_filter_all_status',
        'wms_po_section_info', 'wms_po_section_lines', 'wms_po_lines_hint', 'wms_po_estimated_total',
        'wms_po_lines_empty', 'wms_po_product_search', 'wms_supplier_placeholder', 'wms_add_line',
        'wms_select_product', 'wms_col_product', 'wms_qty_short', 'wms_unit_cost', 'wms_line_subtotal',
        'wms_product_filter', 'wms_remove_line', 'wms_col_value', 'wms_col_items', 'wms_view_details',
        'wms_receipt_notes', 'col_date', 'wms_select_warehouse', 'wms_product_duplicate',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-po-hero" aria-labelledby="whPoHeroTitle">
    <div class="wh-po-hero__intro">
        <h2 class="wh-po-hero__title" id="whPoHeroTitle"><?php echo __t('wh_po_subtitle', 'warehouse'); ?></h2>
        <p class="wh-po-hero__meta" id="whPoHeroMeta" aria-live="polite">—</p>
        <p class="wh-po-hero__hint"><?php echo __t('wh_po_hint', 'warehouse'); ?></p>
        <div class="wh-po-hero__links">
            <a class="wh-po-hero__link" href="goods_receipts.php"><?php echo __t('wh_po_link_grn', 'warehouse'); ?></a>
            <a class="wh-po-hero__link" href="supplier_deliveries.php"><?php echo __t('wh_po_link_deliveries', 'warehouse'); ?></a>
            <a class="wh-po-hero__link" href="receive_stock.php"><?php echo __t('wh_po_link_receive', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-po-hero__stats" role="group">
        <article class="wh-po-stat wh-po-stat--primary">
            <span class="wh-po-stat__label"><?php echo __t('wh_po_stat_total', 'warehouse'); ?></span>
            <strong class="wh-po-stat__value is-loading" id="whPoStatTotal">—</strong>
        </article>
        <article class="wh-po-stat wh-po-stat--warn">
            <span class="wh-po-stat__label"><?php echo __t('wh_po_stat_open', 'warehouse'); ?></span>
            <strong class="wh-po-stat__value is-loading" id="whPoStatOpen">—</strong>
        </article>
        <article class="wh-po-stat wh-po-stat--success">
            <span class="wh-po-stat__label"><?php echo __t('wh_po_stat_received', 'warehouse'); ?></span>
            <strong class="wh-po-stat__value is-loading" id="whPoStatReceived">—</strong>
        </article>
        <article class="wh-po-stat">
            <span class="wh-po-stat__label"><?php echo __t('wh_po_stat_value', 'warehouse'); ?></span>
            <strong class="wh-po-stat__value is-loading" id="whPoStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-po-breakdown" id="whPoBreakdownPanel" hidden aria-labelledby="whPoBreakdownTitle">
    <div class="wh-po-breakdown__head">
        <h3 id="whPoBreakdownTitle"><?php echo __t('wh_po_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-po-status-chips" id="whPoStatusChips"></div>
</section>

<div class="wh-po-toolbar">
    <div class="wh-po-toolbar__row">
        <div class="wh-po-toolbar__filters">
            <label class="wh-po-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whPoSearch" class="wh-po-search" placeholder="<?php echo htmlspecialchars(__t('wh_po_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whPoWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whPoStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="open"><?php echo __t('wh_po_filter_open', 'warehouse'); ?></option>
                <option value="draft"><?php echo __t('wms_po_status_draft', 'wms'); ?></option>
                <option value="pending"><?php echo __t('wms_po_status_pending', 'wms'); ?></option>
                <option value="approved"><?php echo __t('wms_po_status_approved', 'wms'); ?></option>
                <option value="partial"><?php echo __t('wms_po_status_partial', 'wms'); ?></option>
                <option value="received"><?php echo __t('wms_po_status_received', 'wms'); ?></option>
                <option value="cancelled"><?php echo __t('wms_po_status_cancelled', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-po-toolbar__actions">
            <?php if ($whCanPo): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whPoNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wms_po_new', 'wms'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whPoExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whPoRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-po-panel" aria-live="polite">
    <div class="wh-po-table-wrap" id="whPoTableWrap"></div>
    <div class="wh-po-empty" id="whPoEmpty" hidden>
        <span class="material-icons-round">shopping_cart</span>
        <p><?php echo __t('wh_po_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whPoLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-po-pagination" id="whPoPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whPoPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-po-pagination__meta" id="whPoPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whPoNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-page-meta" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-po-toast" id="whPoToast" role="status" aria-live="polite"></div>

<?php if ($whCanPo): ?>
<div class="wms-modal-overlay" id="whPoCreateModal" aria-hidden="true">
    <div class="wh-grn-modal wh-grn-modal--po" role="dialog" aria-labelledby="whPoCreateTitle" aria-modal="true">
        <div class="wh-grn-modal__swipe-zone">
            <div class="wh-grn-modal__handle" aria-hidden="true"></div>
            <header class="wh-grn-modal__header">
                <div class="wh-grn-modal__brand">
                    <div class="wh-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">shopping_cart</span></div>
                    <div class="wh-grn-modal__titles">
                        <p class="wh-grn-modal__eyebrow"><?php echo __t('wms_po_subtitle', 'wms'); ?></p>
                        <h3 class="wh-grn-modal__title" id="whPoCreateTitle"><?php echo __t('wms_po_new', 'wms'); ?></h3>
                    </div>
                </div>
                <button type="button" class="wh-grn-modal__close" id="whPoCreateClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </header>
        </div>

        <form id="whPoCreateForm" class="wh-grn-modal__form" novalidate>
            <div class="wh-grn-modal__body">
                <details class="wh-grn-meta-wrap" id="whPoMetaWrap">
                    <summary class="wh-grn-meta-wrap__toggle">
                        <span><?php echo __t('wms_po_section_info', 'wms'); ?></span>
                        <span class="material-icons-round" aria-hidden="true">expand_more</span>
                    </summary>
                    <fieldset class="wh-grn-meta" aria-labelledby="whPoMetaTitle">
                        <legend class="wh-grn-sr-only" id="whPoMetaTitle"><?php echo __t('wms_po_section_info', 'wms'); ?></legend>
                        <label class="wh-grn-field wh-grn-field--warehouse">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="warehouse_id" id="whPoFormWarehouse" required></select>
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_supplier', 'wms'); ?></span>
                            <input type="text" name="supplier_name" required placeholder="<?php echo htmlspecialchars(__t('wms_supplier_placeholder', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="wh-grn-field wh-grn-field--date">
                            <span><?php echo __t('wms_po_expected_date', 'wms'); ?></span>
                            <input type="date" name="expected_date">
                        </label>
                        <label class="wh-grn-field wh-grn-field--notes">
                            <span><?php echo __t('wms_receipt_notes', 'wms'); ?></span>
                            <input type="text" name="notes" placeholder="—">
                        </label>
                    </fieldset>
                </details>

                <section class="wh-grn-workspace" aria-labelledby="whPoLinesTitle">
                    <div class="wh-grn-workspace__toolbar">
                        <div class="wh-grn-workspace__heading">
                            <h4 class="wh-grn-workspace__title" id="whPoLinesTitle"><?php echo __t('wms_po_section_lines', 'wms'); ?></h4>
                            <p class="wh-grn-workspace__hint"><?php echo __t('wms_po_lines_hint', 'wms'); ?></p>
                        </div>
                        <div class="wh-grn-workspace__tools">
                            <label class="wh-grn-search">
                                <span class="material-icons-round" aria-hidden="true">search</span>
                                <input type="search" id="whPoProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                            </label>
                            <button type="button" class="wh-btn wh-btn--primary wh-btn--sm wh-grn-btn-add" id="whPoAddLine">
                                <span class="material-icons-round">add</span>
                                <span class="wh-btn-label"><?php echo __t('wms_add_line', 'wms'); ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="wh-grn-workspace__panel">
                        <div class="wh-grn-workspace__scroll" id="whPoLinesScroll">
                            <table class="wh-grn-table wh-grn-table--dense">
                                <thead class="wh-grn-table__head">
                                    <tr>
                                        <th class="wh-grn-th--num">#</th>
                                        <th><?php echo __t('wms_col_product', 'wms'); ?></th>
                                        <th class="wh-grn-th--qty"><?php echo __t('wms_qty_short', 'wms'); ?></th>
                                        <th class="wh-grn-th--cost"><?php echo __t('wms_unit_cost', 'wms'); ?></th>
                                        <th class="wh-grn-th--sub"><?php echo __t('wms_line_subtotal', 'wms'); ?></th>
                                        <th class="wh-grn-th--act" title="<?php echo htmlspecialchars(__t('wms_remove_line', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="material-icons-round wh-grn-th-icon">delete_outline</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="whPoLineItems"></tbody>
                            </table>
                            <div class="wh-grn-empty" id="whPoLinesEmpty" hidden>
                                <div class="wh-grn-empty__icon" aria-hidden="true"><span class="material-icons-round">playlist_add</span></div>
                                <p class="wh-grn-empty__title"><?php echo __t('wms_po_lines_empty', 'wms'); ?></p>
                                <p class="wh-grn-empty__hint"><?php echo __t('wms_po_lines_hint', 'wms'); ?></p>
                                <button type="button" class="wh-btn wh-btn--primary wh-btn--sm" data-trigger-add-line>
                                    <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="wh-grn-modal__footer">
                <p class="wh-grn-modal__error" id="whPoFormError" hidden role="alert"></p>
                <div class="wh-grn-footer-bar">
                    <div class="wh-grn-footer-bar__metrics">
                        <div class="wh-grn-metric">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_items', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whPoLineCount">0</strong>
                        </div>
                        <div class="wh-grn-metric wh-grn-metric--total">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_po_estimated_total', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whPoEstTotal">0</strong>
                        </div>
                    </div>
                    <div class="wh-grn-footer-bar__actions wh-grn-footer-bar__actions--po">
                        <button type="button" class="wh-btn wh-btn--ghost" id="whPoCreateCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
                        <button type="button" class="wh-btn wh-btn--ghost" id="whPoSaveDraft" data-status="draft"><?php echo __t('wms_po_save_draft', 'wms'); ?></button>
                        <button type="submit" class="wh-btn wh-btn--primary wh-grn-btn-submit" data-status="pending">
                            <span class="material-icons-round">send</span><?php echo __t('wms_po_submit', 'wms'); ?>
                        </button>
                    </div>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="whPoDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="whPoDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="whPoDetailTitle"><?php echo __t('wms_po_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whPoDetailSubtitle">—</p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whPoDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whPoDetailBody" class="wms-detail-body wh-po-detail-body"></div>
        <footer class="wms-grn-modal__footer wh-po-detail-actions" id="whPoDetailActions" hidden></footer>
    </div>
</div>

<script>
window.WH_PO_CONFIG = <?php echo json_encode([
    'canPo' => $whCanPo,
    'canApprove' => $whCanApprovePo,
], JSON_UNESCAPED_UNICODE); ?>;
</script>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
