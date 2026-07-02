<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('transfers');

$useWmsModules = true;
$activeWhPage = 'branch_transfer';
$pageTitle = __t('wh_nav_branch_transfer', 'warehouse');
$whCanCreate = $whCanTransfer && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-branch-transfers.js'];
$extraCss = ['wh-grn-create.css', 'wh-btr-create.css'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_btr_subtitle', 'wh_btr_hint', 'wh_btr_stat_total', 'wh_btr_stat_requested', 'wh_btr_stat_progress',
        'wh_btr_stat_completed', 'wh_btr_search', 'wh_btr_empty', 'wh_btr_hero_meta', 'wh_btr_status_breakdown',
        'wh_btr_link_wh_transfer', 'wh_btr_link_requests', 'wh_btr_filter_active', 'wh_btr_filter_pending',
        'wh_btr_new', 'wh_btr_toast_created', 'wh_btr_toast_approved', 'wh_btr_toast_completed',
        'wh_btr_toast_rejected', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error', 'col_date', 'col_status',
        'save', 'cancel',
    ]),
    wms_i18n([
        'wms_col_transfer', 'wms_col_from', 'wms_col_to', 'wms_col_items', 'wms_col_value', 'wms_col_reason',
        'wms_col_product', 'wms_col_qty', 'wms_col_sku', 'wms_unit_cost', 'wms_view_details', 'wms_transfer_details',
        'wms_filter_all_status', 'wms_status_requested', 'wms_status_approved', 'wms_status_picking',
        'wms_status_in_transit', 'wms_status_received', 'wms_status_completed', 'wms_status_rejected',
        'wms_status_cancelled', 'wms_approve', 'wms_complete', 'wms_reject', 'wms_confirm_approve_trf',
        'wms_confirm_complete_trf', 'wms_confirm_reject_trf', 'wms_col_requested_by', 'wms_col_store',
        'wms_new_transfer', 'wms_transfer_section_info', 'wms_transfer_section_lines',
        'wms_transfer_lines_hint', 'wms_transfer_lines_empty', 'wms_transfer_product_search',
        'wms_transfer_estimated_total', 'wms_add_line', 'wms_select_product', 'wms_qty_short',
        'wms_line_subtotal', 'wms_product_filter', 'wms_remove_line', 'wms_select_store',
        'wms_all_stores', 'wms_type_branch', 'wms_product_duplicate', 'wms_reason_placeholder',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-btr-hero" aria-labelledby="whBtrHeroTitle">
    <div class="wh-btr-hero__intro">
        <h2 class="wh-btr-hero__title" id="whBtrHeroTitle"><?php echo __t('wh_btr_subtitle', 'warehouse'); ?></h2>
        <p class="wh-btr-hero__meta" id="whBtrHeroMeta" aria-live="polite">—</p>
        <p class="wh-btr-hero__hint"><?php echo __t('wh_btr_hint', 'warehouse'); ?></p>
        <div class="wh-btr-hero__links">
            <a class="wh-btr-hero__link" href="warehouse_transfer.php"><?php echo __t('wh_btr_link_wh_transfer', 'warehouse'); ?></a>
            <a class="wh-btr-hero__link" href="transfer_requests.php"><?php echo __t('wh_btr_link_requests', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-btr-hero__stats" role="group">
        <article class="wh-btr-stat">
            <span class="wh-btr-stat__label"><?php echo __t('wh_btr_stat_total', 'warehouse'); ?></span>
            <strong class="wh-btr-stat__value is-loading" id="whBtrStatTotal">—</strong>
        </article>
        <article class="wh-btr-stat wh-btr-stat--warn">
            <span class="wh-btr-stat__label"><?php echo __t('wh_btr_stat_requested', 'warehouse'); ?></span>
            <strong class="wh-btr-stat__value is-loading" id="whBtrStatRequested">—</strong>
        </article>
        <article class="wh-btr-stat wh-btr-stat--primary">
            <span class="wh-btr-stat__label"><?php echo __t('wh_btr_stat_progress', 'warehouse'); ?></span>
            <strong class="wh-btr-stat__value is-loading" id="whBtrStatProgress">—</strong>
        </article>
        <article class="wh-btr-stat wh-btr-stat--success">
            <span class="wh-btr-stat__label"><?php echo __t('wh_btr_stat_completed', 'warehouse'); ?></span>
            <strong class="wh-btr-stat__value is-loading" id="whBtrStatCompleted">—</strong>
        </article>
    </div>
</section>

<section class="wh-btr-breakdown" id="whBtrBreakdownPanel" hidden aria-labelledby="whBtrBreakdownTitle">
    <div class="wh-btr-breakdown__head">
        <h3 id="whBtrBreakdownTitle"><?php echo __t('wh_btr_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-btr-status-chips" id="whBtrStatusChips"></div>
</section>

<div class="wh-btr-toolbar">
    <div class="wh-btr-toolbar__row">
        <div class="wh-btr-toolbar__filters">
            <label class="wh-btr-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whBtrSearch" class="wh-btr-search" placeholder="<?php echo htmlspecialchars(__t('wh_btr_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whBtrStore" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wms_all_stores', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wms_all_stores', 'wms'); ?></option>
            </select>
            <select id="whBtrStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="btr_active"><?php echo __t('wh_btr_filter_active', 'warehouse'); ?></option>
                <option value="btr_pending"><?php echo __t('wh_btr_filter_pending', 'warehouse'); ?></option>
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
        <div class="wh-btr-toolbar__actions">
            <?php if ($whCanCreate): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whBtrNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wh_btr_new', 'warehouse'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whBtrExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whBtrRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-btr-panel" aria-live="polite">
    <div class="wh-btr-table-wrap" id="whBtrTableWrap"></div>
    <div class="wh-btr-empty" id="whBtrEmpty" hidden>
        <span class="material-icons-round">store</span>
        <p><?php echo __t('wh_btr_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whBtrLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-btr-pagination" id="whBtrPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whBtrPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-btr-pagination__meta" id="whBtrPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whBtrNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-btr-toast" id="whBtrToast" role="status" aria-live="polite"></div>

<?php if ($whCanCreate): ?>
<div class="wms-modal-overlay" id="whBtrCreateModal" aria-hidden="true">
    <div class="wh-grn-modal wh-grn-modal--btr" role="dialog" aria-labelledby="whBtrCreateTitle" aria-modal="true">
        <div class="wh-grn-modal__swipe-zone">
            <div class="wh-grn-modal__handle" aria-hidden="true"></div>
            <header class="wh-grn-modal__header">
                <div class="wh-grn-modal__brand">
                    <div class="wh-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">store</span></div>
                    <div class="wh-grn-modal__titles">
                        <p class="wh-grn-modal__eyebrow"><?php echo __t('wms_type_branch', 'wms'); ?></p>
                        <h3 class="wh-grn-modal__title" id="whBtrCreateTitle"><?php echo __t('wh_btr_new', 'warehouse'); ?></h3>
                    </div>
                </div>
                <button type="button" class="wh-grn-modal__close" id="whBtrCreateClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </header>
        </div>

        <form id="whBtrCreateForm" class="wh-grn-modal__form" novalidate>
            <div class="wh-grn-modal__body">
                <details class="wh-grn-meta-wrap" id="whBtrMetaWrap">
                    <summary class="wh-grn-meta-wrap__toggle">
                        <span><?php echo __t('wms_transfer_section_info', 'wms'); ?></span>
                        <span class="material-icons-round" aria-hidden="true">expand_more</span>
                    </summary>
                    <fieldset class="wh-grn-meta" aria-labelledby="whBtrMetaTitle">
                        <legend class="wh-grn-sr-only" id="whBtrMetaTitle"><?php echo __t('wms_transfer_section_info', 'wms'); ?></legend>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_from', 'wms'); ?> — <?php echo __t('wms_col_store', 'wms'); ?></span>
                            <select name="from_store_id" id="whBtrFromStore" required></select>
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_to', 'wms'); ?> — <?php echo __t('wms_col_store', 'wms'); ?></span>
                            <select name="to_store_id" id="whBtrToStore" required></select>
                        </label>
                        <label class="wh-grn-field wh-grn-field--reason">
                            <span><?php echo __t('wms_col_reason', 'wms'); ?></span>
                            <input type="text" name="reason" placeholder="<?php echo htmlspecialchars(__t('wms_reason_placeholder', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                    </fieldset>
                </details>

                <section class="wh-grn-workspace" aria-labelledby="whBtrLinesTitle">
                    <div class="wh-grn-workspace__toolbar">
                        <div class="wh-grn-workspace__heading">
                            <h4 class="wh-grn-workspace__title" id="whBtrLinesTitle"><?php echo __t('wms_transfer_section_lines', 'wms'); ?></h4>
                            <p class="wh-grn-workspace__hint"><?php echo __t('wms_transfer_lines_hint', 'wms'); ?></p>
                        </div>
                        <div class="wh-grn-workspace__tools">
                            <label class="wh-grn-search">
                                <span class="material-icons-round" aria-hidden="true">search</span>
                                <input type="search" id="whBtrProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                            </label>
                            <button type="button" class="wh-btn wh-btn--primary wh-btn--sm wh-grn-btn-add" id="whBtrAddLine">
                                <span class="material-icons-round">add</span>
                                <span class="wh-btn-label"><?php echo __t('wms_add_line', 'wms'); ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="wh-grn-workspace__panel">
                        <div class="wh-grn-workspace__scroll" id="whBtrLinesScroll">
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
                                <tbody id="whBtrLineItems"></tbody>
                            </table>
                            <div class="wh-grn-empty" id="whBtrLinesEmpty" hidden>
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
                <p class="wh-grn-modal__error" id="whBtrFormError" hidden role="alert"></p>
                <div class="wh-grn-footer-bar">
                    <div class="wh-grn-footer-bar__metrics">
                        <div class="wh-grn-metric">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_items', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whBtrLineCount">0</strong>
                        </div>
                        <div class="wh-grn-metric wh-grn-metric--total">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_transfer_estimated_total', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whBtrEstTotal">0</strong>
                        </div>
                    </div>
                    <div class="wh-grn-footer-bar__actions">
                        <button type="button" class="wh-btn wh-btn--ghost" id="whBtrCreateCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
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

<div class="wms-modal-overlay" id="whBtrDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--btr" role="dialog" aria-labelledby="whBtrDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">store</span></div>
                <div>
                    <h3 id="whBtrDetailTitle"><?php echo __t('wms_transfer_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whBtrDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whBtrDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whBtrDetailBody" class="wh-btr-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';
