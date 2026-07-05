(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    const STATUS_KEYS = [
        'operational',
        'degraded',
        'partial_outage',
        'major_outage',
        'maintenance',
    ];

    const STATUS_I18N = {
        operational: 'plat_status_operational',
        degraded: 'plat_status_degraded',
        partial_outage: 'plat_status_partial_outage',
        major_outage: 'plat_status_major_outage',
        maintenance: 'plat_status_maintenance',
    };

    const SEVERITY_I18N = {
        minor: 'plat_severity_minor',
        major: 'plat_severity_major',
        critical: 'plat_severity_critical',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function statusLabel(status) {
        const key = STATUS_I18N[status];
        return key ? t(key) : (status || '—');
    }

    function severityLabel(severity) {
        const key = SEVERITY_I18N[severity];
        return key ? t(key) : (severity || '—');
    }

    function formatDate(value) {
        if (!value) return '—';
        try {
            return new Date(value).toLocaleString(cfg.locale || undefined);
        } catch (e) {
            return '—';
        }
    }

    function showAlert(msg, ok) {
        const el = document.getElementById('platStatusAlert');
        if (!el) return;
        el.textContent = msg;
        el.className = 'plat-status-alert ' + (ok ? 'is-success' : 'is-error');
        el.hidden = false;
        el.setAttribute('role', ok ? 'status' : 'alert');
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => {
            el.hidden = true;
        }, 4000);
    }

    function showError(msg) {
        const banner = document.getElementById('platStatusError');
        const text = document.getElementById('platStatusErrorText');
        if (!banner || !text) return;
        text.textContent = msg || t('plat_status_load_error');
        banner.hidden = false;
    }

    function hideError() {
        const banner = document.getElementById('platStatusError');
        if (banner) banner.hidden = true;
    }

    function computeOverall(components) {
        const priority = {
            major_outage: 4,
            partial_outage: 3,
            degraded: 2,
            maintenance: 1,
            operational: 0,
        };
        let worst = 'operational';
        let worstScore = 0;
        (components || []).forEach((c) => {
            const s = c.status || 'operational';
            const score = priority[s] ?? 0;
            if (score > worstScore) {
                worstScore = score;
                worst = s;
            }
        });
        return worst;
    }

    function updateOverall(components) {
        const overall = computeOverall(components);
        const wrap = document.getElementById('platOverallStatus');
        const label = document.getElementById('platOverallLabel');
        if (wrap) {
            wrap.className = `plat-status-overall is-${overall}`;
        }
        if (label) {
            label.textContent = statusLabel(overall);
        }
    }

    function componentOptions(selected) {
        return STATUS_KEYS.map((s) =>
            `<option value="${s}"${selected === s ? ' selected' : ''}>${esc(statusLabel(s))}</option>`
        ).join('');
    }

    async function loadComponents() {
        const list = document.getElementById('platComponentList');
        if (!list) return [];

        list.innerHTML = `<li class="plat-status-loading"><span class="plat-status-spinner" aria-hidden="true"></span>${esc(t('loading'))}…</li>`;

        try {
            const res = await apiGet('incidents/components');
            if (res.status !== 'success') {
                throw new Error(res.message || t('plat_status_load_error'));
            }

            const components = res.data || [];
            updateOverall(components);

            if (!components.length) {
                list.innerHTML = `<li class="plat-status-empty">${esc(t('plat_no_data'))}</li>`;
                return components;
            }

            list.innerHTML = components.map((c) => {
                const status = c.status || 'operational';
                return `<li class="plat-component-card">
                    <div class="plat-component-card__info">
                        <span class="plat-component-card__dot is-${esc(status)}" aria-hidden="true"></span>
                        <span class="plat-component-card__name">${esc(c.name || c.code)}</span>
                    </div>
                    <select data-code="${esc(c.code)}" class="plat-status-select" aria-label="${esc(c.name || c.code)}">
                        ${componentOptions(status)}
                    </select>
                </li>`;
            }).join('');

            list.querySelectorAll('.plat-status-select').forEach((sel) => {
                sel.addEventListener('change', async () => {
                    const code = sel.dataset.code;
                    const status = sel.value;
                    try {
                        const res = await fetch(
                            `${cfg.apiBase}?request=platform/incidents/components/${encodeURIComponent(code)}`,
                            {
                                method: 'PUT',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ status }),
                            }
                        ).then((r) => r.json());

                        if (res.status !== 'success') {
                            throw new Error(res.message || t('action_error'));
                        }

                        const dot = sel.closest('.plat-component-card')?.querySelector('.plat-component-card__dot');
                        if (dot) {
                            dot.className = `plat-component-card__dot is-${status}`;
                        }

                        const allRes = await apiGet('incidents/components');
                        if (allRes.status === 'success') {
                            updateOverall(allRes.data || []);
                        }
                    } catch (e) {
                        showAlert(e.message || t('action_error'), false);
                    }
                });
            });

            return components;
        } catch (e) {
            list.innerHTML = `<li class="plat-status-empty">${esc(t('load_error'))}</li>`;
            showError(e.message || t('load_error'));
            return [];
        }
    }

    async function loadIncidents() {
        const el = document.getElementById('platIncidentList');
        const countEl = document.getElementById('platIncidentsCount');
        if (!el) return;

        el.innerHTML = `<div class="plat-status-loading"><span class="plat-status-spinner" aria-hidden="true"></span>${esc(t('loading'))}…</div>`;

        try {
            const res = await apiGet('incidents');
            if (res.status !== 'success') {
                throw new Error(res.message || t('plat_status_load_error'));
            }

            const incidents = res.data || [];
            const openCount = incidents.filter((i) => i.status !== 'resolved').length;

            if (countEl) {
                countEl.textContent = `${openCount} ${t('plat_status_incident_open')}`;
            }

            if (!incidents.length) {
                el.innerHTML = `<div class="plat-status-empty">${esc(t('plat_no_data'))}</div>`;
                return;
            }

            el.innerHTML = incidents.map((i) => {
                const isResolved = i.status === 'resolved';
                const statusBadge = isResolved
                    ? t('plat_status_incident_resolved')
                    : (i.status || t('plat_status_incident_open'));
                return `<article class="plat-incident-card${isResolved ? ' is-resolved' : ''}">
                    <div class="plat-incident-card__body">
                        <div class="plat-incident-card__title">
                            <strong>${esc(i.title)}</strong>
                            <span class="plat-severity-badge plat-severity-badge--${esc(i.severity || 'minor')}">${esc(severityLabel(i.severity))}</span>
                            <span class="plat-incident-status-badge plat-incident-status-badge--${isResolved ? 'resolved' : 'open'}">${esc(statusBadge)}</span>
                        </div>
                        <p class="plat-incident-card__message">${esc(i.message)}</p>
                        <small class="plat-incident-card__meta">${esc(formatDate(i.started_at))}</small>
                    </div>
                    ${!isResolved ? `<button type="button" class="plat-resolve-btn" data-resolve="${esc(String(i.id))}">
                        <span class="material-icons-round" aria-hidden="true" style="font-size:16px">check_circle</span>
                        ${esc(t('plat_status_resolve'))}
                    </button>` : ''}
                </article>`;
            }).join('');

            el.querySelectorAll('[data-resolve]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    if (!window.confirm(t('plat_status_confirm_resolve'))) {
                        return;
                    }
                    btn.disabled = true;
                    try {
                        const res = await apiPost(`incidents/${btn.dataset.resolve}/resolve`, {});
                        if (res.status !== 'success') {
                            throw new Error(res.message || t('action_error'));
                        }
                        showAlert(t('action_success'), true);
                        await refresh();
                    } catch (e) {
                        showAlert(e.message || t('action_error'), false);
                        btn.disabled = false;
                    }
                });
            });
        } catch (e) {
            el.innerHTML = `<div class="plat-status-empty">${esc(t('load_error'))}</div>`;
            showError(e.message || t('load_error'));
        }
    }

    function setSubmitting(isLoading) {
        const btn = document.getElementById('platIncidentSubmit');
        if (!btn) return;
        btn.disabled = isLoading;
        btn.classList.toggle('is-loading', isLoading);
    }

    document.getElementById('platIncidentForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError();
        setSubmitting(true);

        try {
            const fd = new FormData(e.target);
            const affects = [...e.target.querySelectorAll('input[name="affects"]:checked')].map((i) => i.value);
            const res = await apiPost('incidents', {
                title: fd.get('title'),
                message: fd.get('message'),
                severity: fd.get('severity'),
                affects,
            });

            if (res.status !== 'success') {
                throw new Error(res.message || t('plat_status_create_error'));
            }

            e.target.reset();
            showAlert(t('plat_status_create_ok'), true);
            await refresh();
        } catch (err) {
            showAlert(err.message || t('plat_status_create_error'), false);
        } finally {
            setSubmitting(false);
        }
    });

    async function refresh() {
        hideError();
        await Promise.all([loadComponents(), loadIncidents()]);
        setLastUpdated?.();
    }

    document.addEventListener('plat:refresh', refresh);
    refresh();
})();
