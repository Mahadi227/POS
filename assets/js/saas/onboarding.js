(function () {
    'use strict';

    const t = (k) => (window.ONBOARDING_I18N && window.ONBOARDING_I18N[k]) || k;
    const form = document.getElementById('onboardingForm');
    const progress = document.getElementById('onboardingProgress');
    const nextBtn = document.getElementById('nextBtn');
    const alertEl = document.getElementById('onboardingAlert');

    let state = { current_step: 1, total_steps: 6, completed: false };

    const steps = [
        { n: 1, title: 'step1_title', fields: [
            { id: 'org_name', label: 'Organization', type: 'text' },
            { id: 'address', label: 'Address', type: 'text' },
            { id: 'country_code', label: 'Country (SN, NG…)', type: 'text', value: 'SN' },
            { id: 'currency', label: 'Currency', type: 'text', value: 'XOF' },
        ]},
        { n: 2, title: 'step2_title', fields: [
            { id: 'store_name', label: 'Store name', type: 'text' },
            { id: 'location', label: 'Location', type: 'text' },
        ]},
        { n: 3, title: 'step3_title', fields: [
            { id: 'emails', label: 'Team emails (comma separated)', type: 'textarea' },
        ]},
        { n: 4, title: 'step4_title', fields: [
            { id: 'tax_rate', label: 'Tax rate %', type: 'number', value: '18' },
        ]},
        { n: 5, title: 'step5_title', fields: [
            { id: 'product_name', label: 'First product name (optional)', type: 'text' },
            { id: 'price', label: 'Price', type: 'number', value: '1000' },
            { id: 'stock', label: 'Stock', type: 'number', value: '10' },
        ]},
        { n: 6, title: 'step6_title', fields: [] },
    ];

    async function api(path, options) {
        const res = await fetch(`../api/v1/index.php?request=onboarding/${path}`, {
            credentials: 'same-origin',
            ...options,
        });
        return res.json();
    }

    function showAlert(msg) {
        alertEl.hidden = false;
        alertEl.textContent = msg;
    }

    function renderProgress() {
        const pct = Math.round(((state.current_step - 1) / state.total_steps) * 100);
        progress.innerHTML = `<div class="onboarding-progress__bar" style="width:${pct}%"></div>`;
    }

    function renderStep() {
        const step = steps.find((s) => s.n === state.current_step) || steps[0];
        nextBtn.textContent = state.current_step >= state.total_steps ? t('finish') : t('next');

        if (step.n === 6) {
            form.innerHTML = `<p>${t('step6_title')}</p><p>🎉 <a href="admin/index.php">Open Admin Dashboard</a> · <a href="cashier/pos.php">Launch POS</a></p>`;
            return;
        }

        form.innerHTML = `<h2>${t(step.title)}</h2>` + step.fields.map((f) => `
            <label>${f.label}
                <${f.type === 'textarea' ? 'textarea' : 'input'} id="${f.id}" name="${f.id}"
                    ${f.type !== 'textarea' ? `type="${f.type}"` : ''}
                    ${f.value ? `value="${f.value}"` : ''}></${f.type === 'textarea' ? 'textarea' : 'input'}>
            </label>
        `).join('');
        renderProgress();
    }

    async function load() {
        const res = await api('state');
        if (res.status === 'success') {
            state = res.data;
            if (state.completed) {
                window.location.href = 'admin/index.php';
                return;
            }
        }
        renderStep();
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {};
        form.querySelectorAll('input, textarea, select').forEach((el) => {
            if (el.id) payload[el.id] = el.value;
        });
        if (payload.emails) {
            payload.emails = payload.emails.split(/[\s,;]+/).filter(Boolean);
        }

        const res = await api('step', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ step: state.current_step, data: payload }),
        });

        if (res.status !== 'success') {
            showAlert(res.message || t('error'));
            return;
        }

        state = res.data;
        if (state.completed || state.current_step > state.total_steps) {
            window.location.href = 'admin/index.php';
            return;
        }
        renderStep();
    });

    document.getElementById('skipBtn')?.addEventListener('click', async () => {
        await api('skip', { method: 'POST' });
        window.location.href = 'admin/index.php';
    });

    load().catch(() => showAlert(t('error')));
})();
