<?php

/** Layout start — expects auth-guard variables. */

$cssFiles = array_merge(['manager-layout.css'], $pageCss ?? []);

$cssVersion = 8;

$storeLabel = htmlspecialchars($managerConfig['store']['name'] ?? '', ENT_QUOTES, 'UTF-8');

if (!empty($managerConfig['store']['location'])) {

    $storeLabel .= ' — ' . htmlspecialchars($managerConfig['store']['location'], ENT_QUOTES, 'UTF-8');

}

?>

<!DOCTYPE html>

<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="manager" data-theme-accent="#7c3aed">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <meta name="theme-color" content="#7c3aed">

    <meta name="theme-accent" content="#7c3aed">

    <?php
    $themeAccent = '#7c3aed';
    $themePortal = 'manager';
    include __DIR__ . '/../../includes/theme-head.php';
    ?>

    <title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo htmlspecialchars(__t('app_title_suffix', 'manager'), ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo $mgrPrefix; ?>../../assets/css/admin.css">

    <link rel="stylesheet" href="<?php echo $mgrPrefix; ?>../../assets/css/admin-dashboard.css?v=5">

    <?php foreach ($cssFiles as $css): ?>

    <link rel="stylesheet" href="<?php echo $mgrPrefix; ?>../../assets/css/manager/<?php echo htmlspecialchars($css); ?>?v=<?php echo $cssVersion; ?>">

    <?php endforeach; ?>

</head>

<body class="<?php echo htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">

<div class="admin-layout mgr-layout">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <main class="main-content">

        <header class="top-header mgr-page-header ad-page-header">

            <div class="header-left mgr-header-left ad-header-left">

                <button type="button" class="icon-btn mobile-menu-btn mgr-header-menu ad-header-menu" id="mobileMenuBtn" aria-label="<?php echo htmlspecialchars(__t('menu', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">

                    <span class="material-icons-round">menu</span>

                </button>

                <div class="header-title-group">

                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

                    <div class="header-subline">

                        <span class="date-display" id="mgrHeaderDate">—</span>

                        <span class="header-dot" aria-hidden="true">·</span>

                        <span class="mgr-last-updated" id="lastUpdated" aria-live="polite"></span>

                        <?php if ($storeLabel !== ''): ?>

                        <span class="header-dot mgr-store-dot" aria-hidden="true">·</span>

                        <span class="mgr-store-label"><?php echo $storeLabel; ?></span>

                        <?php endif; ?>

                    </div>

                </div>

            </div>



            <div class="header-tools mgr-header-tools ad-header-tools">

                <?php include __DIR__ . '/../../includes/language_switcher.php'; ?>

                <div class="mgr-header-user user-profile">

                    <div class="user-info">

                        <span class="name"><?php echo htmlspecialchars($managerConfig['user']['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>

                        <span class="role"><?php echo htmlspecialchars($managerConfig['user']['role'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>

                    </div>

                </div>

            </div>



            <div class="header-actions mgr-header-actions ad-header-actions">

                <button type="button" class="ad-refresh-btn mgr-header-refresh" id="mgrRefreshBtn" title="<?php echo htmlspecialchars(__t('refresh', 'manager'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(__t('refresh', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">

                    <span class="material-icons-round">refresh</span>

                    <span class="mgr-refresh-label"><?php echo htmlspecialchars(__t('refresh', 'manager'), ENT_QUOTES, 'UTF-8'); ?></span>

                </button>

                <?php include __DIR__ . '/theme-toggle.php'; ?>

            </div>

        </header>

        <div class="dashboard-scroll-area mgr-content">

