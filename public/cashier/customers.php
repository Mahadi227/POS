<?php
/**
 * Gestion des clients — caissier (liste, recherche, ajout, modification).
 */
require_once '../../includes/Config/session.php';
requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['cashier', 'admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}

$displayName = htmlspecialchars($_SESSION['name'] ?? 'Caissier', ENT_QUOTES, 'UTF-8');
$displayRole = htmlspecialchars($_SESSION['role'] ?? 'Caissier', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Clients — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-customers.css?v=1">
</head>

<body class="cu-page">
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <h1>Gestion des clients</h1>
                </div>
                <div class="header-right">
                    <a href="pos.php" class="cu-btn cu-btn--outline" style="height:40px;font-size:0.85rem;">
                        <span class="material-icons-round">point_of_sale</span>
                        Caisse
                    </a>
                    <div class="user-profile">
                        <div class="user-info">
                            <span class="user-name"><?php echo $displayName; ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="cu-toolbar">
                    <div class="cu-search">
                        <span class="material-icons-round">search</span>
                        <input type="search" id="customerSearch" placeholder="Rechercher par nom, téléphone ou e-mail…"
                            autocomplete="off">
                    </div>
                    <button type="button" class="cu-btn cu-btn--outline" id="refreshCustomersBtn">
                        <span class="material-icons-round">refresh</span>
                    </button>
                    <button type="button" class="cu-btn cu-btn--primary" id="addCustomerBtn">
                        <span class="material-icons-round">person_add</span>
                        Nouveau client
                    </button>
                </div>

                <div class="cu-summary">
                    <div class="cu-summary-card">
                        <span class="cu-summary-card__icon material-icons-round">groups</span>
                        <div>
                            <div class="cu-summary-card__label">Total clients</div>
                            <div class="cu-summary-card__value" id="totalCustomers">0</div>
                        </div>
                    </div>
                    <div class="cu-summary-card">
                        <span class="cu-summary-card__icon material-icons-round"
                            style="background:rgba(16,185,129,0.12);color:#059669;">filter_list</span>
                        <div>
                            <div class="cu-summary-card__label">Affichage</div>
                            <div class="cu-summary-card__value" id="filteredCustomers" style="font-size:0.95rem;">—
                            </div>
                        </div>
                    </div>
                </div>

                <section class="cu-panel">
                    <div class="cu-panel__head">
                        <h2>Base clients</h2>
                        <span class="cu-count" id="panelCountLabel">Chargement…</span>
                    </div>
                    <div class="cu-table-wrap">
                        <table class="cu-table">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Téléphone</th>
                                    <th>E-mail</th>
                                    <th>Activité</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="customersTableBody">
                                <tr>
                                    <td colspan="5" style="text-align:center;padding:40px;">Chargement…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="cu-cards" id="customersCards" aria-label="Clients (mobile)"></div>
                </section>
            </div>
        </main>
    </div>

    <!-- Modal ajout / édition -->
    <div class="cu-modal" id="customerModal" aria-hidden="true">
        <div class="cu-modal__backdrop" data-close-modal></div>
        <div class="cu-modal__box" role="dialog" aria-labelledby="modalTitle">
            <header class="cu-modal__head">
                <h3 id="modalTitle">Nouveau client</h3>
                <button type="button" class="cu-modal__close" id="closeModalBtn" aria-label="Fermer">
                    <span class="material-icons-round">close</span>
                </button>
            </header>
            <form id="customerForm">
                <div class="cu-modal__body">
                    <div class="cu-field">
                        <label for="formName">Nom complet *</label>
                        <input type="text" id="formName" name="name" required minlength="2" maxlength="120"
                            placeholder="Ex: Kouassi Jean">
                    </div>
                    <div class="cu-field">
                        <label for="formPhone">Téléphone</label>
                        <input type="tel" id="formPhone" name="phone" placeholder="07 XX XX XX XX" inputmode="tel">
                    </div>
                    <div class="cu-field">
                        <label for="formEmail">E-mail</label>
                        <input type="email" id="formEmail" name="email" placeholder="client@email.com">
                    </div>
                </div>
                <footer class="cu-modal__foot">
                    <button type="button" class="cu-btn cu-btn--outline" data-close-modal>Annuler</button>
                    <button type="submit" class="cu-btn cu-btn--primary" id="saveCustomerBtn">
                        <span class="material-icons-round">save</span>
                        Enregistrer
                    </button>
                </footer>
            </form>
        </div>
    </div>

    <div class="cu-toast" id="customerToast" role="status" aria-live="polite"></div>

    <?php include 'includes/scripts.php'; ?>
    <script src="../../assets/js/cashier/customers.js?v=1"></script>
</body>

</html>