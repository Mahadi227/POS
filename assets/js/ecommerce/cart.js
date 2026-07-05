(function () {
    'use strict';

    var cfg = window.ECOM || {};
    var apiBase = (cfg.apiBase || 'api/').replace(/\/?$/, '/');
    var i18n = cfg.i18n || {};
    var paystackCfg = cfg.paystack || {};
    var checkoutCfg = cfg.checkout || {};

    function apiUrl(route) {
        var url = apiBase + (apiBase.indexOf('?') >= 0 ? '&' : '?') + 'route=' + encodeURIComponent(route);
        if (cfg.tenantSlug && url.indexOf((cfg.tenantParam || 'tenant') + '=') < 0) {
            url += '&' + (cfg.tenantParam || 'tenant') + '=' + encodeURIComponent(cfg.tenantSlug);
        }
        if (cfg.storeId && url.indexOf('store_id=') < 0) {
            url += '&store_id=' + encodeURIComponent(cfg.storeId);
        }
        return url;
    }

    function t(key, fallback) {
        return i18n[key] || fallback || key;
    }

    function post(route, body) {
        return fetch(apiUrl(route), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body || {})
        }).then(function (r) { return r.json(); });
    }

    function setBadge(id, n) {
        var el = document.getElementById(id);
        if (el) el.textContent = String(n);
    }

    function debounce(fn, ms) {
        var timer;
        return function () {
            var args = arguments;
            var ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    function updateQty(productId, quantity, form) {
        post('cart/update', { product_id: productId, quantity: quantity }).then(function (res) {
            if (res.ok) {
                setBadge('ecom-cart-count', res.count);
                window.location.reload();
            }
        }).catch(function () {
            if (form) form.submit();
        });
    }

    document.querySelectorAll('.ecom-qty-form').forEach(function (form) {
        var productId = parseInt(form.getAttribute('data-product-id'), 10);
        var input = form.querySelector('.ecom-qty-stepper__input');
        if (!input) return;

        var submitUpdate = debounce(function () {
            var qty = parseInt(input.value, 10);
            var max = parseInt(input.getAttribute('max'), 10) || 99;
            if (isNaN(qty) || qty < 1) qty = 1;
            if (qty > max) qty = max;
            input.value = String(qty);
            updateQty(productId, qty, form);
        }, 400);

        form.querySelectorAll('[data-qty-delta]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var delta = parseInt(btn.getAttribute('data-qty-delta'), 10);
                var max = parseInt(input.getAttribute('max'), 10) || 99;
                var next = parseInt(input.value, 10) + delta;
                if (next < 1) next = 1;
                if (next > max) next = max;
                input.value = String(next);
                submitUpdate();
            });
        });

        input.addEventListener('change', submitUpdate);
    });

    document.querySelectorAll('.ecom-cart-clear-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var msg = form.getAttribute('data-confirm') || 'Clear cart?';
            if (!window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });

    document.querySelectorAll('.ecom-pay-option__input').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.ecom-pay-option').forEach(function (el) {
                el.classList.remove('is-selected');
            });
            var label = radio.closest('.ecom-pay-option');
            if (label) label.classList.add('is-selected');
            updateCheckoutSubmitLabel();
        });
    });

    function selectedPaymentMethod() {
        var checked = document.querySelector('.ecom-pay-option__input:checked');
        return checked ? checked.value : 'card';
    }

    function updateCheckoutSubmitLabel() {
        var el = document.getElementById('ecomCheckoutSubmitLabel');
        if (!el) return;
        var method = selectedPaymentMethod();
        if (method === 'cash_on_delivery') {
            el.textContent = t('place_order', 'Place order');
        } else {
            el.textContent = t('pay_with_paystack', 'Pay with Paystack');
        }
    }

    function guestField(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function resolveGuestDetails() {
        if (!checkoutCfg.isGuest) {
            return {
                name: '',
                email: checkoutCfg.accountEmail || '',
                phone: ''
            };
        }
        return {
            name: guestField('ecomCheckoutName'),
            email: guestField('ecomCheckoutEmail'),
            phone: guestField('ecomCheckoutPhone')
        };
    }

    function validateGuestDetails() {
        if (!checkoutCfg.isGuest) {
            return true;
        }
        var details = resolveGuestDetails();
        if (!details.name) {
            window.alert(t('checkout_name_required', 'Enter your full name.'));
            document.getElementById('ecomCheckoutName')?.focus();
            return false;
        }
        if (details.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(details.email)) {
            window.alert(t('checkout_email_invalid', 'Enter a valid email address or leave it blank.'));
            document.getElementById('ecomCheckoutEmail')?.focus();
            return false;
        }
        if (!details.phone) {
            window.alert(t('checkout_phone_required', 'Enter your phone number.'));
            document.getElementById('ecomCheckoutPhone')?.focus();
            return false;
        }
        var digits = details.phone.replace(/\D/g, '');
        if (digits.length < 8) {
            window.alert(t('checkout_phone_invalid', 'Enter a valid phone number.'));
            document.getElementById('ecomCheckoutPhone')?.focus();
            return false;
        }
        return true;
    }

    function setSubmitLoading(loading) {
        var btn = document.getElementById('ecomCheckoutSubmit');
        if (!btn) return;
        btn.disabled = loading;
        btn.classList.toggle('is-loading', loading);
    }

    function redirectToOrder(saleId, paid) {
        var base = checkoutCfg.orderViewUrl || 'orders/view.php';
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        var url = base + sep + 'id=' + encodeURIComponent(String(saleId)) + '&placed=1';
        if (paid) url += '&paid=1';
        window.location.href = url;
    }

    function submitRedirectFallback(form) {
        var hidden = document.getElementById('ecomPaystackRedirect');
        if (hidden) hidden.value = '1';
        form.submit();
    }

    function openPaystackPop(pop, redirectUrl, form) {
        if (!window.PaystackPop || !pop.public_key) {
            if (redirectUrl) {
                window.location.href = redirectUrl;
                return;
            }
            submitRedirectFallback(form);
            return;
        }

        var handler = PaystackPop.setup({
            key: pop.public_key,
            email: pop.email,
            amount: pop.amount,
            currency: pop.currency,
            ref: pop.reference,
            channels: pop.channels,
            metadata: pop.metadata || {},
            callback: function (response) {
                setSubmitLoading(true);
                post('checkout/paystack-verify', { reference: response.reference })
                    .then(function (res) {
                        if (res.ok && res.sale_id) {
                            redirectToOrder(res.sale_id, true);
                            return;
                        }
                        window.alert(res.message || t('paystack_error', 'Payment verification failed.'));
                        setSubmitLoading(false);
                    })
                    .catch(function () {
                        window.alert(t('paystack_error', 'Payment verification failed.'));
                        setSubmitLoading(false);
                    });
            },
            onClose: function () {
                setSubmitLoading(false);
            }
        });

        try {
            if (typeof handler.openIframe === 'function') {
                handler.openIframe();
            } else if (typeof handler.open === 'function') {
                handler.open();
            } else {
                throw new Error('PaystackPop handler unavailable');
            }
        } catch (err) {
            if (redirectUrl) {
                window.location.href = redirectUrl;
                return;
            }
            submitRedirectFallback(form);
        }
    }

    function startPaystackCheckout(form, method) {
        var details = resolveGuestDetails();
        setSubmitLoading(true);

        post('checkout/paystack-init', {
            payment_method: method,
            email: details.email,
            phone: details.phone,
            name: details.name
        }).then(function (res) {
            if (!res.ok || !res.pop) {
                window.alert(res.message || t('paystack_error', 'Could not start payment.'));
                setSubmitLoading(false);
                return;
            }
            openPaystackPop(res.pop, res.redirect_url || '', form);
        }).catch(function () {
            window.alert(t('paystack_error', 'Could not start payment.'));
            setSubmitLoading(false);
        });
    }

    var checkoutForm = document.getElementById('ecom-checkout-form');
    if (checkoutForm && checkoutCfg.isCheckout) {
        updateCheckoutSubmitLabel();

        checkoutForm.addEventListener('submit', function (e) {
            var terms = checkoutForm.querySelector('input[name="terms_accepted"]');
            if (terms && !terms.checked) {
                e.preventDefault();
                window.alert(t('terms_required', 'Please accept the terms to continue.'));
                return;
            }

            if (!validateGuestDetails()) {
                e.preventDefault();
                return;
            }

            var method = selectedPaymentMethod();
            if (method === 'cash_on_delivery') {
                return;
            }

            if (!paystackCfg.enabled) {
                e.preventDefault();
                window.alert(t('paystack_error', 'Online payment is not available.'));
                return;
            }

            e.preventDefault();
            startPaystackCheckout(checkoutForm, method);
        });
    }
})();
