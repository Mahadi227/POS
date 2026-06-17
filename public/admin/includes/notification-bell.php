<?php
/**
 * Admin header notification bell + dropdown (enterprise).
 * Expects: __t(), $activeLang optional
 */
$notifUnread = 0;
try {
    if (!empty($_SESSION['user_id'])) {
        require_once __DIR__ . '/../../../includes/Notifications/Services/NotificationService.php';
        $notifUnread = (new NotificationService())->unreadCount((int) $_SESSION['user_id']);
    }
} catch (Throwable $e) {
    $notifUnread = 0;
}

$notifCenterUrl = '../notifications/notification_center.php';
$notifPrefsUrl = '../notifications/preferences.php';
$notifTitle = __t('notif_title', 'notifications');
$notifEmpty = __t('notif_empty', 'notifications');
$notifMarkAll = __t('mark_all_read', 'notifications');
$notifViewAll = __t('view_all', 'notifications');
$notifPreferences = __t('preferences', 'notifications');
?>
<div class="ad-notif-wrap" id="adminNotifWrap">
    <button type="button" class="icon-btn ad-notif-btn" id="adminNotifBtn"
        aria-label="<?php echo htmlspecialchars($notifTitle, ENT_QUOTES, 'UTF-8'); ?>"
        aria-expanded="false" aria-haspopup="true">
        <span class="material-icons-round">notifications</span>
        <span class="ad-notif-badge" id="adminNotifBadge"<?php echo $notifUnread > 0 ? '' : ' hidden'; ?>>
            <?php echo $notifUnread > 99 ? '99+' : (string) $notifUnread; ?>
        </span>
    </button>
    <div class="ad-notif-backdrop" id="adminNotifBackdrop" aria-hidden="true"></div>
    <div class="ad-notif-panel" id="adminNotifPanel" role="menu" aria-label="<?php echo htmlspecialchars($notifTitle, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="ad-notif-panel__handle" aria-hidden="true"></div>
        <div class="ad-notif-panel__head">
            <div class="ad-notif-panel__title">
                <strong><?php echo htmlspecialchars($notifTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                <span class="ad-notif-panel__count" id="adminNotifUnreadLabel"<?php echo $notifUnread > 0 ? '' : ' hidden'; ?>>
                    <?php echo (int) $notifUnread; ?> <?php echo __t('unread', 'notifications'); ?>
                </span>
            </div>
            <div class="ad-notif-panel__actions">
                <button type="button" class="ad-notif-mark" id="adminNotifMarkRead" title="<?php echo htmlspecialchars($notifMarkAll, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round ad-notif-mark__icon">done_all</span>
                    <span class="ad-notif-mark__text"><?php echo htmlspecialchars($notifMarkAll, ENT_QUOTES, 'UTF-8'); ?></span>
                </button>
                <button type="button" class="ad-notif-close" id="adminNotifClose" aria-label="<?php echo __t('close', 'admin'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
        </div>
        <div class="ad-notif-tabs" role="tablist">
            <button type="button" class="ad-notif-tab is-active" data-filter="all" role="tab"><?php echo __t('tab_all', 'notifications'); ?></button>
            <button type="button" class="ad-notif-tab" data-filter="unread" role="tab"><?php echo __t('tab_unread', 'notifications'); ?></button>
            <button type="button" class="ad-notif-tab" data-filter="critical" role="tab"><?php echo __t('priority_critical', 'notifications'); ?></button>
        </div>
        <ul class="ad-notif-list" id="adminNotifList">
            <li class="ad-notif-empty"><?php echo __t('loading', 'notifications'); ?></li>
        </ul>
        <div class="ad-notif-panel__foot">
            <a href="<?php echo htmlspecialchars($notifPrefsUrl, ENT_QUOTES, 'UTF-8'); ?>" class="ad-notif-foot-link">
                <span class="material-icons-round">tune</span>
                <?php echo htmlspecialchars($notifPreferences, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <a href="<?php echo htmlspecialchars($notifCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" class="ad-notif-footer">
                <?php echo htmlspecialchars($notifViewAll, ENT_QUOTES, 'UTF-8'); ?> →
            </a>
        </div>
    </div>
</div>
