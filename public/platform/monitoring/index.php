<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'monitoring';
$pageTitle = __t('plat_nav_monitoring', 'platform');
$extraStyles = ['platform-status.css'];
$extraScripts = ['platform-common.js', 'platform-status.js'];
$pageI18n = plat_i18n([
    'plat_status_components', 'plat_status_incidents', 'plat_status_create',
    'plat_status_title', 'plat_status_message', 'plat_status_severity', 'plat_no_data',
    'plat_status_public', 'plat_status_subtitle', 'plat_status_badge', 'plat_status_overall',
    'plat_status_operational', 'plat_status_degraded', 'plat_status_partial_outage',
    'plat_status_major_outage', 'plat_status_maintenance',
    'plat_severity_minor', 'plat_severity_major', 'plat_severity_critical',
    'plat_component_api', 'plat_component_pos', 'plat_component_portals',
    'plat_component_sync', 'plat_component_billing', 'plat_status_affects',
    'plat_status_resolve', 'plat_status_incident_open', 'plat_status_incident_resolved',
    'plat_status_create_ok', 'plat_status_create_error', 'plat_status_load_error',
    'plat_status_confirm_resolve', 'loading', 'load_error', 'action_success', 'action_error',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-status-console">
    <div class="plat-status-alert" id="platStatusAlert" hidden role="status"></div>
    <div class="plat-status-error" id="platStatusError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platStatusErrorText"></span>
    </div>

    <section class="plat-status-hero" aria-labelledby="platStatusHeroTitle">
        <div class="plat-status-hero__intro">
            <div class="plat-status-badge">
                <span class="material-icons-round" aria-hidden="true">monitor_heart</span>
                <?php echo __t('plat_status_badge', 'platform'); ?>
            </div>
            <h2 class="plat-status-hero__title" id="platStatusHeroTitle"><?php echo __t('plat_nav_status', 'platform'); ?></h2>
            <p class="plat-status-hero__desc"><?php echo __t('plat_status_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-status-overall" id="platOverallStatus" aria-live="polite">
            <span class="plat-status-overall__dot" aria-hidden="true"></span>
            <div>
                <span class="plat-status-overall__label"><?php echo __t('plat_status_overall', 'platform'); ?></span>
                <strong class="plat-status-overall__value" id="platOverallLabel"><?php echo __t('loading', 'platform'); ?>…</strong>
            </div>
        </div>
        <a href="../status.php" class="plat-status-public-link" target="_blank" rel="noopener noreferrer">
            <span class="material-icons-round" aria-hidden="true">public</span>
            <?php echo __t('plat_status_public', 'platform'); ?>
        </a>
    </section>

    <div class="plat-status-grid">
        <section class="plat-panel plat-status-components-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">hub</span>
                <?php echo __t('plat_status_components', 'platform'); ?>
            </h2>
            <ul class="plat-component-grid" id="platComponentList" aria-live="polite">
                <li class="plat-status-loading">
                    <span class="plat-status-spinner" aria-hidden="true"></span>
                    <?php echo __t('loading', 'platform'); ?>…
                </li>
            </ul>
        </section>

        <section class="plat-panel plat-status-form-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">report</span>
                <?php echo __t('plat_status_create', 'platform'); ?>
            </h2>
            <form id="platIncidentForm" class="plat-incident-form" novalidate>
                <div class="plat-form-field">
                    <label for="platIncidentTitle"><?php echo __t('plat_status_title', 'platform'); ?></label>
                    <input type="text" id="platIncidentTitle" name="title" required maxlength="255"
                           placeholder="<?php echo htmlspecialchars(__t('plat_status_title', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="plat-form-field">
                    <label for="platIncidentMessage"><?php echo __t('plat_status_message', 'platform'); ?></label>
                    <textarea id="platIncidentMessage" name="message" rows="4" required
                              placeholder="<?php echo htmlspecialchars(__t('plat_status_message', 'platform'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
                </div>
                <div class="plat-form-field">
                    <label for="platIncidentSeverity"><?php echo __t('plat_status_severity', 'platform'); ?></label>
                    <select id="platIncidentSeverity" name="severity">
                        <option value="minor"><?php echo __t('plat_severity_minor', 'platform'); ?></option>
                        <option value="major"><?php echo __t('plat_severity_major', 'platform'); ?></option>
                        <option value="critical"><?php echo __t('plat_severity_critical', 'platform'); ?></option>
                    </select>
                </div>
                <fieldset class="plat-affects-fieldset">
                    <legend><?php echo __t('plat_status_affects', 'platform'); ?></legend>
                    <div class="plat-affects-grid">
                        <label class="plat-affect-chip">
                            <input type="checkbox" name="affects" value="api">
                            <span><?php echo __t('plat_component_api', 'platform'); ?></span>
                        </label>
                        <label class="plat-affect-chip">
                            <input type="checkbox" name="affects" value="pos">
                            <span><?php echo __t('plat_component_pos', 'platform'); ?></span>
                        </label>
                        <label class="plat-affect-chip">
                            <input type="checkbox" name="affects" value="portals">
                            <span><?php echo __t('plat_component_portals', 'platform'); ?></span>
                        </label>
                        <label class="plat-affect-chip">
                            <input type="checkbox" name="affects" value="sync">
                            <span><?php echo __t('plat_component_sync', 'platform'); ?></span>
                        </label>
                        <label class="plat-affect-chip">
                            <input type="checkbox" name="affects" value="billing">
                            <span><?php echo __t('plat_component_billing', 'platform'); ?></span>
                        </label>
                    </div>
                </fieldset>
                <button type="submit" class="plat-status-submit-btn" id="platIncidentSubmit">
                    <span class="material-icons-round" aria-hidden="true">add_alert</span>
                    <span class="btn-label"><?php echo __t('plat_status_create', 'platform'); ?></span>
                    <span class="plat-status-spinner plat-status-spinner--btn" aria-hidden="true"></span>
                </button>
            </form>
        </section>
    </div>

    <section class="plat-panel plat-status-incidents-panel">
        <div class="plat-status-incidents-head">
            <h2>
                <span class="material-icons-round" aria-hidden="true">history</span>
                <?php echo __t('plat_status_incidents', 'platform'); ?>
            </h2>
            <span class="plat-status-incidents-count" id="platIncidentsCount" aria-live="polite"></span>
        </div>
        <div class="plat-incident-list" id="platIncidentList" aria-live="polite">
            <div class="plat-status-loading">
                <span class="plat-status-spinner" aria-hidden="true"></span>
                <?php echo __t('loading', 'platform'); ?>…
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
