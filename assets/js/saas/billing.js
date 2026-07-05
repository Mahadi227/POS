(function () {
    'use strict';

    const cfg = window.BILLING_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;
    let selectedProvider = 'stripe';
    let upgradingPlan = null;

    function showAlert(message, type) {
        const el = document.getElementById('billingAlert');
        if (!el) return;
        el.textContent = message;
        el.className = 'billing-alert billing-alert--' + (type || 'error') + ' is-visible';
        el.setAttribute('role', type === 'success' ? 'status' : 'alert');
    }

    function hideAlert() {
        const el = document.getElementById('billingAlert');
        if (!el) return;
        el.className = 'billing-alert';
        el.textContent = '';
    }

    async function api(path, options) {
        const url = `${cfg.apiBase}?request=billing/${path}`;
        const res = await fetch(url, { credentials: 'same-origin', ...options });
        return res.json();
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleDateString();
        } catch (_) {
            return iso;
        }
    }

    function renderCurrent(data) {
        const el = document.getElementById('billingCurrent');
        if (!el || !data) return;

        const u = data.usage || {};
        const l = data.limits || {};
        const status = data.subscription_status || 'active';
        const storesLimit = l.stores != null ? ` / ${l.stores}` : '';
        const usersLimit = l.users != null ? ` / ${l.users}` : '';

        el.innerHTML = `
            <h2>${escapeHtml(t('current_plan'))}: <strong>${escapeHtml(data.plan_name || '—')}</strong></h2>
            <div class="billing-current__grid">
                <div class="billing-metric">
                    <span class="billing-metric__label">${escapeHtml(t('usage_stores'))}</span>
                    <span class="billing-metric__value">${u.stores ?? 0}${escapeHtml(storesLimit)}</span>
                </div>
                <div class="billing-metric">
                    <span class="billing-metric__label">${escapeHtml(t('usage_users'))}</span>
                    <span class="billing-metric__value">${u.users ?? 0}${escapeHtml(usersLimit)}</span>
                </div>
            </div>
            <div class="billing-current__meta">
                <span>${escapeHtml(t('billing_status'))}:
                    <span class="plat-badge plat-badge--${escapeHtml(status)}">${escapeHtml(status)}</span>
                </span>
                ${data.trial_ends_at ? `<span>${escapeHtml(t('trial_ends'))}: ${escapeHtml(formatDate(data.trial_ends_at))}</span>` : ''}
            </div>
        `;
    }

    function setupProviders(providers) {
        document.querySelectorAll('.billing-provider-btn').forEach((btn) => {
            const p = btn.dataset.provider;
            if (providers && providers[p] === false) {
                btn.disabled = true;
            }
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', () => {
                if (btn.disabled) return;
                selectedProvider = p;
                document.querySelectorAll('.billing-provider-btn').forEach((b) => {
                    const active = b === btn;
                    b.classList.toggle('is-active', active);
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                const mm = document.getElementById('billingMmFields');
                if (mm) mm.hidden = p !== 'mobile_money';
            });
        });
    }

    function setUpgradeLoading(planCode, isLoading) {
        upgradingPlan = isLoading ? planCode : null;
        document.querySelectorAll('.billing-upgrade-btn').forEach((btn) => {
            const active = btn.dataset.plan === planCode && isLoading;
            btn.disabled = isLoading || btn.dataset.current === '1';
            btn.classList.toggle('is-loading', active);
            btn.setAttribute('aria-busy', active ? 'true' : 'false');
        });
    }

    function renderPlans(plans, currentCode) {
        const grid = document.getElementById('billingPlans');
        if (!grid) return;

        grid.innerHTML = (plans || []).map((p) => {
            let mod = p.modules;
            if (!mod && p.modules_json) {
                try {
                    mod = typeof p.modules_json === 'string' ? JSON.parse(p.modules_json) : p.modules_json;
                } catch (_) {
                    mod = {};
                }
            }
            const modules = Object.entries(mod || {}).filter(([, v]) => v).map(([k]) => k).join(', ');
            const isCurrent = p.code === currentCode;
            const label = isCurrent ? t('plan_current') : t('upgrade');
            return `
            <article class="billing-plan-card${isCurrent ? ' is-current' : ''}">
                <h3>${escapeHtml(p.name)}</h3>
                <p class="billing-plan-price">${escapeHtml(String(p.price_monthly))} ${escapeHtml(p.currency)}<span>/mo</span></p>
                <p class="billing-plan-modules">${escapeHtml(modules || '—')}</p>
                <button type="button" class="billing-upgrade-btn" data-plan="${escapeHtml(p.code)}"
                        data-current="${isCurrent ? '1' : '0'}" ${isCurrent ? 'disabled' : ''}>
                    <span class="btn-label">${escapeHtml(label)}</span>
                    <span class="spinner" aria-hidden="true"></span>
                </button>
            </article>`;
        }).join('');

        grid.querySelectorAll('.billing-upgrade-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (btn.disabled || upgradingPlan) return;
                upgrade(btn.dataset.plan);
            });
        });
    }

    async function upgrade(planCode) {
        hideAlert();
        setUpgradeLoading(planCode, true);

        const successUrl = window.location.origin + window.location.pathname + '?success=1&provider=' + selectedProvider;
        const cancelUrl = window.location.href.split('?')[0];
        const body = {
            plan_code: planCode,
            provider: selectedProvider,
            success_url: successUrl,
            cancel_url: cancelUrl,
        };

        if (selectedProvider === 'mobile_money') {
            body.phone = document.getElementById('billingMmPhone')?.value?.trim() || '';
            body.mobile_provider = document.getElementById('billingMmProvider')?.value || 'wave';
        }

        try {
            const res = await api('checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });

            if (res.status === 'success') {
                if (res.data?.url) {
                    window.location.href = res.data.url;
                    return;
                }
                if (res.data?.mode === 'mobile_money' || res.data?.mode === 'mobile_money_demo') {
                    showAlert(res.data.message || t('mm_pending'), 'success');
                    if (res.data.reference) {
                        await api('complete', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ reference: res.data.reference, provider: 'mobile_money' }),
                        });
                        await load(true);
                    }
                    return;
                }
                if (res.data?.mode === 'simulated') {
                    window.location.href = successUrl;
                    return;
                }
            }

            showAlert(res.message || t('checkout_error'), 'error');
        } catch (_) {
            showAlert(t('load_error'), 'error');
        } finally {
            setUpgradeLoading(planCode, false);
        }
    }

    function showLoadingCurrent() {
        const el = document.getElementById('billingCurrent');
        if (!el) return;
        el.innerHTML = `<div class="billing-loading"><span class="spinner" aria-hidden="true"></span>${escapeHtml(t('loading'))}…</div>`;
    }

    async function load(reloadAfterComplete) {
        showLoadingCurrent();
        try {
            const res = await api('subscription');
            if (res.status !== 'success') throw new Error('load');

            renderCurrent(res.data);
            renderPlans(res.plans, res.data?.plan_code);
            setupProviders(res.providers);

            if (reloadAfterComplete) {
                showAlert(t('billing_success_msg'), 'success');
                return;
            }

            const params = new URLSearchParams(window.location.search);
            const ref = cfg.reference || params.get('reference');
            const provider = cfg.provider || params.get('provider') || 'stripe';

            if (cfg.success && (cfg.sessionId || ref)) {
                await api('complete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: cfg.sessionId,
                        reference: ref,
                        provider,
                    }),
                });
                if (window.history.replaceState) {
                    window.history.replaceState({}, '', window.location.pathname);
                }
                await load(true);
                return;
            }

            if (cfg.success) {
                showAlert(t('billing_success_msg'), 'success');
            }
        } catch (_) {
            const el = document.getElementById('billingCurrent');
            if (el) {
                el.innerHTML = `<p class="billing-alert billing-alert--error is-visible" style="display:block;margin:0;">${escapeHtml(t('load_error'))}</p>`;
            }
        }
    }

    document.getElementById('billingThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    load(false);
})();
