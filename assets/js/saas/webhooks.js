(function () {
    'use strict';

    const cfg = window.WEBHOOKS_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;
    let events = [];

    async function api(path, options = {}) {
        const url = `${cfg.apiBase}?request=webhooks/${path}`;
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
        const el = document.getElementById('webhooksAlert');
        if (!el) return;
        el.textContent = message;
        el.className = 'webhooks-alert webhooks-alert--' + (type || 'error') + ' is-visible';
        el.setAttribute('role', type === 'success' ? 'status' : 'alert');
    }

    function hideAlert() {
        const el = document.getElementById('webhooksAlert');
        if (!el) return;
        el.className = 'webhooks-alert';
        el.textContent = '';
    }

    function showSecret(secret) {
        const box = document.getElementById('webhookSecretBox');
        const code = document.getElementById('webhookSecretValue');
        if (!box || !code || !secret) return;
        code.textContent = secret;
        box.hidden = false;
    }

    function hideSecret() {
        const box = document.getElementById('webhookSecretBox');
        if (box) box.hidden = true;
    }

    function showLocked() {
        document.getElementById('webhooksLocked').hidden = false;
        document.getElementById('webhooksMain').hidden = true;
        hideSecret();
    }

    function showMain() {
        document.getElementById('webhooksLocked').hidden = true;
        document.getElementById('webhooksMain').hidden = false;
    }

    function setSubmitLoading(isLoading) {
        const btn = document.getElementById('webhookSubmitBtn');
        if (!btn) return;
        btn.disabled = isLoading;
        btn.classList.toggle('is-loading', isLoading);
        btn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function setTestLoading(isLoading) {
        const btn = document.getElementById('webhookTestBtn');
        if (!btn) return;
        btn.disabled = isLoading;
        btn.setAttribute('aria-busy', isLoading ? 'true' : 'false');
    }

    function deliveryStatus(d) {
        if (d.delivered_at) return { cls: 'ok', label: t('webhooks_status_ok') };
        if (d.failed_at) return { cls: 'failed', label: t('webhooks_status_failed') };
        return { cls: 'pending', label: t('webhooks_status_pending') };
    }

    function renderEvents() {
        const box = document.getElementById('webhookEvents');
        if (!box) return;
        if (!events.length) {
            box.innerHTML = `<p class="webhooks-empty">${escapeHtml(t('loading'))}…</p>`;
            return;
        }
        box.innerHTML = events.map((ev) => `
            <label class="webhook-event-check">
                <input type="checkbox" name="events" value="${escapeHtml(ev)}">
                <span>${escapeHtml(ev)}</span>
            </label>
        `).join('');
    }

    function renderEndpoints(rows) {
        const el = document.getElementById('webhookEndpointList');
        if (!el) return;
        if (!rows.length) {
            el.innerHTML = `<p class="webhooks-empty">${escapeHtml(t('webhooks_no_endpoints'))}</p>`;
            return;
        }
        el.innerHTML = rows.map((row) => `
            <article class="webhook-endpoint-card">
                <div class="webhook-endpoint-head">
                    <strong>${escapeHtml(row.url)}</strong>
                    <span class="plat-badge">${row.is_active ? escapeHtml(t('webhooks_active')) : escapeHtml(t('webhooks_inactive'))}</span>
                </div>
                ${row.description ? `<p class="webhook-endpoint-desc">${escapeHtml(row.description)}</p>` : ''}
                <p class="webhook-endpoint-events">${escapeHtml((row.events || []).join(', '))}</p>
                <button type="button" class="webhook-delete-btn" data-delete="${escapeHtml(String(row.id))}">${escapeHtml(t('webhooks_delete'))}</button>
            </article>
        `).join('');

        el.querySelectorAll('[data-delete]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!confirm(t('webhooks_confirm_delete'))) return;
                hideAlert();
                const res = await api(`endpoints/${btn.dataset.delete}`, { method: 'DELETE' });
                if (res.status === 'success') {
                    await load();
                    showAlert(t('webhooks_deleted'), 'success');
                } else {
                    showAlert(res.message || t('load_error'), 'error');
                }
            });
        });
    }

    function renderDeliveries(rows) {
        const el = document.getElementById('webhookDeliveryList');
        if (!el) return;
        if (!rows.length) {
            el.innerHTML = `<p class="webhooks-empty">${escapeHtml(t('webhooks_no_deliveries'))}</p>`;
            return;
        }
        el.innerHTML = `<table class="webhook-table"><thead><tr>
            <th>${escapeHtml(t('webhooks_col_event'))}</th>
            <th>${escapeHtml(t('webhooks_col_url'))}</th>
            <th>${escapeHtml(t('webhooks_col_status'))}</th>
            <th>${escapeHtml(t('webhooks_col_attempts'))}</th>
            <th>${escapeHtml(t('webhooks_col_date'))}</th>
        </tr></thead><tbody>${rows.map((d) => {
            const st = deliveryStatus(d);
            return `<tr>
                <td><code>${escapeHtml(d.event_type)}</code></td>
                <td>${escapeHtml(d.endpoint_url || '')}</td>
                <td><span class="webhook-status webhook-status--${st.cls}">${escapeHtml(st.label)}</span></td>
                <td>${escapeHtml(String(d.attempts ?? 0))}</td>
                <td>${escapeHtml(new Date(d.created_at).toLocaleString())}</td>
            </tr>`;
        }).join('')}</tbody></table>`;
    }

    async function load() {
        const epList = document.getElementById('webhookEndpointList');
        const delList = document.getElementById('webhookDeliveryList');
        if (epList) {
            epList.innerHTML = `<div class="webhooks-loading"><span class="spinner" aria-hidden="true"></span>${escapeHtml(t('loading'))}…</div>`;
        }
        if (delList) {
            delList.innerHTML = `<div class="webhooks-loading"><span class="spinner" aria-hidden="true"></span>${escapeHtml(t('loading'))}…</div>`;
        }

        try {
            const evRes = await api('events');
            if (evRes.status === 'success') {
                events = evRes.data || [];
                renderEvents();
            }

            const epRes = await api('endpoints');
            if (epRes.status === 'error' && (epRes.message || '').includes('API access')) {
                showLocked();
                return;
            }
            if (epRes.status === 'success') {
                showMain();
                renderEndpoints(epRes.data || []);
            } else {
                showAlert(epRes.message || t('load_error'), 'error');
            }

            const delRes = await api('deliveries');
            if (delRes.status === 'success') {
                renderDeliveries(delRes.data || []);
            }
        } catch (_) {
            showLocked();
        }
    }

    document.getElementById('webhookSecretCopy')?.addEventListener('click', async () => {
        const secret = document.getElementById('webhookSecretValue')?.textContent || '';
        if (!secret) return;
        try {
            await navigator.clipboard.writeText(secret);
            showAlert(t('webhooks_secret_copied'), 'success');
        } catch (_) {
            showAlert(t('load_error'), 'error');
        }
    });

    document.getElementById('webhookSecretDismiss')?.addEventListener('click', hideSecret);

    document.getElementById('webhooksThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    document.getElementById('webhookForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();
        setSubmitLoading(true);

        const url = document.getElementById('webhookUrl')?.value?.trim() || '';
        const description = document.getElementById('webhookDesc')?.value?.trim() || '';
        const selected = [...document.querySelectorAll('#webhookEvents input:checked')].map((i) => i.value);

        try {
            const res = await api('endpoints', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ url, description, events: selected }),
            });

            if (res.status === 'success') {
                showAlert(t('webhooks_created'), 'success');
                if (res.data?.secret) {
                    showSecret(res.data.secret);
                }
                e.target.reset();
                renderEvents();
                await load();
            } else {
                showAlert(res.message || t('webhooks_create_error'), 'error');
            }
        } catch (_) {
            showAlert(t('load_error'), 'error');
        } finally {
            setSubmitLoading(false);
        }
    });

    document.getElementById('webhookTestBtn')?.addEventListener('click', async () => {
        hideAlert();
        setTestLoading(true);
        try {
            const res = await api('test', { method: 'POST' });
            if (res.status === 'success') {
                showAlert(`${t('webhooks_test_ok')} (${res.queued ?? 0})`, 'success');
                await load();
            } else {
                showAlert(res.message || t('load_error'), 'error');
            }
        } catch (_) {
            showAlert(t('load_error'), 'error');
        } finally {
            setTestLoading(false);
        }
    });

    load();
})();
