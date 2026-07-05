<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$activePage = 'pricing';
$pageTitle = __t('mkt_pricing_title', 'marketing');
$pageMeta = ['description' => __t('mkt_pricing_desc', 'marketing')];
$extraStyles = ['marketing-pricing.css'];
$extraScripts = ['pricing.js'];

$plans = mkt_pricing_plans();
$trustItems = ['trial', 'no_card', 'cancel'];
$annualDiscountPct = (int) round((1 - MarketingPricingService::annualDiscount()) * 100);

require __DIR__ . '/includes/layout-start.php';
?>

<section class="mkt-pricing-page"
         data-locale="<?php echo htmlspecialchars($activeLang === 'fr' ? 'fr-FR' : 'en-US', ENT_QUOTES, 'UTF-8'); ?>"
         data-annual-discount="<?php echo MarketingPricingService::annualDiscount(); ?>"
         data-billing-monthly="<?php echo htmlspecialchars(__t('mkt_plan_month', 'marketing'), ENT_QUOTES, 'UTF-8'); ?>"
         data-billing-annual="<?php echo htmlspecialchars(__t('mkt_plan_month_billed', 'marketing'), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="mkt-pricing-page__hero">
        <div class="mkt-container mkt-pricing-page__hero-inner">
            <p class="mkt-pricing-page__eyebrow"><?php echo __t('mkt_pricing_eyebrow', 'marketing'); ?></p>
            <h1 class="mkt-pricing-page__title"><?php echo __t('mkt_pricing_title', 'marketing'); ?></h1>
            <p class="mkt-pricing-page__desc"><?php echo __t('mkt_pricing_desc', 'marketing'); ?></p>

            <ul class="mkt-pricing-trust" aria-label="<?php echo __t('mkt_pricing_trust_label', 'marketing'); ?>">
                <?php foreach ($trustItems as $item): ?>
                <li>
                    <span class="material-icons-round" aria-hidden="true">check_circle</span>
                    <?php echo __t('mkt_pricing_trust_' . $item, 'marketing'); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="mkt-pricing-page__cards" id="plans">
        <div class="mkt-container">
            <div class="mkt-pricing-toolbar" role="region" aria-label="<?php echo __t('mkt_pricing_billing_label', 'marketing'); ?>">
                <div class="mkt-pricing-toolbar__left">
                    <div class="mkt-pricing-billing" role="group" aria-label="<?php echo __t('mkt_pricing_billing_label', 'marketing'); ?>"
                         aria-describedby="mkt-pricing-billing-status">
                        <span class="mkt-pricing-billing__thumb" aria-hidden="true"></span>
                        <button type="button" class="mkt-pricing-billing__btn is-active" data-billing="monthly" aria-pressed="true">
                            <?php echo __t('mkt_pricing_billing_monthly', 'marketing'); ?>
                        </button>
                        <button type="button" class="mkt-pricing-billing__btn" data-billing="annual" aria-pressed="false">
                            <?php echo __t('mkt_pricing_billing_annual', 'marketing'); ?>
                            <span class="mkt-pricing-billing__save"><?php echo sprintf(__t('mkt_pricing_billing_save', 'marketing'), $annualDiscountPct); ?></span>
                        </button>
                    </div>
                </div>
                <div class="mkt-pricing-toolbar__right">
                    <p class="mkt-pricing-sync" title="<?php echo __t('mkt_pricing_live', 'marketing'); ?>">
                        <span class="mkt-pricing-sync__icon" aria-hidden="true">
                            <span class="material-icons-round">sync</span>
                        </span>
                        <span class="mkt-pricing-sync__text"><?php echo __t('mkt_pricing_live', 'marketing'); ?></span>
                    </p>
                </div>
            </div>

            <p class="mkt-pricing-billing-hint" id="mkt-pricing-billing-status" aria-live="polite"
               data-monthly="<?php echo htmlspecialchars(__t('mkt_pricing_billing_status_monthly', 'marketing'), ENT_QUOTES, 'UTF-8'); ?>"
               data-annual="<?php echo htmlspecialchars(__t('mkt_pricing_billing_status_annual', 'marketing'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo __t('mkt_pricing_billing_status_monthly', 'marketing'); ?>
            </p>

            <?php $pricingCompact = false; require __DIR__ . '/includes/partials/pricing-cards.php'; ?>

            <p class="mkt-pricing-page__compare-link">
                <a href="#compare">
                    <?php echo __t('mkt_pricing_compare_link', 'marketing'); ?>
                    <span class="material-icons-round" aria-hidden="true">arrow_downward</span>
                </a>
            </p>
        </div>
    </div>
</section>

<section class="mkt-section mkt-section--alt mkt-pricing-compare" id="compare">
    <div class="mkt-container">
        <div class="mkt-section__head">
            <div class="mkt-section__badge">
                <span class="material-icons-round" aria-hidden="true">compare_arrows</span>
                <?php echo __t('mkt_compare_title', 'marketing'); ?>
            </div>
            <h2 class="mkt-section__title"><?php echo __t('mkt_compare_title', 'marketing'); ?></h2>
            <p class="mkt-section__desc"><?php echo __t('mkt_pricing_compare_desc', 'marketing'); ?></p>
        </div>

        <p class="mkt-pricing-compare__hint">
            <span class="material-icons-round" aria-hidden="true">swipe</span>
            <?php echo __t('mkt_pricing_compare_hint', 'marketing'); ?>
        </p>

        <div class="mkt-table-wrap mkt-table-wrap--pricing">
            <table class="mkt-table mkt-table--pricing">
                <thead>
                    <tr>
                        <th scope="col"><?php echo __t('mkt_pricing_feature_col', 'marketing'); ?></th>
                        <?php foreach ($plans as $plan): ?>
                        <?php
                            $isFeatured = !empty($plan['featured']);
                            $price = (float) $plan['price'];
                            $currency = (string) ($plan['currency'] ?? 'EUR');
                        ?>
                        <th scope="col" class="<?php echo $isFeatured ? 'is-featured' : ''; ?>"
                            data-plan-code="<?php echo htmlspecialchars((string) $plan['code'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="mkt-table__plan-name"><?php echo __t(mkt_plan_label_key($plan), 'marketing'); ?></span>
                            <span class="mkt-table__plan-price mkt-js-plan-price"
                                  data-monthly-price="<?php echo htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8'); ?>"
                                  data-currency="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="mkt-js-plan-value"><?php echo mkt_format_price($price, $currency); ?></span>
                                <small class="mkt-js-plan-interval"><?php echo __t('mkt_plan_month', 'marketing'); ?></small>
                            </span>
                            <?php if ($isFeatured): ?>
                            <span class="mkt-table__plan-badge"><?php echo __t('mkt_plan_popular', 'marketing'); ?></span>
                            <?php endif; ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (mkt_pricing_features() as $feat): ?>
                    <tr class="<?php echo $feat === 'ecommerce' ? 'mkt-table__row--ecom' : ''; ?>">
                        <th scope="row">
                            <?php if ($feat === 'ecommerce'): ?>
                            <span class="material-icons-round mkt-table__row-icon" aria-hidden="true">storefront</span>
                            <?php endif; ?>
                            <?php echo __t('mkt_pf_' . $feat, 'marketing'); ?>
                        </th>
                        <?php foreach ($plans as $plan): ?>
                        <?php
                            $has = mkt_plan_has_feature($plan, $feat);
                            $isFeatured = !empty($plan['featured']);
                        ?>
                        <td class="<?php echo ($has ? 'yes' : 'no') . ($isFeatured ? ' is-featured' : ''); ?>"
                            data-plan-code="<?php echo htmlspecialchars((string) $plan['code'], ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="material-icons-round" aria-hidden="true"><?php echo $has ? 'check_circle' : 'remove_circle_outline'; ?></span>
                            <span class="mkt-sr-only"><?php echo $has ? __t('mkt_pricing_yes', 'marketing') : __t('mkt_pricing_no', 'marketing'); ?></span>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="mkt-section mkt-pricing-faq" id="pricing-faq">
    <div class="mkt-container">
        <div class="mkt-section__head">
            <h2 class="mkt-section__title"><?php echo __t('mkt_pricing_faq_title', 'marketing'); ?></h2>
            <p class="mkt-section__desc"><?php echo __t('mkt_pricing_faq_desc', 'marketing'); ?></p>
        </div>
        <div class="mkt-faq mkt-pricing-faq__list">
            <?php foreach (mkt_pricing_faq_items() as $key): ?>
            <div class="mkt-faq__item">
                <button type="button" class="mkt-faq__question">
                    <?php echo __t('mkt_faq_' . $key . '_q', 'marketing'); ?>
                    <span class="material-icons-round" aria-hidden="true">expand_more</span>
                </button>
                <div class="mkt-faq__answer">
                    <p><?php echo __t('mkt_faq_' . $key . '_a', 'marketing'); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/partials/cta.php'; ?>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
