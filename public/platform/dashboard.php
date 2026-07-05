<?php
require __DIR__ . '/includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'dashboard';
$pageTitle = __t('plat_nav_dashboard', 'platform');
$extraStyles = ['platform-dashboard.css'];
$extraScripts = ['platform-common.js', 'platform-dashboard.js'];
$platformName = htmlspecialchars($_SESSION['platform_name'] ?? '', ENT_QUOTES, 'UTF-8');
$pageI18n = plat_i18n([
    'plat_kpi_tenants', 'plat_kpi_active', 'plat_kpi_stores', 'plat_kpi_users',
    'plat_tenants_by_status', 'plat_schema_version', 'plat_dash_subtitle', 'plat_dash_welcome',
    'plat_dash_badge', 'plat_dash_quick_actions', 'plat_status_trial', 'plat_status_active',
    'plat_status_suspended', 'plat_status_cancelled', 'plat_dash_system', 'plat_dash_manage_tenants',
    'plat_dash_incidents', 'plat_dash_public_status', 'plat_dash_load_error', 'load_error',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<div class="plat-dash">
    <div class="plat-dash-error" id="platDashError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platDashErrorText"></span>
    </div>

    <section class="plat-dash-hero" aria-labelledby="platDashHeroTitle">
        <div class="plat-dash-hero__intro">
            <div class="plat-dash-badge">
                <span class="material-icons-round" aria-hidden="true">cloud</span>
                <?php echo __t('plat_dash_badge', 'platform'); ?>
            </div>
            <h2 class="plat-dash-hero__title" id="platDashHeroTitle"><?php echo __t('plat_dash_subtitle', 'platform'); ?></h2>
            <?php if ($platformName !== ''): ?>
            <p class="plat-dash-welcome"><?php echo sprintf(__t('plat_dash_welcome', 'platform'), $platformName); ?></p>
            <?php endif; ?>
        </div>

        <div class="plat-kpi-grid plat-kpi-grid--hero" id="platKpiGrid" role="group" aria-label="<?php echo htmlspecialchars(__t('plat_nav_dashboard', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">
            <article class="plat-kpi-card plat-kpi-card--icon is-loading" data-kpi="tenants">
                <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">business</span></span>
                <span class="plat-kpi-card__label"><?php echo __t('plat_kpi_tenants', 'platform'); ?></span>
                <strong class="plat-kpi-card__value" id="platKpiTenants">—</strong>
            </article>
            <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading" data-kpi="active">
                <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">verified</span></span>
                <span class="plat-kpi-card__label"><?php echo __t('plat_kpi_active', 'platform'); ?></span>
                <strong class="plat-kpi-card__value" id="platKpiActive">—</strong>
            </article>
            <article class="plat-kpi-card plat-kpi-card--icon is-loading" data-kpi="stores">
                <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">storefront</span></span>
                <span class="plat-kpi-card__label"><?php echo __t('plat_kpi_stores', 'platform'); ?></span>
                <strong class="plat-kpi-card__value" id="platKpiStores">—</strong>
            </article>
            <article class="plat-kpi-card plat-kpi-card--icon is-loading" data-kpi="users">
                <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">groups</span></span>
                <span class="plat-kpi-card__label"><?php echo __t('plat_kpi_users', 'platform'); ?></span>
                <strong class="plat-kpi-card__value" id="platKpiUsers">—</strong>
            </article>
        </div>

        <nav class="plat-quick-actions" aria-label="<?php echo htmlspecialchars(__t('plat_dash_quick_actions', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">
            <a href="<?php echo htmlspecialchars(plat_href('companies/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-quick-btn">
                <span class="material-icons-round" aria-hidden="true">business</span>
                <?php echo __t('plat_dash_manage_tenants', 'platform'); ?>
            </a>
            <a href="<?php echo htmlspecialchars(plat_href('monitoring/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-quick-btn">
                <span class="material-icons-round" aria-hidden="true">monitor_heart</span>
                <?php echo __t('plat_dash_incidents', 'platform'); ?>
            </a>
            <a href="../status.php" class="plat-quick-btn plat-quick-btn--accent" target="_blank" rel="noopener noreferrer">
                <span class="material-icons-round" aria-hidden="true">public</span>
                <?php echo __t('plat_dash_public_status', 'platform'); ?>
            </a>
        </nav>
    </section>

    <div class="plat-dash-grid">
        <section class="plat-panel plat-dash-status-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">donut_small</span>
                <?php echo __t('plat_tenants_by_status', 'platform'); ?>
            </h2>
            <ul class="plat-status-breakdown" id="platStatusBreakdown" aria-live="polite">
                <li class="plat-status-row plat-status-row--trial" data-status="trial">
                    <div class="plat-status-row__head">
                        <span class="plat-badge plat-badge--trial"><?php echo __t('plat_status_trial', 'platform'); ?></span>
                        <strong id="platStatTrial">—</strong>
                    </div>
                    <div class="plat-status-row__bar" aria-hidden="true"><span id="platBarTrial"></span></div>
                </li>
                <li class="plat-status-row plat-status-row--active" data-status="active">
                    <div class="plat-status-row__head">
                        <span class="plat-badge plat-badge--active"><?php echo __t('plat_status_active', 'platform'); ?></span>
                        <strong id="platStatActive">—</strong>
                    </div>
                    <div class="plat-status-row__bar" aria-hidden="true"><span id="platBarActive"></span></div>
                </li>
                <li class="plat-status-row plat-status-row--suspended" data-status="suspended">
                    <div class="plat-status-row__head">
                        <span class="plat-badge plat-badge--suspended"><?php echo __t('plat_status_suspended', 'platform'); ?></span>
                        <strong id="platStatSuspended">—</strong>
                    </div>
                    <div class="plat-status-row__bar" aria-hidden="true"><span id="platBarSuspended"></span></div>
                </li>
                <li class="plat-status-row plat-status-row--cancelled" data-status="cancelled">
                    <div class="plat-status-row__head">
                        <span class="plat-badge plat-badge--cancelled"><?php echo __t('plat_status_cancelled', 'platform'); ?></span>
                        <strong id="platStatCancelled">—</strong>
                    </div>
                    <div class="plat-status-row__bar" aria-hidden="true"><span id="platBarCancelled"></span></div>
                </li>
            </ul>
        </section>

        <section class="plat-panel plat-dash-system-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">settings</span>
                <?php echo __t('plat_dash_system', 'platform'); ?>
            </h2>
            <dl class="plat-system-meta">
                <div class="plat-system-meta__row">
                    <dt><?php echo __t('plat_schema_version', 'platform'); ?></dt>
                    <dd><code id="platSchemaVersion">—</code></dd>
                </div>
            </dl>
            <div class="plat-system-links">
                <a href="../developers/index.php" class="plat-link-btn" target="_blank" rel="noopener noreferrer">
                    <span class="material-icons-round" aria-hidden="true">code</span>
                    <?php echo __t('plat_dash_dev_portal', 'platform'); ?>
                </a>
                <a href="../admin/index.php" class="plat-link-btn">
                    <span class="material-icons-round" aria-hidden="true">storefront</span>
                    <?php echo __t('plat_open_tenant_app', 'platform'); ?>
                </a>
            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>
