(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    let debounceTimer = null;
    let currentUserId = 0;

    const ROLE_I18N = {
        platform_admin: 'plat_users_role_platform_admin',
        support: 'plat_users_role_support',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function roleLabel(role) {
        const key = ROLE_I18N[role];
        return key ? t(key) : (role || '—');
    }

    function formatDateTime(value) {
        if (!value) return '—';
        try {
            return new Date(value).toLocaleString(cfg.locale || undefined);
        } catch (e) {
            return '—';
        }
    }

    function showError(msg) {
        const el = document.getElementById('platUsersError');
        const text = document.getElementById('platUsersErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_users_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platUsersError');
        if (el) el.hidden = true;
    }

    function showAlert(msg) {
        const el = document.getElementById('platUsersAlert');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => { el.hidden = true; }, 3500);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platUsersKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platUsersCount');
        if (!el) return;
        const template = t('plat_users_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearButton() {
        const search = document.getElementById('platUsersSearch')?.value || '';
        const role = document.getElementById('platUsersRoleFilter')?.value || '';
        const active = document.getElementById('platUsersActiveFilter')?.value || '';
        const btn = document.getElementById('platUsersClearFilters');
        if (btn) btn.hidden = !search && !role && !active;
    }

    function listQuery() {
        const params = new URLSearchParams();
        const q = document.getElementById('platUsersSearch')?.value?.trim();
        const role = document.getElementById('platUsersRoleFilter')?.value;
        const active = document.getElementById('platUsersActiveFilter')?.value;
        if (q) params.set('q', q);
        if (role) params.set('role', role);
        if (active) params.set('active', active);
        params.set('per_page', '50');
        const qs = params.toString();
        return qs ? `users?${qs}` : 'users?per_page=50';
    }

    function statusBadge(isActive) {
        const active = Number(isActive) === 1;
        const cls = active ? 'plat-users-status--active' : 'plat-users-status--inactive';
        const label = active ? t('plat_users_status_active') : t('plat_users_status_inactive');
        return `<span class="plat-users-status ${cls}">${esc(label)}</span>`;
    }

    function roleBadge(role) {
        const cls = role === 'platform_admin' ? ' plat-users-role--admin' : ' plat-users-role--support';
        return `<span class="plat-users-role${cls}">${esc(roleLabel(role))}</span>`;
    }

    async function toggleActive(id, isActive) {
        const active = Number(isActive) === 1;
        const confirmKey = active ? 'plat_users_confirm_deactivate' : 'plat_users_confirm_activate';
        if (!window.confirm(t(confirmKey))) return;

        const res = await apiPost(`users/${id}/toggle-active`, {});
        if (res.status === 'success') {
            showAlert(t('action_success'));
            await refresh();
        } else {
            showError(res.message || t('action_error'));
        }
    }

    async function loadStats() {
        const res = await apiGet('users/stats');
        if (res.status !== 'success') return;
        const stats = res.data || {};
        document.getElementById('platUsrKpiTotal').textContent = String(stats.total ?? 0);
        document.getElementById('platUsrKpiActive').textContent = String(stats.active ?? 0);
        document.getElementById('platUsrKpiInactive').textContent = String(stats.inactive ?? 0);
        document.getElementById('platUsrKpiAdmins').textContent = String(stats.platform_admin ?? 0);
    }

    async function loadUsers() {
        hideError();
        const body = document.getElementById('platUsersBody');
        const empty = document.getElementById('platUsersEmpty');
        const wrap = document.querySelector('.plat-users-table-wrap');
        if (!body) return;

        body.innerHTML = `<tr class="plat-users-loading-row"><td colspan="7">
            <span class="plat-users-loading">
                <span class="plat-users-spinner" aria-hidden="true"></span>
                ${esc(t('loading'))}…
            </span>
        </td></tr>`;
        if (empty) empty.hidden = true;
        if (wrap) wrap.hidden = false;

        const res = await apiGet(listQuery());
        if (res.status !== 'success') {
            throw new Error(res.message || t('plat_users_load_error'));
        }

        if (res.meta && res.meta.current_user_id) {
            currentUserId = Number(res.meta.current_user_id) || 0;
        }

        const rows = res.data || [];
        updateCount(rows.length);

        if (!rows.length) {
            body.innerHTML = '';
            if (wrap) wrap.hidden = true;
            if (empty) empty.hidden = false;
            return;
        }

        body.innerHTML = rows.map((row) => {
            const isSelf = currentUserId > 0 && Number(row.id) === currentUserId;
            const isActive = Number(row.is_active) === 1;
            const canToggle = !isSelf;
            const toggleCls = isActive ? 'plat-users-action-btn--danger' : 'plat-users-action-btn--primary';
            const toggleLabel = isActive ? t('plat_users_deactivate') : t('plat_users_activate');

            return `<tr>
                <td>
                    <strong>${esc(row.name || '—')}</strong>
                    ${isSelf ? `<span class="plat-users-you">${esc(t('plat_users_you'))}</span>` : ''}
                </td>
                <td>${esc(row.email || '—')}</td>
                <td>${roleBadge(row.role)}</td>
                <td>${statusBadge(row.is_active)}</td>
                <td>${esc(formatDateTime(row.last_login))}</td>
                <td>${esc(formatDateTime(row.created_at))}</td>
                <td>
                    <div class="plat-users-actions">
                        ${canToggle ? `<button type="button" class="plat-users-action-btn ${toggleCls} plat-users-toggle-btn" data-id="${esc(String(row.id))}" data-active="${isActive ? '1' : '0'}">
                            ${esc(toggleLabel)}
                        </button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');

        body.querySelectorAll('.plat-users-toggle-btn').forEach((btn) => {
            btn.addEventListener('click', () => toggleActive(btn.dataset.id, btn.dataset.active));
        });
    }

    function openAddDialog() {
        const dialog = document.getElementById('platUsersAddDialog');
        const name = document.getElementById('platUsrAddName');
        const email = document.getElementById('platUsrAddEmail');
        const password = document.getElementById('platUsrAddPassword');
        const role = document.getElementById('platUsrAddRole');
        if (name) name.value = '';
        if (email) email.value = '';
        if (password) password.value = '';
        if (role) role.value = 'platform_admin';
        dialog?.showModal();
    }

    function closeAddDialog() {
        document.getElementById('platUsersAddDialog')?.close();
    }

    async function submitAddUser(e) {
        e.preventDefault();
        const name = document.getElementById('platUsrAddName')?.value?.trim();
        const email = document.getElementById('platUsrAddEmail')?.value?.trim();
        const password = document.getElementById('platUsrAddPassword')?.value || '';
        const role = document.getElementById('platUsrAddRole')?.value || 'platform_admin';

        const res = await apiPost('users', { name, email, password, role });
        if (res.status === 'success') {
            closeAddDialog();
            showAlert(t('action_success'));
            await refresh();
        } else {
            showError(res.message || t('action_error'));
        }
    }

    async function refresh() {
        setKpiLoading(true);
        try {
            await Promise.all([loadStats(), loadUsers()]);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    document.getElementById('platUsersSearch')?.addEventListener('input', () => {
        updateClearButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadUsers().catch((e) => showError(e.message)), 300);
    });

    ['platUsersRoleFilter', 'platUsersActiveFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => {
            updateClearButton();
            loadUsers().catch((e) => showError(e.message));
        });
    });

    document.getElementById('platUsersClearFilters')?.addEventListener('click', () => {
        const search = document.getElementById('platUsersSearch');
        const role = document.getElementById('platUsersRoleFilter');
        const active = document.getElementById('platUsersActiveFilter');
        if (search) search.value = '';
        if (role) role.value = '';
        if (active) active.value = '';
        updateClearButton();
        loadUsers().catch((e) => showError(e.message));
    });

    document.getElementById('platUsersAddOpen')?.addEventListener('click', openAddDialog);
    document.getElementById('platUsersAddClose')?.addEventListener('click', closeAddDialog);
    document.getElementById('platUsersAddCancel')?.addEventListener('click', closeAddDialog);
    document.getElementById('platUsersAddForm')?.addEventListener('submit', (e) => {
        submitAddUser(e).catch((err) => showError(err.message));
    });

    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const role = params.get('role');
        if (role) {
            const roleFilter = document.getElementById('platUsersRoleFilter');
            if (roleFilter) roleFilter.value = role;
            updateClearButton();
        }
        refresh();
    });
    document.addEventListener('plat:refresh', refresh);
})();
