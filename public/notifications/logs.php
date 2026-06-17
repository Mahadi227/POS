<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if (!$isAdmin) { header('Location: notification_center.php'); exit; }

require_once __DIR__ . '/../../includes/Notifications/Services/NotificationService.php';
$logs = (new NotificationService())->logs(['limit' => 200]);
$pageTitle = __t('logs', 'notifications');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/notifications.css?v=1">
</head>
<body class="notif-page">
    <header class="notif-header">
        <a href="notification_center.php" class="notif-back">← <?php echo __t('center_title', 'notifications'); ?></a>
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </header>
    <main class="notif-main" style="max-width:1200px">
        <table style="width:100%;border-collapse:collapse;font-size:0.875rem">
            <thead><tr style="text-align:left;border-bottom:1px solid #e2e8f0">
                <th style="padding:8px">Action</th><th>Channel</th><th>Status</th><th>Date</th>
            </tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr style="border-bottom:1px solid #f1f5f9">
                <td style="padding:8px"><?php echo htmlspecialchars($log['action']); ?></td>
                <td><?php echo htmlspecialchars($log['channel_slug'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($log['status']); ?></td>
                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
