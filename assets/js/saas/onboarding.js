(function () {
    'use strict';

    const cfg = window.ONBOARDING_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;

    const form = document.getElementById('onboardingForm');
    const progressBar = document.getElementById('onboardingProgressBar');
    const heroProgressBar = document.getElementById('onboardingHeroProgressBar');
    const progressLabel = document.getElementById('onboardingProgressLabel');
    const stepper = document.getElementById('onboardingStepper');
    const nextBtn = document.getElementById('nextBtn');
    const backBtn = document.getElementById('backBtn');
    const skipBtn = document.getElementById('skipBtn');
    const alertEl = document.getElementById('onboardingAlert');
    const loadingEl = document.getElementById('onboardingLoading');
    const actionsEl = document.getElementById('onboardingActions');
    const completeEl = document.getElementById('onboardingComplete');
    const heroDesc = document.getElementById('onboardingHeroDesc');

    let state = { current_step: 1, total_steps: 6, completed: false, steps: {} };
    let submitting = false;

    const STEPS = [
        {
            n: 1,
            titleKey: 'step1_title',
            descKey: 'step1_desc',
            icon: 'business',
            fields: [
                { id: 'org_name', labelKey: 'org_name', type: 'text', icon: 'business', required: true },
                { id: 'address', labelKey: 'address', type: 'text', icon: 'location_on' },
                { id: 'country_code', labelKey: 'country_code', type: 'text', icon: 'public', value: 'SN' },
                { id: 'currency', labelKey: 'currency', type: 'text', icon: 'payments', value: 'XOF' },
            ],
        },
        {
            n: 2,
            titleKey: 'step2_title',
            descKey: 'step2_desc',
            icon: 'store',
            fields: [
                { id: 'store_name', labelKey: 'store_name', type: 'text', icon: 'store', required: true },
                { id: 'location', labelKey: 'location', type: 'text', icon: 'place' },
            ],
        },
        {
            n: 3,
            titleKey: 'step3_title',
            descKey: 'step3_desc',
            icon: 'groups',
            fields: [
                { id: 'emails', labelKey: 'emails', type: 'textarea', hintKey: 'emails_hint' },
            ],
        },
        {
            n: 4,
            titleKey: 'step4_title',
            descKey: 'step4_desc',
            icon: 'receipt_long',
            fields: [
                { id: 'tax_rate', labelKey: 'tax_rate', type: 'number', icon: 'percent', value: '18', hintKey: 'tax_hint' },
            ],
        },
        {
            n: 5,
            titleKey: 'step5_title',
            descKey: 'step5_desc',
            icon: 'inventory_2',
            optional: true,
            fields: [
                { id: 'product_name', labelKey: 'product_name', type: 'text', icon: 'inventory_2', optional: true },
                { id: 'price', labelKey: 'price', type: 'number', icon: 'sell', value: '1000' },
                { id: 'stock', labelKey: 'stock', type: 'number', icon: 'inventory', value: '10' },
            ],
        },
        {
            n: 6,
            titleKey: 'step6_title',
            descKey: 'step6_desc',
            icon: 'celebration',
            fields: [],
        },
    ];

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    async function api(path, options) {
        const res = await fetch((cfg.apiBase || '../api/v1/index.php?request=onboarding/') + path, {
            credentials: 'same-origin',
            ...options,
        });
        return res.json();
    }

    function showAlert(msg) {
        if (!alertEl) return;
        alertEl.hidden = false;
        alertEl.textContent = msg;
    }

    function hideAlert() {
        if (alertEl) alertEl.hidden = true;
    }

    function setLoading(loading) {
        if (loadingEl) loadingEl.hidden = !loading;
        if (form) form.hidden = loading;
        if (actionsEl) actionsEl.hidden = loading;
        if (completeEl) completeEl.hidden = loading || state.current_step < 6;
    }

    function setSubmitting(busy) {
        submitting = busy;
        if (nextBtn) nextBtn.disabled = busy;
        if (skipBtn) skipBtn.disabled = busy;
        if (backBtn) backBtn.disabled = busy;
        const text = document.getElementById('nextBtnText');
        if (text) text.textContent = busy ? t('loading') : (state.current_step >= state.total_steps ? t('finish') : t('next'));
    }

    function stepData(stepNum) {
        return state.steps?.['step_' + stepNum] || {};
    }

    function progressPct() {
        if (state.current_step >= state.total_steps) return 100;
        return Math.round(((state.current_step - 1) / state.total_steps) * 100);
    }

    function updateProgress() {
        const pct = progressPct();
        if (progressBar) progressBar.style.width = pct + '%';
        if (heroProgressBar) heroProgressBar.style.width = pct + '%';
        if (progressLabel) {
            const template = t('progress');
            progressLabel.textContent = template.includes('%1') && template.includes('%2')
                ? template.replace('%1', String(state.current_step)).replace('%2', String(state.total_steps))
                : `Step ${state.current_step} / ${state.total_steps}`;
        }
    }

    function renderStepper() {
        if (!stepper) return;
        stepper.innerHTML = STEPS.map((step) => {
            const cls = step.n < state.current_step ? 'is-done'
                : step.n === state.current_step ? 'is-active' : '';
            const numContent = step.n < state.current_step
                ? '<span class="material-icons-round" style="font-size:14px">check</span>'
                : esc(String(step.n));
            return `<span class="onboarding-stepper__item ${cls}" aria-current="${step.n === state.current_step ? 'step' : 'false'}">
                <span class="onboarding-stepper__num">${numContent}</span>
                <span class="onboarding-stepper__label">${esc(t(step.titleKey))}</span>
            </span>`;
        }).join('');
    }

    function renderField(field, saved) {
        const value = saved[field.id] ?? field.value ?? '';
        const label = t(field.labelKey);
        const hint = field.hintKey ? `<small class="onboarding-field__hint">${esc(t(field.hintKey))}</small>` : '';
        const optionalCls = field.optional ? ' onboarding-field--optional' : '';
        const optionalAttr = field.optional ? ` data-optional="${esc(t('product_optional'))}"` : '';
        const req = field.required ? ' required' : '';

        if (field.type === 'textarea') {
            return `<div class="onboarding-field${optionalCls}"${optionalAttr}>
                <label for="${esc(field.id)}">${esc(label)}</label>
                <textarea id="${esc(field.id)}" name="${esc(field.id)}"${req}>${esc(String(value))}</textarea>
                ${hint}
            </div>`;
        }

        const iconHtml = field.icon
            ? `<div class="input-icon-wrapper">
                <span class="material-icons-round" aria-hidden="true">${esc(field.icon)}</span>
                <input type="${esc(field.type)}" id="${esc(field.id)}" name="${esc(field.id)}" value="${esc(String(value))}"${req}>
               </div>`
            : `<input type="${esc(field.type)}" id="${esc(field.id)}" name="${esc(field.id)}" value="${esc(String(value))}"${req}>`;

        return `<div class="onboarding-field${optionalCls}"${optionalAttr}>
            <label for="${esc(field.id)}">${esc(label)}</label>
            ${iconHtml}
            ${hint}
        </div>`;
    }

    async function ensureStep6Complete() {
        if (state.completed) return;
        setSubmitting(true);
        const res = await api('step', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ step: 6, data: {} }),
        });
        setSubmitting(false);
        if (res.status === 'success') {
            state = { ...state, ...res.data };
        }
    }

    function renderComplete() {
        if (!completeEl) return;
        completeEl.hidden = false;
        completeEl.innerHTML = `
            <div class="onboarding-complete__icon" aria-hidden="true">
                <span class="material-icons-round">celebration</span>
            </div>
            <h2>${esc(t('complete_title'))}</h2>
            <p>${esc(t('complete_desc'))}</p>
            <div class="onboarding-complete__links">
                <a href="admin/index.php" class="onboarding-complete__card">
                    <span class="material-icons-round" aria-hidden="true">dashboard</span>
                    <div>
                        <strong>${esc(t('open_admin'))}</strong>
                        <span>Admin portal</span>
                    </div>
                </a>
                <a href="cashier/pos.php" class="onboarding-complete__card">
                    <span class="material-icons-round" aria-hidden="true">point_of_sale</span>
                    <div>
                        <strong>${esc(t('launch_pos'))}</strong>
                        <span>Point of sale</span>
                    </div>
                </a>
            </div>
        `;
        if (form) form.hidden = true;
        if (actionsEl) actionsEl.hidden = true;
    }

    function renderStep() {
        hideAlert();
        const step = STEPS.find((s) => s.n === state.current_step) || STEPS[0];
        const saved = stepData(step.n);

        if (heroDesc && step.descKey) {
            heroDesc.textContent = t(step.descKey);
        }

        updateProgress();
        renderStepper();

        if (backBtn) backBtn.hidden = state.current_step <= 1;
        if (nextBtn) {
            const text = document.getElementById('nextBtnText');
            if (text) text.textContent = state.current_step >= state.total_steps ? t('finish') : t('next');
        }

        if (step.n === 6) {
            ensureStep6Complete().then(renderComplete).catch(() => showAlert(t('error')));
            return;
        }

        if (completeEl) completeEl.hidden = true;
        if (form) {
            form.hidden = false;
            form.innerHTML = `
                <div class="onboarding-form__head">
                    <h2>${esc(t(step.titleKey))}</h2>
                    <p>${esc(t(step.descKey))}</p>
                </div>
                ${step.fields.map((f) => renderField(f, saved)).join('')}
            `;

            if (step.n === 3 && saved.emails && Array.isArray(saved.emails)) {
                const emailsEl = form.querySelector('#emails');
                if (emailsEl) emailsEl.value = saved.emails.join(', ');
            }
        }

        if (actionsEl) actionsEl.hidden = false;
        setLoading(false);
    }

    function collectPayload() {
        const payload = {};
        if (!form) return payload;
        form.querySelectorAll('input, textarea, select').forEach((el) => {
            if (el.id) payload[el.id] = el.value;
        });
        if (payload.emails) {
            payload.emails = payload.emails.split(/[\s,;]+/).filter(Boolean);
        }
        return payload;
    }

    async function load() {
        setLoading(true);
        try {
            const res = await api('state');
            if (res.status === 'success') {
                state = { ...state, ...res.data };
                if (state.completed) {
                    state.current_step = state.total_steps;
                    renderStep();
                    return;
                }
            }
            renderStep();
        } catch (_) {
            showAlert(t('error'));
            setLoading(false);
        }
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (submitting || state.current_step >= 6) return;

        hideAlert();
        setSubmitting(true);

        const payload = collectPayload();
        const res = await api('step', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ step: state.current_step, data: payload }),
        });

        setSubmitting(false);

        if (res.status !== 'success') {
            showAlert(res.message || t('error'));
            return;
        }

        state = { ...state, ...res.data };
        renderStep();
    });

    backBtn?.addEventListener('click', () => {
        if (state.current_step <= 1) return;
        state.current_step -= 1;
        renderStep();
    });

    skipBtn?.addEventListener('click', async () => {
        if (submitting) return;
        setSubmitting(true);
        try {
            await api('skip', { method: 'POST' });
            window.location.href = 'admin/index.php';
        } catch (_) {
            showAlert(t('error'));
            setSubmitting(false);
        }
    });

    document.getElementById('onboardingThemeToggle')?.addEventListener('click', () => {
        window.AppTheme?.toggle?.();
    });

    load();
})();
