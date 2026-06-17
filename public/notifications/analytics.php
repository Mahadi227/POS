<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
if (!$isAdmin) {
    header('Location: notification_center.php');
    exit;
}

$pageTitle = __t('analytics_title', 'notifications');
$notifI18n = notif_i18n(['analytics_title', 'total_sent', 'unread', 'critical_alerts', 'failed_deliveries', 'loading']);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/notifications.css?v=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="notif-page">
    <header class="notif-header">
        <a href="notification_center.php" class="notif-back">← <?php echo __t('center_title', 'notifications'); ?></a>
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </header>
    <div class="notif-analytics">
        <div class="notif-stat-grid" id="notifStats"></div>
        <div class="notif-charts">
            <canvas id="notifChartDay" height="120"></canvas>
            <canvas id="notifChartCategory" height="120"></canvas>
        </div>
    </div>
    <script>window.NOTIF_I18N = <?php echo json_encode($notifI18n, JSON_UNESCAPED_UNICODE); ?>;</script>
    <script>window.NOTIF_API = <?php echo json_encode(['base' => $apiBase], JSON_UNESCAPED_UNICODE); ?>;</script>
    <script src="<?php echo $assetsBase; ?>/js/notifications/notification-api.js?v=1"></script>
    <script src="<?php echo $assetsBase; ?>/js/notifications/notification-analytics.js?v=1"></script>
</body>
</html>
