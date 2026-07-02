(function () {
    'use strict';

    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    const statusLabels = {
        operational: 'Operational',
        degraded: 'Degraded',
        partial_outage: 'Partial outage',
        major_outage: 'Major outage',
        maintenance: 'Maintenance',
    };

    async function loadComponents() {
        const res = await apiGet('incidents/components');
        const list = document.getElementById('platComponentList');
        if (!list || res.status !== 'success') return;
        list.innerHTML = (res.data || []).map((c) => `
            <li>
                <span>${c.name || c.code}</span>
                <select data-code="${c.code}" class="plat-status-select">
                    ${Object.keys(statusLabels).map((s) => `
                        <option value="${s}"${c.status === s ? ' selected' : ''}>${statusLabels[s]}</option>
                    `).join('')}
                </select>
            </li>
        `).join('');

        list.querySelectorAll('.plat-status-select').forEach((sel) => {
            sel.addEventListener('change', async () => {
                await fetch(`${window.PLATFORM_CONFIG.apiBase}?request=platform/incidents/components/${sel.dataset.code}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: sel.value }),
                });
            });
        });
    }

    async function loadIncidents() {
        const res = await apiGet('incidents');
        const el = document.getElementById('platIncidentList');
        if (!el || res.status !== 'success') return;
        el.innerHTML = (res.data || []).map((i) => `
            <article class="plat-incident-row">
                <div>
                    <strong>${i.title}</strong>
                    <p>${i.message}</p>
                    <small>${i.severity} · ${i.status} · ${new Date(i.started_at).toLocaleString()}</small>
                </div>
                ${i.status !== 'resolved' ? `<button type="button" class="btn-secondary btn-sm" data-resolve="${i.id}">Resolve</button>` : ''}
            </article>
        `).join('') || `<p>${t('plat_no_data')}</p>`;

        el.querySelectorAll('[data-resolve]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                await apiPost(`incidents/${btn.dataset.resolve}/resolve`, {});
                await loadIncidents();
                await loadComponents();
            });
        });
    }

    document.getElementById('platIncidentForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const affects = [...e.target.querySelectorAll('input[name="affects"]:checked')].map((i) => i.value);
        await apiPost('incidents', {
            title: fd.get('title'),
            message: fd.get('message'),
            severity: fd.get('severity'),
            affects,
        });
        e.target.reset();
        await loadIncidents();
        await loadComponents();
    });

    async function refresh() {
        await Promise.all([loadComponents(), loadIncidents()]);
        setLastUpdated?.();
    }

    document.addEventListener('plat:refresh', refresh);
    refresh();
})();
