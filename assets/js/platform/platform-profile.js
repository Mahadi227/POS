(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPut, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    const ACTION_I18N = {
        'platform.login_success': 'plat_audit_action_platform_login_success',
        'platform.login_failed': 'plat_audit_action_platform_login_failed',
        'platform.logout': 'plat_audit_action_platform_logout',
        'tenant.impersonate_start': 'plat_audit_action_tenant_impersonate_start',
        'tenant.impersonate_end': 'plat_audit_action_tenant_impersonate_end',
        'platform.settings_update': 'plat_audit_action_platform_settings_update',
        'platform.profile_update': 'plat_profile_action_update',
        'platform.password_change': 'plat_profile_action_password',
    };

    const ROLE_I18N = {
        platform_admin: 'plat_users_role_platform_admin',
        support: 'plat_users_role_support',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function fmt(v) {
        if (!v) return '—';
        try {
            return new Date(v).toLocaleString(cfg.locale || 'fr-FR');
        } catch (e) {
            return '—';
        }
    }

    function actionLabel(action) {
        const key = ACTION_I18N[action];
        return key ? t(key) : action;
    }

    function roleLabel(role) {
        const key = ROLE_I18N[role];
        return key ? t(key) : role;
    }

    function showError(msg) {
        const el = document.getElementById('platProfileError');
        const text = document.getElementById('platProfileErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_profile_load_error');
        el.hidden = false;
        document.getElementById('platProfileAlert').hidden = true;
    }

    function showSuccess(msg) {
        const el = document.getElementById('platProfileAlert');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        document.getElementById('platProfileError').hidden = true;
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.hidden = true; }, 4000);
    }

    function hideError() {
        const el = document.getElementById('platProfileError');
        if (el) el.hidden = true;
    }

    function renderActivity(rows) {
        const body = document.getElementById('platProfileActivityBody');
        if (!body) return;

        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="4" class="plat-gov-muted">${esc(t('plat_profile_no_activity'))}</td></tr>`;
            return;
        }

        body.innerHTML = rows.map((row) => `<tr>
            <td>${esc(fmt(row.created_at))}</td>
            <td>${esc(actionLabel(row.action || ''))}</td>
            <td>${esc(row.tenant_name || '—')}</td>
            <td>${esc(row.ip_address || '—')}</td>
        </tr>`).join('');
    }

    function renderProfile(data) {
        const name = data.name || '';
        const email = data.email || '';
        const initial = (name || email || 'P').charAt(0).toUpperCase();

        const avatar = document.getElementById('platProfileAvatar');
        if (avatar) avatar.textContent = initial;

        const meta = document.getElementById('platProfileMeta');
        if (meta) meta.textContent = `${name} · ${email}`;

        const roleEl = document.getElementById('platProfRole');
        if (roleEl) roleEl.textContent = roleLabel(data.role);

        const statusEl = document.getElementById('platProfStatus');
        if (statusEl) {
            const active = Number(data.is_active) === 1;
            const cls = active ? 'plat-profile-status-pill--active' : 'plat-profile-status-pill--inactive';
            const label = active ? t('plat_profile_status_active') : t('plat_profile_status_inactive');
            statusEl.innerHTML = `<span class="plat-profile-status-pill ${cls}">${esc(label)}</span>`;
        }

        const created = document.getElementById('platProfCreated');
        if (created) created.textContent = fmt(data.created_at);

        const lastLogin = document.getElementById('platProfLastLogin');
        if (lastLogin) lastLogin.textContent = fmt(data.last_login);

        const session = document.getElementById('platProfSession');
        if (session) session.textContent = fmt(data.session_started_at);

        const nameInput = document.getElementById('platProfName');
        const emailInput = document.getElementById('platProfEmail');
        if (nameInput) nameInput.value = name;
        if (emailInput) emailInput.value = email;

        renderActivity(data.recent_activity || []);
    }

    async function loadProfile() {
        hideError();
        try {
            const res = await apiGet('profile');
            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || t('plat_profile_load_error'));
            }
            renderProfile(res.data);
            setLastUpdated();
        } catch (e) {
            console.error(e);
            showError(e.message || t('load_error'));
            renderActivity([]);
        }
    }

    async function saveProfile(e) {
        e.preventDefault();
        const btn = document.getElementById('platProfileSaveBtn');
        const name = document.getElementById('platProfName')?.value?.trim() || '';
        const email = document.getElementById('platProfEmail')?.value?.trim() || '';

        if (btn) btn.disabled = true;
        try {
            const res = await apiPut('profile', { name, email });
            if (res.status !== 'success') {
                throw new Error(res.message || t('action_error'));
            }
            renderProfile(res.data || {});
            showSuccess(t('plat_profile_update_success'));
        } catch (err) {
            showError(err.message || t('action_error'));
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function savePassword(e) {
        e.preventDefault();
        const btn = document.getElementById('platPasswordSaveBtn');
        const current = document.getElementById('platProfCurrentPw')?.value || '';
        const next = document.getElementById('platProfNewPw')?.value || '';
        const confirm = document.getElementById('platProfConfirmPw')?.value || '';

        if (next !== confirm) {
            showError(t('plat_profile_password_mismatch'));
            return;
        }

        if (btn) btn.disabled = true;
        try {
            const res = await apiPost('profile/password', {
                current_password: current,
                new_password: next,
                confirm_password: confirm,
            });
            if (res.status !== 'success') {
                throw new Error(res.message || t('action_error'));
            }
            document.getElementById('platPasswordForm')?.reset();
            showSuccess(t('plat_profile_password_success'));
            await loadProfile();
        } catch (err) {
            showError(err.message || t('action_error'));
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadProfile();
        document.getElementById('platProfileForm')?.addEventListener('submit', saveProfile);
        document.getElementById('platPasswordForm')?.addEventListener('submit', savePassword);
    });
    document.addEventListener('plat:refresh', loadProfile);
})();
