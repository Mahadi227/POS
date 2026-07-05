(function () {
    'use strict';

    const cfg = window.TENANT_FORGOT_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;

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
        if (alertBox) {
            alertBox.hidden = true;
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

    document.getElementById('tenantThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    document.getElementById('forgotPasswordForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        setLoading(true);

        const payload = {
            email: document.getElementById('email')?.value.trim() || '',
            csrf_token: document.getElementById('csrf_token')?.value || '',
        };

        if (!payload.email) {
            showAlert(t('invalid_email'), 'error');
            setLoading(false);
            return;
        }

        try {
            const res = await fetch(cfg.apiBase || '../api/v1/index.php?request=auth/forgot-password', {
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
                showAlert(result.message || t('forgot_success'), 'success');
                document.getElementById('forgotPasswordForm')?.reset();
                return;
            }

            showAlert(result.message || t('error_generic'), 'error');
        } catch (_) {
            showAlert(t('server_error'), 'error');
        } finally {
            setLoading(false);
        }
    });
})();
