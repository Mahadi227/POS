(function () {
    'use strict';

    const cfg = window.APIKEYS_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;
    let scopes = [];

    async function api(path, options = {}) {
        const url = `${cfg.apiBase}?request=api-keys/${path}`;
        const res = await fetch(url, { credentials: 'same-origin', ...options });
        return res.json();
    }

    function showLocked() {
        document.getElementById('apikeysLocked').hidden = false;
        document.getElementById('apikeysMain').hidden = true;
    }

    function showMain() {
        document.getElementById('apikeysLocked').hidden = true;
        document.getElementById('apikeysMain').hidden = false;
    }

    function renderScopes() {
        const box = document.getElementById('apikeyScopes');
        if (!box) return;
        box.innerHTML = scopes.map((s) => `
            <label><input type="checkbox" name="scopes" value="${s}"${s === 'tenant:read' ? ' checked' : ''}> ${s}</label>
        `).join('');
    }

    function renderKeys(rows) {
        const el = document.getElementById('apikeyList');
        if (!el) return;
        if (!rows.length) {
            el.innerHTML = `<p>${t('apikeys_no_keys')}</p>`;
            return;
        }
        el.innerHTML = rows.map((k) => `
            <article class="apikey-card">
                <div class="apikey-card-head">
                    <strong>${k.name}</strong>
                    <span class="plat-badge">${k.is_active ? 'active' : 'revoked'}</span>
                </div>
                <p><code>${k.key_prefix}…</code></p>
                <p>${(k.scopes || []).join(', ')}</p>
                <small>${t('apikeys_last_used')}: ${k.last_used_at ? new Date(k.last_used_at).toLocaleString() : '—'}</small>
                ${k.is_active ? `<button type="button" class="btn-danger btn-sm" data-revoke="${k.id}">${t('apikeys_revoke')}</button>` : ''}
            </article>
        `).join('');

        el.querySelectorAll('[data-revoke]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!confirm(t('apikeys_confirm_revoke'))) return;
                await api(btn.dataset.revoke, { method: 'DELETE' });
                await load();
            });
        });
    }

    async function load() {
        const sc = await api('scopes');
        if (sc.status === 'success') {
            scopes = sc.data || [];
            renderScopes();
        }

        const res = await api('');
        if (res.status === 'error' && (res.message || '').includes('API')) {
            showLocked();
            return;
        }
        if (res.status === 'success') {
            showMain();
            renderKeys(res.data || []);
        }
    }

    document.getElementById('apikeyForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const name = document.getElementById('apikeyName')?.value || '';
        const selected = [...document.querySelectorAll('#apikeyScopes input:checked')].map((i) => i.value);
        const res = await api('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, scopes: selected }),
        });
        if (res.status === 'success' && res.data?.raw_key) {
            prompt(`${t('apikeys_created')}\n${t('apikeys_copy')}`, res.data.raw_key);
            e.target.reset();
            renderScopes();
            await load();
        }
    });

    load().catch(() => showLocked());
})();
