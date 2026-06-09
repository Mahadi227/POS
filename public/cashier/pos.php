<?php

/**
 * Terminal de caisse — structure HTML fixe + JS dynamique (catalogue / panier).
 */
require_once '../../includes/Config/session.php';
requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['cashier', 'admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/includes/pos-config.php';

$displayName = htmlspecialchars($posConfig['user']['name'], ENT_QUOTES, 'UTF-8');
$storeName = htmlspecialchars($posConfig['store']['name'], ENT_QUOTES, 'UTF-8');
$taxPercent = (float) ($posConfig['settings']['tax_percent'] ?? 18);
$currencySymbol = htmlspecialchars($posConfig['settings']['currency_symbol'] ?? 'FCFA', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2563eb">
    <title>Caisse — <?php echo $storeName; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/pos-cashier.css?v=8">
</head>

<body>
    <div id="pos-cashier-app" class="pos-cashier pos-cashier--view-catalog">
        <!-- En-tête -->
        <header class="pos-cashier__header">
            <div class="pos-cashier__header-left">
                <a href="dashboard.php" class="pos-cashier__back" title="Tableau de bord">
                    <span class="material-icons-round">arrow_back</span>
                </a>
                <div class="pos-cashier__brand">
                    <strong id="storeName"><?php echo $storeName; ?></strong>
                    <span id="cashierName"><?php echo $displayName; ?></span>
                </div>
            </div>
            <div class="pos-cashier__header-center">
                <span class="pos-cashier__clock" id="liveClock">--:--</span>
            </div>
            <div class="pos-cashier__header-right">
                <span class="pos-cashier__status pos-cashier__status--online" id="connectionBadge">
                    <span class="pos-cashier__status-dot"></span>
                    <span class="pos-cashier__status-text">En ligne</span>
                </span>
                <span class="pos-cashier__pending hidden" id="pendingBadge" title="Ventes en attente">0</span>
                <button type="button" class="pos-cashier__icon-btn" id="syncBtn" title="Synchroniser">
                    <span class="material-icons-round">sync</span>
                </button>
            </div>
        </header>

        <!-- Corps : catalogue + panier -->
        <div class="pos-cashier__main">
            <section class="pos-cashier__catalog">
                <div class="pos-cashier__search-wrap">
                    <div class="pos-cashier__search-row">
                        <div class="pos-cashier__search">
                            <span class="material-icons-round">search</span>
                            <input type="search" id="searchInput" placeholder="Scanner ou chercher un produit (F2)" autocomplete="off" autofocus>
                            <button type="button" class="pos-cashier__search-clear" id="clearSearchBtn" title="Effacer">
                                <span class="material-icons-round">close</span>
                            </button>
                        </div>
                        <button type="button" class="pos-cashier__scan-btn" id="openCameraScannerBtn" title="Scanner caméra (F3)">
                            <span class="material-icons-round">qr_code_scanner</span>
                        </button>
                    </div>
                    <div class="pos-cashier__scan-status" id="scanStatusBadge" hidden aria-live="polite"></div>
                </div>
                <div class="pos-cashier__categories-wrap">
                    <span class="pos-cashier__label">Catégories</span>
                    <div class="pos-cashier__categories" id="categoriesWrapper">
                        <button type="button" class="pos-cashier__cat active" data-id="all">Tout</button>
                    </div>
                </div>
                <div class="pos-cashier__products-scroll" aria-label="Liste des produits">
                    <div class="pos-cashier__products" id="productGrid">
                        <div class="pos-cashier__loading">Chargement du catalogue…</div>
                    </div>
                </div>
            </section>

            <aside class="pos-cashier__cart-panel">
                <div class="pos-cashier__cart-head">
                    <div class="pos-cashier__cart-title">
                        <span class="material-icons-round">shopping_cart</span>
                        Panier
                        <span class="pos-cashier__cart-badge" id="cartCount">0</span>
                    </div>
                    <button type="button" class="pos-cashier__icon-btn pos-cashier__icon-btn--danger" id="clearCartBtn" title="Vider le panier">
                        <span class="material-icons-round">delete_sweep</span>
                    </button>
                </div>
                <div class="pos-cashier__customer">
                    <span class="material-icons-round">person_outline</span>
                    <select id="customerSelect" class="pos-cashier__select" aria-label="Client">
                        <option value="">Client passage</option>
                    </select>
                </div>
                <div class="pos-cashier__cart-list-head" id="cartListHead" hidden>
                    <span id="cartArticlesLabel">0 article(s)</span>
                    <span class="pos-cashier__cart-list-hint">Faites défiler pour tout voir</span>
                </div>
                <div class="pos-cashier__cart-list" id="cartItems">
                    <div class="pos-cashier__cart-empty">
                        <span class="material-icons-round">shopping_cart</span>
                        <p>Le panier est vide</p>
                        <small>Cliquez sur un produit ou scannez un code-barres</small>
                    </div>
                </div>
                <div class="pos-cashier__totals">
                    <div class="pos-cashier__total-line">
                        <span>Sous-total</span>
                        <span id="subtotalDisplay">0 <?php echo $currencySymbol; ?></span>
                    </div>
                    <div class="pos-cashier__total-line">
                        <span id="taxLabel">TVA (<?php echo $taxPercent; ?>%)</span>
                        <span id="taxDisplay">0 <?php echo $currencySymbol; ?></span>
                    </div>
                    <div class="pos-cashier__total-line pos-cashier__total-line--click" id="discountDisplay" title="Cliquer pour ajouter une remise">
                        <span>Remise</span>
                        <span>- 0 <?php echo $currencySymbol; ?></span>
                    </div>
                    <div class="pos-cashier__total-line pos-cashier__total-line--grand">
                        <span>Total</span>
                        <span id="totalDisplay">0 <?php echo $currencySymbol; ?></span>
                    </div>
                    <button type="button" class="pos-cashier__pay-btn" id="checkoutBtn" disabled>
                        <span class="material-icons-round">payments</span>
                        Encaisser
                    </button>
                </div>
            </aside>
        </div>

        <!-- Mobile : résumé panier (vue produits) -->
        <div class="pos-cashier__mobile-dock" id="mobileCartDock" hidden>
            <button type="button" class="pos-cashier__mobile-dock-cart" id="mobileDockOpenCart" aria-label="Ouvrir le panier">
                <span class="material-icons-round">shopping_cart</span>
                <span class="pos-cashier__mobile-dock-info">
                    <strong id="mobileCartUnits">0</strong> article(s)
                    <span id="mobileCartTotal">0 <?php echo $currencySymbol; ?></span>
                </span>
            </button>
            <button type="button" class="pos-cashier__mobile-dock-pay" id="mobileDockPay" disabled>
                <span class="material-icons-round">payments</span>
                Encaisser
            </button>
        </div>

        <nav class="pos-cashier__mobile-nav" id="mobileNav" aria-label="Navigation caisse">
            <button type="button" class="pos-cashier__mobile-nav-btn active" data-view="catalog">
                <span class="material-icons-round">grid_view</span>
                <span>Produits</span>
            </button>
            <button type="button" class="pos-cashier__mobile-nav-btn" data-view="cart">
                <span class="material-icons-round">shopping_cart</span>
                <span>Panier</span>
                <span class="pos-cashier__mobile-nav-badge hidden" id="mobileNavBadge">0</span>
            </button>
        </nav>

        <!-- Modal paiement -->
        <div class="pos-cashier__modal" id="checkoutModal" aria-hidden="true">
            <div class="pos-cashier__modal-backdrop" data-close-modal></div>
            <div class="pos-cashier__modal-box" role="dialog" aria-labelledby="modalTitle">
                <header class="pos-cashier__modal-head">
                    <div class="pos-cashier__modal-title-wrap">
                        <span class="pos-cashier__modal-icon material-icons-round">point_of_sale</span>
                        <div>
                            <h2 id="modalTitle">Encaissement</h2>
                            <p class="pos-cashier__modal-sub" id="modalStoreLabel"><?php echo $storeName; ?></p>
                        </div>
                    </div>
                    <button type="button" class="pos-cashier__modal-close close-modal" aria-label="Fermer">
                        <span class="material-icons-round">close</span>
                    </button>
                </header>

                <div class="pos-cashier__modal-body">
                    <div class="pos-cashier__pay-recap">
                        <div class="pos-cashier__pay-recap-row">
                            <span><span id="modalItemCount">0</span> article(s)</span>
                            <span id="modalSubtotal">0 <?php echo $currencySymbol; ?></span>
                        </div>
                        <div class="pos-cashier__pay-recap-row">
                            <span id="modalTaxLabel">TVA</span>
                            <span id="modalTax">0 <?php echo $currencySymbol; ?></span>
                        </div>
                        <div class="pos-cashier__pay-recap-row pos-cashier__pay-recap-row--discount" id="modalDiscountRow" hidden>
                            <span>Remise</span>
                            <span id="modalDiscount">- 0 <?php echo $currencySymbol; ?></span>
                        </div>
                    </div>

                    <div class="pos-cashier__pay-hero">
                        <span class="pos-cashier__pay-hero-label">Total à payer</span>
                        <strong class="pos-cashier__pay-hero-amount" id="modalTotalDisplay">0 <?php echo $currencySymbol; ?></strong>
                    </div>

                    <p class="pos-cashier__pay-section-title">Mode de paiement</p>
                    <div class="pos-cashier__pay-methods">
                        <button type="button" class="pos-cashier__pay-method active" data-method="cash">
                            <span class="pos-cashier__pay-method-icon material-icons-round">payments</span>
                            <span class="pos-cashier__pay-method-name">Espèces</span>
                        </button>
                        <button type="button" class="pos-cashier__pay-method" data-method="mobile_money">
                            <span class="pos-cashier__pay-method-icon material-icons-round">smartphone</span>
                            <span class="pos-cashier__pay-method-name">Mobile Money</span>
                        </button>
                        <button type="button" class="pos-cashier__pay-method" data-method="card">
                            <span class="pos-cashier__pay-method-icon material-icons-round">credit_card</span>
                            <span class="pos-cashier__pay-method-name">Carte</span>
                        </button>
                    </div>

                    <div class="pos-cashier__pay-panels">
                        <div class="pos-cashier__pay-panel" id="cashDetails" data-panel="cash">
                            <label class="pos-cashier__field-label" for="amountTendered">Montant reçu du client</label>
                            <input type="number" id="amountTendered" class="pos-cashier__input-lg" placeholder="0" min="0" step="1" inputmode="decimal">
                            <div class="pos-cashier__quick-cash">
                                <button type="button" class="pos-cashier__quick" data-val="10000">10 000</button>
                                <button type="button" class="pos-cashier__quick" data-val="20000">20 000</button>
                                <button type="button" class="pos-cashier__quick" data-val="50000">50 000</button>
                                <button type="button" class="pos-cashier__quick" data-val="100000">100 000</button>
                                <button type="button" class="pos-cashier__quick" data-val="200000">200 000</button>
                                <button type="button" class="pos-cashier__quick pos-cashier__quick--primary exact-btn">Montant exact</button>
                            </div>
                            <div class="pos-cashier__change" id="changeBox">
                                <div class="pos-cashier__change-label">
                                    <span class="material-icons-round">currency_exchange</span>
                                    Monnaie à rendre
                                </div>
                                <strong id="changeDisplay">0 <?php echo $currencySymbol; ?></strong>
                            </div>
                        </div>

                        <div class="pos-cashier__pay-panel hidden" id="momoDetails" data-panel="mobile_money">
                            <label class="pos-cashier__field-label">Opérateur</label>
                            <div class="pos-cashier__momo-providers">
                                <button type="button" class="pos-cashier__momo-chip active" data-provider="orange_money">Orange</button>
                                <button type="button" class="pos-cashier__momo-chip" data-provider="mtn_momo">MTN</button>
                                <button type="button" class="pos-cashier__momo-chip" data-provider="wave">Wave</button>
                                <button type="button" class="pos-cashier__momo-chip" data-provider="moov">Moov</button>
                            </div>
                            <label class="pos-cashier__field-label" for="momoPhone">Téléphone client</label>
                            <input type="tel" id="momoPhone" class="pos-cashier__input-lg" placeholder="07 XX XX XX XX" inputmode="tel">
                            <label class="pos-cashier__field-label" for="momoRef">Réf. transaction (optionnel)</label>
                            <input type="text" id="momoRef" class="pos-cashier__input-md" placeholder="Ex: TXN-123456">
                            <p class="pos-cashier__field-hint">Vérifiez la confirmation sur le téléphone du client avant de valider.</p>
                        </div>

                        <div class="pos-cashier__pay-panel hidden" id="cardDetails" data-panel="card">
                            <label class="pos-cashier__field-label" for="cardRef">Réf. paiement carte</label>
                            <input type="text" id="cardRef" class="pos-cashier__input-md" placeholder="4 derniers chiffres ou N° ticket TPE">
                            <p class="pos-cashier__field-hint">Montant débité doit correspondre au total affiché.</p>
                        </div>
                    </div>
                </div>

                <footer class="pos-cashier__modal-foot">
                    <button type="button" class="pos-cashier__btn-secondary" data-close-modal>Annuler</button>
                    <button type="button" class="pos-cashier__btn-confirm" id="confirmPaymentBtn">
                        <span class="material-icons-round">check_circle</span>
                        Confirmer & imprimer
                    </button>
                </footer>
            </div>
        </div>

        <!-- Modal scanner code-barres (caméra) -->
        <div class="pos-cashier__scanner-modal" id="barcodeScannerModal" aria-hidden="true">
            <div class="pos-cashier__scanner-backdrop" data-close-scanner></div>
            <div class="pos-cashier__scanner-box" role="dialog" aria-labelledby="scannerModalTitle">
                <header class="pos-cashier__scanner-head">
                    <div>
                        <h2 id="scannerModalTitle">Scanner un code-barres</h2>
                        <p class="pos-cashier__scanner-sub">Placez le code-barres dans le cadre</p>
                    </div>
                    <button type="button" class="pos-cashier__modal-close" id="closeBarcodeScannerBtn" aria-label="Fermer">
                        <span class="material-icons-round">close</span>
                    </button>
                </header>
                <div class="pos-cashier__scanner-body">
                    <div id="barcode-scanner-reader" class="pos-cashier__scanner-reader"></div>
                    <p class="pos-cashier__scanner-hint">
                        <span class="material-icons-round">usb</span>
                        Les scanners USB fonctionnent aussi sans ouvrir cette fenêtre — scannez directement.
                    </p>
                </div>
            </div>
        </div>

        <div class="pos-cashier__toasts" id="toastContainer"></div>
    </div>

    <script src="https://unpkg.com/dexie/dist/dexie.js"></script>
    <script>
        window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="../../assets/js/cashier/cashier-api.js?v=3"></script>
    <script src="../../assets/js/cashier/barcode-scanner.js?v=1"></script>
    <script src="../../assets/js/cashier/pos-app.js?v=12"></script>
</body>

</html>