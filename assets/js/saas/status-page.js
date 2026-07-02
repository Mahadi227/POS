(function () {
    'use strict';

    const cfg = window.STATUS_CONFIG || {};
    const t = (k) => cfg.i18n?.[k] || k;

    const statusClass = {
        operational: 'is-ok',
        degraded: 'is-warn',
        partial_outage: 'is-warn',
        major_outage: 'is-down',
        maintenance: 'is-maint',
    };

    async function load() {
        const res = await fetch(`${cfg.apiBase}?request=status/public`, { credentials: 'same-origin' });
        const json = await res.json();
        if (json.status !== 'success' || !json.data) {
            throw new Error('load');
        }
        render(json.data);
    }

    function render(data) {
        const overall = data.overall || 'operational';
        const overallEl = document.getElementById('statusOverall');
        if (overallEl) {
            overallEl.className = `status-overall ${statusClass[overall] || 'is-ok'}`;
            overallEl.innerHTML = `<span class="status-dot"></span><strong>${t(overall)}</strong>`;
        }

        const list = document.getElementById('statusComponentList');
        if (list) {
            list.innerHTML = (data.components || []).map((c) => `
                <li class="status-component-item ${statusClass[c.status] || ''}">
                    <span>${c.name || c.code}</span>
                    <strong>${t(c.status || 'operational')}</strong>
                </li>
            `).join('');
        }

        const incidents = data.incidents || [];
        const incSection = document.getElementById('statusIncidents');
        const incList = document.getElementById('statusIncidentList');
        if (incSection && incList) {
            if (incidents.length) {
                incSection.hidden = false;
                incList.innerHTML = incidents.map((i) => `
                    <article class="status-incident-card severity-${i.severity || 'minor'}">
                        <h3>${i.title || 'Incident'}</h3>
                        <p>${i.message || ''}</p>
                        <small>${i.status || ''} · ${new Date(i.started_at).toLocaleString()}</small>
                    </article>
                `).join('');
            } else {
                incSection.hidden = false;
                incList.innerHTML = `<p class="status-empty">${t('no_incidents')}</p>`;
            }
        }

        const updated = document.getElementById('statusUpdated');
        if (updated && data.updated_at) {
            updated.textContent = new Date(data.updated_at).toLocaleString();
        }
    }

    load().catch(() => {
        const overallEl = document.getElementById('statusOverall');
        if (overallEl) {
            overallEl.innerHTML = `<span class="status-dot"></span><strong>${t('load_error')}</strong>`;
        }
    });

    setInterval(load, 60000);
})();
