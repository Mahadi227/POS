(function () {
    'use strict';

    const i18n = window.SIGNUP_I18N || {};
    const form = document.getElementById('signupOrgForm');
    const planSelect = document.getElementById('plan_code');
    const slugInput = document.getElementById('slug');
    const orgInput = document.getElementById('org_name');
    const slugHint = document.getElementById('slugHint');
    const alertBox = document.getElementById('alertBox');

    function slugify(text) {
        return text.toLowerCase().trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function showAlert(msg, type) {
        alertBox.className = 'alert alert-' + (type || 'error');
        alertBox.textContent = msg;
        alertBox.style.display = 'block';
    }

    async function loadPlans() {
        const res = await fetch('../api/v1/index.php?request=tenant-signup/plans');
        const data = await res.json();
        if (data.status !== 'success') return;
        planSelect.innerHTML = (data.data || []).map((p) =>
            `<option value="${p.code}">${p.name} — ${p.price_monthly} ${p.currency}/mo</option>`
        ).join('');
    }

    let slugTimer;
    slugInput?.addEventListener('input', () => {
        clearTimeout(slugTimer);
        slugTimer = setTimeout(checkSlug, 400);
    });
    orgInput?.addEventListener('blur', () => {
        if (!slugInput.value.trim() && orgInput.value.trim()) {
            slugInput.value = slugify(orgInput.value);
            checkSlug();
        }
    });

    async function checkSlug() {
        const slug = slugify(slugInput.value);
        if (!slug) {
            slugHint.textContent = '';
            return;
        }
        const res = await fetch('../api/v1/index.php?request=tenant-signup/check-slug&slug=' + encodeURIComponent(slug));
        const data = await res.json();
        slugHint.textContent = data.available ? (i18n.slug_available || 'Available') : (i18n.slug_taken || 'Taken');
        slugHint.style.color = data.available ? 'var(--success)' : 'var(--danger)';
    }

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            csrf_token: document.getElementById('csrf_token').value,
            org_name: orgInput.value.trim(),
            slug: slugify(slugInput.value),
            plan_code: planSelect.value,
            store_name: document.getElementById('store_name').value.trim(),
            admin_name: document.getElementById('admin_name').value.trim(),
            admin_email: document.getElementById('admin_email').value.trim(),
            password: document.getElementById('password').value,
        };
        try {
            const res = await fetch('../api/v1/index.php?request=tenant-signup/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.status === 'success') {
                window.location.href = data.redirect || 'admin/index.php';
                return;
            }
            showAlert(data.message || i18n.error_generic, 'error');
        } catch (err) {
            showAlert(i18n.error_generic || 'Error', 'error');
        }
    });

    loadPlans();
})();
