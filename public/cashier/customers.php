<?php

/**
 * Customer management — cashier (list, search, add, edit).
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
$displayName = htmlspecialchars($_SESSION['name'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');
$displayRole = htmlspecialchars($_SESSION['role'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');
$brandName = htmlspecialchars($adminBrandName, ENT_QUOTES, 'UTF-8');
$accentEsc = htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8');

$customersI18nKeys = [
    'modal_new', 'modal_edit', 'shown_count', 'clients_count', 'results_count',
    'no_match', 'no_customers', 'sales_count', 'points', 'edit', 'delete',
    'loading', 'load_error', 'error', 'saved', 'connection_error', 'deleted', 'delete_confirm',
    'last_updated',
];
$customersI18n = [];
foreach ($customersI18nKeys as $key) {
    $customersI18n[$key] = __t($key, 'customers');
}

$posConfig['lang'] = $activeLang;
$posConfig['locale'] = $locale;

$changeUrl = '../change_language.php';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="cashier" data-theme-accent="<?php echo $accentEsc; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/cashier-head-theme.php'; ?>
    <title><?php echo __t('title', 'customers'); ?> — <?php echo $brandName; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-customers.css?v=4">
    <?php echo cashier_theme_css_block($adminAccent); ?>
</head>

<body class="cu-page cu-pro-page">
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

        <main class="main-content">
            <header class="top-header cu-page-header">
                <div class="header-left cu-header-left">
                    <button type="button" class="icon-btn mobile-menu-btn cu-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'customers'); ?>">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="header-title-group">
                        <h1><?php echo __t('heading', 'customers'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="cuHeaderDate">—</span>
                            <span class="header-dot" aria-hidden="true">·</span>
                            <span class="cu-last-updated" id="lastUpdated" aria-live="polite"></span>
                        </div>
                    </div>
                </div>

                <div class="header-tools cu-header-tools">
                    <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
                    <div class="cu-header-user user-profile">
                        <div class="user-info">
                            <span class="name"><?php echo $displayName; ?></span>
                            <span class="role"><?php echo $displayRole; ?></span>
                        </div>
                    </div>
                </div>

                <div class="header-actions cu-header-actions">
                    <a href="pos.php" class="cu-header-pos" title="<?php echo __t('open_pos', 'customers'); ?>">
                        <span class="material-icons-round">point_of_sale</span>
                        <span class="cu-header-pos__label"><?php echo __t('open_pos', 'customers'); ?></span>
                    </a>
                    <button type="button" class="cu-refresh-btn cu-header-refresh" id="refreshCustomersBtn" title="<?php echo __t('refresh', 'customers'); ?>" aria-label="<?php echo __t('refresh', 'customers'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="cu-refresh-btn__label"><?php echo __t('refresh', 'customers'); ?></span>
                    </button>
                    <?php $themeToggleClass = 'cu-header-icon'; include __DIR__ . '/includes/theme-toggle.php'; ?>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="cu-error-banner" id="customersError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="cu-error-text"></span>
                </div>

                <nav class="cu-quick-nav" aria-label="<?php echo __t('menu', 'customers'); ?>">
                    <a href="dashboard.php" class="cu-quick-nav__item">
                        <span class="material-icons-round">dashboard</span>
                        <span><?php echo __t('nav_dashboard', 'cashier'); ?></span>
                    </a>
                    <a href="pos.php" class="cu-quick-nav__item">
                        <span class="material-icons-round">point_of_sale</span>
                        <span><?php echo __t('nav_pos', 'cashier'); ?></span>
                    </a>
                    <a href="sales_history.php" class="cu-quick-nav__item">
                        <span class="material-icons-round">receipt_long</span>
                        <span><?php echo __t('nav_sales_history', 'cashier'); ?></span>
                    </a>
                    <a href="returns.php" class="cu-quick-nav__item">
                        <span class="material-icons-round">assignment_return</span>
                        <span><?php echo __t('nav_returns', 'cashier'); ?></span>
                    </a>
                    <a href="customers.php" class="cu-quick-nav__item cu-quick-nav__item--accent">
                        <span class="material-icons-round">people</span>
                        <span><?php echo __t('nav_customers', 'cashier'); ?></span>
                    </a>
                </nav>

                <div class="cu-toolbar">
                    <div class="cu-search">
                        <span class="material-icons-round">search</span>
                        <input type="search" id="customerSearch" placeholder="<?php echo __t('search_placeholder', 'customers'); ?>"
                            autocomplete="off">
                    </div>
                    <button type="button" class="cu-btn cu-btn--primary" id="addCustomerBtn">
                        <span class="material-icons-round">person_add</span>
                        <?php echo __t('add_customer', 'customers'); ?>
                    </button>
                </div>

                <div class="cu-summary">
                    <div class="cu-summary-card">
                        <span class="cu-summary-card__icon material-icons-round">groups</span>
                        <div>
                            <div class="cu-summary-card__label"><?php echo __t('total_customers', 'customers'); ?></div>
                            <div class="cu-summary-card__value" id="totalCustomers">0</div>
                        </div>
                    </div>
                    <div class="cu-summary-card">
                        <span class="cu-summary-card__icon material-icons-round cu-summary-card__icon--green">filter_list</span>
                        <div>
                            <div class="cu-summary-card__label"><?php echo __t('display_count', 'customers'); ?></div>
                            <div class="cu-summary-card__value cu-summary-card__value--sm" id="filteredCustomers">—</div>
                        </div>
                    </div>
                </div>

                <section class="cu-panel">
                    <div class="cu-panel__head">
                        <h2><?php echo __t('customer_base', 'customers'); ?></h2>
                        <span class="cu-count" id="panelCountLabel"><?php echo __t('loading', 'customers'); ?></span>
                    </div>
                    <div class="cu-table-wrap">
                        <table class="cu-table cu-customers-table">
                            <thead>
                                <tr>
                                    <th><?php echo __t('col_customer', 'customers'); ?></th>
                                    <th><?php echo __t('col_phone', 'customers'); ?></th>
                                    <th><?php echo __t('col_email', 'customers'); ?></th>
                                    <th><?php echo __t('col_activity', 'customers'); ?></th>
                                    <th><?php echo __t('col_actions', 'customers'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="customersTableBody">
                                <tr>
                                    <td colspan="5" class="cu-loading-row"><?php echo __t('loading', 'customers'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div class="cu-modal" id="customerModal" aria-hidden="true">
        <div class="cu-modal__backdrop" data-close-modal></div>
        <div class="cu-modal__box" role="dialog" aria-labelledby="modalTitle">
            <header class="cu-modal__head">
                <h3 id="modalTitle"><?php echo __t('modal_new', 'customers'); ?></h3>
                <button type="button" class="cu-modal__close" id="closeModalBtn" aria-label="<?php echo __t('close', 'customers'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </header>
            <form id="customerForm">
                <div class="cu-modal__body">
                    <div class="cu-field">
                        <label for="formName"><?php echo __t('full_name', 'customers'); ?></label>
                        <input type="text" id="formName" name="name" required minlength="2" maxlength="120"
                            placeholder="<?php echo __t('name_placeholder', 'customers'); ?>">
                    </div>
                    <div class="cu-field">
                        <label for="formPhone"><?php echo __t('phone', 'customers'); ?></label>
                        <input type="tel" id="formPhone" name="phone" placeholder="07 XX XX XX XX" inputmode="tel">
                    </div>
                    <div class="cu-field">
                        <label for="formEmail"><?php echo __t('email', 'customers'); ?></label>
                        <input type="email" id="formEmail" name="email" placeholder="<?php echo __t('email_placeholder', 'customers'); ?>">
                    </div>
                </div>
                <footer class="cu-modal__foot">
                    <button type="button" class="cu-btn cu-btn--outline" data-close-modal><?php echo __t('cancel', 'customers'); ?></button>
                    <button type="submit" class="cu-btn cu-btn--primary" id="saveCustomerBtn">
                        <span class="material-icons-round">save</span>
                        <?php echo __t('save', 'customers'); ?>
                    </button>
                </footer>
            </form>
        </div>
    </div>

    <div class="cu-toast" id="customerToast" role="status" aria-live="polite"></div>

    <?php include 'includes/scripts.php'; ?>
    <script>
    window.CUSTOMERS_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?> };
    window.CUSTOMERS_I18N = <?php echo json_encode($customersI18n, JSON_UNESCAPED_UNICODE); ?>;
    window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/cashier/customers.js?v=3"></script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>
</body>

</html>
