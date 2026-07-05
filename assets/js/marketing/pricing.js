/**
 * Pricing page — billing toggle, synced prices, persistence
 */
(function () {
    'use strict';

    const root = document.querySelector('.mkt-pricing-page');
    if (!root) return;

    const STORAGE_KEY = 'mkt_pricing_billing';
    const discount = parseFloat(root.dataset.annualDiscount || '0.85');
    const locale = root.dataset.locale || 'en-US';
    const labelMonthly = root.dataset.billingMonthly || '/month';
    const labelAnnual = root.dataset.billingAnnual || '/mo billed yearly';
    const statusEl = document.getElementById('mkt-pricing-billing-status');

    const symbols = { EUR: '€', USD: '$', XOF: 'CFA ', GBP: '£' };

    function formatPrice(amount, currency) {
        const code = (currency || 'EUR').toUpperCase();
        const sym = symbols[code] || code + ' ';
        const hasDecimals = Math.abs(amount - Math.round(amount)) > 0.001;
        const formatted = Number(amount).toLocaleString(locale, {
            minimumFractionDigits: hasDecimals ? 2 : 0,
            maximumFractionDigits: 2,
        });
        return sym.endsWith(' ') ? sym + formatted : sym + formatted;
    }

    function getInitialMode() {
        const params = new URLSearchParams(window.location.search);
        const fromUrl = params.get('billing');
        if (fromUrl === 'annual' || fromUrl === 'monthly') {
            return fromUrl;
        }
        try {
            const stored = sessionStorage.getItem(STORAGE_KEY);
            if (stored === 'annual' || stored === 'monthly') {
                return stored;
            }
        } catch (_) { /* ignore */ }
        return 'monthly';
    }

    function updateCards(mode) {
        const isAnnual = mode === 'annual';

        document.querySelectorAll('.mkt-price-card[data-monthly-price]').forEach((card) => {
            const monthly = parseFloat(card.dataset.monthlyPrice || '0');
            const annualTotal = parseFloat(card.dataset.annualTotal || '0');
            const currency = card.dataset.currency || 'EUR';
            const valueEl = card.querySelector('.mkt-price-card__value');
            const intervalEl = card.querySelector('.mkt-price-card__interval');
            const annualNote = card.querySelector('.mkt-price-card__annual-note');
            if (!valueEl) return;

            const display = isAnnual ? monthly * discount : monthly;

            valueEl.classList.add('is-updating');
            valueEl.textContent = formatPrice(display, currency);
            window.setTimeout(() => valueEl.classList.remove('is-updating'), 280);

            if (intervalEl) {
                intervalEl.textContent = isAnnual ? labelAnnual : labelMonthly;
            }

            if (annualNote) {
                annualNote.hidden = !isAnnual;
                if (isAnnual && annualTotal > 0 && annualNote.dataset.template) {
                    annualNote.textContent = annualNote.dataset.template.replace(
                        '%s',
                        formatPrice(annualTotal, currency)
                    );
                }
            }

            card.classList.toggle('is-annual', isAnnual);
        });
    }

    function updateCompareTable(mode) {
        const isAnnual = mode === 'annual';

        document.querySelectorAll('.mkt-js-plan-price').forEach((cell) => {
            const monthly = parseFloat(cell.dataset.monthlyPrice || '0');
            const currency = cell.dataset.currency || 'EUR';
            const valueEl = cell.querySelector('.mkt-js-plan-value');
            const intervalEl = cell.querySelector('.mkt-js-plan-interval');
            const display = isAnnual ? monthly * discount : monthly;

            if (valueEl) {
                valueEl.textContent = formatPrice(display, currency);
            }
            if (intervalEl) {
                intervalEl.textContent = isAnnual ? labelAnnual : labelMonthly;
            }
        });
    }

    function updateBillingThumb() {
        requestAnimationFrame(() => {
            const group = document.querySelector('.mkt-pricing-billing');
            const thumb = group?.querySelector('.mkt-pricing-billing__thumb');
            const active = group?.querySelector('.mkt-pricing-billing__btn.is-active');
            if (!group || !thumb || !active) return;

            thumb.style.width = active.offsetWidth + 'px';
            thumb.style.transform = 'translateX(' + active.offsetLeft + 'px)';
        });
    }

    function updateSignupLinks(mode) {
        document.querySelectorAll('.mkt-price-card__cta[data-signup-cta]').forEach((link) => {
            if (!link.dataset.baseHref) {
                link.dataset.baseHref = link.getAttribute('href') || '';
            }
            const base = link.dataset.baseHref;
            const sep = base.includes('?') ? '&' : '?';
            link.setAttribute('href', base + sep + 'billing=' + encodeURIComponent(mode));
        });
    }

    function setBilling(mode) {
        const isAnnual = mode === 'annual';

        document.querySelectorAll('.mkt-pricing-billing__btn').forEach((btn) => {
            const active = btn.dataset.billing === mode;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        root.classList.toggle('is-annual-billing', isAnnual);
        updateCards(mode);
        updateCompareTable(mode);
        updateSignupLinks(mode);

        if (statusEl) {
            statusEl.textContent = isAnnual
                ? (statusEl.dataset.annual || '')
                : (statusEl.dataset.monthly || '');
        }

        updateBillingThumb();

        try {
            sessionStorage.setItem(STORAGE_KEY, mode);
        } catch (_) { /* ignore */ }

        const url = new URL(window.location.href);
        url.searchParams.set('billing', mode);
        window.history.replaceState({}, '', url);
    }

    const billingGroup = document.querySelector('.mkt-pricing-billing');
    if (billingGroup) {
        billingGroup.querySelectorAll('.mkt-pricing-billing__btn').forEach((btn) => {
            btn.addEventListener('click', () => setBilling(btn.dataset.billing || 'monthly'));
        });

        billingGroup.addEventListener('keydown', (e) => {
            const buttons = [...billingGroup.querySelectorAll('.mkt-pricing-billing__btn')];
            const idx = buttons.findIndex((b) => b.classList.contains('is-active'));
            if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                e.preventDefault();
                const next = e.key === 'ArrowRight'
                    ? buttons[(idx + 1) % buttons.length]
                    : buttons[(idx - 1 + buttons.length) % buttons.length];
                if (next) {
                    next.focus();
                    setBilling(next.dataset.billing || 'monthly');
                }
            }
        });
    }

    document.querySelectorAll('.mkt-price-card__annual-note').forEach((note) => {
        note.dataset.template = note.textContent.trim();
    });

    setBilling(getInitialMode());

    window.addEventListener('resize', updateBillingThumb, { passive: true });

    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(updateBillingThumb);
    }

    function highlightPlanColumn(planCode, on) {
        if (!planCode) return;
        document.querySelectorAll(
            '.mkt-price-card[data-plan-code="' + planCode + '"], ' +
            '.mkt-pricing-compare [data-plan-code="' + planCode + '"]'
        ).forEach((el) => el.classList.toggle('is-highlighted', on));
    }

    document.querySelectorAll('.mkt-price-card[data-plan-code]').forEach((card) => {
        const code = card.dataset.planCode;
        card.addEventListener('mouseenter', () => highlightPlanColumn(code, true));
        card.addEventListener('mouseleave', () => highlightPlanColumn(code, false));
    });

    document.querySelector('.mkt-pricing-page__compare-link a')?.addEventListener('click', (e) => {
        const target = document.getElementById('compare');
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });

    if ('IntersectionObserver' in window) {
        const cards = document.querySelectorAll('.mkt-pricing-page .mkt-price-card');
        cards.forEach((card, i) => {
            card.style.setProperty('--card-delay', (i * 70) + 'ms');
            card.classList.add('mkt-price-card--animate');
        });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -32px 0px' });

        cards.forEach((card) => observer.observe(card));
    }
})();
