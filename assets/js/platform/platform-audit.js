(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    let debounce = null;
    let knownActions = [];

    const ACTION_I18N = {
        'platform.login_success': 'plat_audit_action_platform_login_success',
        'platform.login_failed': 'plat_audit_action_platform_login_failed',
        'platform.logout': 'plat_audit_action_platform_logout',
        'tenant.impersonate_start': 'plat_audit_action_tenant_impersonate_start',
        'tenant.impersonate_end': 'plat_audit_action_tenant_impersonate_end',
        'tenant.status_change': 'plat_audit_action_tenant_status_change',
        'tenant.trial_extended': 'plat_audit_action_tenant_trial_extended',
        'tenant.plan_change': 'plat_audit_action_tenant_plan_change',
        'tenant.module_overrides': 'plat_audit_action_tenant_module_overrides',
        'tenant.feature_flags': 'plat_audit_action_tenant_feature_flags',
        'license.issue': 'plat_audit_action_license_issue',
        'license.revoke': 'plat_audit_action_license_revoke',
        'payment.mobile_money_confirm': 'plat_audit_action_payment_mobile_money_confirm',
        'domain.verify': 'plat_audit_action_domain_verify',
        'platform_user.create': 'plat_audit_action_platform_user_create',
        'platform_user.activate': 'plat_audit_action_platform_user_activate',
        'platform_user.deactivate': 'plat_audit_action_platform_user_deactivate',
        'platform.settings_update': 'plat_audit_action_platform_settings_update',
        'platform.feature_flags_update': 'plat_audit_action_platform_feature_flags_update',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function actionLabel(action) {
        const key = ACTION_I18N[action];
        return key ? t(key) : action;
    }

    function fmt(v) {
        if (!v) return '—';
        try { return new Date(v).toLocaleString(cfg.locale); } catch (e) { return '—'; }
    }

    function showError(msg) {
        const el = document.getElementById('platAuditError');
        document.getElementById('platAuditErrorText').textContent = msg || t('plat_audit_load_error');
        el.hidden = false;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platAuditKpiGrid .plat-kpi-card').forEach((c) => {
            c.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platAuditCount');
        if (!el) return;
        const template = t('plat_audit_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearBtn() {
        const search = document.getElementById('platAuditSearch')?.value || '';
        const action = document.getElementById('platAuditActionFilter')?.value || '';
        const btn = document.getElementById('platAuditClearFilters');
        if (btn) btn.hidden = !search && !action;
    }

    function renderKpis(stats) {
        document.getElementById('platAuditKpiTotal').textContent = String(stats?.total ?? 0);
        document.getElementById('platAuditKpiToday').textContent = String(stats?.today ?? 0);
        document.getElementById('platAuditKpiUsers').textContent = String(stats?.users ?? 0);
        document.getElementById('platAuditKpiTenants').textContent = String(stats?.tenants ?? 0);
        updateCount(stats?.total ?? 0);
    }

    function renderActions(rows) {
        const el = document.getElementById('platAuditActions');
        if (!rows?.length) {
            el.innerHTML = `<span class="plat-gov-muted">${esc(t('plat_no_data'))}</span>`;
            return;
        }
        el.innerHTML = rows.map((r) =>
            `<span class="plat-gov-action-chip">${esc(actionLabel(r.action))} <strong>${esc(String(r.count))}</strong></span>`
        ).join('');
    }

    function renderActionFilter(actions) {
        const sel = document.getElementById('platAuditActionFilter');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = `<option value="">${esc(t('plat_audit_filter_all_actions'))}</option>` +
            (actions || []).map((a) => `<option value="${esc(a)}">${esc(actionLabel(a))}</option>`).join('');
        if (current) sel.value = current;
    }

    function renderLogs(rows) {
        const body = document.getElementById('platAuditLogs');
        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="6" class="plat-gov-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }
        body.innerHTML = rows.map((r) => {
            const user = r.platform_user_name || r.platform_user_email || '—';
            return `<tr>
                <td><code>${esc(r.action)}</code></td>
                <td>${esc(user)}</td>
                <td>${esc(r.tenant_name || '—')}</td>
                <td>${esc(r.ip_address || '—')}</td>
                <td>${esc(fmt(r.created_at))}</td>
                <td><button type="button" class="plat-gov-btn" data-audit-id="${esc(String(r.id))}">${esc(t('plat_view_detail'))}</button></td>
            </tr>`;
        }).join('');
    }

    function openDetail(id) {
        apiGet(`audit/${id}`).then((res) => {
            if (res.status !== 'success') return;
            const d = res.data;
            let details = '—';
            if (d.details_json) {
                try {
                    const parsed = typeof d.details_json === 'string' ? JSON.parse(d.details_json) : d.details_json;
                    details = JSON.stringify(parsed, null, 2);
                } catch (e) {
                    details = String(d.details_json);
                }
            }
            document.getElementById('platAuditDetail').innerHTML = `
                <div class="plat-gov-detail-row"><span>${esc(t('plat_audit_col_action'))}</span>${esc(actionLabel(d.action))} <code>${esc(d.action)}</code></div>
                <div class="plat-gov-detail-row"><span>${esc(t('plat_audit_col_user'))}</span>${esc(d.platform_user_name || d.platform_user_email || '—')}</div>
                <div class="plat-gov-detail-row"><span>${esc(t('plat_audit_col_org'))}</span>${esc(d.tenant_name || '—')}</div>
                <div class="plat-gov-detail-row"><span>${esc(t('plat_audit_col_ip'))}</span>${esc(d.ip_address || '—')}</div>
                <div class="plat-gov-detail-row"><span>${esc(t('plat_audit_col_date'))}</span>${esc(fmt(d.created_at))}</div>
                <div class="plat-gov-detail-row"><span>${esc(t('plat_audit_detail_details'))}</span><pre class="plat-gov-json">${esc(details)}</pre></div>
            `;
            document.getElementById('platAuditModal').hidden = false;
        }).catch(() => {});
    }

    function loadDashboard() {
        setKpiLoading(true);
        apiGet('audit/dashboard').then((res) => {
            if (res.status !== 'success') throw new Error();
            renderKpis(res.data?.stats);
            renderActions(res.data?.actions);
            renderLogs(res.data?.recent);
            setLastUpdated?.();
        }).catch(() => showError()).finally(() => setKpiLoading(false));
    }

    function loadLogs() {
        const p = new URLSearchParams();
        const q = document.getElementById('platAuditSearch')?.value?.trim();
        const action = document.getElementById('platAuditActionFilter')?.value;
        if (q) p.set('q', q);
        if (action) p.set('action', action);
        const qs = p.toString();
        apiGet(`audit/logs${qs ? `?${qs}` : ''}`).then((res) => {
            if (res.status !== 'success') throw new Error();
            knownActions = res.data?.actions || knownActions;
            renderActionFilter(knownActions);
            renderLogs(res.data?.logs);
            renderKpis(res.data?.stats);
            updateClearBtn();
        }).catch(() => showError());
    }

    function scheduleLogs() {
        clearTimeout(debounce);
        debounce = setTimeout(loadLogs, 300);
    }

    document.getElementById('platAuditSearch')?.addEventListener('input', scheduleLogs);
    document.getElementById('platAuditActionFilter')?.addEventListener('change', loadLogs);
    document.getElementById('platAuditClearFilters')?.addEventListener('click', () => {
        document.getElementById('platAuditSearch').value = '';
        document.getElementById('platAuditActionFilter').value = '';
        loadLogs();
    });
    document.getElementById('platAuditLogs')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-audit-id]');
        if (btn) openDetail(btn.dataset.auditId);
    });
    document.querySelectorAll('[data-close-audit-modal]').forEach((el) => {
        el.addEventListener('click', () => { document.getElementById('platAuditModal').hidden = true; });
    });

    loadDashboard();
    loadLogs();
})();
