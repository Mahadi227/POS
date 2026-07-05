<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('../login.php');

$activePlatPage = 'help';
$pageTitle = __t('plat_nav_help', 'platform');
$extraStyles = ['platform-help.css'];
$extraScripts = ['platform-common.js', 'platform-help.js'];
$pageI18n = plat_i18n([
    'plat_nav_help', 'plat_help_badge', 'plat_help_subtitle', 'plat_help_search_placeholder',
    'plat_help_quick_links', 'plat_help_faq_title', 'plat_help_guides_title', 'plat_help_guides_empty',
    'plat_help_load_error', 'plat_help_open_kb', 'plat_help_contact_support', 'plat_help_read_guide',
    'plat_help_close', 'loading', 'load_error', 'plat_no_data', 'plat_search',
    'plat_nav_companies', 'plat_nav_subscriptions', 'plat_nav_support', 'plat_nav_knowledge_base',
    'plat_nav_tickets', 'plat_nav_monitoring', 'plat_nav_developers', 'plat_nav_api',
    'plat_dash_public_status', 'plat_help_link_companies_desc', 'plat_help_link_subscriptions_desc',
    'plat_help_link_support_desc', 'plat_help_link_kb_desc', 'plat_help_link_tickets_desc',
    'plat_help_link_monitoring_desc', 'plat_help_link_developers_desc', 'plat_help_link_status_desc',
    'plat_help_faq_tenant_q', 'plat_help_faq_tenant_a', 'plat_help_faq_impersonate_q', 'plat_help_faq_impersonate_a',
    'plat_help_faq_billing_q', 'plat_help_faq_billing_a', 'plat_help_faq_users_q', 'plat_help_faq_users_a',
    'plat_help_faq_audit_q', 'plat_help_faq_audit_a', 'plat_help_faq_status_q', 'plat_help_faq_status_a',
    'plat_kb_type_article', 'plat_kb_type_guide', 'plat_kb_type_faq',
]);
require __DIR__ . '/../includes/layout-start.php';

$helpLinks = [
    ['path' => 'companies/index.php', 'icon' => 'business', 'label' => 'plat_nav_companies', 'desc' => 'plat_help_link_companies_desc', 'accent' => '#2563eb'],
    ['path' => 'subscriptions/index.php', 'icon' => 'autorenew', 'label' => 'plat_nav_subscriptions', 'desc' => 'plat_help_link_subscriptions_desc', 'accent' => '#7c3aed'],
    ['path' => 'support/index.php', 'icon' => 'support_agent', 'label' => 'plat_nav_support', 'desc' => 'plat_help_link_support_desc', 'accent' => '#0891b2'],
    ['path' => 'knowledge_base/index.php', 'icon' => 'menu_book', 'label' => 'plat_nav_knowledge_base', 'desc' => 'plat_help_link_kb_desc', 'accent' => '#ca8a04'],
    ['path' => 'tickets/index.php', 'icon' => 'confirmation_number', 'label' => 'plat_nav_tickets', 'desc' => 'plat_help_link_tickets_desc', 'accent' => '#ea580c'],
    ['path' => 'monitoring/index.php', 'icon' => 'monitor_heart', 'label' => 'plat_nav_monitoring', 'desc' => 'plat_help_link_monitoring_desc', 'accent' => '#16a34a'],
    ['path' => 'developers/index.php', 'icon' => 'code', 'label' => 'plat_nav_developers', 'desc' => 'plat_help_link_developers_desc', 'accent' => '#6366f1', 'external' => 'developers/index.php'],
    ['path' => '../status.php', 'icon' => 'public', 'label' => 'plat_dash_public_status', 'desc' => 'plat_help_link_status_desc', 'accent' => '#64748b', 'external' => '../status.php', 'blank' => true],
];

$helpFaqs = [
    ['q' => 'plat_help_faq_tenant_q', 'a' => 'plat_help_faq_tenant_a'],
    ['q' => 'plat_help_faq_impersonate_q', 'a' => 'plat_help_faq_impersonate_a'],
    ['q' => 'plat_help_faq_billing_q', 'a' => 'plat_help_faq_billing_a'],
    ['q' => 'plat_help_faq_users_q', 'a' => 'plat_help_faq_users_a'],
    ['q' => 'plat_help_faq_audit_q', 'a' => 'plat_help_faq_audit_a'],
    ['q' => 'plat_help_faq_status_q', 'a' => 'plat_help_faq_status_a'],
];
?>

<div class="plat-help">
    <div class="plat-help-error" id="platHelpError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platHelpErrorText"></span>
    </div>

    <section class="plat-help-hero" aria-labelledby="platHelpHeroTitle">
        <div class="plat-help-hero__intro">
            <div class="plat-help-badge">
                <span class="material-icons-round" aria-hidden="true">help</span>
                <?php echo __t('plat_help_badge', 'platform'); ?>
            </div>
            <h2 class="plat-help-hero__title" id="platHelpHeroTitle"><?php echo __t('plat_nav_help', 'platform'); ?></h2>
            <p class="plat-help-hero__desc"><?php echo __t('plat_help_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-help-search-wrap">
            <span class="material-icons-round plat-help-search-icon" aria-hidden="true">search</span>
            <input type="search" id="platHelpSearch" class="plat-search plat-help-search"
                   placeholder="<?php echo __t('plat_help_search_placeholder', 'platform'); ?>" autocomplete="off">
        </div>
    </section>

    <section class="plat-panel plat-help-section" aria-labelledby="platHelpLinksTitle">
        <h3 id="platHelpLinksTitle">
            <span class="material-icons-round" aria-hidden="true">link</span>
            <?php echo __t('plat_help_quick_links', 'platform'); ?>
        </h3>
        <div class="plat-help-links-grid" id="platHelpLinksGrid">
            <?php foreach ($helpLinks as $link):
                $href = !empty($link['external'])
                    ? plat_public_href((string) $link['external'])
                    : plat_href((string) $link['path']);
                $blank = !empty($link['blank']);
            ?>
            <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
               class="plat-help-link-card"
               style="--plat-help-accent: <?php echo htmlspecialchars($link['accent'], ENT_QUOTES, 'UTF-8'); ?>"
               data-help-search="<?php echo htmlspecialchars(strtolower(__t($link['label'], 'platform') . ' ' . __t($link['desc'], 'platform')), ENT_QUOTES, 'UTF-8'); ?>"
               <?php echo $blank ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                <span class="plat-help-link-card__icon" aria-hidden="true">
                    <span class="material-icons-round"><?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
                <span class="plat-help-link-card__body">
                    <strong><?php echo __t($link['label'], 'platform'); ?></strong>
                    <span><?php echo __t($link['desc'], 'platform'); ?></span>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="plat-help-columns">
        <section class="plat-panel plat-help-section" aria-labelledby="platHelpFaqTitle">
            <h3 id="platHelpFaqTitle">
                <span class="material-icons-round" aria-hidden="true">quiz</span>
                <?php echo __t('plat_help_faq_title', 'platform'); ?>
            </h3>
            <div class="plat-help-faq" id="platHelpFaq">
                <?php foreach ($helpFaqs as $i => $faq): ?>
                <details class="plat-help-faq-item" data-help-search="<?php echo htmlspecialchars(strtolower(__t($faq['q'], 'platform') . ' ' . __t($faq['a'], 'platform')), ENT_QUOTES, 'UTF-8'); ?>">
                    <summary><?php echo __t($faq['q'], 'platform'); ?></summary>
                    <p><?php echo __t($faq['a'], 'platform'); ?></p>
                </details>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="plat-panel plat-help-section" aria-labelledby="platHelpGuidesTitle">
            <div class="plat-help-guides-head">
                <h3 id="platHelpGuidesTitle">
                    <span class="material-icons-round" aria-hidden="true">menu_book</span>
                    <?php echo __t('plat_help_guides_title', 'platform'); ?>
                </h3>
                <a href="<?php echo htmlspecialchars(plat_href('knowledge_base/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-help-kb-link">
                    <?php echo __t('plat_help_open_kb', 'platform'); ?>
                    <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
                </a>
            </div>
            <div class="plat-help-guides" id="platHelpGuides">
                <p class="plat-help-muted"><?php echo __t('loading', 'platform'); ?>…</p>
            </div>
        </section>
    </div>

    <section class="plat-help-footer-cta">
        <div class="plat-help-footer-cta__inner">
            <span class="material-icons-round" aria-hidden="true">support_agent</span>
            <div>
                <strong><?php echo __t('plat_help_contact_support', 'platform'); ?></strong>
                <p><?php echo __t('plat_help_link_support_desc', 'platform'); ?></p>
            </div>
            <a href="<?php echo htmlspecialchars(plat_href('support/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-help-cta-btn">
                <?php echo __t('plat_nav_support', 'platform'); ?>
            </a>
        </div>
    </section>
</div>

<dialog class="plat-help-dialog" id="platHelpArticleDialog">
    <div class="plat-help-dialog__inner">
        <header class="plat-help-dialog__head">
            <h3 id="platHelpArticleTitle">—</h3>
            <button type="button" class="plat-help-dialog__close" id="platHelpArticleClose" aria-label="<?php echo __t('plat_help_close', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-help-dialog__body" id="platHelpArticleBody"></div>
    </div>
</dialog>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>
