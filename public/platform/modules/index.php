<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'modules';
$pageTitle = __t('plat_nav_modules', 'platform');
$extraStyles = ['platform-modules.css'];
$extraScripts = ['platform-common.js', 'platform-modules.js'];
$pageI18n = plat_i18n([
    'plat_nav_modules', 'plat_modules', 'plat_col_plan', 'plat_no_data', 'plat_search',
    'plat_clear_filters', 'loading', 'load_error',
    'plat_modules_subtitle', 'plat_modules_badge', 'plat_modules_count', 'plat_modules_load_error',
    'plat_modules_empty', 'plat_modules_empty_hint', 'plat_modules_kpi_total', 'plat_modules_kpi_plans',
    'plat_modules_kpi_overrides', 'plat_modules_kpi_tenants', 'plat_modules_col_workspace',
    'plat_modules_col_plans', 'plat_modules_col_adoption', 'plat_modules_plan_included',
    'plat_modules_plan_excluded', 'plat_modules_tenants_plan', 'plat_modules_overrides_on',
    'plat_modules_overrides_off', 'plat_modules_view_plans', 'plat_module_inherit',
    'plat_mod_pos', 'plat_mod_inventory', 'plat_mod_cash_registers', 'plat_mod_manager',
    'plat_mod_warehouse', 'plat_mod_accounting', 'plat_mod_api_access', 'plat_mod_white_label',
    'plat_mod_ws_cashier', 'plat_mod_ws_admin', 'plat_mod_ws_cash_registers', 'plat_mod_ws_manager',
    'plat_mod_ws_warehouse', 'plat_mod_ws_accounting', 'plat_mod_ws_api', 'plat_mod_ws_branding',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-modules">
    <div class="plat-modules-error" id="platModulesError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platModulesErrorText"></span>
    </div>

    <section class="plat-modules-hero" aria-labelledby="platModulesHeroTitle">
        <div class="plat-modules-hero__intro">
            <div class="plat-modules-badge">
                <span class="material-icons-round" aria-hidden="true">extension</span>
                <?php echo __t('plat_modules_badge', 'platform'); ?>
            </div>
            <h2 class="plat-modules-hero__title" id="platModulesHeroTitle"><?php echo __t('plat_nav_modules', 'platform'); ?></h2>
            <p class="plat-modules-hero__desc"><?php echo __t('plat_modules_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-modules-hero__actions">
            <p class="plat-modules-count" id="platModulesCount" aria-live="polite"></p>
            <a href="<?php echo htmlspecialchars(plat_href('plans/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-modules-link-btn">
                <span class="material-icons-round" aria-hidden="true">layers</span>
                <?php echo __t('plat_modules_view_plans', 'platform'); ?>
            </a>
        </div>
    </section>

    <section class="plat-kpi-grid plat-modules-kpi-grid" id="platModulesKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">extension</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_modules_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platModKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">layers</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_modules_kpi_plans', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platModKpiPlans">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">tune</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_modules_kpi_overrides', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platModKpiOverrides">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">business</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_modules_kpi_tenants', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platModKpiTenants">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-modules-panel">
        <div class="plat-modules-toolbar">
            <div class="plat-modules-search-wrap">
                <span class="material-icons-round plat-modules-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platModulesSearch" class="plat-search plat-modules-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <button type="button" class="plat-modules-clear-btn" id="platModulesClearSearch" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-modules-grid" id="platModulesGrid" aria-live="polite">
            <div class="plat-modules-loading">
                <span class="plat-modules-spinner" aria-hidden="true"></span>
                <?php echo __t('loading', 'platform'); ?>…
            </div>
        </div>

        <div class="plat-modules-empty" id="platModulesEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">extension_off</span>
            <h3><?php echo __t('plat_modules_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_modules_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
