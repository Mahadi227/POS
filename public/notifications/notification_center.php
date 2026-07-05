<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = __t('center_title', 'notifications');
$activePage = 'center';
$notifI18nKeys = [
    'center_title', 'tab_all', 'tab_unread', 'tab_archived', 'tab_pinned', 'search_placeholder',
    'filter_category', 'filter_priority', 'mark_read', 'mark_all_read', 'archive', 'delete', 'restore',
    'pin', 'unpin', 'empty', 'loading', 'priority_low', 'priority_normal', 'priority_high', 'priority_critical',
    'back_dashboard', 'preferences', 'analytics', 'logs', 'all_categories', 'all_priorities',
];
$notifI18n = notif_i18n($notifI18nKeys);
$notifI18n['theme'] = __t('theme', 'admin');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="notifications" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/notif-head-theme.php'; ?>
    <title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo htmlspecialchars($adminBrandName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/notifications.css?v=3">
    <?php require __DIR__ . '/../admin/includes/admin-tail-theme.php'; ?>
</head>
<body class="notif-page">
    <header class="notif-header">
        <div class="notif-header__left">
            <a href="../admin/index.php" class="notif-back" aria-label="<?php echo htmlspecialchars(__t('back_dashboard', 'notifications'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">arrow_back</span>
            </a>
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        </div>
        <div class="notif-header__actions">
            <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
            <button type="button" class="notif-theme-btn theme-toggle" id="theme-toggle" aria-label="<?php echo htmlspecialchars(__t('theme', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">dark_mode</span>
            </button>
            <a href="preferences.php" class="notif-btn notif-btn--ghost notif-btn--hide-sm"><?php echo __t('preferences', 'notifications'); ?></a>
            <?php if ($isAdmin): ?>
            <a href="analytics.php" class="notif-btn notif-btn--ghost notif-btn--hide-sm"><?php echo __t('analytics', 'notifications'); ?></a>
            <?php endif; ?>
            <button type="button" class="notif-btn" id="notifMarkAllRead"><?php echo __t('mark_all_read', 'notifications'); ?></button>
        </div>
    </header>

    <div class="notif-toolbar">
        <div class="notif-tabs" role="tablist">
            <button type="button" class="notif-tab is-active" data-tab="all"><?php echo __t('tab_all', 'notifications'); ?></button>
            <button type="button" class="notif-tab" data-tab="unread"><?php echo __t('tab_unread', 'notifications'); ?> <span class="notif-tab-badge" id="unreadTabBadge" hidden>0</span></button>
            <button type="button" class="notif-tab" data-tab="pinned"><?php echo __t('tab_pinned', 'notifications'); ?></button>
            <button type="button" class="notif-tab" data-tab="archived"><?php echo __t('tab_archived', 'notifications'); ?></button>
        </div>
        <div class="notif-filters">
            <input type="search" id="notifSearch" placeholder="<?php echo __t('search_placeholder', 'notifications'); ?>" class="notif-search">
            <select id="notifCategory" class="notif-select"><option value=""><?php echo __t('all_categories', 'notifications'); ?></option></select>
            <select id="notifPriority" class="notif-select">
                <option value=""><?php echo __t('all_priorities', 'notifications'); ?></option>
                <option value="low"><?php echo __t('priority_low', 'notifications'); ?></option>
                <option value="normal"><?php echo __t('priority_normal', 'notifications'); ?></option>
                <option value="high"><?php echo __t('priority_high', 'notifications'); ?></option>
                <option value="critical"><?php echo __t('priority_critical', 'notifications'); ?></option>
            </select>
        </div>
    </div>

    <main class="notif-main">
        <ul class="notif-list" id="notifList" aria-live="polite"></ul>
        <p class="notif-empty hidden" id="notifEmpty"><?php echo __t('empty', 'notifications'); ?></p>
    </main>

    <script src="<?php echo $assetsBase; ?>/js/app-theme.js?v=2"></script>
    <script>window.NOTIF_I18N = <?php echo json_encode($notifI18n, JSON_UNESCAPED_UNICODE); ?>;</script>
    <script>window.NOTIF_API = <?php echo json_encode(['base' => $apiBase], JSON_UNESCAPED_UNICODE); ?>;</script>
    <script src="<?php echo $assetsBase; ?>/js/notifications/notification-api.js?v=1"></script>
    <script src="<?php echo $assetsBase; ?>/js/notifications/notification-offline.js?v=1"></script>
    <script src="<?php echo $assetsBase; ?>/js/notifications/notification-center.js?v=1"></script>
</body>
</html>
