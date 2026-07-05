<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'notifications';
$pageTitle = __t('plat_nav_notifications', 'platform');
$extraStyles = ['platform-comms.css'];
$extraScripts = ['platform-common.js', 'platform-notifications.js'];
$pageI18n = plat_i18n([
    'plat_nav_notifications', 'plat_no_data', 'loading', 'load_error', 'action_success', 'action_error',
    'plat_notif_subtitle', 'plat_notif_badge', 'plat_notif_load_error', 'plat_notif_add',
    'plat_notif_kpi_broadcasts', 'plat_notif_kpi_sent', 'plat_notif_kpi_drafts', 'plat_notif_kpi_templates',
    'plat_notif_kpi_channels', 'plat_notif_col_title', 'plat_notif_col_audience', 'plat_notif_col_status',
    'plat_notif_col_recipients', 'plat_notif_col_sent', 'plat_notif_status_sent', 'plat_notif_status_draft',
    'plat_notif_audience_all', 'plat_notif_audience_active', 'plat_notif_audience_trial', 'plat_notif_audience_suspended',
    'plat_notif_send', 'plat_notif_add_title', 'plat_notif_add_submit', 'plat_notif_add_cancel',
    'plat_notif_field_title_en', 'plat_notif_field_title_fr', 'plat_notif_field_message_en', 'plat_notif_field_message_fr',
    'plat_notif_field_audience', 'plat_notif_channels_title',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-comms plat-comms-notify">
    <div class="plat-comms-error" id="platNotifError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platNotifErrorText"></span>
    </div>
    <div class="plat-comms-alert" id="platNotifAlert" hidden role="status"></div>

    <section class="plat-comms-hero">
        <div class="plat-comms-hero__intro">
            <div class="plat-comms-badge"><span class="material-icons-round" aria-hidden="true">notifications</span><?php echo __t('plat_notif_badge', 'platform'); ?></div>
            <h2 class="plat-comms-hero__title"><?php echo __t('plat_nav_notifications', 'platform'); ?></h2>
            <p class="plat-comms-hero__desc"><?php echo __t('plat_notif_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-comms-hero__actions">
            <button type="button" class="plat-comms-add-btn" id="platNotifAddOpen">
                <span class="material-icons-round" aria-hidden="true">campaign</span>
                <?php echo __t('plat_notif_add', 'platform'); ?>
            </button>
        </div>
    </section>

    <section class="plat-kpi-grid plat-comms-kpi-grid" id="platNotifKpiGrid">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">campaign</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_notif_kpi_broadcasts', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platNotifKpiTotal">—</strong></article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">send</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_notif_kpi_sent', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platNotifKpiSent">—</strong></article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">edit_note</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_notif_kpi_drafts', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platNotifKpiDrafts">—</strong></article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">description</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_notif_kpi_templates', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platNotifKpiTemplates">—</strong></article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">hub</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_notif_kpi_channels', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platNotifKpiChannels">—</strong></article>
    </section>

    <section class="plat-panel plat-comms-panel">
        <h3 style="padding:16px 20px 0;margin:0;font-size:1rem;"><?php echo __t('plat_notif_channels_title', 'platform'); ?></h3>
        <div class="plat-comms-grid" id="platNotifChannels"></div>
        <div class="plat-table-wrap plat-comms-table-wrap">
            <table class="plat-table plat-comms-table">
                <thead><tr>
                    <th><?php echo __t('plat_notif_col_title', 'platform'); ?></th>
                    <th><?php echo __t('plat_notif_col_audience', 'platform'); ?></th>
                    <th><?php echo __t('plat_notif_col_status', 'platform'); ?></th>
                    <th><?php echo __t('plat_notif_col_recipients', 'platform'); ?></th>
                    <th><?php echo __t('plat_notif_col_sent', 'platform'); ?></th>
                    <th></th>
                </tr></thead>
                <tbody id="platNotifBroadcasts"><tr><td colspan="6" class="plat-comms-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr></tbody>
            </table>
        </div>
    </section>
</div>

<div class="plat-comms-modal" id="platNotifModal" hidden>
    <div class="plat-comms-modal__backdrop" data-close-modal></div>
    <form class="plat-comms-modal__panel" id="platNotifForm">
        <header class="plat-comms-modal__head"><h3><?php echo __t('plat_notif_add_title', 'platform'); ?></h3></header>
        <div class="plat-comms-modal__body">
            <label class="plat-comms-field"><span><?php echo __t('plat_notif_field_title_en', 'platform'); ?></span><input name="title_en" required maxlength="200"></label>
            <label class="plat-comms-field"><span><?php echo __t('plat_notif_field_title_fr', 'platform'); ?></span><input name="title_fr" maxlength="200"></label>
            <label class="plat-comms-field"><span><?php echo __t('plat_notif_field_audience', 'platform'); ?></span>
                <select name="audience">
                    <option value="all"><?php echo __t('plat_notif_audience_all', 'platform'); ?></option>
                    <option value="active"><?php echo __t('plat_notif_audience_active', 'platform'); ?></option>
                    <option value="trial"><?php echo __t('plat_notif_audience_trial', 'platform'); ?></option>
                    <option value="suspended"><?php echo __t('plat_notif_audience_suspended', 'platform'); ?></option>
                </select>
            </label>
            <label class="plat-comms-field"><span><?php echo __t('plat_notif_field_message_en', 'platform'); ?></span><textarea name="message_en" rows="4" required></textarea></label>
            <label class="plat-comms-field"><span><?php echo __t('plat_notif_field_message_fr', 'platform'); ?></span><textarea name="message_fr" rows="4"></textarea></label>
        </div>
        <footer class="plat-comms-modal__foot">
            <button type="button" class="plat-comms-btn" data-close-modal><?php echo __t('plat_notif_add_cancel', 'platform'); ?></button>
            <button type="submit" class="plat-comms-btn plat-comms-btn--primary"><?php echo __t('plat_notif_add_submit', 'platform'); ?></button>
        </footer>
    </form>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
