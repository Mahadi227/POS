(function () {
    'use strict';

    const cfg = window.BRANDING_CONFIG || {};
    const t = (k) => (cfg.i18n && cfg.i18n[k]) || k;

    function api(path, options) {
        return fetch(`${cfg.apiBase}?request=branding/${path}`, {
            credentials: 'same-origin',
            ...options,
        }).then((r) => r.json());
    }

    function alert(msg, ok) {
        const el = document.getElementById('brandingAlert');
        if (!el) return;
        el.hidden = false;
        el.textContent = msg;
        el.className = 'branding-alert ' + (ok ? 'is-success' : 'is-error');
    }

    function renderUsage(data) {
        const grid = document.getElementById('usageGrid');
        const items = data?.items || [];
        if (!items.length) {
            grid.innerHTML = `<p>${t('load_error')}</p>`;
            return;
        }
        grid.innerHTML = items.map((item) => {
            const limit = item.limit != null ? item.limit : t('usage_unlimited');
            const cls = item.alert_100 ? 'is-over' : (item.alert_80 ? 'is-warn' : '');
            const pct = item.percent != null ? ` (${item.percent}%)` : '';
            return `<div class="branding-usage-row ${cls}">
                <span>${item.metric}</span>
                <strong>${item.used} / ${limit}${pct}</strong>
            </div>`;
        }).join('');
    }

    async function load() {
        try {
            const [settingsRes, usageRes] = await Promise.all([
                api('settings'),
                api('usage'),
            ]);
            const data = settingsRes.data || {};
            renderUsage(usageRes.data);

            if (!data.can_customize) {
                document.getElementById('brandingLocked').hidden = false;
                return;
            }
            document.getElementById('brandingForm').hidden = false;
            document.getElementById('brandName').value = data.brand_name || '';
            document.getElementById('brandAccent').value = data.accent || '#2563eb';
            document.getElementById('customDomain').value = data.custom_domain || '';
            const logo = document.getElementById('logoPreview');
            if (data.logo_url) {
                logo.src = data.logo_url;
                logo.hidden = false;
            }
        } catch (e) {
            alert(t('load_error'), false);
        }
    }

    document.getElementById('brandingForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            const res = await api('settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    brand_name: document.getElementById('brandName').value,
                    accent: document.getElementById('brandAccent').value,
                    custom_domain: document.getElementById('customDomain').value,
                }),
            });
            if (res.status !== 'success') throw new Error(res.message);
            alert(t('save_ok'), true);

            const file = document.getElementById('logoFile').files[0];
            if (file) {
                const fd = new FormData();
                fd.append('logo', file);
                const up = await fetch(`${cfg.apiBase}?request=branding/logo`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd,
                }).then((r) => r.json());
                if (up.status === 'success' && up.data?.logo_url) {
                    const logo = document.getElementById('logoPreview');
                    logo.src = up.data.logo_url;
                    logo.hidden = false;
                }
            }
            load();
        } catch (err) {
            alert(err.message || t('save_error'), false);
        }
    });

    document.addEventListener('DOMContentLoaded', load);
})();
