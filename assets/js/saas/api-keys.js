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

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showAlert(message, type) {
        const el = document.getElementById('apikeysAlert');
        if (!el) return;
        el.textContent = message;
        el.className = 'apikeys-alert apikeys-alert--' + (type || 'error') + ' is-visible';
        el.setAttribute('role', type === 'success' ? 'status' : 'alert');
    }

    function hideAlert() {
        const el = document.getElementById('apikeysAlert');
        if (!el) return;
        el.className = 'apikeys-alert';
        el.textContent = '';
    }

    function showSecret(rawKey) {
        const box = document.getElementById('apikeySecretBox');
        const code = document.getElementById('apikeySecretValue');
        if (!box || !code || !rawKey) return;
        code.textContent = rawKey;
        box.hidden = false;
    }

    function hideSecret() {
        const box = document.getElementById('apikeySecretBox');
        if (box) box.hidden = true;
    }

    function showLocked() {
        document.getElementById('apikeysLocked').hidden = false;
        document.getElementById('apikeysMain').hidden = true;
        hideSecret();
    }

    function showMain() {
        document.getElementById('apikeysLocked').hidden = true;
        document.getElementById('apikeysMain').hidden = false;
    }

    function setSubmitLoading(isLoading) {
        const btn = document.getElementById('apikeySubmitBtn');
        if (!btn) return;
        btn.disabled = isLoading;
        btn.classList.toggle('is-loading', isLoading);
        btn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function renderScopes() {
        const box = document.getElementById('apikeyScopes');
        if (!box) return;
        if (!scopes.length) {
            box.innerHTML = `<p class="apikeys-empty">${escapeHtml(t('loading'))}…</p>`;
            return;
        }
        box.innerHTML = scopes.map((s) => `
            <label class="apikey-scope-check">
                <input type="checkbox" name="scopes" value="${escapeHtml(s)}"${s === 'tenant:read' ? ' checked' : ''}>
                <span>${escapeHtml(s)}</span>
            </label>
        `).join('');
    }

    function renderKeys(rows) {
        const el = document.getElementById('apikeyList');
        if (!el) return;
        if (!rows.length) {
            el.innerHTML = `<p class="apikeys-empty">${escapeHtml(t('apikeys_no_keys'))}</p>`;
            return;
        }
        el.innerHTML = rows.map((k) => {
            const active = !!k.is_active;
            const lastUsed = k.last_used_at ? new Date(k.last_used_at).toLocaleString() : '—';
            return `
            <article class="apikey-card${active ? '' : ' is-revoked'}">
                <div class="apikey-card-head">
                    <strong>${escapeHtml(k.name)}</strong>
                    <span class="plat-badge">${active ? escapeHtml(t('apikeys_active')) : escapeHtml(t('apikeys_revoked'))}</span>
                </div>
                <p class="apikey-prefix"><code>${escapeHtml(k.key_prefix)}…</code></p>
                <p class="apikey-scopes-list">${escapeHtml((k.scopes || []).join(', '))}</p>
                <small class="apikey-meta">${escapeHtml(t('apikeys_last_used'))}: ${escapeHtml(lastUsed)}</small>
                ${active ? `<button type="button" class="apikey-revoke-btn" data-revoke="${escapeHtml(String(k.id))}">${escapeHtml(t('apikeys_revoke'))}</button>` : ''}
            </article>`;
        }).join('');

        el.querySelectorAll('[data-revoke]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!confirm(t('apikeys_confirm_revoke'))) return;
                hideAlert();
                const res = await api(btn.dataset.revoke, { method: 'DELETE' });
                if (res.status === 'success') {
                    showAlert(t('apikeys_revoked_ok'), 'success');
                    await load();
                } else {
                    showAlert(res.message || t('load_error'), 'error');
                }
            });
        });
    }

    async function load() {
        const list = document.getElementById('apikeyList');
        if (list) {
            list.innerHTML = `<div class="apikeys-loading"><span class="spinner" aria-hidden="true"></span>${escapeHtml(t('loading'))}…</div>`;
        }

        try {
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
            } else {
                showAlert(res.message || t('load_error'), 'error');
            }
        } catch (_) {
            showLocked();
        }
    }

    document.getElementById('apikeySecretCopy')?.addEventListener('click', async () => {
        const key = document.getElementById('apikeySecretValue')?.textContent || '';
        if (!key) return;
        try {
            await navigator.clipboard.writeText(key);
            showAlert(t('apikeys_secret_copied'), 'success');
        } catch (_) {
            showAlert(t('load_error'), 'error');
        }
    });

    document.getElementById('apikeySecretDismiss')?.addEventListener('click', hideSecret);

    document.getElementById('apikeysThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    document.getElementById('apikeyForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        setSubmitLoading(true);

        const name = document.getElementById('apikeyName')?.value?.trim() || '';
        const selected = [...document.querySelectorAll('#apikeyScopes input:checked')].map((i) => i.value);

        try {
            const res = await api('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, scopes: selected }),
            });

            if (res.status === 'success' && res.data?.raw_key) {
                showAlert(t('apikeys_created'), 'success');
                showSecret(res.data.raw_key);
                e.target.reset();
                renderScopes();
                await load();
            } else {
                showAlert(res.message || t('apikeys_create_error'), 'error');
            }
        } catch (_) {
            showAlert(t('load_error'), 'error');
        } finally {
            setSubmitLoading(false);
        }
    });

    load();
})();
