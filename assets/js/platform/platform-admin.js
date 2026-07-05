(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    const ACTION_I18N = {
        'platform.login_success': 'plat_audit_action_platform_login_success',
        'platform.login_failed': 'plat_audit_action_platform_login_failed',
        'platform.logout': 'plat_audit_action_platform_logout',
        'tenant.impersonate_start': 'plat_audit_action_tenant_impersonate_start',
        'tenant.impersonate_end': 'plat_audit_action_tenant_impersonate_end',
        'tenant.status_change': 'plat_audit_action_tenant_status_change',
        'platform_user.create': 'plat_audit_action_platform_user_create',
        'platform_user.activate': 'plat_audit_action_platform_user_activate',
        'platform_user.deactivate': 'plat_audit_action_platform_user_deactivate',
        'platform.settings_update': 'plat_audit_action_platform_settings_update',
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
        try {
            return new Date(v).toLocaleString(cfg.locale || 'fr-FR');
        } catch (e) {
            return '—';
        }
    }

    function showError(msg) {
        const banner = document.getElementById('platAdminError');
        const text = document.getElementById('platAdminErrorText');
        if (!banner || !text) return;
        text.textContent = msg || t('plat_admin_load_error');
        banner.hidden = false;
    }

    function hideError() {
        const banner = document.getElementById('platAdminError');
        if (banner) banner.hidden = true;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platAdminKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value ?? '0';
    }

    function renderAuditRows(rows) {
        const body = document.getElementById('platAdminAuditBody');
        if (!body) return;

        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="4" class="plat-gov-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }

        body.innerHTML = rows.slice(0, 8).map((row) => {
            const user = row.platform_user_name || row.platform_user_email || '—';
            return `<tr>
                <td>${esc(fmt(row.created_at))}</td>
                <td>${esc(actionLabel(row.action || ''))}</td>
                <td>${esc(user)}</td>
                <td>${esc(row.ip_address || '—')}</td>
            </tr>`;
        }).join('');
    }

    async function loadAdminHub() {
        hideError();
        setKpiLoading(true);

        try {
            const [usersRes, auditRes, securityRes] = await Promise.all([
                apiGet('users/stats'),
                apiGet('audit/dashboard'),
                apiGet('security/dashboard'),
            ]);

            const userStats = usersRes.status === 'success' ? (usersRes.data || {}) : {};
            const auditData = auditRes.status === 'success' ? (auditRes.data || {}) : {};
            const auditStats = auditData.stats || {};
            const securityData = securityRes.status === 'success' ? (securityRes.data || {}) : {};
            const secStats = securityData.stats || {};

            setText('platAdminKpiOperators', userStats.total ?? '0');
            setText('platAdminKpiActive', userStats.active ?? '0');
            setText('platAdminKpiAudit', auditStats.today ?? '0');
            setText('platAdminKpiFailed', secStats.failed_today ?? '0');

            renderAuditRows(auditData.recent || []);

            if (usersRes.status !== 'success' && auditRes.status !== 'success') {
                throw new Error(t('plat_admin_load_error'));
            }

            setLastUpdated();
        } catch (e) {
            console.error(e);
            showError(e.message || t('load_error'));
            renderAuditRows([]);
        } finally {
            setKpiLoading(false);
        }
    }

    document.addEventListener('DOMContentLoaded', loadAdminHub);
    document.addEventListener('plat:refresh', loadAdminHub);
})();
