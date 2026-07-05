<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'plans';
$pageTitle = __t('plat_nav_plans', 'platform');
$extraStyles = ['platform-plans.css'];
$extraScripts = ['platform-common.js', 'platform-plans.js'];
$pageI18n = plat_i18n([
    'plat_nav_plans', 'plat_col_name', 'plat_col_plan', 'plat_col_stores', 'plat_col_users', 'plat_col_status',
    'plat_modules', 'plat_no_data', 'plat_search', 'plat_clear_filters', 'loading', 'load_error',
    'plat_plans_subtitle', 'plat_plans_badge', 'plat_plans_count', 'plat_plans_load_error',
    'plat_plans_empty', 'plat_plans_empty_hint', 'plat_plans_kpi_total', 'plat_plans_kpi_active',
    'plat_plans_kpi_subscribers', 'plat_plans_kpi_mrr', 'plat_plans_filter_all',
    'plat_plans_filter_active', 'plat_plans_filter_inactive', 'plat_plans_col_price',
    'plat_plans_col_subscribers', 'plat_plans_col_limits', 'plat_plans_status_active',
    'plat_plans_status_inactive', 'plat_plans_view_subs', 'plat_plans_unlimited',
    'plat_plans_edit', 'plat_plans_edit_title', 'plat_plans_edit_save', 'plat_plans_edit_cancel',
    'plat_plans_edit_success', 'plat_plans_edit_error', 'plat_plans_marketing_note',
    'plat_sub_kpi_mrr',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-plans">
    <div class="plat-plans-error" id="platPlansError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platPlansErrorText"></span>
    </div>

    <section class="plat-plans-hero" aria-labelledby="platPlansHeroTitle">
        <div class="plat-plans-hero__intro">
            <div class="plat-plans-badge">
                <span class="material-icons-round" aria-hidden="true">layers</span>
                <?php echo __t('plat_plans_badge', 'platform'); ?>
            </div>
            <h2 class="plat-plans-hero__title" id="platPlansHeroTitle"><?php echo __t('plat_nav_plans', 'platform'); ?></h2>
            <p class="plat-plans-hero__desc"><?php echo __t('plat_plans_subtitle', 'platform'); ?></p>
        </div>
        <p class="plat-plans-count" id="platPlansCount" aria-live="polite"></p>
    </section>

    <section class="plat-kpi-grid plat-plans-kpi-grid" id="platPlansKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">layers</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_plans_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPlansKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_plans_kpi_active', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPlansKpiActive">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">business</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_plans_kpi_subscribers', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPlansKpiSubs">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">payments</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_plans_kpi_mrr', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPlansKpiMrr">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-plans-panel">
        <div class="plat-plans-toolbar" id="platPlansFilters">
            <div class="plat-plans-search-wrap">
                <span class="material-icons-round plat-plans-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platPlansSearch" class="plat-search plat-plans-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platPlansStatusFilter" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_plans_filter_all', 'platform'); ?></option>
                <option value="active"><?php echo __t('plat_plans_filter_active', 'platform'); ?></option>
                <option value="inactive"><?php echo __t('plat_plans_filter_inactive', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-plans-clear-btn" id="platPlansClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-plans-grid" id="platPlansGrid" aria-live="polite">
            <div class="plat-plans-loading">
                <span class="plat-plans-spinner" aria-hidden="true"></span>
                <?php echo __t('loading', 'platform'); ?>…
            </div>
        </div>

        <div class="plat-plans-empty" id="platPlansEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">layers_clear</span>
            <h3><?php echo __t('plat_plans_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_plans_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<div class="plat-plan-modal" id="platPlanModal" hidden>
    <div class="plat-plan-modal__backdrop" data-close-modal></div>
    <div class="plat-plan-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="platPlanModalTitle">
        <div class="plat-plan-modal__head">
            <h3 id="platPlanModalTitle"><?php echo __t('plat_plans_edit_title', 'platform'); ?></h3>
            <button type="button" class="plat-plan-modal__close" data-close-modal aria-label="<?php echo __t('plat_plans_edit_cancel', 'platform'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <p class="plat-plan-modal__note">
            <span class="material-icons-round" aria-hidden="true">storefront</span>
            <?php echo __t('plat_plans_marketing_note', 'platform'); ?>
        </p>
        <form id="platPlanEditForm" class="plat-plan-modal__form">
            <input type="hidden" id="platPlanEditId" name="id">
            <div class="plat-plan-modal__field">
                <label for="platPlanEditCode"><?php echo __t('plat_col_plan', 'platform'); ?></label>
                <input type="text" id="platPlanEditCode" readonly>
            </div>
            <div class="plat-plan-modal__field">
                <label for="platPlanEditName"><?php echo __t('plat_col_name', 'platform'); ?></label>
                <input type="text" id="platPlanEditName" name="name" required>
            </div>
            <div class="plat-plan-modal__row">
                <div class="plat-plan-modal__field">
                    <label for="platPlanEditPrice"><?php echo __t('plat_plans_col_price', 'platform'); ?></label>
                    <input type="number" id="platPlanEditPrice" name="price_monthly" min="0" step="0.01" required>
                </div>
                <div class="plat-plan-modal__field">
                    <label for="platPlanEditCurrency">Currency</label>
                    <input type="text" id="platPlanEditCurrency" name="currency" maxlength="3" required>
                </div>
            </div>
            <div class="plat-plan-modal__row">
                <div class="plat-plan-modal__field">
                    <label for="platPlanEditStores"><?php echo __t('plat_col_stores', 'platform'); ?></label>
                    <input type="number" id="platPlanEditStores" name="max_stores" min="0" placeholder="<?php echo __t('plat_plans_unlimited', 'platform'); ?>">
                </div>
                <div class="plat-plan-modal__field">
                    <label for="platPlanEditUsers"><?php echo __t('plat_col_users', 'platform'); ?></label>
                    <input type="number" id="platPlanEditUsers" name="max_users" min="0" placeholder="<?php echo __t('plat_plans_unlimited', 'platform'); ?>">
                </div>
            </div>
            <label class="plat-plan-modal__check">
                <input type="checkbox" id="platPlanEditActive" name="is_active" value="1">
                <span><?php echo __t('plat_plans_status_active', 'platform'); ?></span>
            </label>
            <p class="plat-plan-modal__error" id="platPlanEditError" hidden></p>
            <div class="plat-plan-modal__actions">
                <button type="button" class="plat-plans-clear-btn" data-close-modal><?php echo __t('plat_plans_edit_cancel', 'platform'); ?></button>
                <button type="submit" class="plat-plan-card__link plat-plan-card__link--button" id="platPlanEditSave"><?php echo __t('plat_plans_edit_save', 'platform'); ?></button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
