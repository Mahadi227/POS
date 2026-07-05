(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    let attemptDebounce = null;
    let eventDebounce = null;

    const STATUS_I18N = {
        success: 'plat_security_status_success',
        failed: 'plat_security_status_failed',
        locked: 'plat_security_status_locked',
    };

    const SEVERITY_I18N = {
        low: 'plat_security_severity_low',
        medium: 'plat_security_severity_medium',
        high: 'plat_security_severity_high',
        critical: 'plat_security_severity_critical',
    };

    const EVENT_I18N = {
        'login.lockout': 'plat_security_event_login_lockout',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function label(map, key) {
        return map[key] ? t(map[key]) : key;
    }

    function fmt(v) {
        if (!v) return '—';
        try { return new Date(v).toLocaleString(cfg.locale); } catch (e) { return '—'; }
    }

    function showError(msg) {
        const el = document.getElementById('platSecurityError');
        document.getElementById('platSecurityErrorText').textContent = msg || t('plat_security_load_error');
        el.hidden = false;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platSecurityKpiGrid .plat-kpi-card').forEach((c) => {
            c.classList.toggle('is-loading', loading);
        });
    }

    function renderKpis(stats) {
        document.getElementById('platSecKpiFailedToday').textContent = String(stats?.failed_today ?? 0);
        document.getElementById('platSecKpiFailedTotal').textContent = String(stats?.failed_total ?? 0);
        document.getElementById('platSecKpiSuccessToday').textContent = String(stats?.success_today ?? 0);
        document.getElementById('platSecKpiEvents').textContent = String(stats?.events_total ?? 0);
        document.getElementById('platSecKpiActiveUsers').textContent = String(stats?.active_users ?? 0);
    }

    function statusPill(status) {
        const cls = status === 'success' ? 'success' : (status === 'locked' ? 'locked' : 'failed');
        return `<span class="plat-gov-pill plat-gov-pill--${cls}">${esc(label(STATUS_I18N, status))}</span>`;
    }

    function severityPill(severity) {
        const safe = esc(severity || 'medium');
        return `<span class="plat-gov-pill plat-gov-pill--${safe}">${esc(label(SEVERITY_I18N, severity))}</span>`;
    }

    function renderAttempts(rows) {
        const body = document.getElementById('platSecAttempts');
        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="4" class="plat-gov-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }
        body.innerHTML = rows.map((r) => `<tr>
            <td>${esc(r.email)}</td>
            <td>${statusPill(r.status)}</td>
            <td>${esc(r.ip_address || '—')}</td>
            <td>${esc(fmt(r.created_at))}</td>
        </tr>`).join('');
    }

    function renderEvents(rows) {
        const body = document.getElementById('platSecEvents');
        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="4" class="plat-gov-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }
        body.innerHTML = rows.map((r) => `<tr>
            <td><code>${esc(r.event_type)}</code> ${esc(label(EVENT_I18N, r.event_type) !== r.event_type ? `(${esc(label(EVENT_I18N, r.event_type))})` : '')}</td>
            <td>${severityPill(r.severity)}</td>
            <td>${esc(r.email || '—')}</td>
            <td>${esc(fmt(r.created_at))}</td>
        </tr>`).join('');
    }

    function loadDashboard() {
        setKpiLoading(true);
        apiGet('security/dashboard').then((res) => {
            if (res.status !== 'success') throw new Error();
            renderKpis(res.data?.stats);
            renderAttempts(res.data?.recent_attempts);
            renderEvents(res.data?.recent_events);
            setLastUpdated?.();
        }).catch(() => showError()).finally(() => setKpiLoading(false));
    }

    function loadAttempts() {
        const p = new URLSearchParams();
        const q = document.getElementById('platSecAttemptSearch')?.value?.trim();
        const status = document.getElementById('platSecAttemptStatus')?.value;
        if (q) p.set('q', q);
        if (status) p.set('status', status);
        const qs = p.toString();
        apiGet(`security/login-attempts${qs ? `?${qs}` : ''}`).then((res) => {
            if (res.status !== 'success') throw new Error();
            renderAttempts(res.data?.attempts);
            renderKpis(res.data?.stats);
        }).catch(() => showError());
    }

    function loadEvents() {
        const p = new URLSearchParams();
        const q = document.getElementById('platSecEventSearch')?.value?.trim();
        const severity = document.getElementById('platSecEventSeverity')?.value;
        if (q) p.set('q', q);
        if (severity) p.set('severity', severity);
        const qs = p.toString();
        apiGet(`security/events${qs ? `?${qs}` : ''}`).then((res) => {
            if (res.status !== 'success') throw new Error();
            renderEvents(res.data?.events);
            renderKpis(res.data?.stats);
        }).catch(() => showError());
    }

    document.getElementById('platSecAttemptSearch')?.addEventListener('input', () => {
        clearTimeout(attemptDebounce);
        attemptDebounce = setTimeout(loadAttempts, 300);
    });
    document.getElementById('platSecAttemptStatus')?.addEventListener('change', loadAttempts);
    document.getElementById('platSecEventSearch')?.addEventListener('input', () => {
        clearTimeout(eventDebounce);
        eventDebounce = setTimeout(loadEvents, 300);
    });
    document.getElementById('platSecEventSeverity')?.addEventListener('change', loadEvents);

    loadDashboard();
})();
