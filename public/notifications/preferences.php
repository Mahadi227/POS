<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = __t('preferences_title', 'notifications');
$prefs = [];
try {
    require_once __DIR__ . '/../../includes/Notifications/Services/NotificationService.php';
    $prefs = (new NotificationService())->getPreferences((int) $_SESSION['user_id']);
} catch (Throwable $e) {
}
$prefsI18n = notif_i18n([
    'save',
    'reset',
    'prefs_saved',
    'prefs_up_to_date',
    'prefs_unsaved',
    'prefs_saving',
    'channel_email',
    'channel_sms',
    'channel_push',
    'channel_whatsapp',
    'whatsapp_phone',
    'whatsapp_phone_hint',
    'whatsapp_phone_required',
    'channel_browser',
    'sound_enabled',
    'quiet_hours_start',
    'quiet_hours_end',
    'min_priority',
    'priority_low',
    'priority_normal',
    'priority_high',
    'priority_critical',
    'loading',
]);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/notifications.css?v=3">
</head>
<body class="notif-page">
    <header class="notif-header">
        <div class="notif-header__left">
            <a href="notification_center.php" class="notif-back" title="<?php echo __t('center_title', 'notifications'); ?>">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        </div>
        <div class="notif-header__actions">
            <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
            <button type="button" class="notif-theme-btn" id="notifThemeBtn" aria-label="Theme">
                <span class="material-icons-round">dark_mode</span>
            </button>
        </div>
    </header>
    <main class="notif-main notif-main--prefs">
        <div class="notif-alert hidden" id="prefsAlert"></div>

        <form id="notifPrefsForm" class="notif-prefs-pro">
            <section class="notif-prefs-card">
                <div class="notif-prefs-card__head">
                    <h2><?php echo __t('preferences', 'notifications'); ?></h2>
                    <span class="notif-prefs-state" id="prefsState"><?php echo __t('loading', 'notifications'); ?></span>
                </div>

                <div class="notif-prefs-grid">
                    <label class="notif-pref-switch">
                        <input type="checkbox" name="email_enabled" value="1" <?php echo !empty($prefs['email_enabled']) ? 'checked' : ''; ?>>
                        <span><?php echo __t('channel_email', 'notifications'); ?></span>
                    </label>
                    <label class="notif-pref-switch">
                        <input type="checkbox" name="sms_enabled" value="1" <?php echo !empty($prefs['sms_enabled']) ? 'checked' : ''; ?>>
                        <span><?php echo __t('channel_sms', 'notifications'); ?></span>
                    </label>
                    <label class="notif-pref-switch">
                        <input type="checkbox" name="push_enabled" value="1" <?php echo !empty($prefs['push_enabled']) ? 'checked' : ''; ?>>
                        <span><?php echo __t('channel_push', 'notifications'); ?></span>
                    </label>
                    <label class="notif-pref-switch notif-pref-switch--whatsapp">
                        <input type="checkbox" name="whatsapp_enabled" value="1" id="whatsappEnabled" <?php echo !empty($prefs['whatsapp_enabled']) ? 'checked' : ''; ?>>
                        <span><?php echo __t('channel_whatsapp', 'notifications'); ?></span>
                    </label>
                    <div class="notif-prefs-whatsapp-phone<?php echo empty($prefs['whatsapp_enabled']) ? ' hidden' : ''; ?>" id="whatsappPhoneWrap">
                        <label class="notif-prefs-field">
                            <span><?php echo __t('whatsapp_phone', 'notifications'); ?></span>
                            <input type="tel" name="whatsapp_phone" id="whatsappPhone"
                                   value="<?php echo htmlspecialchars($prefs['whatsapp_phone'] ?? '', ENT_QUOTES); ?>"
                                   placeholder="<?php echo htmlspecialchars(__t('whatsapp_phone_hint', 'notifications'), ENT_QUOTES); ?>"
                                   inputmode="tel" autocomplete="tel">
                            <small class="notif-prefs-hint"><?php echo __t('whatsapp_phone_hint', 'notifications'); ?></small>
                        </label>
                    </div>
                    <label class="notif-pref-switch">
                        <input type="checkbox" name="browser_enabled" value="1" <?php echo !empty($prefs['browser_enabled']) ? 'checked' : ''; ?>>
                        <span><?php echo __t('channel_browser', 'notifications'); ?></span>
                    </label>
                    <label class="notif-pref-switch">
                        <input type="checkbox" name="sound_enabled" value="1" <?php echo !empty($prefs['sound_enabled']) ? 'checked' : ''; ?>>
                        <span><?php echo __t('sound_enabled', 'notifications'); ?></span>
                    </label>
                </div>
            </section>

            <section class="notif-prefs-card">
                <div class="notif-prefs-field-row">
                    <label class="notif-prefs-field">
                        <span><?php echo __t('quiet_hours_start', 'notifications'); ?></span>
                        <input type="time" name="quiet_hours_start" value="<?php echo htmlspecialchars($prefs['quiet_hours_start'] ?? '', ENT_QUOTES); ?>">
                    </label>
                    <label class="notif-prefs-field">
                        <span><?php echo __t('quiet_hours_end', 'notifications'); ?></span>
                        <input type="time" name="quiet_hours_end" value="<?php echo htmlspecialchars($prefs['quiet_hours_end'] ?? '', ENT_QUOTES); ?>">
                    </label>
                </div>
                <label class="notif-prefs-field">
                    <span><?php echo __t('min_priority', 'notifications'); ?></span>
                    <select name="min_priority">
                        <?php foreach (['low','normal','high','critical'] as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo ($prefs['min_priority'] ?? 'low') === $p ? 'selected' : ''; ?>>
                            <?php echo __t('priority_' . $p, 'notifications'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </section>

            <div class="notif-prefs-actions">
                <button type="button" class="notif-btn notif-btn--ghost" id="prefsResetBtn"><?php echo __t('reset', 'notifications'); ?></button>
                <button type="submit" class="notif-btn" id="prefsSaveBtn"><?php echo __t('save', 'notifications'); ?></button>
            </div>
        </form>
    </main>

    <script>
    window.NOTIF_API = { base: <?php echo json_encode($apiBase); ?> };
    window.NOTIF_PREFS_I18N = <?php echo json_encode($prefsI18n, JSON_UNESCAPED_UNICODE); ?>;
    window.NOTIF_PREFS_INITIAL = <?php echo json_encode($prefs, JSON_UNESCAPED_UNICODE); ?>;
    window.NOTIF_PREFS_LOCALE = <?php echo json_encode($locale); ?>;
    </script>
    <script src="<?php echo $assetsBase; ?>/js/notifications/notification-api.js?v=1"></script>
    <script src="<?php echo $assetsBase; ?>/js/notifications/notification-preferences.js?v=3"></script>
</body>
</html>
