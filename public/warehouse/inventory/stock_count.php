<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('inventory');

$useWmsModules = true;
$activeWhPage = 'stock_count';
$pageTitle = __t('wh_nav_stock_count', 'warehouse');
$whCanCount = $whCanInventory && !$whReadOnly;
$whCanApproveCount = $whCanManage && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-stock-count.js'];
$extraCss = ['wh-grn-create.css', 'wh-sc-create.css'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_sc_subtitle', 'wh_sc_hint', 'wh_sc_stat_total', 'wh_sc_stat_open', 'wh_sc_stat_variance',
        'wh_sc_stat_completed', 'wh_sc_search', 'wh_sc_empty', 'wh_sc_status_breakdown', 'wh_sc_filter_open',
        'wh_sc_hero_meta', 'wh_sc_link_adjustments', 'wh_sc_link_ledger', 'wh_sc_link_inventory', 'wh_sc_link_history',
        'wh_sc_toast_created', 'wh_sc_toast_submit', 'wh_sc_toast_approve', 'wh_sc_toast_reject',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'cancel', 'save', 'error', 'col_status',
    ]),
    wms_i18n([
        'wms_audit_title', 'wms_audit_subtitle', 'wms_new_audit', 'wms_audit_details', 'wms_audit_form_subtitle',
        'wms_audit_section_info', 'wms_audit_section_lines', 'wms_audit_lines_hint', 'wms_audit_lines_empty',
        'wms_audit_product_search', 'wms_add_line', 'wms_product_duplicate',
        'wms_select_product', 'wms_col_counted_qty', 'wms_product_filter', 'wms_remove_line', 'wms_nav_warehouses',
        'wms_select_warehouse',
        'wms_col_product', 'wms_receipt_notes', 'wms_col_audit_type', 'wms_type_cycle_count', 'wms_type_physical_count',
        'wms_type_spot_check', 'wms_filter_all_status', 'wms_filter_all_types', 'wms_col_items', 'wms_col_expected',
        'wms_col_counted_value', 'wms_col_variance', 'wms_col_system_qty', 'wms_submit_audit', 'wms_approve', 'wms_reject',
        'wms_confirm_submit_audit', 'wms_confirm_approve_audit', 'wms_confirm_reject_audit', 'wms_view_details',
        'wms_status_draft', 'wms_status_in_progress', 'wms_status_pending_approval', 'wms_status_approved',
        'wms_status_rejected', 'col_date',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-sc-hero" aria-labelledby="whScHeroTitle">
    <div class="wh-sc-hero__intro">
        <h2 class="wh-sc-hero__title" id="whScHeroTitle"><?php echo __t('wh_sc_subtitle', 'warehouse'); ?></h2>
        <p class="wh-sc-hero__meta" id="whScHeroMeta" aria-live="polite">—</p>
        <p class="wh-sc-hero__hint"><?php echo __t('wh_sc_hint', 'warehouse'); ?></p>
        <div class="wh-sc-hero__links">
            <a class="wh-sc-hero__link" href="stock_adjustments.php"><?php echo __t('wh_sc_link_adjustments', 'warehouse'); ?></a>
            <a class="wh-sc-hero__link" href="stock_ledger.php"><?php echo __t('wh_sc_link_ledger', 'warehouse'); ?></a>
            <a class="wh-sc-hero__link" href="warehouse_inventory.php"><?php echo __t('wh_sc_link_inventory', 'warehouse'); ?></a>
            <a class="wh-sc-hero__link" href="inventory_history.php"><?php echo __t('wh_sc_link_history', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-sc-hero__stats" role="group">
        <article class="wh-sc-stat wh-sc-stat--primary">
            <span class="wh-sc-stat__label"><?php echo __t('wh_sc_stat_total', 'warehouse'); ?></span>
            <strong class="wh-sc-stat__value is-loading" id="whScStatTotal">—</strong>
        </article>
        <article class="wh-sc-stat wh-sc-stat--warn">
            <span class="wh-sc-stat__label"><?php echo __t('wh_sc_stat_open', 'warehouse'); ?></span>
            <strong class="wh-sc-stat__value is-loading" id="whScStatOpen">—</strong>
        </article>
        <article class="wh-sc-stat wh-sc-stat--danger">
            <span class="wh-sc-stat__label"><?php echo __t('wh_sc_stat_variance', 'warehouse'); ?></span>
            <strong class="wh-sc-stat__value is-loading" id="whScStatVariance">—</strong>
        </article>
        <article class="wh-sc-stat wh-sc-stat--success">
            <span class="wh-sc-stat__label"><?php echo __t('wh_sc_stat_completed', 'warehouse'); ?></span>
            <strong class="wh-sc-stat__value is-loading" id="whScStatCompleted">—</strong>
        </article>
    </div>
</section>

<section class="wh-sc-breakdown" id="whScBreakdownPanel" hidden aria-labelledby="whScBreakdownTitle">
    <div class="wh-sc-breakdown__head">
        <h3 id="whScBreakdownTitle"><?php echo __t('wh_sc_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-sc-status-chips" id="whScStatusChips"></div>
</section>

<div class="wh-sc-toolbar">
    <div class="wh-sc-toolbar__row">
        <div class="wh-sc-toolbar__filters">
            <label class="wh-sc-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whScSearch" class="wh-sc-search" placeholder="<?php echo htmlspecialchars(__t('wh_sc_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whScWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whScType" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wms_col_audit_type', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_types', 'wms'); ?></option>
                <option value="cycle_count"><?php echo __t('wms_type_cycle_count', 'wms'); ?></option>
                <option value="physical_count"><?php echo __t('wms_type_physical_count', 'wms'); ?></option>
                <option value="spot_check"><?php echo __t('wms_type_spot_check', 'wms'); ?></option>
            </select>
            <select id="whScStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="open"><?php echo __t('wh_sc_filter_open', 'warehouse'); ?></option>
                <option value="draft"><?php echo __t('wms_status_draft', 'wms'); ?></option>
                <option value="in_progress"><?php echo __t('wms_status_in_progress', 'wms'); ?></option>
                <option value="pending_approval"><?php echo __t('wms_status_pending_approval', 'wms'); ?></option>
                <option value="approved"><?php echo __t('wms_status_approved', 'wms'); ?></option>
                <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-sc-toolbar__actions">
            <?php if ($whCanCount): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whScNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wms_new_audit', 'wms'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whScExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whScRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-sc-panel" aria-live="polite">
    <div class="wh-sc-table-wrap" id="whScTableWrap"></div>
    <div class="wh-sc-empty" id="whScEmpty" hidden>
        <span class="material-icons-round">fact_check</span>
        <p><?php echo __t('wh_sc_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whScLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-sc-pagination" id="whScPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whScPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-sc-pagination__meta" id="whScPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whScNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-page-meta" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-sc-toast" id="whScToast" role="status" aria-live="polite"></div>

<?php if ($whCanCount): ?>
<div class="wms-modal-overlay" id="whScCreateModal" aria-hidden="true">
    <div class="wh-grn-modal wh-grn-modal--sc" role="dialog" aria-labelledby="whScCreateTitle" aria-modal="true">
        <div class="wh-grn-modal__swipe-zone">
            <div class="wh-grn-modal__handle" aria-hidden="true"></div>
            <header class="wh-grn-modal__header">
                <div class="wh-grn-modal__brand">
                    <div class="wh-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">fact_check</span></div>
                    <div class="wh-grn-modal__titles">
                        <p class="wh-grn-modal__eyebrow"><?php echo __t('wms_audit_section_info', 'wms'); ?></p>
                        <h3 class="wh-grn-modal__title" id="whScCreateTitle"><?php echo __t('wms_new_audit', 'wms'); ?></h3>
                    </div>
                </div>
                <button type="button" class="wh-grn-modal__close" id="whScCreateClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </header>
        </div>

        <form id="whScCreateForm" class="wh-grn-modal__form" novalidate>
            <div class="wh-grn-modal__body">
                <details class="wh-grn-meta-wrap" id="whScMetaWrap">
                    <summary class="wh-grn-meta-wrap__toggle">
                        <span><?php echo __t('wms_audit_section_info', 'wms'); ?></span>
                        <span class="material-icons-round" aria-hidden="true">expand_more</span>
                    </summary>
                    <fieldset class="wh-grn-meta" aria-labelledby="whScMetaTitle">
                        <legend class="wh-grn-sr-only" id="whScMetaTitle"><?php echo __t('wms_audit_section_info', 'wms'); ?></legend>
                        <label class="wh-grn-field wh-grn-field--warehouse">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="warehouse_id" id="whScFormWarehouse" required></select>
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_audit_type', 'wms'); ?></span>
                            <select name="audit_type" required>
                                <option value="cycle_count"><?php echo __t('wms_type_cycle_count', 'wms'); ?></option>
                                <option value="physical_count"><?php echo __t('wms_type_physical_count', 'wms'); ?></option>
                                <option value="spot_check"><?php echo __t('wms_type_spot_check', 'wms'); ?></option>
                            </select>
                        </label>
                        <label class="wh-grn-field wh-grn-field--notes">
                            <span><?php echo __t('wms_receipt_notes', 'wms'); ?></span>
                            <input type="text" name="notes" placeholder="—">
                        </label>
                    </fieldset>
                </details>

                <section class="wh-grn-workspace" aria-labelledby="whScLinesTitle">
                    <div class="wh-grn-workspace__toolbar">
                        <div class="wh-grn-workspace__heading">
                            <h4 class="wh-grn-workspace__title" id="whScLinesTitle"><?php echo __t('wms_audit_section_lines', 'wms'); ?></h4>
                            <p class="wh-grn-workspace__hint"><?php echo __t('wms_audit_lines_hint', 'wms'); ?></p>
                        </div>
                        <div class="wh-grn-workspace__tools">
                            <label class="wh-grn-search">
                                <span class="material-icons-round" aria-hidden="true">search</span>
                                <input type="search" id="whScProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                            </label>
                            <button type="button" class="wh-btn wh-btn--primary wh-btn--sm wh-grn-btn-add" id="whScAddLine">
                                <span class="material-icons-round">add</span>
                                <span class="wh-btn-label"><?php echo __t('wms_add_line', 'wms'); ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="wh-grn-workspace__panel">
                        <div class="wh-grn-workspace__scroll" id="whScLinesScroll">
                            <table class="wh-grn-table wh-grn-table--dense">
                                <thead class="wh-grn-table__head">
                                    <tr>
                                        <th class="wh-grn-th--num">#</th>
                                        <th><?php echo __t('wms_col_product', 'wms'); ?></th>
                                        <th class="wh-grn-th--qty"><?php echo __t('wms_col_counted_qty', 'wms'); ?></th>
                                        <th class="wh-grn-th--act" title="<?php echo htmlspecialchars(__t('wms_remove_line', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="material-icons-round wh-grn-th-icon">delete_outline</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="whScLineItems"></tbody>
                            </table>
                            <div class="wh-grn-empty" id="whScLinesEmpty" hidden>
                                <div class="wh-grn-empty__icon" aria-hidden="true"><span class="material-icons-round">playlist_add</span></div>
                                <p class="wh-grn-empty__title"><?php echo __t('wms_audit_lines_empty', 'wms'); ?></p>
                                <p class="wh-grn-empty__hint"><?php echo __t('wms_audit_lines_hint', 'wms'); ?></p>
                                <button type="button" class="wh-btn wh-btn--primary wh-btn--sm" data-trigger-add-line>
                                    <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="wh-grn-modal__footer">
                <p class="wh-grn-modal__error" id="whScFormError" hidden role="alert"></p>
                <div class="wh-grn-footer-bar">
                    <div class="wh-grn-footer-bar__metrics">
                        <div class="wh-grn-metric">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_items', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whScLineCount">0</strong>
                        </div>
                        <div class="wh-grn-metric wh-grn-metric--total">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_counted_qty', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whScTotalCounted">0</strong>
                        </div>
                    </div>
                    <div class="wh-grn-footer-bar__actions">
                        <button type="button" class="wh-btn wh-btn--ghost" id="whScCreateCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
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

<div class="wms-modal-overlay" id="whScDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="whScDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="whScDetailTitle"><?php echo __t('wms_audit_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whScDetailSubtitle">—</p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whScDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whScDetailBody" class="wms-detail-body wh-sc-detail-body"></div>
        <footer class="wms-grn-modal__footer wh-sc-detail-actions" id="whScDetailActions" hidden></footer>
    </div>
</div>

<script>
window.WH_SC_CONFIG = <?php echo json_encode([
    'canCount' => $whCanCount,
    'canApprove' => $whCanApproveCount,
], JSON_UNESCAPED_UNICODE); ?>;
</script>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
