<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('dispatch');

$useWmsModules = true;
$activeWhPage = 'dispatch_orders';
$pageTitle = __t('wms_dispatch_title', 'wms');
$whCanCreateDispatch = $whCanDispatch && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-dispatch-orders.js'];
$extraCss = ['wh-grn-create.css', 'wh-dsp-create.css'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_dsp_subtitle', 'wh_dsp_hint', 'wh_dsp_stat_total', 'wh_dsp_stat_draft', 'wh_dsp_stat_outgoing',
        'wh_dsp_stat_delivered', 'wh_dsp_search', 'wh_dsp_empty', 'wh_dsp_hero_meta', 'wh_dsp_status_breakdown',
        'wh_dsp_link_history', 'wh_dsp_link_inventory', 'wh_dsp_link_picking', 'wh_dsp_link_packing', 'wh_dsp_link_shipping', 'wh_dsp_filter_open',
        'wh_dsp_filter_in_flight', 'wh_dsp_toast_created', 'wh_dsp_toast_dispatched', 'wh_dsp_lines_empty',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'cancel', 'save', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_dispatch_title', 'wms_dispatch_subtitle', 'wms_new_dispatch', 'wms_dispatch_form_subtitle',
        'wms_dispatch_section_info', 'wms_dispatch_section_lines', 'wms_dispatch_lines_hint',
        'wms_dispatch_product_search', 'wms_product_duplicate',
        'wms_filter_all_status', 'wms_status_draft', 'wms_status_picking', 'wms_status_packed',
        'wms_status_dispatched', 'wms_status_in_transit', 'wms_status_delivered', 'wms_status_cancelled',
        'wms_col_dispatch', 'wms_col_destination', 'wms_col_items', 'wms_col_value', 'wms_col_driver',
        'wms_col_vehicle', 'wms_col_delivery_date', 'wms_dispatch_details', 'wms_dispatch_btn',
        'wms_view_details', 'wms_confirm_dispatch', 'wms_nav_warehouses', 'wms_col_product',
        'wms_add_line', 'wms_select_product', 'wms_qty_short', 'wms_unit_cost', 'wms_line_subtotal',
        'wms_grn_lines_count', 'wms_grn_estimated_total', 'wms_product_filter', 'wms_remove_line',
        'wms_dest_store', 'wms_dest_warehouse', 'wms_select_store', 'wms_select_dest_type',
        'wms_select_warehouse', 'wms_col_store', 'wms_receipt_notes', 'wms_driver_placeholder',
        'wms_vehicle_placeholder', 'wms_col_qty', 'wms_col_sku', 'wms_no_data',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-dsp-hero" aria-labelledby="whDspHeroTitle">
    <div class="wh-dsp-hero__intro">
        <h2 class="wh-dsp-hero__title" id="whDspHeroTitle"><?php echo __t('wh_dsp_subtitle', 'warehouse'); ?></h2>
        <p class="wh-dsp-hero__meta" id="whDspHeroMeta" aria-live="polite">—</p>
        <p class="wh-dsp-hero__hint"><?php echo __t('wh_dsp_hint', 'warehouse'); ?></p>
        <div class="wh-dsp-hero__links">
            <a class="wh-dsp-hero__link" href="pick_list.php"><?php echo __t('wh_dsp_link_picking', 'warehouse'); ?></a>
            <a class="wh-dsp-hero__link" href="packing.php"><?php echo __t('wh_dsp_link_packing', 'warehouse'); ?></a>
            <a class="wh-dsp-hero__link" href="shipping.php"><?php echo __t('wh_dsp_link_shipping', 'warehouse'); ?></a>
            <a class="wh-dsp-hero__link" href="dispatch_history.php"><?php echo __t('wh_dsp_link_history', 'warehouse'); ?></a>
            <a class="wh-dsp-hero__link" href="../inventory/warehouse_inventory.php"><?php echo __t('wh_dsp_link_inventory', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-dsp-hero__stats" role="group">
        <article class="wh-dsp-stat wh-dsp-stat--primary">
            <span class="wh-dsp-stat__label"><?php echo __t('wh_dsp_stat_total', 'warehouse'); ?></span>
            <strong class="wh-dsp-stat__value is-loading" id="whDspStatTotal">—</strong>
        </article>
        <article class="wh-dsp-stat wh-dsp-stat--warn">
            <span class="wh-dsp-stat__label"><?php echo __t('wh_dsp_stat_draft', 'warehouse'); ?></span>
            <strong class="wh-dsp-stat__value is-loading" id="whDspStatDraft">—</strong>
        </article>
        <article class="wh-dsp-stat wh-dsp-stat--info">
            <span class="wh-dsp-stat__label"><?php echo __t('wh_dsp_stat_outgoing', 'warehouse'); ?></span>
            <strong class="wh-dsp-stat__value is-loading" id="whDspStatOutgoing">—</strong>
        </article>
        <article class="wh-dsp-stat wh-dsp-stat--success">
            <span class="wh-dsp-stat__label"><?php echo __t('wh_dsp_stat_delivered', 'warehouse'); ?></span>
            <strong class="wh-dsp-stat__value is-loading" id="whDspStatDelivered">—</strong>
        </article>
    </div>
</section>

<section class="wh-dsp-breakdown" id="whDspBreakdownPanel" hidden aria-labelledby="whDspBreakdownTitle">
    <div class="wh-dsp-breakdown__head">
        <h3 id="whDspBreakdownTitle"><?php echo __t('wh_dsp_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-dsp-status-chips" id="whDspStatusChips"></div>
</section>

<div class="wh-dsp-toolbar">
    <div class="wh-dsp-toolbar__row">
        <div class="wh-dsp-toolbar__filters">
            <label class="wh-dsp-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whDspSearch" class="wh-dsp-search" placeholder="<?php echo htmlspecialchars(__t('wh_dsp_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whDspWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whDspStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="open"><?php echo __t('wh_dsp_filter_open', 'warehouse'); ?></option>
                <option value="in_flight"><?php echo __t('wh_dsp_filter_in_flight', 'warehouse'); ?></option>
                <option value="draft"><?php echo __t('wms_status_draft', 'wms'); ?></option>
                <option value="picking"><?php echo __t('wms_status_picking', 'wms'); ?></option>
                <option value="packed"><?php echo __t('wms_status_packed', 'wms'); ?></option>
                <option value="dispatched"><?php echo __t('wms_status_dispatched', 'wms'); ?></option>
                <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
                <option value="delivered"><?php echo __t('wms_status_delivered', 'wms'); ?></option>
                <option value="cancelled"><?php echo __t('wms_status_cancelled', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-dsp-toolbar__actions">
            <?php if ($whCanCreateDispatch): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whDspNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wms_new_dispatch', 'wms'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whDspExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whDspRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-dsp-panel" aria-live="polite">
    <div class="wh-dsp-table-wrap" id="whDspTableWrap"></div>
    <div class="wh-dsp-empty" id="whDspEmpty" hidden>
        <span class="material-icons-round">local_shipping</span>
        <p><?php echo __t('wh_dsp_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whDspLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-dsp-pagination" id="whDspPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whDspPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-dsp-pagination__meta" id="whDspPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whDspNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-dsp-toast" id="whDspToast" role="status" aria-live="polite"></div>

<?php if ($whCanCreateDispatch): ?>
<div class="wms-modal-overlay" id="whDspCreateModal" aria-hidden="true">
    <div class="wh-grn-modal wh-grn-modal--dsp" role="dialog" aria-labelledby="whDspCreateTitle" aria-modal="true">
        <div class="wh-grn-modal__swipe-zone">
            <div class="wh-grn-modal__handle" aria-hidden="true"></div>
            <header class="wh-grn-modal__header">
                <div class="wh-grn-modal__brand">
                    <div class="wh-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">local_shipping</span></div>
                    <div class="wh-grn-modal__titles">
                        <p class="wh-grn-modal__eyebrow"><?php echo __t('wms_dispatch_section_info', 'wms'); ?></p>
                        <h3 class="wh-grn-modal__title" id="whDspCreateTitle"><?php echo __t('wms_new_dispatch', 'wms'); ?></h3>
                    </div>
                </div>
                <button type="button" class="wh-grn-modal__close" id="whDspCreateClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </header>
        </div>

        <form id="whDspCreateForm" class="wh-grn-modal__form" novalidate>
            <div class="wh-grn-modal__body">
                <details class="wh-grn-meta-wrap" id="whDspMetaWrap">
                    <summary class="wh-grn-meta-wrap__toggle">
                        <span><?php echo __t('wms_dispatch_section_info', 'wms'); ?></span>
                        <span class="material-icons-round" aria-hidden="true">expand_more</span>
                    </summary>
                    <fieldset class="wh-grn-meta" aria-labelledby="whDspMetaTitle">
                        <legend class="wh-grn-sr-only" id="whDspMetaTitle"><?php echo __t('wms_dispatch_section_info', 'wms'); ?></legend>
                        <label class="wh-grn-field wh-grn-field--warehouse">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?> (<?php echo strtolower(__t('wms_dispatch_btn', 'wms')); ?>)</span>
                            <select name="from_warehouse_id" id="whDspFormWarehouse" required></select>
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_select_dest_type', 'wms'); ?></span>
                            <select name="dest_type" id="whDspDestType" required>
                                <option value="store"><?php echo __t('wms_dest_store', 'wms'); ?></option>
                                <option value="warehouse"><?php echo __t('wms_dest_warehouse', 'wms'); ?></option>
                            </select>
                        </label>
                        <label class="wh-grn-field wh-grn-field--dest" id="whDspStoreField">
                            <span><?php echo __t('wms_col_store', 'wms'); ?></span>
                            <select name="to_store_id" id="whDspFormStore"></select>
                        </label>
                        <label class="wh-grn-field wh-grn-field--dest" id="whDspWhDestField" hidden>
                            <span><?php echo __t('wms_dest_warehouse', 'wms'); ?></span>
                            <select name="to_warehouse_id" id="whDspFormWhDest"></select>
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_driver', 'wms'); ?></span>
                            <input type="text" name="driver_name" placeholder="<?php echo htmlspecialchars(__t('wms_driver_placeholder', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_vehicle', 'wms'); ?></span>
                            <input type="text" name="vehicle_number" placeholder="<?php echo htmlspecialchars(__t('wms_vehicle_placeholder', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                        </label>
                        <label class="wh-grn-field wh-grn-field--date">
                            <span><?php echo __t('wms_col_delivery_date', 'wms'); ?></span>
                            <input type="date" name="delivery_date">
                        </label>
                        <label class="wh-grn-field wh-grn-field--notes">
                            <span><?php echo __t('wms_receipt_notes', 'wms'); ?></span>
                            <input type="text" name="notes" placeholder="—">
                        </label>
                    </fieldset>
                </details>

                <section class="wh-grn-workspace" aria-labelledby="whDspLinesTitle">
                    <div class="wh-grn-workspace__toolbar">
                        <div class="wh-grn-workspace__heading">
                            <h4 class="wh-grn-workspace__title" id="whDspLinesTitle"><?php echo __t('wms_dispatch_section_lines', 'wms'); ?></h4>
                            <p class="wh-grn-workspace__hint"><?php echo __t('wms_dispatch_lines_hint', 'wms'); ?></p>
                        </div>
                        <div class="wh-grn-workspace__tools">
                            <label class="wh-grn-search">
                                <span class="material-icons-round" aria-hidden="true">search</span>
                                <input type="search" id="whDspProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                            </label>
                            <button type="button" class="wh-btn wh-btn--primary wh-btn--sm wh-grn-btn-add" id="whDspAddLine">
                                <span class="material-icons-round">add</span>
                                <span class="wh-btn-label"><?php echo __t('wms_add_line', 'wms'); ?></span>
                            </button>
                        </div>
                    </div>

                    <div class="wh-grn-workspace__panel">
                        <div class="wh-grn-workspace__scroll" id="whDspLinesScroll">
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
                                <tbody id="whDspLineItems"></tbody>
                            </table>
                            <div class="wh-grn-empty" id="whDspLinesEmpty" hidden>
                                <div class="wh-grn-empty__icon" aria-hidden="true"><span class="material-icons-round">playlist_add</span></div>
                                <p class="wh-grn-empty__title"><?php echo __t('wh_dsp_lines_empty', 'warehouse'); ?></p>
                                <p class="wh-grn-empty__hint"><?php echo __t('wms_dispatch_lines_hint', 'wms'); ?></p>
                                <button type="button" class="wh-btn wh-btn--primary wh-btn--sm" data-trigger-add-line>
                                    <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="wh-grn-modal__footer">
                <p class="wh-grn-modal__error" id="whDspFormError" hidden role="alert"></p>
                <div class="wh-grn-footer-bar">
                    <div class="wh-grn-footer-bar__metrics">
                        <div class="wh-grn-metric">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_items', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whDspLineCount">0</strong>
                        </div>
                        <div class="wh-grn-metric wh-grn-metric--total">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_grn_estimated_total', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whDspEstTotal">0</strong>
                        </div>
                    </div>
                    <div class="wh-grn-footer-bar__actions">
                        <button type="button" class="wh-btn wh-btn--ghost" id="whDspCreateCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
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

<div class="wms-modal-overlay" id="whDspDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wh-form-modal wh-form-modal--dispatch" role="dialog" aria-labelledby="whDspDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="whDspDetailTitle"><?php echo __t('wms_dispatch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whDspDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whDspDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whDspDetailBody" class="wh-dsp-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';
