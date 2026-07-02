(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const t = (key) => (cfg.i18n && cfg.i18n[key]) || key;

    function apiUrl(path) {
        const [base, query] = String(path).split('?');
        const url = `${cfg.apiBase}?request=platform/${base}`;
        return query ? `${url}&${query}` : url;
    }

    async function apiGet(path) {
        const res = await fetch(apiUrl(path), { credentials: 'same-origin' });
        return res.json();
    }

    async function apiPost(path, body) {
        const res = await fetch(apiUrl(path), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body || {}),
        });
        return res.json();
    }

    function setLastUpdated() {
        const el = document.getElementById('platLastUpdated');
        if (el) {
            el.textContent = `${t('last_updated')}: ${new Date().toLocaleString(cfg.locale || 'fr-FR')}`;
        }
    }

    document.getElementById('platRefreshBtn')?.addEventListener('click', () => {
        document.dispatchEvent(new CustomEvent('plat:refresh'));
    });

    document.getElementById('platMenuBtn')?.addEventListener('click', () => {
        document.getElementById('platSidebar')?.classList.toggle('open');
    });

    window.PlatformAPI = { apiGet, apiPost, t, setLastUpdated };
})();
