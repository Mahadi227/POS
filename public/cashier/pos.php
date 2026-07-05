<?php

/**
 * POS terminal — fixed HTML structure + dynamic JS (catalog / cart).
 */
require_once '../../includes/Config/session.php';
requireLogin();

require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['cashier', 'admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/includes/pos-config.php';
require_once __DIR__ . '/includes/cashier-branding.php';

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$displayName = htmlspecialchars($posConfig['user']['name'], ENT_QUOTES, 'UTF-8');
$storeName = htmlspecialchars($posConfig['store']['name'], ENT_QUOTES, 'UTF-8');
$brandName = htmlspecialchars($adminBrandName, ENT_QUOTES, 'UTF-8');
$brandDomain = htmlspecialchars($adminCustomDomain, ENT_QUOTES, 'UTF-8');
$accentEsc = htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8');
$taxPercent = (float) ($posConfig['settings']['tax_percent'] ?? 18);
$currencySymbol = htmlspecialchars($posConfig['settings']['currency_symbol'] ?? 'FCFA', ENT_QUOTES, 'UTF-8');
$taxLabel = sprintf(__t('tax_label', 'pos'), $taxPercent);

$posI18nKeys = [
    'online', 'offline', 'category_all', 'loading_catalog', 'no_products', 'out_of_stock', 'stock_label',
    'walk_in_customer', 'cart_empty', 'cart_empty_hint', 'cart_scroll_hint', 'cart_lines_one', 'cart_lines_many',
    'offline_catalog', 'syncing', 'products_loaded', 'sync_failed', 'invalid_qty', 'stock_max_item', 'stock_max',
    'product_out_of_stock', 'remove_item', 'per_unit', 'qty_label', 'decrease', 'increase', 'qty_aria', 'qty_title',
    'line_total', 'discount', 'discount_prompt', 'discount_mode_amount', 'discount_mode_percent', 'discount_input_aria', 'clear_cart_confirm', 'popup_blocked', 'insufficient_amount',
    'sale_recorded', 'error', 'sale_queued_offline', 'checkout_error', 'sales_synced', 'product_not_found',
    'product_out_of_stock_named', 'pos_ready', 'tax_label', 'modal_items', 'mobile_articles', 'pay_cash', 'pay_card', 'pay_mobile',
    'items_suffix', 'grand_total', 'theme',
    'scanner_camera_select', 'scanner_camera_rear', 'scanner_camera_front', 'scanner_start', 'scanner_stop',
    'scanner_allow_camera', 'scanner_permission_denied',
    'shift_closed_title', 'shift_closed_desc', 'shift_status_open', 'shift_open_title',
    'shift_open_prompt', 'shift_close_prompt', 'shift_close_hint', 'shift_invalid_float', 'shift_invalid_count',
    'shift_none_open', 'shift_required', 'shift_required_confirm', 'shift_migration_hint',
];
$posI18n = [];
foreach ($posI18nKeys as $key) {
    $posI18n[$key] = __t($key, 'pos');
}

$posConfig['branding'] = [
    'name' => $adminBrandName,
    'accent' => $adminAccent,
    'logo_url' => $adminLogoUrl,
    'domain' => $adminCustomDomain,
];
$posConfig['lang'] = $activeLang;
$posConfig['locale'] = $locale;

$changeUrl = '../change_language.php';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="cashier" data-theme-accent="<?php echo $accentEsc; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1.0, user-scalable=no">
    <?php require __DIR__ . '/includes/cashier-head-theme.php'; ?>
    <title><?php echo sprintf(__t('title', 'pos'), $brandName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/pos-cashier.css?v=22">
    <?php echo cashier_theme_css_block($adminAccent); ?>
</head>

<body class="pos-page">
    <div id="pos-cashier-app" class="pos-cashier pos-cashier--view-catalog">
        <header class="pos-cashier__header">
            <div class="pos-cashier__header-left">
                <a href="dashboard.php" class="pos-cashier__back" title="<?php echo __t('back_dashboard', 'pos'); ?>">
                    <span class="material-icons-round">arrow_back</span>
                </a>
                <div class="pos-cashier__brand">
                    <?php if (($adminLogoUrl ?? '') !== ''): ?>
                    <img src="<?php echo htmlspecialchars($adminLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="pos-cashier__brand-logo">
                    <?php endif; ?>
                    <div class="pos-cashier__brand-text">
                        <strong id="brandName"><?php echo $brandName; ?></strong>
                        <?php if ($brandDomain !== ''): ?>
                        <span class="pos-cashier__brand-domain" title="<?php echo $brandDomain; ?>">
                            <span class="material-icons-round" aria-hidden="true">language</span>
                            <?php echo $brandDomain; ?>
                        </span>
                        <?php endif; ?>
                        <span id="cashierMeta" class="pos-cashier__brand-meta" data-cashier="<?php echo $displayName; ?>"><?php echo $storeName; ?> · <?php echo $displayName; ?></span>
                    </div>
                </div>
            </div>
            <div class="pos-cashier__header-center">
                <span class="pos-cashier__clock" id="liveClock" aria-live="polite">--:--</span>
            </div>
            <div class="pos-cashier__header-right">
                <div class="pos-cashier__lang">
                    <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
                </div>
                <button type="button" class="pos-cashier__icon-btn" id="theme-toggle" aria-label="<?php echo __t('theme', 'pos'); ?>">
                    <span class="material-icons-round">dark_mode</span>
                </button>
                <span class="pos-cashier__shift hidden" id="shiftBadge" title="<?php echo __t('shift_open_title', 'pos'); ?>"></span>
                <span class="pos-cashier__status pos-cashier__status--online" id="connectionBadge">
                    <span class="pos-cashier__status-dot"></span>
                    <span class="pos-cashier__status-text"><?php echo __t('online', 'pos'); ?></span>
                </span>
                <span class="pos-cashier__pending hidden" id="pendingBadge" title="<?php echo __t('pending_sales', 'pos'); ?>">0</span>
                <button type="button" class="pos-cashier__icon-btn" id="syncBtn" title="<?php echo __t('sync', 'pos'); ?>">
                    <span class="material-icons-round">sync</span>
                </button>
            </div>
        </header>

        <nav class="pos-cashier__quick-nav" aria-label="<?php echo __t('menu', 'pos'); ?>">
            <a href="dashboard.php" class="pos-cashier__quick-nav-item">
                <span class="material-icons-round">dashboard</span>
                <span><?php echo __t('back_dashboard', 'pos'); ?></span>
            </a>
            <a href="sales_history.php" class="pos-cashier__quick-nav-item">
                <span class="material-icons-round">receipt_long</span>
                <span><?php echo __t('nav_history', 'pos'); ?></span>
            </a>
            <a href="returns.php" class="pos-cashier__quick-nav-item">
                <span class="material-icons-round">assignment_return</span>
                <span><?php echo __t('nav_returns', 'pos'); ?></span>
            </a>
            <a href="customers.php" class="pos-cashier__quick-nav-item">
                <span class="material-icons-round">people</span>
                <span><?php echo __t('nav_customers', 'pos'); ?></span>
            </a>
        </nav>

        <div class="pos-cashier__main">
            <section class="pos-cashier__catalog">
                <div class="pos-cashier__search-wrap">
                    <div class="pos-cashier__search-row">
                        <div class="pos-cashier__search">
                            <span class="material-icons-round">search</span>
                            <input type="search" id="searchInput" placeholder="<?php echo __t('search_placeholder', 'pos'); ?>" autocomplete="off" autofocus>
                            <button type="button" class="pos-cashier__search-clear" id="clearSearchBtn" title="<?php echo __t('clear_search', 'pos'); ?>">
                                <span class="material-icons-round">close</span>
                            </button>
                        </div>
                        <button type="button" class="pos-cashier__scan-btn" id="openCameraScannerBtn" title="<?php echo __t('scan_camera', 'pos'); ?>">
                            <span class="material-icons-round">qr_code_scanner</span>
                        </button>
                    </div>
                    <div class="pos-cashier__scan-status" id="scanStatusBadge" hidden aria-live="polite"></div>
                </div>
                <div class="pos-cashier__categories-wrap">
                    <span class="pos-cashier__label"><?php echo __t('categories', 'pos'); ?></span>
                    <div class="pos-cashier__categories" id="categoriesWrapper">
                        <button type="button" class="pos-cashier__cat active" data-id="all"><?php echo __t('category_all', 'pos'); ?></button>
                    </div>
                </div>
                <div class="pos-cashier__products-scroll" aria-label="<?php echo __t('products_list', 'pos'); ?>">
                    <div class="pos-cashier__products" id="productGrid">
                        <div class="pos-cashier__loading"><?php echo __t('loading_catalog', 'pos'); ?></div>
                    </div>
                </div>
            </section>

            <aside class="pos-cashier__cart-panel">
                <header class="pos-cashier__cart-head">
                    <div class="pos-cashier__cart-head-row">
                        <div class="pos-cashier__cart-title">
                            <span class="material-icons-round" aria-hidden="true">shopping_cart</span>
                            <span><?php echo __t('cart', 'pos'); ?></span>
                            <span class="pos-cashier__cart-badge" id="cartCount">0</span>
                        </div>
                        <button type="button" class="pos-cashier__icon-btn pos-cashier__icon-btn--danger pos-cashier__icon-btn--sm" id="clearCartBtn" title="<?php echo __t('clear_cart', 'pos'); ?>">
                            <span class="material-icons-round">delete_sweep</span>
                        </button>
                    </div>
                    <p class="pos-cashier__cart-meta" id="cartListHead" hidden>
                        <span id="cartArticlesLabel">0</span>
                    </p>
                    <label class="pos-cashier__customer">
                        <span class="material-icons-round" aria-hidden="true">person_outline</span>
                        <select id="customerSelect" class="pos-cashier__select" aria-label="<?php echo __t('walk_in_customer', 'pos'); ?>">
                            <option value=""><?php echo __t('walk_in_customer', 'pos'); ?></option>
                        </select>
                    </label>
                </header>
                <div class="pos-cashier__cart-body">
                    <div class="pos-cashier__cart-list" id="cartItems" aria-live="polite">
                        <div class="pos-cashier__cart-empty">
                            <span class="material-icons-round" aria-hidden="true">shopping_cart</span>
                            <p><?php echo __t('cart_empty', 'pos'); ?></p>
                            <small><?php echo __t('cart_empty_hint', 'pos'); ?></small>
                        </div>
                    </div>
                </div>
                <footer class="pos-cashier__totals">
                    <div class="pos-cashier__totals-body">
                        <div class="pos-cashier__total-line">
                            <span><?php echo __t('subtotal', 'pos'); ?></span>
                            <span id="subtotalDisplay">0 <?php echo $currencySymbol; ?></span>
                        </div>
                        <div class="pos-cashier__total-line">
                            <span id="taxLabel"><?php echo $taxLabel; ?></span>
                            <span id="taxDisplay">0 <?php echo $currencySymbol; ?></span>
                        </div>
                        <div class="pos-cashier__total-line pos-cashier__total-line--discount">
                            <span class="pos-cashier__discount-label"><?php echo __t('discount', 'pos'); ?></span>
                            <div class="pos-cashier__discount-inline">
                                <div class="pos-cashier__discount-mode" role="group" aria-label="<?php echo __t('discount', 'pos'); ?>">
                                    <button type="button" class="pos-cashier__discount-mode-btn active" data-mode="amount" title="<?php echo __t('discount_mode_amount', 'pos'); ?>"><?php echo $currencySymbol; ?></button>
                                    <button type="button" class="pos-cashier__discount-mode-btn" data-mode="percent" title="<?php echo __t('discount_mode_percent', 'pos'); ?>">%</button>
                                </div>
                                <input
                                    type="number"
                                    id="discountInput"
                                    class="pos-cashier__discount-input"
                                    min="0"
                                    step="0.01"
                                    value="0"
                                    inputmode="decimal"
                                    aria-label="<?php echo __t('discount_input_aria', 'pos'); ?>"
                                >
                                <span id="discountAmountDisplay" class="pos-cashier__discount-amount">- 0 <?php echo $currencySymbol; ?></span>
                            </div>
                        </div>
                        <div class="pos-cashier__total-line pos-cashier__total-line--grand">
                            <span><?php echo __t('grand_total', 'pos'); ?></span>
                            <span id="totalDisplay">0 <?php echo $currencySymbol; ?></span>
                        </div>
                    </div>
                    <button type="button" class="pos-cashier__pay-btn" id="checkoutBtn" disabled>
                        <span class="material-icons-round">payments</span>
                        <?php echo __t('checkout', 'pos'); ?>
                    </button>
                </footer>
            </aside>
        </div>

        <div class="pos-cashier__mobile-dock" id="mobileCartDock" hidden>
            <button type="button" class="pos-cashier__mobile-dock-cart" id="mobileDockOpenCart" aria-label="<?php echo __t('mobile_open_cart', 'pos'); ?>">
                <span class="material-icons-round">shopping_cart</span>
                <span class="pos-cashier__mobile-dock-info">
                    <strong id="mobileCartUnits">0</strong> <?php echo __t('mobile_articles', 'pos'); ?>
                    <span id="mobileCartTotal">0 <?php echo $currencySymbol; ?></span>
                </span>
            </button>
            <button type="button" class="pos-cashier__mobile-dock-pay" id="mobileDockPay" disabled>
                <span class="material-icons-round">payments</span>
                <?php echo __t('checkout', 'pos'); ?>
            </button>
        </div>

        <nav class="pos-cashier__mobile-nav" id="mobileNav" aria-label="<?php echo __t('menu', 'pos'); ?>">
            <button type="button" class="pos-cashier__mobile-nav-btn active" data-view="catalog">
                <span class="material-icons-round">grid_view</span>
                <span><?php echo __t('nav_products', 'pos'); ?></span>
            </button>
            <button type="button" class="pos-cashier__mobile-nav-btn" data-view="cart">
                <span class="material-icons-round">shopping_cart</span>
                <span><?php echo __t('nav_cart', 'pos'); ?></span>
                <span class="pos-cashier__mobile-nav-badge hidden" id="mobileNavBadge">0</span>
            </button>
        </nav>

        <div class="pos-cashier__modal" id="checkoutModal" aria-hidden="true">
            <div class="pos-cashier__modal-backdrop" data-close-modal></div>
            <div class="pos-cashier__modal-box" role="dialog" aria-labelledby="modalTitle">
                <header class="pos-cashier__modal-head">
                    <div class="pos-cashier__modal-title-wrap">
                        <span class="pos-cashier__modal-icon material-icons-round">point_of_sale</span>
                        <div>
                            <h2 id="modalTitle"><?php echo __t('checkout_title', 'pos'); ?></h2>
                            <p class="pos-cashier__modal-sub" id="modalStoreLabel"><?php echo $storeName; ?></p>
                        </div>
                    </div>
                    <button type="button" class="pos-cashier__modal-close close-modal" aria-label="<?php echo __t('close', 'pos'); ?>">
                        <span class="material-icons-round">close</span>
                    </button>
                </header>

                <div class="pos-cashier__modal-body">
                    <div class="pos-cashier__pay-recap">
                        <div class="pos-cashier__pay-recap-row">
                            <span id="modalRecapItems"><span id="modalItemCount">0</span></span>
                            <span id="modalSubtotal">0 <?php echo $currencySymbol; ?></span>
                        </div>
                        <div class="pos-cashier__pay-recap-row">
                            <span id="modalTaxLabel"><?php echo $taxLabel; ?></span>
                            <span id="modalTax">0 <?php echo $currencySymbol; ?></span>
                        </div>
                        <div class="pos-cashier__pay-recap-row pos-cashier__pay-recap-row--discount" id="modalDiscountRow" hidden>
                            <span><?php echo __t('discount', 'pos'); ?></span>
                            <span id="modalDiscount">- 0 <?php echo $currencySymbol; ?></span>
                        </div>
                    </div>

                    <div class="pos-cashier__pay-hero">
                        <span class="pos-cashier__pay-hero-label"><?php echo __t('total_to_pay', 'pos'); ?></span>
                        <strong class="pos-cashier__pay-hero-amount" id="modalTotalDisplay">0 <?php echo $currencySymbol; ?></strong>
                    </div>

                    <p class="pos-cashier__pay-section-title"><?php echo __t('payment_method', 'pos'); ?></p>
                    <div class="pos-cashier__pay-methods">
                        <button type="button" class="pos-cashier__pay-method active" data-method="cash">
                            <span class="pos-cashier__pay-method-icon material-icons-round">payments</span>
                            <span class="pos-cashier__pay-method-name"><?php echo __t('pay_cash', 'pos'); ?></span>
                        </button>
                        <button type="button" class="pos-cashier__pay-method" data-method="mobile_money">
                            <span class="pos-cashier__pay-method-icon material-icons-round">smartphone</span>
                            <span class="pos-cashier__pay-method-name"><?php echo __t('pay_mobile', 'pos'); ?></span>
                        </button>
                        <button type="button" class="pos-cashier__pay-method" data-method="card">
                            <span class="pos-cashier__pay-method-icon material-icons-round">credit_card</span>
                            <span class="pos-cashier__pay-method-name"><?php echo __t('pay_card', 'pos'); ?></span>
                        </button>
                    </div>

                    <div class="pos-cashier__pay-panels">
                        <div class="pos-cashier__pay-panel" id="cashDetails" data-panel="cash">
                            <label class="pos-cashier__field-label" for="amountTendered"><?php echo __t('amount_received', 'pos'); ?></label>
                            <input type="number" id="amountTendered" class="pos-cashier__input-lg" placeholder="0" min="0" step="1" inputmode="decimal">
                            <div class="pos-cashier__quick-cash">
                                <button type="button" class="pos-cashier__quick" data-val="10000">10 000</button>
                                <button type="button" class="pos-cashier__quick" data-val="20000">20 000</button>
                                <button type="button" class="pos-cashier__quick" data-val="50000">50 000</button>
                                <button type="button" class="pos-cashier__quick" data-val="100000">100 000</button>
                                <button type="button" class="pos-cashier__quick" data-val="200000">200 000</button>
                                <button type="button" class="pos-cashier__quick pos-cashier__quick--primary exact-btn"><?php echo __t('exact_amount', 'pos'); ?></button>
                            </div>
                            <div class="pos-cashier__change" id="changeBox">
                                <div class="pos-cashier__change-label">
                                    <span class="material-icons-round">currency_exchange</span>
                                    <?php echo __t('change_due', 'pos'); ?>
                                </div>
                                <strong id="changeDisplay">0 <?php echo $currencySymbol; ?></strong>
                            </div>
                        </div>

                        <div class="pos-cashier__pay-panel hidden" id="momoDetails" data-panel="mobile_money">
                            <label class="pos-cashier__field-label"><?php echo __t('operator', 'pos'); ?></label>
                            <div class="pos-cashier__momo-providers">
                                <button type="button" class="pos-cashier__momo-chip active" data-provider="orange_money">Orange</button>
                                <button type="button" class="pos-cashier__momo-chip" data-provider="mtn_momo">MTN</button>
                                <button type="button" class="pos-cashier__momo-chip" data-provider="wave">Wave</button>
                                <button type="button" class="pos-cashier__momo-chip" data-provider="moov">Moov</button>
                            </div>
                            <label class="pos-cashier__field-label" for="momoPhone"><?php echo __t('momo_phone', 'pos'); ?></label>
                            <input type="tel" id="momoPhone" class="pos-cashier__input-lg" placeholder="07 XX XX XX XX" inputmode="tel">
                            <label class="pos-cashier__field-label" for="momoRef"><?php echo __t('momo_ref', 'pos'); ?></label>
                            <input type="text" id="momoRef" class="pos-cashier__input-md" placeholder="TXN-123456">
                            <p class="pos-cashier__field-hint"><?php echo __t('momo_hint', 'pos'); ?></p>
                        </div>

                        <div class="pos-cashier__pay-panel hidden" id="cardDetails" data-panel="card">
                            <label class="pos-cashier__field-label" for="cardRef"><?php echo __t('card_ref', 'pos'); ?></label>
                            <input type="text" id="cardRef" class="pos-cashier__input-md" placeholder="****">
                            <p class="pos-cashier__field-hint"><?php echo __t('card_hint', 'pos'); ?></p>
                        </div>
                    </div>
                </div>

                <footer class="pos-cashier__modal-foot">
                    <button type="button" class="pos-cashier__btn-secondary" data-close-modal><?php echo __t('cancel', 'pos'); ?></button>
                    <button type="button" class="pos-cashier__btn-confirm" id="confirmPaymentBtn">
                        <span class="material-icons-round">check_circle</span>
                        <?php echo __t('confirm_print', 'pos'); ?>
                    </button>
                </footer>
            </div>
        </div>

        <div class="pos-cashier__scanner-modal" id="barcodeScannerModal" aria-hidden="true">
            <div class="pos-cashier__scanner-backdrop" data-close-scanner></div>
            <div class="pos-cashier__scanner-box" role="dialog" aria-labelledby="scannerModalTitle">
                <header class="pos-cashier__scanner-head">
                    <div>
                        <h2 id="scannerModalTitle"><?php echo __t('scanner_title', 'pos'); ?></h2>
                        <p class="pos-cashier__scanner-sub"><?php echo __t('scanner_sub', 'pos'); ?></p>
                    </div>
                    <button type="button" class="pos-cashier__modal-close" id="closeBarcodeScannerBtn" aria-label="<?php echo __t('close', 'pos'); ?>">
                        <span class="material-icons-round">close</span>
                    </button>
                </header>
                <div class="pos-cashier__scanner-body">
                    <div id="barcode-scanner-reader" class="pos-cashier__scanner-reader"></div>
                    <div class="pos-cashier__scanner-controls">
                        <label for="scannerCameraSelect"><?php echo __t('scanner_camera_select', 'pos'); ?></label>
                        <div class="pos-cashier__scanner-controls-row">
                            <select id="scannerCameraSelect">
                                <option value="__environment__"><?php echo __t('scanner_camera_rear', 'pos'); ?></option>
                                <option value="__user__"><?php echo __t('scanner_camera_front', 'pos'); ?></option>
                            </select>
                            <button type="button" class="pos-cashier__quick" id="scannerStartBtn"><?php echo __t('scanner_start', 'pos'); ?></button>
                            <button type="button" class="pos-cashier__quick" id="scannerStopBtn" hidden><?php echo __t('scanner_stop', 'pos'); ?></button>
                        </div>
                    </div>
                    <p class="pos-cashier__scanner-hint">
                        <span class="material-icons-round">usb</span>
                        <?php echo __t('scanner_usb_hint', 'pos'); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="pos-cashier__toasts" id="toastContainer"></div>
    </div>

    <script src="https://unpkg.com/dexie/dist/dexie.js"></script>
    <script>
        window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
        window.POS_I18N = <?php echo json_encode($posI18n, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script src="../../assets/js/cashier/cashier-api.js?v=5"></script>
    <script src="../../assets/js/cashier/cashier-shift.js?v=1"></script>
    <script src="../../assets/js/cashier/cashier-sync-heartbeat.js?v=1"></script>
    <script src="../../assets/js/cashier/barcode-scanner.js?v=2"></script>
    <script src="../../assets/js/cashier/pos-app.js?v=25"></script>
    <script src="../../assets/js/app-theme.js?v=2"></script>
</body>

</html>
