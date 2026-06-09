<?php

/**
 * Profil caissier — informations personnelles et mot de passe.
 */
require_once '../../includes/Config/session.php';
requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['cashier', 'admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/includes/pos-config.php';

$displayName = htmlspecialchars($_SESSION['name'] ?? 'Caissier', ENT_QUOTES, 'UTF-8');
$displayRole = htmlspecialchars($_SESSION['role'] ?? 'Caissier', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Mon profil — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-profile.css?v=1">
</head>

<body class="cp-page">
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <h1>Mon profil</h1>
                </div>
                <div class="header-right">
                    <div class="user-profile">
                        <div class="avatar"><?php echo strtoupper(substr($_SESSION['name'] ?? 'C', 0, 1)); ?></div>
                        <div class="user-info">
                            <span class="user-name"><?php echo $displayName; ?></span>
                            <span class="user-role"><?php echo $displayRole; ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div id="profileRoot">
                    <div class="cp-loading">
                        <span class="material-icons-round">hourglass_empty</span>
                        <p>Chargement du profil…</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="cp-toast" id="profileToast" role="status" aria-live="polite"></div>

    <?php include 'includes/scripts.php'; ?>
    <script>
        window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/cashier/profile.js?v=1"></script>
</body>

</html>