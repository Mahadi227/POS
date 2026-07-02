<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('transfers');

$useWmsModules = true;
$activeWhPage = 'warehouse_transfer';
$pageTitle = __t('wh_nav_wh_transfer', 'warehouse');
$whCanCreate = $whCanTransfer && !$whReadOnly;
$whCanApprove = $whCanManage && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-transfers.js'];
$extraCss = ['wh-grn-create.css', 'wh-trf-create.css'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_trf_subtitle', 'wh_trf_hint', 'wh_trf_stat_total', 'wh_trf_stat_requested', 'wh_trf_stat_progress',
        'wh_trf_stat_completed', 'wh_trf_search', 'wh_trf_empty', 'wh_trf_hero_meta', 'wh_trf_status_breakdown',
        'wh_trf_link_requests', 'wh_trf_link_incoming', 'wh_trf_link_outgoing', 'wh_trf_filter_active',
        'wh_trf_filter_pending', 'wh_trf_new', 'wh_trf_toast_created', 'wh_trf_toast_approved',
        'wh_trf_toast_completed', 'wh_trf_toast_rejected', 'wh_all_warehouses', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page',
        'records', 'close', 'error', 'col_date', 'col_status', 'save', 'cancel',
    ]),
    wms_i18n([
        'wms_col_transfer', 'wms_col_type', 'wms_col_from', 'wms_col_to', 'wms_col_items', 'wms_col_value',
        'wms_col_reason', 'wms_col_product', 'wms_col_qty', 'wms_col_sku', 'wms_unit_cost', 'wms_view_details',
        'wms_transfer_details', 'wms_filter_all_status', 'wms_status_requested', 'wms_status_approved',
        'wms_status_picking', 'wms_status_in_transit', 'wms_status_received', 'wms_status_completed',
        'wms_status_rejected', 'wms_status_cancelled', 'wms_type_wh_wh', 'wms_type_wh_store', 'wms_type_store_wh',
        'wms_type_branch', 'wms_approve', 'wms_complete', 'wms_reject', 'wms_confirm_approve_trf',
        'wms_confirm_complete_trf', 'wms_confirm_reject_trf', 'wms_col_requested_by', 'wms_nav_warehouses',
        'wms_col_store',         'wms_new_transfer', 'wms_transfer_section_info', 'wms_transfer_section_lines', 'wms_transfer_lines_hint',
        'wms_transfer_lines_empty', 'wms_transfer_product_search', 'wms_transfer_estimated_total',
        'wms_add_line', 'wms_select_product', 'wms_qty_short', 'wms_line_subtotal', 'wms_product_filter',
        'wms_remove_line', 'wms_select_store', 'wms_select_warehouse', 'wms_reason_placeholder', 'wms_product_duplicate',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-trf-hero" aria-labelledby="whTrfHeroTitle">
    <div class="wh-trf-hero__intro">
        <h2 class="wh-trf-hero__title" id="whTrfHeroTitle"><?php echo __t('wh_trf_subtitle', 'warehouse'); ?></h2>
        <p class="wh-trf-hero__meta" id="whTrfHeroMeta" aria-live="polite">—</p>
        <p class="wh-trf-hero__hint"><?php echo __t('wh_trf_hint', 'warehouse'); ?></p>
        <div class="wh-trf-hero__links">
            <a class="wh-trf-hero__link" href="transfer_requests.php"><?php echo __t('wh_trf_link_requests', 'warehouse'); ?></a>
            <a class="wh-trf-hero__link" href="incoming_transfers.php"><?php echo __t('wh_trf_link_incoming', 'warehouse'); ?></a>
            <a class="wh-trf-hero__link" href="outgoing_transfers.php"><?php echo __t('wh_trf_link_outgoing', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-trf-hero__stats" role="group">
        <article class="wh-trf-stat">
            <span class="wh-trf-stat__label"><?php echo __t('wh_trf_stat_total', 'warehouse'); ?></span>
            <strong class="wh-trf-stat__value is-loading" id="whTrfStatTotal">—</strong>
        </article>
        <article class="wh-trf-stat wh-trf-stat--warn">
            <span class="wh-trf-stat__label"><?php echo __t('wh_trf_stat_requested', 'warehouse'); ?></span>
            <strong class="wh-trf-stat__value is-loading" id="whTrfStatRequested">—</strong>
        </article>
        <article class="wh-trf-stat wh-trf-stat--primary">
            <span class="wh-trf-stat__label"><?php echo __t('wh_trf_stat_progress', 'warehouse'); ?></span>
            <strong class="wh-trf-stat__value is-loading" id="whTrfStatProgress">—</strong>
        </article>
        <article class="wh-trf-stat wh-trf-stat--success">
            <span class="wh-trf-stat__label"><?php echo __t('wh_trf_stat_completed', 'warehouse'); ?></span>
            <strong class="wh-trf-stat__value is-loading" id="whTrfStatCompleted">—</strong>
        </article>
    </div>
</section>

<section class="wh-trf-breakdown" id="whTrfBreakdownPanel" hidden aria-labelledby="whTrfBreakdownTitle">
    <div class="wh-trf-breakdown__head">
        <h3 id="whTrfBreakdownTitle"><?php echo __t('wh_trf_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-trf-status-chips" id="whTrfStatusChips"></div>
</section>

<div class="wh-trf-toolbar">
    <div class="wh-trf-toolbar__row">
        <div class="wh-trf-toolbar__filters">
            <label class="wh-trf-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whTrfSearch" class="wh-trf-search" placeholder="<?php echo htmlspecialchars(__t('wh_trf_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whTrfWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whTrfStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="trf_active"><?php echo __t('wh_trf_filter_active', 'warehouse'); ?></option>
                <option value="trf_pending"><?php echo __t('wh_trf_filter_pending', 'warehouse'); ?></option>
                <option value="requested"><?php echo __t('wms_status_requested', 'wms'); ?></option>
                <option value="approved"><?php echo __t('wms_status_approved', 'wms'); ?></option>
                <option value="picking"><?php echo __t('wms_status_picking', 'wms'); ?></option>
                <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
                <option value="received"><?php echo __t('wms_status_received', 'wms'); ?></option>
                <option value="completed"><?php echo __t('wms_status_completed', 'wms'); ?></option>
                <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
                <option value="cancelled"><?php echo __t('wms_status_cancelled', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-trf-toolbar__actions">
            <?php if ($whCanCreate): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whTrfNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wh_trf_new', 'warehouse'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whTrfExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whTrfRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-trf-panel" aria-live="polite">
    <div class="wh-trf-table-wrap" id="whTrfTableWrap"></div>
    <div class="wh-trf-empty" id="whTrfEmpty" hidden>
        <span class="material-icons-round">swap_horiz</span>
        <p><?php echo __t('wh_trf_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whTrfLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-trf-pagination" id="whTrfPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTrfPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-trf-pagination__meta" id="whTrfPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTrfNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-trf-toast" id="whTrfToast" role="status" aria-live="polite"></div>

<?php if ($whCanCreate): ?>
<div class="wms-modal-overlay" id="whTrfCreateModal" aria-hidden="true">
    <div class="wh-grn-modal wh-grn-modal--trf" role="dialog" aria-labelledby="whTrfCreateTitle" aria-modal="true">
        <div class="wh-grn-modal__swipe-zone">
            <div class="wh-grn-modal__handle" aria-hidden="true"></div>
            <header class="wh-grn-modal__header">
                <div class="wh-grn-modal__brand">
                    <div class="wh-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">swap_horiz</span></div>
                    <div class="wh-grn-modal__titles">
                        <p class="wh-grn-modal__eyebrow"><?php echo __t('wms_transfer_section_info', 'wms'); ?></p>
                        <h3 class="wh-grn-modal__title" id="whTrfCreateTitle"><?php echo __t('wms_new_transfer', 'wms'); ?></h3>
                    </div>
                </div>
                <button type="button" class="wh-grn-modal__close" id="whTrfCreateClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </header>
        </div>

        <form id="whTrfCreateForm" class="wh-grn-modal__form" novalidate>
            <div class="wh-grn-modal__body">
                <details class="wh-grn-meta-wrap" id="whTrfMetaWrap">
                    <summary class="wh-grn-meta-wrap__toggle">
                        <span><?php echo __t('wms_transfer_section_info', 'wms'); ?></span>
                        <span class="material-icons-round" aria-hidden="true">expand_more</span>
                    </summary>
                    <fieldset class="wh-grn-meta" aria-labelledby="whTrfMetaTitle">
                        <legend class="wh-grn-sr-only" id="whTrfMetaTitle"><?php echo __t('wms_transfer_section_info', 'wms'); ?></legend>
                        <label class="wh-grn-field wh-grn-field--type">
                            <span><?php echo __t('wms_col_type', 'wms'); ?></span>
                            <select name="transfer_type" id="whTrfType" required>
                                <option value="warehouse_to_warehouse"><?php echo __t('wms_type_wh_wh', 'wms'); ?></option>
                                <option value="warehouse_to_store"><?php echo __t('wms_type_wh_store', 'wms'); ?></option>
                                <option value="store_to_warehouse"><?php echo __t('wms_type_store_wh', 'wms'); ?></option>
                                <option value="branch_to_branch"><?php echo __t('wms_type_branch', 'wms'); ?></option>
                            </select>
                        </label>
                        <label class="wh-grn-field" id="whTrfFromWhField">
                            <span><?php echo __t('wms_col_from', 'wms'); ?> — <?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="from_warehouse_id" id="whTrfFromWh"></select>
                        </label>
                        <label class="wh-grn-field" id="whTrfFromStoreField" hidden>
                            <span><?php echo __t('wms_col_from', 'wms'); ?> — <?php echo __t('wms_col_store', 'wms'); ?></span>
                            <select name="from_store_id" id="whTrfFromStore"></select>
                        </label>
                        <label class="wh-grn-field" id="whTrfToWhField">
                            <span><?php echo __t('wms_col_to', 'wms'); ?> — <?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="to_warehouse_id" id="whTrfToWh"></select>
                        </label>
                        <label class="wh-grn-field" id="whTrfToStoreField" hidden>
                            <span><?php echo __t('wms_col_to', 'wms'); ?> — <?php echo __t('wms_col_store', 'wms'); ?></span>
                            <select name="to_store_id" id="whTrfToStore"></select>
                        </label>
                        <label class="wh-grn-field wh-grn-field--reason">
                            <span><?php echo __t('wms_col_reason', 'wms'); ?></span>
                            <input type="text" name="reason" placeholder="<?php echo htmlspecialchars(__t('wms_reason_placeholder', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </fieldset>
                </details>

                <section class="wh-grn-workspace" aria-labelledby="whTrfLinesTitle">
                    <div class="wh-grn-workspace__toolbar">
                        <div class="wh-grn-workspace__heading">
                            <h4 class="wh-grn-workspace__title" id="whTrfLinesTitle"><?php echo __t('wms_transfer_section_lines', 'wms'); ?></h4>
                            <p class="wh-grn-workspace__hint"><?php echo __t('wms_transfer_lines_hint', 'wms'); ?></p>
                        </div>
                        <div class="wh-grn-workspace__tools">
                            <label class="wh-grn-search">
                                <span class="material-icons-round" aria-hidden="true">search</span>
                                <input type="search" id="whTrfProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                            </label>
                            <button type="button" class="wh-btn wh-btn--primary wh-btn--sm wh-grn-btn-add" id="whTrfAddLine">
                                <span class="material-icons-round">add</span>
                                <span class="wh-btn-label"><?php echo __t('wms_add_line', 'wms'); ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="wh-grn-workspace__panel">
                        <div class="wh-grn-workspace__scroll" id="whTrfLinesScroll">
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
                                <tbody id="whTrfLineItems"></tbody>
                            </table>
                            <div class="wh-grn-empty" id="whTrfLinesEmpty" hidden>
                                <div class="wh-grn-empty__icon" aria-hidden="true"><span class="material-icons-round">playlist_add</span></div>
                                <p class="wh-grn-empty__title"><?php echo __t('wms_transfer_lines_empty', 'wms'); ?></p>
                                <p class="wh-grn-empty__hint"><?php echo __t('wms_transfer_lines_hint', 'wms'); ?></p>
                                <button type="button" class="wh-btn wh-btn--primary wh-btn--sm" data-trigger-add-line>
                                    <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="wh-grn-modal__footer">
                <p class="wh-grn-modal__error" id="whTrfFormError" hidden role="alert"></p>
                <div class="wh-grn-footer-bar">
                    <div class="wh-grn-footer-bar__metrics">
                        <div class="wh-grn-metric">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_items', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whTrfLineCount">0</strong>
                        </div>
                        <div class="wh-grn-metric wh-grn-metric--total">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_transfer_estimated_total', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whTrfEstTotal">0</strong>
                        </div>
                    </div>
                    <div class="wh-grn-footer-bar__actions">
                        <button type="button" class="wh-btn wh-btn--ghost" id="whTrfCreateCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
                        <button type="submit" class="wh-btn wh-btn--primary wh-grn-btn-submit">
                            <span class="material-icons-round">save</span><?php echo __t('save', 'warehouse'); ?>
                        </button>
                    </div>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="whTrfDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--trf" role="dialog" aria-labelledby="whTrfDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="whTrfDetailTitle"><?php echo __t('wms_transfer_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whTrfDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whTrfDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whTrfDetailBody" class="wh-trf-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';
