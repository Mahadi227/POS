<?php
require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Auth/RoleRedirect.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

if (isset($_SESSION['user_id'])) {
    header('Location: ' . RoleRedirect::publicPath($_SESSION['role'] ?? ''));
    exit;
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
$signupAccent = '#7c3aed';
$themePortal = 'auth';
$themeAccent = $signupAccent;
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $signupAccent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $signupAccent; ?>">
    <meta name="theme-accent" content="<?php echo $signupAccent; ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo __t('signup_title', 'saas'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="../assets/css/signup-organization.css?v=3">
</head>
<body class="signup-org-page">
<div class="signup-org-shell">
    <aside class="signup-org-hero" aria-label="<?php echo htmlspecialchars(__t('signup_hero_aria', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="signup-org-hero__grid" aria-hidden="true"></div>
        <div class="signup-org-hero__inner">
            <div class="signup-org-hero__brand">
                <span class="material-icons-round" aria-hidden="true">cloud</span>
                <span>RetailPOS Cloud</span>
            </div>

            <p class="signup-org-hero__eyebrow">
                <span class="material-icons-round" aria-hidden="true">verified</span>
                <?php echo __t('signup_hero_eyebrow', 'saas'); ?>
            </p>

            <h2><?php echo __t('signup_hero_title', 'saas'); ?></h2>
            <p class="signup-org-hero__lead"><?php echo __t('signup_hero_desc', 'saas'); ?></p>

            <ul class="signup-org-features">
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">timer</span></span>
                    <span><?php echo __t('signup_feat_trial', 'saas'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">store</span></span>
                    <span><?php echo __t('signup_feat_stores', 'saas'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">groups</span></span>
                    <span><?php echo __t('signup_feat_team', 'saas'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">shield</span></span>
                    <span><?php echo __t('signup_feat_security', 'saas'); ?></span>
                </li>
            </ul>

            <div class="signup-org-trust" aria-label="<?php echo htmlspecialchars(__t('signup_trust_aria', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">lock</span>
                    <span><?php echo __t('signup_trust_secure', 'saas'); ?></span>
                </div>
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">cloud_done</span>
                    <span><?php echo __t('signup_trust_cloud', 'saas'); ?></span>
                </div>
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">support_agent</span>
                    <span><?php echo __t('signup_trust_support', 'saas'); ?></span>
                </div>
            </div>
        </div>
    </aside>

    <main class="signup-org-panel">
        <div class="signup-org-toolbar">
            <?php $changeUrl = 'change_language.php'; include __DIR__ . '/includes/language_switcher.php'; ?>
            <button type="button" class="signup-theme-toggle" id="signupThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>

        <div class="auth-container signup-org-card">
            <div class="signup-org-badge">
                <span class="material-icons-round" aria-hidden="true">rocket_launch</span>
                <?php echo __t('signup_badge', 'saas'); ?>
            </div>

            <div class="auth-header signup-org-header">
                <h1><?php echo __t('signup_heading', 'saas'); ?></h1>
                <p><?php echo __t('signup_subtitle', 'saas'); ?></p>
            </div>

            <div id="alertBox" class="alert" aria-live="polite" style="display:none;"></div>

            <form id="signupOrgForm" method="post" action="#" novalidate>
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <section class="signup-org-section" aria-labelledby="signupSectionOrg">
                    <div class="signup-org-section__head">
                        <span class="signup-org-step">1</span>
                        <h2 id="signupSectionOrg"><?php echo __t('signup_section_org', 'saas'); ?></h2>
                    </div>

                    <div class="form-group">
                        <label for="org_name"><?php echo __t('signup_org_name', 'saas'); ?></label>
                        <div class="input-icon-wrapper">
                            <span class="material-icons-round" aria-hidden="true">business</span>
                            <input type="text" id="org_name" name="org_name" required
                                   placeholder="<?php echo htmlspecialchars(__t('signup_org_placeholder', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="slug"><?php echo __t('signup_slug', 'saas'); ?></label>
                        <div class="input-icon-wrapper">
                            <span class="material-icons-round" aria-hidden="true">link</span>
                            <input type="text" id="slug" name="slug" pattern="[a-z0-9-]+" required
                                   autocomplete="off"
                                   placeholder="<?php echo htmlspecialchars(__t('signup_slug_placeholder', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <small id="slugHint" class="field-hint" aria-live="polite"></small>
                    </div>

                    <div class="form-group">
                        <label for="plan_code"><?php echo __t('signup_plan', 'saas'); ?></label>
                        <div class="input-icon-wrapper">
                            <span class="material-icons-round" aria-hidden="true">sell</span>
                            <select id="plan_code" name="plan_code" required>
                                <option value=""><?php echo __t('loading', 'saas'); ?>…</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="store_name"><?php echo __t('signup_store', 'saas'); ?></label>
                        <div class="input-icon-wrapper">
                            <span class="material-icons-round" aria-hidden="true">storefront</span>
                            <input type="text" id="store_name" name="store_name"
                                   placeholder="<?php echo htmlspecialchars(__t('signup_store_placeholder', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                </section>

                <section class="signup-org-section" aria-labelledby="signupSectionAdmin">
                    <div class="signup-org-section__head">
                        <span class="signup-org-step">2</span>
                        <div>
                            <h2 id="signupSectionAdmin"><?php echo __t('signup_section_admin', 'saas'); ?></h2>
                            <p class="signup-org-section__hint"><?php echo __t('signup_admin_super_hint', 'saas'); ?></p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="admin_name"><?php echo __t('full_name', 'auth'); ?></label>
                        <div class="input-icon-wrapper">
                            <span class="material-icons-round" aria-hidden="true">person</span>
                            <input type="text" id="admin_name" name="admin_name" required autocomplete="name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="admin_email"><?php echo __t('email', 'auth'); ?></label>
                        <div class="input-icon-wrapper">
                            <span class="material-icons-round" aria-hidden="true">email</span>
                            <input type="email" id="admin_email" name="admin_email" required
                                   autocomplete="username"
                                   placeholder="<?php echo htmlspecialchars(__t('email_placeholder_register', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password"><?php echo __t('password', 'auth'); ?></label>
                        <div class="input-icon-wrapper">
                            <span class="material-icons-round" aria-hidden="true">lock</span>
                            <input type="password" id="password" name="password" required minlength="8"
                                   autocomplete="new-password"
                                   placeholder="••••••••">
                            <button type="button" class="material-icons-round toggle-password" id="togglePassword"
                                    aria-label="<?php echo htmlspecialchars(__t('signup_show_password', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">visibility</button>
                        </div>
                        <small class="field-hint"><?php echo __t('signup_password_hint', 'saas'); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm"><?php echo __t('confirm_password', 'auth'); ?></label>
                        <div class="input-icon-wrapper">
                            <span class="material-icons-round" aria-hidden="true">lock_reset</span>
                            <input type="password" id="password_confirm" name="password_confirm" required minlength="8"
                                   autocomplete="new-password"
                                   placeholder="••••••••">
                            <button type="button" class="material-icons-round toggle-password" id="togglePasswordConfirm"
                                    aria-label="<?php echo htmlspecialchars(__t('show_password_confirm', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">visibility</button>
                        </div>
                        <small id="passwordConfirmHint" class="field-hint" aria-live="polite"></small>
                    </div>
                </section>

                <button type="submit" class="btn-primary signup-org-submit" id="submitBtn">
                    <span id="btnText"><?php echo __t('signup_submit', 'saas'); ?></span>
                    <div class="spinner" id="spinner" aria-hidden="true"></div>
                </button>

                <p class="signup-org-terms"><?php echo __t('signup_terms_note', 'saas'); ?></p>
            </form>

            <p class="signup-org-signin">
                <?php echo __t('signup_already_account', 'saas'); ?>
                <a href="login.php"><?php echo __t('login_link', 'auth'); ?></a>
            </p>

            <nav class="signup-org-footer-links" aria-label="<?php echo htmlspecialchars(__t('signup_nav', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <a href="marketing/pricing.php"><?php echo __t('signup_footer_pricing', 'saas'); ?></a>
                <a href="marketing/contact.php"><?php echo __t('signup_footer_contact', 'saas'); ?></a>
                <a href="marketing/faq.php"><?php echo __t('signup_footer_faq', 'saas'); ?></a>
            </nav>

            <p class="signup-org-copy">© <?php echo $year; ?> RetailPOS Cloud</p>
        </div>
    </main>
</div>

<script>
window.SIGNUP_CONFIG = {
    plansUrl: <?php echo json_encode('../api/v1/index.php?request=tenant-signup/plans', JSON_THROW_ON_ERROR); ?>,
    checkSlugUrl: <?php echo json_encode('../api/v1/index.php?request=tenant-signup/check-slug', JSON_THROW_ON_ERROR); ?>,
    registerUrl: <?php echo json_encode('../api/v1/index.php?request=tenant-signup/register', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'slug_available' => __t('signup_slug_ok', 'saas'),
        'slug_taken' => __t('signup_slug_taken', 'saas'),
        'error_generic' => __t('register_error', 'auth'),
        'server_error' => __t('signup_server_error', 'saas'),
        'show_password' => __t('signup_show_password', 'saas'),
        'hide_password' => __t('signup_hide_password', 'saas'),
        'password_mismatch' => __t('password_mismatch', 'auth'),
        'show_password_confirm' => __t('show_password_confirm', 'auth'),
        'hide_password_confirm' => __t('hide_password_confirm', 'auth'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/saas/signup-organization.js?v=3"></script>
</body>
</html>
