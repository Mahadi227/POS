(function () {
    'use strict';

    const cfg = window.BILLING_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;
    let selectedProvider = 'stripe';

    async function api(path, options) {
        const url = `${cfg.apiBase}?request=billing/${path}`;
        const res = await fetch(url, { credentials: 'same-origin', ...options });
        return res.json();
    }

    function renderCurrent(data) {
        const el = document.getElementById('billingCurrent');
        if (!el || !data) return;
        const u = data.usage || {};
        const l = data.limits || {};
        el.innerHTML = `
            <h2>${t('current_plan')}: <strong>${data.plan_name || '—'}</strong></h2>
            <p>Status: <span class="plat-badge plat-badge--${data.subscription_status || 'active'}">${data.subscription_status || '—'}</span>
            ${data.trial_ends_at ? ` · ${t('trial_ends')}: ${new Date(data.trial_ends_at).toLocaleDateString()}` : ''}</p>
            <p>${t('usage_stores')}: ${u.stores ?? 0}${l.stores != null ? ` / ${l.stores}` : ''}</p>
            <p>${t('usage_users')}: ${u.users ?? 0}${l.users != null ? ` / ${l.users}` : ''}</p>
        `;
    }

    function setupProviders(providers) {
        document.querySelectorAll('.billing-provider-btn').forEach((btn) => {
            const p = btn.dataset.provider;
            if (providers && providers[p] === false) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
            }
            btn.addEventListener('click', () => {
                selectedProvider = p;
                document.querySelectorAll('.billing-provider-btn').forEach((b) => b.classList.toggle('is-active', b === btn));
                document.getElementById('billingMmFields').hidden = p !== 'mobile_money';
            });
        });
    }

    function renderPlans(plans, currentCode) {
        const grid = document.getElementById('billingPlans');
        if (!grid) return;
        grid.innerHTML = (plans || []).map((p) => {
            let mod = p.modules;
            if (!mod && p.modules_json) {
                mod = typeof p.modules_json === 'string' ? JSON.parse(p.modules_json) : p.modules_json;
            }
            const modules = Object.entries(mod || {}).filter(([, v]) => v).map(([k]) => k).join(', ');
            const isCurrent = p.code === currentCode;
            return `
            <article class="billing-plan-card${isCurrent ? ' is-current' : ''}">
                <h3>${p.name}</h3>
                <p class="billing-plan-price">${p.price_monthly} ${p.currency}<span>/mo</span></p>
                <p class="billing-plan-modules">${modules}</p>
                <button type="button" class="btn-primary billing-upgrade-btn" data-plan="${p.code}" ${isCurrent ? 'disabled' : ''}>
                    ${isCurrent ? 'Current' : t('upgrade')}
                </button>
            </article>`;
        }).join('');

        grid.querySelectorAll('.billing-upgrade-btn').forEach((btn) => {
            btn.addEventListener('click', () => upgrade(btn.dataset.plan));
        });
    }

    async function upgrade(planCode) {
        const successUrl = window.location.origin + window.location.pathname + '?success=1&provider=' + selectedProvider;
        const cancelUrl = window.location.href.split('?')[0];
        const body = {
            plan_code: planCode,
            provider: selectedProvider,
            success_url: successUrl,
            cancel_url: cancelUrl,
        };
        if (selectedProvider === 'mobile_money') {
            body.phone = document.getElementById('billingMmPhone')?.value || '';
            body.mobile_provider = document.getElementById('billingMmProvider')?.value || 'wave';
        }
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
                alert(res.data.message || t('mm_pending'));
                if (res.data.reference) {
                    await api('complete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ reference: res.data.reference, provider: 'mobile_money' }),
                    });
                    load();
                }
                return;
            }
            if (res.data?.mode === 'simulated') {
                window.location.href = successUrl;
            }
        } else {
            alert(res.message || t('load_error'));
        }
    }

    async function load(reloadAfterComplete) {
        try {
            const res = await api('subscription');
            if (res.status !== 'success') throw new Error();
            renderCurrent(res.data);
            renderPlans(res.plans, res.data?.plan_code);
            if (!document.querySelector('.billing-provider-btn.is-active')) {
                setupProviders(res.providers);
            }

            if (reloadAfterComplete) return;

            const ref = cfg.reference || new URLSearchParams(window.location.search).get('reference');
            const provider = cfg.provider || new URLSearchParams(window.location.search).get('provider') || 'stripe';
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
                await load(true);
            }
        } catch (e) {
            document.getElementById('billingCurrent').innerHTML = `<p>${t('load_error')}</p>`;
        }
    }

    load(false);
})();
