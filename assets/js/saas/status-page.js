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

    let refreshTimer = null;

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setLoading() {
        const list = document.getElementById('statusComponentList');
        if (list) {
            list.innerHTML = `<li class="status-loading"><span class="spinner" aria-hidden="true"></span>${escapeHtml(t('loading'))}…</li>`;
        }
    }

    async function load() {
        setLoading();
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
            overallEl.innerHTML = `<span class="status-dot" aria-hidden="true"></span><strong>${escapeHtml(t(overall))}</strong>`;
        }

        const list = document.getElementById('statusComponentList');
        if (list) {
            const components = data.components || [];
            if (!components.length) {
                list.innerHTML = `<li class="status-empty">${escapeHtml(t('no_components'))}</li>`;
            } else {
                list.innerHTML = components.map((c) => {
                    const st = c.status || 'operational';
                    return `
                    <li class="status-component-item ${statusClass[st] || ''}">
                        <span>${escapeHtml(c.name || c.code || '—')}</span>
                        <strong>${escapeHtml(t(st))}</strong>
                    </li>`;
                }).join('');
            }
        }

        const incidents = data.incidents || [];
        const incSection = document.getElementById('statusIncidents');
        const incList = document.getElementById('statusIncidentList');
        if (incSection && incList) {
            incSection.hidden = false;
            if (incidents.length) {
                incList.innerHTML = incidents.map((i) => `
                    <article class="status-incident-card severity-${escapeHtml(i.severity || 'minor')}">
                        <h3>${escapeHtml(i.title || 'Incident')}</h3>
                        <p>${escapeHtml(i.message || '')}</p>
                        <small>${escapeHtml(i.status || '')} · ${escapeHtml(new Date(i.started_at).toLocaleString())}</small>
                    </article>
                `).join('');
            } else {
                incList.innerHTML = `<p class="status-empty">${escapeHtml(t('no_incidents'))}</p>`;
            }
        }

        const updated = document.getElementById('statusUpdated');
        if (updated) {
            const label = t('last_updated');
            const when = data.updated_at ? new Date(data.updated_at).toLocaleString() : '—';
            updated.textContent = `${label}: ${when}`;
        }
    }

    function showError() {
        const overallEl = document.getElementById('statusOverall');
        if (overallEl) {
            overallEl.className = 'status-overall is-down';
            overallEl.innerHTML = `<span class="status-dot" aria-hidden="true"></span><strong>${escapeHtml(t('load_error'))}</strong>`;
        }
        const list = document.getElementById('statusComponentList');
        if (list) {
            list.innerHTML = `<li class="status-empty">${escapeHtml(t('load_error'))}</li>`;
        }
    }

    function startAutoRefresh() {
        if (refreshTimer) clearInterval(refreshTimer);
        refreshTimer = setInterval(() => {
            load().catch(showError);
        }, 60000);
    }

    document.getElementById('statusThemeToggle')?.addEventListener('click', () => {
        if (window.AppTheme?.toggle) {
            window.AppTheme.toggle();
        }
    });

    document.getElementById('statusRefreshBtn')?.addEventListener('click', () => {
        load().catch(showError);
    });

    load().catch(showError).finally(startAutoRefresh);
})();
