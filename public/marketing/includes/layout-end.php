</main>

<footer class="mkt-footer">
    <div class="mkt-footer__grid">
        <div class="mkt-footer__brand">
            <a href="<?php echo $depthPrefix; ?>index.php" class="mkt-logo mkt-logo--footer">
                <span class="mkt-logo__icon" aria-hidden="true"><span class="material-icons-round">storefront</span></span>
                <span class="mkt-logo__text">Retail<span>POS</span></span>
            </a>
            <p class="mkt-footer__tagline"><?php echo __t('mkt_footer_tagline', 'marketing'); ?></p>
            <div class="mkt-footer__social">
                <?php foreach (mkt_social_links() as $social): ?>
                <a href="<?php echo htmlspecialchars($social['href'], ENT_QUOTES, 'UTF-8'); ?>" class="mkt-footer__social-link" aria-label="<?php echo htmlspecialchars($social['label'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                    <span class="material-icons-round" aria-hidden="true"><?php echo htmlspecialchars($social['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mkt-footer__col">
            <h4><?php echo __t('mkt_footer_product', 'marketing'); ?></h4>
            <ul>
                <li><a href="<?php echo $depthPrefix; ?>features.php"><?php echo __t('mkt_nav_features', 'marketing'); ?></a></li>
                <li><a href="<?php echo $depthPrefix; ?>features.php#ecommerce"><?php echo __t('mkt_feat_ecommerce', 'marketing'); ?></a></li>
                <li><a href="<?php echo $depthPrefix; ?>pricing.php"><?php echo __t('mkt_nav_pricing', 'marketing'); ?></a></li>
                <li><a href="<?php echo $depthPrefix; ?>integrations.php"><?php echo __t('mkt_nav_integrations', 'marketing'); ?></a></li>
                <li><a href="<?php echo $depthPrefix; ?>api.php"><?php echo __t('mkt_nav_api', 'marketing'); ?></a></li>
            </ul>
        </div>

        <div class="mkt-footer__col">
            <h4><?php echo __t('mkt_footer_company', 'marketing'); ?></h4>
            <ul>
                <li><a href="<?php echo $depthPrefix; ?>about.php"><?php echo __t('mkt_nav_about', 'marketing'); ?></a></li>
                <li><a href="<?php echo $depthPrefix; ?>careers.php"><?php echo __t('mkt_nav_careers', 'marketing'); ?></a></li>
                <li><a href="<?php echo $depthPrefix; ?>partners.php"><?php echo __t('mkt_nav_partners', 'marketing'); ?></a></li>
                <li><a href="<?php echo $depthPrefix; ?>contact.php"><?php echo __t('mkt_nav_contact', 'marketing'); ?></a></li>
            </ul>
        </div>

        <div class="mkt-footer__col">
            <h4><?php echo __t('mkt_footer_resources', 'marketing'); ?></h4>
            <ul>
                <li><a href="<?php echo $depthPrefix; ?>blog/index.php"><?php echo __t('mkt_nav_blog', 'marketing'); ?></a></li>
                <li><a href="<?php echo $depthPrefix; ?>documentation/index.php"><?php echo __t('mkt_nav_docs', 'marketing'); ?></a></li>
                <li><a href="<?php echo $depthPrefix; ?>support.php"><?php echo __t('mkt_nav_support', 'marketing'); ?></a></li>
                <li><a href="<?php echo $depthPrefix; ?>faq.php"><?php echo __t('mkt_nav_faq', 'marketing'); ?></a></li>
            </ul>
        </div>
    </div>

    <div class="mkt-footer__bottom">
        <p>&copy; <?php echo date('Y'); ?> RetailPOS Cloud. <?php echo __t('mkt_footer_rights', 'marketing'); ?></p>
        <div class="mkt-footer__legal">
            <a href="<?php echo $depthPrefix; ?>privacy-policy.php"><?php echo __t('mkt_nav_privacy', 'marketing'); ?></a>
            <a href="<?php echo $depthPrefix; ?>terms.php"><?php echo __t('mkt_nav_terms', 'marketing'); ?></a>
            <a href="<?php echo $depthPrefix; ?>cookies.php"><?php echo __t('mkt_nav_cookies', 'marketing'); ?></a>
        </div>
    </div>
</footer>

<script src="<?php echo $assetsBase; ?>/js/marketing/marketing.js?v=1" defer></script>
<?php if (!empty($extraScripts)): foreach ($extraScripts as $js): ?>
<script src="<?php echo $assetsBase; ?>/js/marketing/<?php echo htmlspecialchars($js, ENT_QUOTES, 'UTF-8'); ?>?v=4" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
