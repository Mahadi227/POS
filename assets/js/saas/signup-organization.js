(function () {
    'use strict';

    const cfg = window.SIGNUP_CONFIG || {};
    const i18n = cfg.i18n || {};
    const t = (k) => i18n[k] || k;

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
        if (!alertBox) return;
        alertBox.className = 'alert alert-' + (type || 'error');
        alertBox.textContent = msg;
        alertBox.style.display = 'block';
        alertBox.setAttribute('role', type === 'error' ? 'alert' : 'status');
    }

    function hideAlert() {
        if (!alertBox) return;
        alertBox.style.display = 'none';
        alertBox.textContent = '';
    }

    function setLoading(isLoading) {
        const btnText = document.getElementById('btnText');
        const spinner = document.getElementById('spinner');
        const submitBtn = document.getElementById('submitBtn');
        if (!submitBtn) return;
        if (btnText) btnText.style.display = isLoading ? 'none' : 'inline';
        if (spinner) spinner.style.display = isLoading ? 'block' : 'none';
        submitBtn.disabled = isLoading;
        submitBtn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input || !btn) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'visibility_off' : 'visibility';
        btn.setAttribute('aria-label', show ? t('hide_password') : t('show_password'));
    }

    function setSlugHint(available) {
        if (!slugHint) return;
        if (available === null) {
            slugHint.textContent = '';
            slugHint.className = 'field-hint';
            return;
        }
        slugHint.textContent = available
            ? (t('slug_available') || 'Available')
            : (t('slug_taken') || 'Taken');
        slugHint.className = 'field-hint ' + (available ? 'is-ok' : 'is-error');
    }

    async function loadPlans() {
        if (!planSelect) return;
        planSelect.disabled = true;
        try {
            const res = await fetch(cfg.plansUrl || '../api/v1/index.php?request=tenant-signup/plans');
            const data = await res.json();
            if (data.status !== 'success') return;
            planSelect.innerHTML = (data.data || []).map((p) =>
                `<option value="${escapeAttr(p.code)}">${escapeHtml(p.name)} — ${escapeHtml(String(p.price_monthly))} ${escapeHtml(p.currency)}/mo</option>`
            ).join('');
            const urlPlan = new URLSearchParams(window.location.search).get('plan_code');
            if (urlPlan) {
                planSelect.value = urlPlan;
            }
        } catch (_) {
            showAlert(t('server_error'), 'error');
        } finally {
            planSelect.disabled = false;
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/'/g, '&#39;');
    }

    let slugTimer;
    slugInput?.addEventListener('input', () => {
        clearTimeout(slugTimer);
        slugTimer = setTimeout(checkSlug, 400);
    });

    orgInput?.addEventListener('blur', () => {
        if (!slugInput?.value.trim() && orgInput.value.trim()) {
            slugInput.value = slugify(orgInput.value);
            checkSlug();
        }
    });

    async function checkSlug() {
        const slug = slugify(slugInput?.value || '');
        if (!slug) {
            setSlugHint(null);
            return;
        }
        try {
            const url = (cfg.checkSlugUrl || '../api/v1/index.php?request=tenant-signup/check-slug')
                + '&slug=' + encodeURIComponent(slug);
            const res = await fetch(url);
            const data = await res.json();
            setSlugHint(!!data.available);
        } catch (_) {
            setSlugHint(null);
        }
    }

    document.getElementById('togglePassword')?.addEventListener('click', function () {
        togglePassword('password', this);
    });

    document.getElementById('togglePasswordConfirm')?.addEventListener('click', function () {
        togglePassword('password_confirm', this);
    });

    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const passwordConfirmHint = document.getElementById('passwordConfirmHint');

    function setPasswordConfirmHint(match) {
        if (!passwordConfirmHint) return;
        if (match === null) {
            passwordConfirmHint.textContent = '';
            passwordConfirmHint.className = 'field-hint';
            return;
        }
        if (match) {
            passwordConfirmHint.textContent = '';
            passwordConfirmHint.className = 'field-hint';
            return;
        }
        passwordConfirmHint.textContent = t('password_mismatch') || 'Passwords do not match.';
        passwordConfirmHint.className = 'field-hint is-error';
    }

    function passwordsMatch() {
        const pwd = passwordInput?.value || '';
        const confirm = passwordConfirmInput?.value || '';
        if (confirm === '') return null;
        return pwd === confirm;
    }

    function validatePasswords() {
        const match = passwordsMatch();
        setPasswordConfirmHint(match);
        return match !== false;
    }

    passwordInput?.addEventListener('input', validatePasswords);
    passwordConfirmInput?.addEventListener('input', validatePasswords);

    document.getElementById('signupThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();

        if (!validatePasswords() || passwordsMatch() === false) {
            showAlert(t('password_mismatch') || 'Passwords do not match.', 'error');
            passwordConfirmInput?.focus();
            return;
        }

        setLoading(true);

        const payload = {
            csrf_token: document.getElementById('csrf_token')?.value || '',
            org_name: orgInput?.value.trim() || '',
            slug: slugify(slugInput?.value || ''),
            plan_code: planSelect?.value || '',
            store_name: document.getElementById('store_name')?.value.trim() || '',
            admin_name: document.getElementById('admin_name')?.value.trim() || '',
            admin_email: document.getElementById('admin_email')?.value.trim() || '',
            password: passwordInput?.value || '',
            password_confirm: passwordConfirmInput?.value || '',
        };

        try {
            const res = await fetch(cfg.registerUrl || '../api/v1/index.php?request=tenant-signup/register', {
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
            showAlert(data.message || t('error_generic'), 'error');
        } catch (_) {
            showAlert(t('server_error'), 'error');
        } finally {
            setLoading(false);
        }
    });

    loadPlans();
})();
