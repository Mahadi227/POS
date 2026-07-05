(function () {
    'use strict';

    const cfg = window.TENANT_REGISTER_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;
    const STORAGE_SLUG = 'retailpos_login_slug';

    function togglePassword(inputId, btn, showKey, hideKey) {
        const input = document.getElementById(inputId);
        if (!input || !btn) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'visibility_off' : 'visibility';
        btn.setAttribute('aria-label', show ? t(hideKey || 'hide_password') : t(showKey || 'show_password'));
    }

    function showAlert(message, type) {
        const alertBox = document.getElementById('alertBox');
        if (!alertBox) return;
        alertBox.className = `alert tenant-login-alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.hidden = false;
        alertBox.setAttribute('role', type === 'error' ? 'alert' : 'status');
    }

    function hideAlert() {
        const alertBox = document.getElementById('alertBox');
        if (!alertBox) return;
        alertBox.hidden = true;
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

    function scorePassword(value) {
        let score = 0;
        if (value.length >= 8) score += 1;
        if (value.length >= 12) score += 1;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1;
        if (/\d/.test(value)) score += 1;
        if (/[^a-zA-Z0-9]/.test(value)) score += 1;
        if (score <= 1) return 'weak';
        if (score <= 2) return 'fair';
        if (score <= 3) return 'good';
        return 'strong';
    }

    function updatePasswordStrength() {
        const input = document.getElementById('password');
        const meter = document.getElementById('passwordStrength');
        if (!input || !meter) return;
        const value = input.value || '';
        meter.className = 'password-strength';
        meter.setAttribute('aria-hidden', value.length === 0 ? 'true' : 'false');
        if (value.length === 0) return;
        meter.classList.add('is-' + scorePassword(value));
    }

    function updatePasswordMatch() {
        const pass = document.getElementById('password')?.value || '';
        const confirm = document.getElementById('password_confirmation')?.value || '';
        const hint = document.getElementById('matchHint');
        if (!hint || confirm.length === 0) {
            if (hint) hint.hidden = true;
            return;
        }
        hint.hidden = false;
        const match = pass === confirm;
        hint.textContent = match ? t('password_match') : t('password_no_match');
        hint.className = 'field-hint ' + (match ? 'is-ok' : 'is-error');
    }

    function initRememberedSlug() {
        const slugEl = document.getElementById('tenant_slug');
        if (!cfg.hasTenant && slugEl && slugEl.type !== 'hidden') {
            try {
                const saved = localStorage.getItem(STORAGE_SLUG);
                if (saved && !slugEl.value) slugEl.value = saved;
            } catch (_) { /* ignore */ }
        }
    }

    document.getElementById('togglePassword')?.addEventListener('click', function () {
        togglePassword('password', this);
    });

    document.getElementById('togglePasswordConfirm')?.addEventListener('click', function () {
        togglePassword('password_confirmation', this, 'show_password_confirm', 'hide_password_confirm');
    });

    document.getElementById('tenantThemeToggle')?.addEventListener('click', () => {
        window.AppTheme?.toggle?.();
    });

    document.getElementById('password')?.addEventListener('input', () => {
        updatePasswordStrength();
        updatePasswordMatch();
    });

    document.getElementById('password_confirmation')?.addEventListener('input', updatePasswordMatch);

    const slugInput = document.getElementById('tenant_slug');
    if (slugInput && slugInput.type !== 'hidden') {
        slugInput.addEventListener('input', () => {
            slugInput.value = slugInput.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        });
    }

    document.getElementById('registerForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        setLoading(true);

        const payload = {
            name: document.getElementById('name')?.value.trim() || '',
            email: document.getElementById('email')?.value.trim() || '',
            password: document.getElementById('password')?.value || '',
            password_confirmation: document.getElementById('password_confirmation')?.value || '',
            csrf_token: document.getElementById('csrf_token')?.value || '',
        };

        const tenantSlugEl = document.getElementById('tenant_slug');
        if (tenantSlugEl?.value) {
            payload.tenant_slug = tenantSlugEl.value.trim().toLowerCase();
        }

        if (!cfg.hasTenant && !payload.tenant_slug) {
            setLoading(false);
            showAlert(t('workspace_required'), 'error');
            tenantSlugEl?.focus();
            return;
        }

        if (payload.password.length < 8) {
            setLoading(false);
            showAlert(t('password_min_length'), 'error');
            return;
        }

        if (payload.password !== payload.password_confirmation) {
            setLoading(false);
            showAlert(t('password_mismatch'), 'error');
            return;
        }

        try {
            if (payload.tenant_slug) {
                localStorage.setItem(STORAGE_SLUG, payload.tenant_slug);
            }
        } catch (_) { /* ignore */ }

        try {
            const res = await fetch(cfg.apiBase || '../api/v1/index.php?request=auth/register', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            let result;
            try {
                result = await res.json();
            } catch (_) {
                throw new Error('parse');
            }

            if (res.ok && result.status === 'success') {
                showAlert(result.message || t('register_success'), 'success');
                document.getElementById('registerForm')?.reset();
                updatePasswordStrength();
                updatePasswordMatch();
                return;
            }

            showAlert(result.message || t('register_error'), 'error');
        } catch (_) {
            showAlert(t('server_error'), 'error');
        } finally {
            setLoading(false);
        }
    });

    initRememberedSlug();
    updatePasswordStrength();
})();
