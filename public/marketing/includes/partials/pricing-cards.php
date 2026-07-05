<?php
declare(strict_types=1);

/** @var bool $pricingCompact */
$pricingCompact = $pricingCompact ?? false;
$plans = mkt_pricing_plans();
?>
<div class="mkt-pricing<?php echo $pricingCompact ? ' mkt-pricing--compact' : ''; ?>">
    <?php foreach ($plans as $plan): ?>
    <?php
        $dbCode = (string) $plan['code'];
        $labelKey = mkt_plan_label_key($plan);
        $isEnterprise = ($plan['marketing_code'] ?? '') === 'enterprise' || $dbCode === 'enterprise';
        $isFeatured = !empty($plan['featured']);
        $ctaHref = $isEnterprise ? 'contact.php' : mkt_signup_url($dbCode);
        $ctaKey = $isEnterprise ? 'mkt_plan_contact' : 'mkt_plan_cta';
        $ctaClass = $isEnterprise ? 'mkt-btn--ghost' : 'mkt-btn--primary';
        $price = (float) $plan['price'];
        $currency = (string) ($plan['currency'] ?? 'EUR');
        $annualTotal = MarketingPricingService::annualTotal($price);
    ?>
    <article class="mkt-price-card<?php echo $isFeatured ? ' mkt-price-card--featured' : ''; ?>"
             id="plan-<?php echo htmlspecialchars($dbCode, ENT_QUOTES, 'UTF-8'); ?>"
             data-plan-code="<?php echo htmlspecialchars($dbCode, ENT_QUOTES, 'UTF-8'); ?>"
             data-monthly-price="<?php echo htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8'); ?>"
             data-annual-total="<?php echo htmlspecialchars((string) $annualTotal, ENT_QUOTES, 'UTF-8'); ?>"
             data-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($isFeatured): ?>
        <span class="mkt-price-card__badge"><?php echo __t('mkt_plan_popular', 'marketing'); ?></span>
        <?php endif; ?>

        <header class="mkt-price-card__head">
            <h3 class="mkt-price-card__name"><?php echo __t($labelKey, 'marketing'); ?></h3>
            <div class="mkt-price-card__amount" aria-live="polite" aria-atomic="true">
                <span class="mkt-price-card__value"><?php echo mkt_format_price($price, $currency); ?></span>
                <span class="mkt-price-card__interval"
                      data-monthly="<?php echo htmlspecialchars(__t('mkt_plan_month', 'marketing'), ENT_QUOTES, 'UTF-8'); ?>"
                      data-annual="<?php echo htmlspecialchars(__t('mkt_plan_month_billed', 'marketing'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo __t('mkt_plan_month', 'marketing'); ?>
                </span>
            </div>
            <p class="mkt-price-card__annual-note" hidden>
                <?php echo sprintf(__t('mkt_plan_annual_total', 'marketing'), mkt_format_price($annualTotal, $currency)); ?>
            </p>
            <div class="mkt-price-card__limits">
                <span class="mkt-price-card__limit">
                    <span class="material-icons-round" aria-hidden="true">store</span>
                    <?php
                    echo $plan['stores'] === null
                        ? __t('mkt_plan_stores_unlimited', 'marketing')
                        : sprintf(__t('mkt_plan_stores', 'marketing'), (int) $plan['stores']);
                    ?>
                </span>
                <span class="mkt-price-card__limit">
                    <span class="material-icons-round" aria-hidden="true">group</span>
                    <?php
                    echo $plan['users'] === null
                        ? __t('mkt_plan_users_unlimited', 'marketing')
                        : sprintf(__t('mkt_plan_users', 'marketing'), (int) $plan['users']);
                    ?>
                </span>
            </div>
        </header>

        <?php if (!$pricingCompact): ?>
        <div class="mkt-price-card__body">
            <p class="mkt-price-card__includes"><?php echo __t('mkt_pricing_includes', 'marketing'); ?></p>
            <ul class="mkt-price-card__features">
                <?php foreach (mkt_pricing_preview_features() as $feat): ?>
                <?php $included = mkt_plan_has_feature($plan, $feat); ?>
                <li class="<?php echo trim(($included ? '' : 'is-no ') . ($feat === 'ecommerce' ? 'is-ecom' : '')); ?>"
                    aria-label="<?php echo __t('mkt_pf_' . $feat, 'marketing'); ?>: <?php echo $included ? __t('mkt_pricing_yes', 'marketing') : __t('mkt_pricing_no', 'marketing'); ?>">
                    <span class="material-icons-round" aria-hidden="true"><?php echo $included ? 'check' : 'close'; ?></span>
                    <?php echo __t('mkt_pf_' . $feat, 'marketing'); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <footer class="mkt-price-card__foot">
            <a href="<?php echo htmlspecialchars($ctaHref, ENT_QUOTES, 'UTF-8'); ?>"
               class="mkt-btn <?php echo $ctaClass; ?> mkt-price-card__cta"
               <?php echo $isEnterprise ? '' : 'data-signup-cta="1"'; ?>>
                <?php echo __t($ctaKey, 'marketing'); ?>
            </a>
            <p class="mkt-price-card__trial"<?php echo $isEnterprise ? ' aria-hidden="true"' : ''; ?>>
                <?php echo $isEnterprise ? "\u{00A0}" : __t('mkt_pricing_card_trial', 'marketing'); ?>
            </p>
        </footer>
    </article>
    <?php endforeach; ?>
</div>
