(function () {
    'use strict';

    const cfg = window.TENANT_LOGIN_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;
    const STORAGE_EMAIL = 'retailpos_login_email';
    const STORAGE_SLUG = 'retailpos_login_slug';

    function togglePassword(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input || !btn) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.textContent = show ? 'visibility_off' : 'visibility';
        btn.setAttribute('aria-label', show ? t('hide_password') : t('show_password'));
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

    function getSlugInput() {
        return document.getElementById('tenant_slug');
    }

    function getWorkspaceSlug() {
        const hidden = getSlugInput();
        if (hidden && hidden.type === 'hidden') {
            return (hidden.value || '').trim().toLowerCase();
        }
        return (hidden?.value || '').trim().toLowerCase();
    }

    function setWorkspaceSlug(value) {
        const hidden = getSlugInput();
        if (hidden) {
            hidden.value = value;
        }
    }

    function syncFromSelect() {
        const select = document.getElementById('tenant_slug_select');
        if (select) {
            setWorkspaceSlug((select.value || '').trim().toLowerCase());
        }
    }

    function syncFromManual() {
        const manual = document.getElementById('tenant_slug_manual');
        if (manual) {
            setWorkspaceSlug(manual.value.trim().toLowerCase());
        }
    }

    function showPickerMode() {
        const picker = document.getElementById('workspacePicker');
        const manual = document.getElementById('workspaceManual');
        const select = document.getElementById('tenant_slug_select');
        if (!picker || !manual) return;
        picker.hidden = false;
        manual.hidden = true;
        if (select) {
            select.required = true;
            syncFromSelect();
        }
        const manualInput = document.getElementById('tenant_slug_manual');
        if (manualInput) manualInput.required = false;
    }

    function showManualMode() {
        const picker = document.getElementById('workspacePicker');
        const manual = document.getElementById('workspaceManual');
        const select = document.getElementById('tenant_slug_select');
        if (!picker || !manual) return;
        picker.hidden = true;
        manual.hidden = false;
        if (select) select.required = false;
        const manualInput = document.getElementById('tenant_slug_manual');
        if (manualInput) {
            manualInput.required = true;
            if (!manualInput.value && select?.value) {
                manualInput.value = select.value;
            }
            syncFromManual();
            manualInput.focus();
        }
    }

    function initWorkspacePicker() {
        const select = document.getElementById('tenant_slug_select');
        const manual = document.getElementById('tenant_slug_manual');
        const search = document.getElementById('workspaceSearch');

        if (select) {
            select.addEventListener('change', syncFromSelect);
            syncFromSelect();
        }

        if (manual) {
            manual.addEventListener('input', () => {
                manual.value = manual.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
                syncFromManual();
            });
        }

        document.getElementById('workspaceManualToggle')?.addEventListener('click', showManualMode);
        document.getElementById('workspacePickerToggle')?.addEventListener('click', showPickerMode);

        if (search && select) {
            search.addEventListener('input', () => {
                const q = search.value.trim().toLowerCase();
                Array.from(select.options).forEach((opt, idx) => {
                    if (idx === 0) return;
                    const text = opt.textContent.toLowerCase();
                    opt.hidden = q !== '' && !text.includes(q);
                });
            });
        }
    }

    function initRemembered() {
        const emailEl = document.getElementById('email');
        const slugEl = getSlugInput();
        const select = document.getElementById('tenant_slug_select');
        const manual = document.getElementById('tenant_slug_manual');

        try {
            const savedEmail = localStorage.getItem(STORAGE_EMAIL);
            if (savedEmail && emailEl && !emailEl.value) {
                emailEl.value = savedEmail;
            }

            if (!cfg.hasTenant) {
                const savedSlug = localStorage.getItem(STORAGE_SLUG);
                const initial = (slugEl?.value || savedSlug || '').trim().toLowerCase();
                if (initial) {
                    if (select) {
                        const hasOption = Array.from(select.options).some((o) => o.value === initial);
                        if (hasOption) {
                            select.value = initial;
                            syncFromSelect();
                        } else if (manual) {
                            manual.value = initial;
                            syncFromManual();
                            showManualMode();
                        }
                    } else if (slugEl && slugEl.type !== 'hidden') {
                        slugEl.value = initial;
                    } else {
                        setWorkspaceSlug(initial);
                    }
                }
            }
        } catch (_) {
            /* ignore */
        }
    }

    function persistRemembered(payload) {
        try {
            if (payload.email) {
                localStorage.setItem(STORAGE_EMAIL, payload.email);
            }
            if (payload.tenant_slug) {
                localStorage.setItem(STORAGE_SLUG, payload.tenant_slug);
            }
        } catch (_) {
            /* ignore */
        }
    }

    document.getElementById('togglePassword')?.addEventListener('click', function () {
        togglePassword('password', this);
    });

    document.getElementById('tenantThemeToggle')?.addEventListener('click', () => {
        window.AppTheme?.toggle?.();
    });

    const slugInput = getSlugInput();
    if (slugInput && slugInput.type !== 'hidden') {
        slugInput.addEventListener('input', () => {
            slugInput.value = slugInput.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
        });
    }

    if (cfg.hasOrgList) {
        initWorkspacePicker();
    }

    document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        setLoading(true);

        const payload = {
            email: document.getElementById('email')?.value.trim() || '',
            password: document.getElementById('password')?.value || '',
            csrf_token: document.getElementById('csrf_token')?.value || '',
            remember: document.getElementById('remember')?.checked ?? false,
        };

        if (cfg.hasOrgList) {
            const manualWrap = document.getElementById('workspaceManual');
            if (manualWrap && !manualWrap.hidden) {
                syncFromManual();
            } else {
                syncFromSelect();
            }
        }

        const tenantSlug = getWorkspaceSlug();
        if (tenantSlug) {
            payload.tenant_slug = tenantSlug;
        }

        if (!cfg.hasTenant && !payload.tenant_slug) {
            setLoading(false);
            showAlert(
                cfg.hasOrgList ? t('workspace_select_required') : t('workspace_required'),
                'error'
            );
            const focusEl = document.getElementById('tenant_slug_select')
                || document.getElementById('tenant_slug_manual')
                || getSlugInput();
            focusEl?.focus();
            return;
        }

        try {
            const res = await fetch(cfg.apiBase || '../api/v1/index.php?request=auth/login', {
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
                if (payload.remember) {
                    persistRemembered(payload);
                }
                window.location.href = result.redirect;
                return;
            }

            showAlert(result.message || t('error_generic'), 'error');
        } catch (_) {
            showAlert(t('server_error'), 'error');
        } finally {
            setLoading(false);
        }
    });

    initRemembered();

    const emailEl = document.getElementById('email');
    if (!emailEl?.value) {
        emailEl?.focus();
    }
})();
