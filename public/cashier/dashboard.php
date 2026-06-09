<?php

/**
 * Tableau de bord caissier — statistiques du jour, ventes récentes, accès rapide.
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
$storeName = htmlspecialchars($posConfig['store']['name'] ?? 'RetailPOS', ENT_QUOTES, 'UTF-8');
$dateLabel = date('d M Y');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Tableau de bord — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-dashboard.css?v=1">
</head>

<body class="cd-page">
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <h1>Tableau de bord</h1>
                </div>
                <div class="header-right">
                    <button type="button" class="cd-refresh-btn" id="dashRefreshBtn" title="Actualiser">
                        <span class="material-icons-round">refresh</span>
                        Actualiser
                    </button>
                    <div class="user-profile">
                        <div class="user-info">
                            <span class="user-name"><?php echo $displayName; ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <section class="cd-hero">
                    <div class="cd-hero__content">
                        <p class="cd-hero__eyebrow">
                            <span class="material-icons-round">storefront</span>
                            <span id="dashStoreName"><?php echo $storeName; ?></span>
                        </p>
                        <h2 id="heroGreeting">Bonjour, <?php echo $displayName; ?> !</h2>
                        <p class="cd-hero__sub">Voici le résumé de votre activité aujourd'hui.</p>
                        <div class="cd-hero__meta">
                            <span>
                                <span class="material-icons-round">calendar_today</span>
                                <?php echo $dateLabel; ?>
                            </span>
                            <span>
                                <span class="material-icons-round">badge</span>
                                <?php echo $displayRole; ?>
                            </span>
                        </div>
                    </div>
                    <div class="cd-hero__actions">
                        <span class="cd-hero__clock" id="dashLiveClock">--:--:--</span>
                        <span class="cd-hero__date"><?php echo $dateLabel; ?></span>
                        <a href="pos.php" class="cd-btn-pos">
                            <span class="material-icons-round">point_of_sale</span>
                            Ouvrir la caisse
                        </a>
                    </div>
                </section>

                <h3 class="cd-section-title">Statistiques du jour</h3>
                <div class="cd-stats">
                    <article class="cd-stat">
                        <div class="cd-stat__icon cd-stat__icon--blue">
                            <span class="material-icons-round">receipt_long</span>
                        </div>
                        <div>
                            <div class="cd-stat__label">Ventes effectuées</div>
                            <div class="cd-stat__value is-loading" id="todaySalesCount">Chargement…</div>
                            <div class="cd-stat__hint" id="lastSaleHint">—</div>
                        </div>
                    </article>
                    <article class="cd-stat">
                        <div class="cd-stat__icon cd-stat__icon--green">
                            <span class="material-icons-round">payments</span>
                        </div>
                        <div>
                            <div class="cd-stat__label">Chiffre d'affaires</div>
                            <div class="cd-stat__value is-loading" id="todayRevenue">Chargement…</div>
                            <div class="cd-stat__hint">Total encaissé aujourd'hui</div>
                        </div>
                    </article>
                    <article class="cd-stat">
                        <div class="cd-stat__icon cd-stat__icon--purple">
                            <span class="material-icons-round">trending_up</span>
                        </div>
                        <div>
                            <div class="cd-stat__label">Panier moyen</div>
                            <div class="cd-stat__value is-loading" id="avgTicket">Chargement…</div>
                            <div class="cd-stat__hint">Par transaction</div>
                        </div>
                    </article>
                    <article class="cd-stat">
                        <div class="cd-stat__icon cd-stat__icon--amber">
                            <span class="material-icons-round">schedule</span>
                        </div>
                        <div>
                            <div class="cd-stat__label">Session</div>
                            <div class="cd-stat__value" style="font-size:1.1rem;">Active</div>
                            <div class="cd-stat__hint">Connecté en tant que caissier</div>
                        </div>
                    </article>
                </div>

                <div class="cd-grid">
                    <section class="cd-panel">
                        <div class="cd-panel__head">
                            <h3>Dernières ventes</h3>
                            <a href="sales_history.php">Tout voir</a>
                        </div>
                        <ul class="cd-sales-list" id="recentSalesList">
                            <li class="cd-empty">
                                <span class="material-icons-round">hourglass_empty</span>
                                <p>Chargement…</p>
                            </li>
                        </ul>
                    </section>

                    <section class="cd-panel">
                        <div class="cd-panel__head">
                            <h3>Répartition des paiements</h3>
                        </div>
                        <div class="cd-pay-bars" id="paymentBars">
                            <div class="cd-empty">
                                <span class="material-icons-round">hourglass_empty</span>
                                <p>Chargement…</p>
                            </div>
                        </div>
                    </section>
                </div>

                <h3 class="cd-section-title">Accès rapide</h3>
                <div class="cd-actions">
                    <a href="pos.php" class="cd-action cd-action--primary">
                        <span class="cd-action__icon">
                            <span class="material-icons-round">point_of_sale</span>
                        </span>
                        <h4>Terminal de caisse</h4>
                        <p>Scanner, vendre et encaisser les clients</p>
                    </a>
                    <a href="sales_history.php" class="cd-action">
                        <span class="cd-action__icon">
                            <span class="material-icons-round">history</span>
                        </span>
                        <h4>Historique</h4>
                        <p>Consulter et réimprimer les tickets</p>
                    </a>
                    <a href="returns.php" class="cd-action">
                        <span class="cd-action__icon">
                            <span class="material-icons-round">assignment_return</span>
                        </span>
                        <h4>Retours</h4>
                        <p>Rembourser ou échanger un article</p>
                    </a>
                    <a href="customers.php" class="cd-action">
                        <span class="cd-action__icon">
                            <span class="material-icons-round">people</span>
                        </span>
                        <h4>Clients</h4>
                        <p>Gérer la base clients du magasin</p>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <script>
    window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/cashier/dashboard.js?v=1"></script>
</body>

</html>