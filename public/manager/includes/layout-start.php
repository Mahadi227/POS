<?php
/** Layout start — expects auth-guard variables. */
$cssFiles = array_merge(['manager-layout.css'], $pageCss ?? []);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#7c3aed">
    <title><?php echo htmlspecialchars($pageTitle); ?> — Supervision RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $mgrPrefix; ?>../../assets/css/admin.css">
    <?php foreach ($cssFiles as $css): ?>
    <link rel="stylesheet" href="<?php echo $mgrPrefix; ?>../../assets/css/manager/<?php echo htmlspecialchars($css); ?>?v=1">
    <?php endforeach; ?>
</head>
<body>
<div class="admin-layout mgr-layout">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main class="main-content">
        <header class="top-header mgr-header">
            <div class="header-left" style="display:flex;align-items:center;gap:16px;">
                <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                    <span class="material-icons-round">menu</span>
                </button>
                <div>
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <p class="date-display mgr-store-label">
                        <?php echo htmlspecialchars($managerConfig['store']['name'] ?? ''); ?>
                        <?php if (!empty($managerConfig['store']['location'])): ?>
                            — <?php echo htmlspecialchars($managerConfig['store']['location']); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="header-right">
                <button type="button" class="ad-refresh-btn" id="mgrRefreshBtn" title="Actualiser">
                    <span class="material-icons-round">refresh</span>
                    Actualiser
                </button>
                <button type="button" class="icon-btn theme-toggle" id="theme-toggle" aria-label="Thème">
                    <span class="material-icons-round">dark_mode</span>
                </button>
            </div>
        </header>
        <div class="dashboard-scroll-area mgr-content">
