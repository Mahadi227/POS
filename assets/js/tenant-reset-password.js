(function () {
    'use strict';

    const cfg = window.TENANT_RESET_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;

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
        alertBox.className = `alert alert-${type}`;
        alertBox.textContent = message;
        alertBox.style.display = 'block';
        alertBox.setAttribute('role', type === 'error' ? 'alert' : 'status');
    }

    function hideAlert() {
        const alertBox = document.getElementById('alertBox');
        if (alertBox) {
            alertBox.style.display = 'none';
            alertBox.textContent = '';
        }
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

    document.getElementById('togglePassword')?.addEventListener('click', function () {
        togglePassword('password', this);
    });

    document.getElementById('togglePasswordConfirm')?.addEventListener('click', function () {
        togglePassword('password_confirmation', this, 'show_password_confirm', 'hide_password_confirm');
    });

    document.getElementById('tenantThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    document.getElementById('resetPasswordForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        setLoading(true);

        const payload = {
            token: document.getElementById('token')?.value || '',
            password: document.getElementById('password')?.value || '',
            password_confirmation: document.getElementById('password_confirmation')?.value || '',
            csrf_token: document.getElementById('csrf_token')?.value || '',
        };

        if (payload.password !== payload.password_confirmation) {
            showAlert(t('password_mismatch'), 'error');
            setLoading(false);
            return;
        }

        if (payload.password.length < 8) {
            showAlert(t('password_min_length'), 'error');
            setLoading(false);
            return;
        }

        try {
            const res = await fetch(cfg.apiBase || '../api/v1/index.php?request=auth/reset-password', {
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
                showAlert(result.message || t('reset_success'), 'success');
                document.getElementById('resetPasswordForm')?.reset();
                const redirect = result.redirect || cfg.loginUrl || 'login.php';
                setTimeout(() => { window.location.href = redirect; }, 2000);
                return;
            }

            showAlert(result.message || t('reset_invalid_token'), 'error');
        } catch (_) {
            showAlert(t('server_error'), 'error');
        } finally {
            setLoading(false);
        }
    });
})();
