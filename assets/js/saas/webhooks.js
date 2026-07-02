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

    function showLocked() {
        document.getElementById('webhooksLocked').hidden = false;
        document.getElementById('webhooksMain').hidden = true;
    }

    function showMain() {
        document.getElementById('webhooksLocked').hidden = true;
        document.getElementById('webhooksMain').hidden = false;
    }

    function renderEvents() {
        const box = document.getElementById('webhookEvents');
        if (!box) return;
        box.innerHTML = events.map((ev) => `
            <label class="webhook-event-check">
                <input type="checkbox" name="events" value="${ev}">
                <span>${ev}</span>
            </label>
        `).join('');
    }

    function renderEndpoints(rows) {
        const el = document.getElementById('webhookEndpointList');
        if (!el) return;
        if (!rows.length) {
            el.innerHTML = `<p class="webhooks-empty">${t('webhooks_no_endpoints')}</p>`;
            return;
        }
        el.innerHTML = rows.map((row) => `
            <article class="webhook-endpoint-card">
                <div class="webhook-endpoint-head">
                    <strong>${row.url}</strong>
                    <span class="plat-badge">${row.is_active ? t('webhooks_active') : t('webhooks_inactive')}</span>
                </div>
                <p>${row.description || ''}</p>
                <p class="webhook-endpoint-events">${(row.events || []).join(', ')}</p>
                <button type="button" class="btn-danger btn-sm" data-delete="${row.id}">${t('webhooks_delete')}</button>
            </article>
        `).join('');

        el.querySelectorAll('[data-delete]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!confirm(t('webhooks_confirm_delete'))) return;
                await api(`endpoints/${btn.dataset.delete}`, { method: 'DELETE' });
                await load();
            });
        });
    }

    function renderDeliveries(rows) {
        const el = document.getElementById('webhookDeliveryList');
        if (!el) return;
        if (!rows.length) {
            el.innerHTML = `<p class="webhooks-empty">${t('webhooks_no_deliveries')}</p>`;
            return;
        }
        el.innerHTML = `<table class="webhook-table"><thead><tr>
            <th>Event</th><th>URL</th><th>Status</th><th>Attempts</th><th>Date</th>
        </tr></thead><tbody>${rows.map((d) => `
            <tr>
                <td><code>${d.event_type}</code></td>
                <td>${d.endpoint_url || ''}</td>
                <td>${d.delivered_at ? 'OK' : (d.failed_at ? 'Failed' : 'Pending')}</td>
                <td>${d.attempts ?? 0}</td>
                <td>${new Date(d.created_at).toLocaleString()}</td>
            </tr>
        `).join('')}</tbody></table>`;
    }

    async function load() {
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
        }

        const delRes = await api('deliveries');
        if (delRes.status === 'success') {
            renderDeliveries(delRes.data || []);
        }
    }

    document.getElementById('webhookForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const url = document.getElementById('webhookUrl')?.value || '';
        const description = document.getElementById('webhookDesc')?.value || '';
        const selected = [...document.querySelectorAll('#webhookEvents input:checked')].map((i) => i.value);
        const res = await api('endpoints', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url, description, events: selected }),
        });
        if (res.status === 'success') {
            alert(`${t('webhooks_created')}\n${t('webhooks_secret')}: ${res.data?.secret || '—'}`);
            e.target.reset();
            await load();
        }
    });

    document.getElementById('webhookTestBtn')?.addEventListener('click', async () => {
        const res = await api('test', { method: 'POST' });
        alert(res.status === 'success' ? `${t('webhooks_test_ok')} (${res.queued ?? 0})` : t('load_error'));
        await load();
    });

    load().catch(() => showLocked());
})();
