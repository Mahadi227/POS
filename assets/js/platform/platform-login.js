(function () {
    'use strict';

    const cfg = window.PLATFORM_LOGIN_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;
    const EMAIL_KEY = 'retailpos_platform_login_email';

    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input || !btn) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'visibility_off' : 'visibility';
        btn.setAttribute('aria-label', show ? t('plat_login_hide_password') : t('plat_login_show_password'));
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

    function restoreEmail() {
        const emailInput = document.getElementById('email');
        const remember = document.getElementById('rememberEmail');
        if (!emailInput) return;
        try {
            const saved = localStorage.getItem(EMAIL_KEY);
            if (saved && remember) {
                emailInput.value = saved;
                remember.checked = true;
            }
        } catch (_) {
        }
    }

    function persistEmail(email, remember) {
        try {
            if (remember) {
                localStorage.setItem(EMAIL_KEY, email);
            } else {
                localStorage.removeItem(EMAIL_KEY);
            }
        } catch (_) {
        }
    }

    document.getElementById('togglePassword')?.addEventListener('click', function () {
        togglePassword('password', this);
    });

    document.getElementById('platThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    document.getElementById('platLoginForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        setLoading(true);

        const email = document.getElementById('email')?.value.trim() || '';
        const password = document.getElementById('password')?.value || '';
        const remember = document.getElementById('rememberEmail')?.checked ?? false;

        try {
            const res = await fetch(cfg.apiBase || '../../api/v1/index.php?request=platform/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ email, password }),
            });

            let data;
            try {
                data = await res.json();
            } catch (_) {
                throw new Error('parse');
            }

            if (res.ok && data.status === 'success') {
                persistEmail(email, remember);
                window.location.href = data.redirect || 'index.php';
                return;
            }

            showAlert(data.message || t('plat_login_error_generic'), 'error');
        } catch (_) {
            showAlert(t('plat_login_server_error'), 'error');
        } finally {
            setLoading(false);
        }
    });

    restoreEmail();
})();
