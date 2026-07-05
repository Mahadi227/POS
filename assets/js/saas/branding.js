(function () {
    'use strict';

    const cfg = window.BRANDING_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;
    let serverLogoUrl = null;

    function api(path, options) {
        return fetch(`${cfg.apiBase}?request=branding/${path}`, {
            credentials: 'same-origin',
            ...options,
        }).then((r) => r.json());
    }

    function showAlert(msg, ok) {
        const el = document.getElementById('brandingAlert');
        if (!el) return;
        el.textContent = msg;
        el.className = 'branding-alert is-visible ' + (ok ? 'is-success' : 'is-error');
        el.setAttribute('role', ok ? 'status' : 'alert');
    }

    function hideAlert() {
        const el = document.getElementById('brandingAlert');
        if (!el) return;
        el.className = 'branding-alert';
        el.textContent = '';
    }

    function setSaving(isLoading) {
        const btn = document.getElementById('brandingSaveBtn');
        if (!btn) return;
        btn.disabled = isLoading;
        btn.classList.toggle('is-loading', isLoading);
        btn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderUsage(data) {
        const grid = document.getElementById('usageGrid');
        if (!grid) return;
        const items = data?.items || [];
        if (!items.length) {
            grid.innerHTML = `<p>${escapeHtml(t('load_error'))}</p>`;
            return;
        }
        grid.innerHTML = items.map((item) => {
            const limit = item.limit != null ? item.limit : t('usage_unlimited');
            const cls = item.alert_100 ? 'is-over' : (item.alert_80 ? 'is-warn' : '');
            const pct = item.percent != null ? ` (${item.percent}%)` : '';
            return `<div class="branding-usage-row ${cls}">
                <span>${escapeHtml(item.metric)}</span>
                <strong>${escapeHtml(String(item.used))} / ${escapeHtml(String(limit))}${escapeHtml(pct)}</strong>
            </div>`;
        }).join('');
    }

    function updateLogoDeleteButton() {
        const deleteBtn = document.getElementById('logoDeleteBtn');
        if (deleteBtn) {
            deleteBtn.hidden = !serverLogoUrl;
        }
    }

    function setLogoPreview(url) {
        const logo = document.getElementById('logoPreview');
        const placeholder = document.getElementById('logoPlaceholder');
        if (!logo) return;
        if (url) {
            logo.src = url;
            logo.hidden = false;
            if (placeholder) placeholder.hidden = true;
        } else {
            logo.hidden = true;
            logo.removeAttribute('src');
            if (placeholder) placeholder.hidden = false;
        }
        updateLogoDeleteButton();
    }

    function previewLocalFile(file) {
        if (!file || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = () => setLogoPreview(reader.result);
        reader.readAsDataURL(file);
    }

    async function load() {
        const usageGrid = document.getElementById('usageGrid');
        if (usageGrid) {
            usageGrid.innerHTML = `<div class="branding-loading"><span class="spinner" aria-hidden="true"></span>${escapeHtml(t('loading'))}…</div>`;
        }

        try {
            const [settingsRes, usageRes] = await Promise.all([
                api('settings'),
                api('usage'),
            ]);

            if (settingsRes.status !== 'success') throw new Error(settingsRes.message);
            renderUsage(usageRes.data);

            const data = settingsRes.data || {};
            const locked = document.getElementById('brandingLocked');
            const form = document.getElementById('brandingForm');

            if (!data.can_customize) {
                if (locked) locked.hidden = false;
                if (form) form.hidden = true;
                return;
            }

            if (locked) locked.hidden = true;
            if (form) form.hidden = false;

            document.getElementById('brandName').value = data.brand_name || '';
            document.getElementById('brandAccent').value = data.accent || '#2563eb';
            document.getElementById('customDomain').value = data.custom_domain || '';
            serverLogoUrl = data.logo_url || null;
            setLogoPreview(serverLogoUrl);
        } catch (e) {
            showAlert(e.message || t('load_error'), false);
            if (usageGrid) {
                usageGrid.innerHTML = `<p>${escapeHtml(t('load_error'))}</p>`;
            }
        }
    }

    document.getElementById('logoFile')?.addEventListener('change', (e) => {
        const file = e.target.files?.[0];
        if (file) previewLocalFile(file);
    });

    document.getElementById('logoDeleteBtn')?.addEventListener('click', async () => {
        if (!window.confirm(t('logo_delete_confirm'))) {
            return;
        }

        hideAlert();
        const deleteBtn = document.getElementById('logoDeleteBtn');
        if (deleteBtn) {
            deleteBtn.disabled = true;
        }

        try {
            const res = await api('logo', { method: 'DELETE' });
            if (res.status !== 'success') {
                throw new Error(res.message || t('logo_delete_error'));
            }

            document.getElementById('logoFile').value = '';
            serverLogoUrl = null;
            setLogoPreview(null);
            showAlert(t('logo_delete_ok'), true);
        } catch (err) {
            showAlert(err.message || t('logo_delete_error'), false);
        } finally {
            if (deleteBtn) {
                deleteBtn.disabled = false;
            }
        }
    });

    document.getElementById('brandingThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    document.getElementById('brandingForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        setSaving(true);

        try {
            const res = await api('settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    brand_name: document.getElementById('brandName')?.value?.trim() || '',
                    accent: document.getElementById('brandAccent')?.value || '#2563eb',
                    custom_domain: document.getElementById('customDomain')?.value?.trim() || '',
                }),
            });

            if (res.status !== 'success') {
                throw new Error(res.message || t('save_error'));
            }

            const file = document.getElementById('logoFile')?.files?.[0];
            if (file) {
                const fd = new FormData();
                fd.append('logo', file);
                const up = await fetch(`${cfg.apiBase}?request=branding/logo`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd,
                }).then((r) => r.json());

                if (up.status === 'success' && up.data?.logo_url) {
                    serverLogoUrl = up.data.logo_url;
                    setLogoPreview(up.data.logo_url);
                    document.getElementById('logoFile').value = '';
                } else if (up.status !== 'success') {
                    throw new Error(up.message || t('save_error'));
                }
            }

            showAlert(t('save_ok'), true);
            await load();
        } catch (err) {
            showAlert(err.message || t('save_error'), false);
        } finally {
            setSaving(false);
        }
    });

    load();
})();
