<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('transfers');

$useWmsModules = true;
$activeWhPage = 'transfer_requests';
$pageTitle = __t('wh_nav_transfer_requests', 'warehouse');
$whCanCreateReq = $whCanManage && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-transfer-requests.js'];
$extraCss = ['wh-grn-create.css', 'wh-trq-create.css'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_trq_subtitle', 'wh_trq_hint', 'wh_trq_stat_total', 'wh_trq_stat_pending', 'wh_trq_stat_approved',
        'wh_trq_stat_urgent', 'wh_trq_search', 'wh_trq_empty', 'wh_trq_hero_meta', 'wh_trq_status_breakdown',
        'wh_trq_link_wh_transfer', 'wh_trq_link_incoming', 'wh_trq_link_outgoing', 'wh_trq_filter_open',
        'wh_trq_filter_active', 'wh_trq_toast_created', 'wh_trq_toast_approved', 'wh_trq_toast_rejected',
        'wh_trq_col_approved', 'wh_trq_col_delivered', 'wh_all_warehouses', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page',
        'records', 'close', 'cancel', 'save', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_requests_title', 'wms_new_request', 'wms_request_section_info',
        'wms_request_section_lines', 'wms_request_lines_hint', 'wms_request_lines_empty',
        'wms_request_product_search', 'wms_add_line', 'wms_select_product', 'wms_qty_short',
        'wms_product_filter', 'wms_remove_line', 'wms_select_store', 'wms_select_warehouse',
        'wms_nav_warehouses', 'wms_col_product', 'wms_col_store', 'wms_col_priority',
        'wms_col_items', 'wms_col_qty', 'wms_col_request', 'wms_col_requested_by', 'wms_receipt_notes',
        'wms_request_details', 'wms_view_details', 'wms_approve', 'wms_approve_warehouse', 'wms_reject',
        'wms_filter_all_status', 'wms_all_stores', 'wms_status_pending', 'wms_status_manager_approved',
        'wms_status_warehouse_approved', 'wms_status_dispatched', 'wms_status_delivered', 'wms_status_rejected',
        'wms_status_cancelled', 'wms_priority_low', 'wms_priority_normal', 'wms_priority_high', 'wms_priority_urgent',
        'wms_confirm_approve_mgr', 'wms_confirm_approve_wh', 'wms_confirm_reject', 'wms_col_sku', 'wms_product_duplicate',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-trq-hero" aria-labelledby="whTrqHeroTitle">
    <div class="wh-trq-hero__intro">
        <h2 class="wh-trq-hero__title" id="whTrqHeroTitle"><?php echo __t('wh_trq_subtitle', 'warehouse'); ?></h2>
        <p class="wh-trq-hero__meta" id="whTrqHeroMeta" aria-live="polite">—</p>
        <p class="wh-trq-hero__hint"><?php echo __t('wh_trq_hint', 'warehouse'); ?></p>
        <div class="wh-trq-hero__links">
            <a class="wh-trq-hero__link" href="warehouse_transfer.php"><?php echo __t('wh_trq_link_wh_transfer', 'warehouse'); ?></a>
            <a class="wh-trq-hero__link" href="incoming_transfers.php"><?php echo __t('wh_trq_link_incoming', 'warehouse'); ?></a>
            <a class="wh-trq-hero__link" href="outgoing_transfers.php"><?php echo __t('wh_trq_link_outgoing', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-trq-hero__stats" role="group">
        <article class="wh-trq-stat wh-trq-stat--primary">
            <span class="wh-trq-stat__label"><?php echo __t('wh_trq_stat_total', 'warehouse'); ?></span>
            <strong class="wh-trq-stat__value is-loading" id="whTrqStatTotal">—</strong>
        </article>
        <article class="wh-trq-stat wh-trq-stat--warn">
            <span class="wh-trq-stat__label"><?php echo __t('wh_trq_stat_pending', 'warehouse'); ?></span>
            <strong class="wh-trq-stat__value is-loading" id="whTrqStatPending">—</strong>
        </article>
        <article class="wh-trq-stat wh-trq-stat--success">
            <span class="wh-trq-stat__label"><?php echo __t('wh_trq_stat_approved', 'warehouse'); ?></span>
            <strong class="wh-trq-stat__value is-loading" id="whTrqStatApproved">—</strong>
        </article>
        <article class="wh-trq-stat wh-trq-stat--danger">
            <span class="wh-trq-stat__label"><?php echo __t('wh_trq_stat_urgent', 'warehouse'); ?></span>
            <strong class="wh-trq-stat__value is-loading" id="whTrqStatUrgent">—</strong>
        </article>
    </div>
</section>

<section class="wh-trq-breakdown" id="whTrqBreakdownPanel" hidden aria-labelledby="whTrqBreakdownTitle">
    <div class="wh-trq-breakdown__head">
        <h3 id="whTrqBreakdownTitle"><?php echo __t('wh_trq_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-trq-status-chips" id="whTrqStatusChips"></div>
</section>

<div class="wh-trq-toolbar">
    <div class="wh-trq-toolbar__row">
        <div class="wh-trq-toolbar__filters">
            <label class="wh-trq-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whTrqSearch" class="wh-trq-search" placeholder="<?php echo htmlspecialchars(__t('wh_trq_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whTrqStore" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wms_all_stores', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wms_all_stores', 'wms'); ?></option>
            </select>
            <select id="whTrqWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whTrqStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="req_open"><?php echo __t('wh_trq_filter_open', 'warehouse'); ?></option>
                <option value="req_active"><?php echo __t('wh_trq_filter_active', 'warehouse'); ?></option>
                <option value="pending"><?php echo __t('wms_status_pending', 'wms'); ?></option>
                <option value="manager_approved"><?php echo __t('wms_status_manager_approved', 'wms'); ?></option>
                <option value="warehouse_approved"><?php echo __t('wms_status_warehouse_approved', 'wms'); ?></option>
                <option value="dispatched"><?php echo __t('wms_status_dispatched', 'wms'); ?></option>
                <option value="delivered"><?php echo __t('wms_status_delivered', 'wms'); ?></option>
                <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
                <option value="cancelled"><?php echo __t('wms_status_cancelled', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-trq-toolbar__actions">
            <?php if ($whCanCreateReq): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whTrqNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wms_new_request', 'wms'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whTrqExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whTrqRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-trq-panel" aria-live="polite">
    <div class="wh-trq-table-wrap" id="whTrqTableWrap"></div>
    <div class="wh-trq-empty" id="whTrqEmpty" hidden>
        <span class="material-icons-round">assignment</span>
        <p><?php echo __t('wh_trq_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whTrqLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-trq-pagination" id="whTrqPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTrqPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-trq-pagination__meta" id="whTrqPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTrqNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-trq-toast" id="whTrqToast" role="status" aria-live="polite"></div>

<?php if ($whCanCreateReq): ?>
<div class="wms-modal-overlay" id="whTrqCreateModal" aria-hidden="true">
    <div class="wh-grn-modal wh-grn-modal--trq" role="dialog" aria-labelledby="whTrqCreateTitle" aria-modal="true">
        <div class="wh-grn-modal__swipe-zone">
            <div class="wh-grn-modal__handle" aria-hidden="true"></div>
            <header class="wh-grn-modal__header">
                <div class="wh-grn-modal__brand">
                    <div class="wh-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">assignment</span></div>
                    <div class="wh-grn-modal__titles">
                        <p class="wh-grn-modal__eyebrow"><?php echo __t('wms_request_section_info', 'wms'); ?></p>
                        <h3 class="wh-grn-modal__title" id="whTrqCreateTitle"><?php echo __t('wms_new_request', 'wms'); ?></h3>
                    </div>
                </div>
                <button type="button" class="wh-grn-modal__close" id="whTrqCreateClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </header>
        </div>

        <form id="whTrqCreateForm" class="wh-grn-modal__form" novalidate>
            <div class="wh-grn-modal__body">
                <details class="wh-grn-meta-wrap" id="whTrqMetaWrap">
                    <summary class="wh-grn-meta-wrap__toggle">
                        <span><?php echo __t('wms_request_section_info', 'wms'); ?></span>
                        <span class="material-icons-round" aria-hidden="true">expand_more</span>
                    </summary>
                    <fieldset class="wh-grn-meta" aria-labelledby="whTrqMetaTitle">
                        <legend class="wh-grn-sr-only" id="whTrqMetaTitle"><?php echo __t('wms_request_section_info', 'wms'); ?></legend>
                        <label class="wh-grn-field wh-grn-field--store">
                            <span><?php echo __t('wms_col_store', 'wms'); ?></span>
                            <select name="store_id" id="whTrqFormStore" required></select>
                        </label>
                        <label class="wh-grn-field wh-grn-field--warehouse">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="warehouse_id" id="whTrqFormWarehouse" required></select>
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_priority', 'wms'); ?></span>
                            <select name="priority" required>
                                <option value="low"><?php echo __t('wms_priority_low', 'wms'); ?></option>
                                <option value="normal" selected><?php echo __t('wms_priority_normal', 'wms'); ?></option>
                                <option value="high"><?php echo __t('wms_priority_high', 'wms'); ?></option>
                                <option value="urgent"><?php echo __t('wms_priority_urgent', 'wms'); ?></option>
                            </select>
                        </label>
                        <label class="wh-grn-field wh-grn-field--notes">
                            <span><?php echo __t('wms_receipt_notes', 'wms'); ?></span>
                            <input type="text" name="notes" placeholder="—">
                        </label>
                    </fieldset>
                </details>

                <section class="wh-grn-workspace" aria-labelledby="whTrqLinesTitle">
                    <div class="wh-grn-workspace__toolbar">
                        <div class="wh-grn-workspace__heading">
                            <h4 class="wh-grn-workspace__title" id="whTrqLinesTitle"><?php echo __t('wms_request_section_lines', 'wms'); ?></h4>
                            <p class="wh-grn-workspace__hint"><?php echo __t('wms_request_lines_hint', 'wms'); ?></p>
                        </div>
                        <div class="wh-grn-workspace__tools">
                            <label class="wh-grn-search">
                                <span class="material-icons-round" aria-hidden="true">search</span>
                                <input type="search" id="whTrqProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                            </label>
                            <button type="button" class="wh-btn wh-btn--primary wh-btn--sm wh-grn-btn-add" id="whTrqAddLine">
                                <span class="material-icons-round">add</span>
                                <span class="wh-btn-label"><?php echo __t('wms_add_line', 'wms'); ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="wh-grn-workspace__panel">
                        <div class="wh-grn-workspace__scroll" id="whTrqLinesScroll">
                            <table class="wh-grn-table wh-grn-table--dense">
                                <thead class="wh-grn-table__head">
                                    <tr>
                                        <th class="wh-grn-th--num">#</th>
                                        <th><?php echo __t('wms_col_product', 'wms'); ?></th>
                                        <th class="wh-grn-th--qty"><?php echo __t('wms_qty_short', 'wms'); ?></th>
                                        <th class="wh-grn-th--act" title="<?php echo htmlspecialchars(__t('wms_remove_line', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="material-icons-round wh-grn-th-icon">delete_outline</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="whTrqLineItems"></tbody>
                            </table>
                            <div class="wh-grn-empty" id="whTrqLinesEmpty" hidden>
                                <div class="wh-grn-empty__icon" aria-hidden="true"><span class="material-icons-round">playlist_add</span></div>
                                <p class="wh-grn-empty__title"><?php echo __t('wms_request_lines_empty', 'wms'); ?></p>
                                <p class="wh-grn-empty__hint"><?php echo __t('wms_request_lines_hint', 'wms'); ?></p>
                                <button type="button" class="wh-btn wh-btn--primary wh-btn--sm" data-trigger-add-line>
                                    <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="wh-grn-modal__footer">
                <p class="wh-grn-modal__error" id="whTrqFormError" hidden role="alert"></p>
                <div class="wh-grn-footer-bar">
                    <div class="wh-grn-footer-bar__metrics">
                        <div class="wh-grn-metric">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_items', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whTrqLineCount">0</strong>
                        </div>
                        <div class="wh-grn-metric wh-grn-metric--total">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_qty', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whTrqTotalQty">0</strong>
                        </div>
                    </div>
                    <div class="wh-grn-footer-bar__actions">
                        <button type="button" class="wh-btn wh-btn--ghost" id="whTrqCreateCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
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

<div class="wms-modal-overlay" id="whTrqDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--trq" role="dialog" aria-labelledby="whTrqDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="whTrqDetailTitle"><?php echo __t('wms_request_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whTrqDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whTrqDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whTrqDetailBody" class="wh-trq-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';
