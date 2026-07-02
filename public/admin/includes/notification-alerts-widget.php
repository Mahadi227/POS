<?php
/**
 * Dashboard alerts widget — enterprise notifications (all modules).
 */
$alertsWidgetTitle = $alertsWidgetTitle ?? __t('alerts_widget', 'notifications');
$alertsWidgetViewAll = $alertsWidgetViewAll ?? __t('view_all', 'notifications');
$notifCenterUrl = '../notifications/notification_center.php';
?>
<div class="card list-widget ad-notif-alerts-card" id="notifAlertsCard" hidden>
    <div class="card-header">
        <h3>
            <span class="material-icons-round ad-notif-alerts-card__icon">notifications_active</span>
            <?php echo htmlspecialchars($alertsWidgetTitle, ENT_QUOTES, 'UTF-8'); ?>
        </h3>
        <a href="<?php echo htmlspecialchars($notifCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-text"><?php echo htmlspecialchars($alertsWidgetViewAll, ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div id="notifAlertsWidget" class="ad-notif-alerts-body"></div>
</div>
