(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    let allRoles = [];
    let matrix = [];
    let search = '';

    const ROLE_I18N = {
        platform_admin: 'plat_role_platform_admin',
        support: 'plat_role_support',
    };

    const ROLE_DESC_I18N = {
        platform_admin: 'plat_role_desc_platform_admin',
        support: 'plat_role_desc_support',
    };

    const PERM_I18N = {
        organizations: 'plat_perm_organizations',
        subscriptions: 'plat_perm_subscriptions',
        billing: 'plat_perm_billing',
        payments: 'plat_perm_payments',
        licenses: 'plat_perm_licenses',
        domains: 'plat_perm_domains',
        marketplace: 'plat_perm_marketplace',
        modules: 'plat_perm_modules',
        monitoring: 'plat_perm_monitoring',
        incidents: 'plat_perm_incidents',
        users: 'plat_perm_users',
        impersonation: 'plat_perm_impersonation',
        audit: 'plat_perm_audit',
        settings: 'plat_perm_settings',
    };

    const ACCESS_ICON = {
        full: 'check_circle',
        view: 'visibility',
        none: 'remove',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function roleLabel(key) {
        const i18n = ROLE_I18N[key];
        return i18n ? t(i18n) : key;
    }

    function roleDesc(key) {
        const i18n = ROLE_DESC_I18N[key];
        return i18n ? t(i18n) : '';
    }

    function permLabel(key) {
        const i18n = PERM_I18N[key];
        return i18n ? t(i18n) : key;
    }

    function accessTitle(level) {
        if (level === 'full') return t('plat_roles_access_full');
        if (level === 'view') return t('plat_roles_access_view');
        return t('plat_roles_access_none');
    }

    function scopeLabel(scope) {
        return scope === 'full' ? t('plat_roles_scope_full') : t('plat_roles_scope_limited');
    }

    function usersHref(roleKey) {
        return `../users/index.php?role=${encodeURIComponent(roleKey)}`;
    }

    function showError(msg) {
        const el = document.getElementById('platRolesError');
        const text = document.getElementById('platRolesErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_roles_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platRolesError');
        if (el) el.hidden = true;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platRolesKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platRolesCount');
        if (!el) return;
        const template = t('plat_roles_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function filteredRoles() {
        const q = search.trim().toLowerCase();
        if (!q) return allRoles;
        return allRoles.filter((role) => {
            const label = roleLabel(role.key).toLowerCase();
            const desc = roleDesc(role.key).toLowerCase();
            return role.key.toLowerCase().includes(q) || label.includes(q) || desc.includes(q);
        });
    }

    function renderRoleCard(role) {
        const cardCls = role.key === 'platform_admin' ? 'plat-role-card--admin' : 'plat-role-card--support';
        const scopeCls = role.scope === 'full' ? 'plat-role-card__scope--full' : 'plat-role-card__scope--limited';
        const icon = role.icon || 'badge';

        return `
            <article class="plat-role-card ${cardCls}">
                <div class="plat-role-card__head">
                    <div class="plat-role-card__icon" aria-hidden="true">
                        <span class="material-icons-round">${esc(icon)}</span>
                    </div>
                    <div>
                        <h3 class="plat-role-card__title">${esc(roleLabel(role.key))}</h3>
                        <span class="plat-role-card__key">${esc(role.key)}</span>
                    </div>
                </div>
                <p class="plat-role-card__desc">${esc(roleDesc(role.key))}</p>
                <span class="plat-role-card__scope ${scopeCls}">${esc(scopeLabel(role.scope))}</span>
                <div class="plat-role-card__stats">
                    <div class="plat-role-stat">
                        <strong>${esc(String(role.users_total ?? 0))}</strong>
                        <span>${esc(t('plat_roles_users_assigned'))}</span>
                    </div>
                    <div class="plat-role-stat">
                        <strong>${esc(String(role.users_active ?? 0))}</strong>
                        <span>${esc(t('plat_roles_users_active'))}</span>
                    </div>
                </div>
                <a class="plat-role-card__link" href="${usersHref(role.key)}">
                    ${esc(t('plat_roles_view_users'))}
                    <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
                </a>
            </article>
        `;
    }

    function renderGrid() {
        const grid = document.getElementById('platRolesGrid');
        const empty = document.getElementById('platRolesEmpty');
        if (!grid) return;

        const rows = filteredRoles();
        updateCount(rows.length);

        if (!rows.length) {
            grid.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }

        if (empty) empty.hidden = true;
        grid.innerHTML = rows.map(renderRoleCard).join('');
    }

    function renderMatrix() {
        const head = document.getElementById('platRolesMatrixHead');
        const body = document.getElementById('platRolesMatrixBody');
        if (!head || !body || !allRoles.length) return;

        head.innerHTML = `<tr>
            <th>${esc(t('plat_roles_matrix_capability'))}</th>
            ${allRoles.map((role) => `<th>${esc(roleLabel(role.key))}</th>`).join('')}
        </tr>`;

        const filtered = matrix.filter((row) => {
            const q = search.trim().toLowerCase();
            if (!q) return true;
            const label = permLabel(row.key).toLowerCase();
            return row.key.toLowerCase().includes(q) || label.includes(q);
        });

        if (!filtered.length) {
            body.innerHTML = `<tr><td colspan="${allRoles.length + 1}">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }

        body.innerHTML = filtered.map((row) => {
            const cells = allRoles.map((role) => {
                const level = row.access?.[role.key] || 'none';
                const icon = ACCESS_ICON[level] || 'remove';
                return `<td>
                    <span class="plat-roles-access plat-roles-access--${esc(level)}" title="${esc(accessTitle(level))}">
                        <span class="material-icons-round" aria-hidden="true">${esc(icon)}</span>
                        <span class="sr-only">${esc(accessTitle(level))}</span>
                    </span>
                </td>`;
            }).join('');

            return `<tr>
                <td>${esc(permLabel(row.key))}</td>
                ${cells}
            </tr>`;
        }).join('');
    }

    async function loadStats() {
        const res = await apiGet('roles/stats');
        if (res.status !== 'success') return;
        const stats = res.data || {};
        document.getElementById('platRoleKpiRoles').textContent = String(stats.roles ?? 0);
        document.getElementById('platRoleKpiPerms').textContent = String(stats.permissions ?? 0);
        document.getElementById('platRoleKpiUsers').textContent = String(stats.users ?? 0);
        document.getElementById('platRoleKpiActive').textContent = String(stats.active_users ?? 0);
    }

    async function loadCatalog() {
        hideError();
        const grid = document.getElementById('platRolesGrid');
        if (grid) {
            grid.innerHTML = `
                <div class="plat-roles-loading">
                    <span class="plat-roles-spinner" aria-hidden="true"></span>
                    ${esc(t('loading'))}…
                </div>
            `;
        }

        const res = await apiGet('roles/catalog');
        if (res.status !== 'success') {
            throw new Error(res.message || t('plat_roles_load_error'));
        }

        allRoles = res.data?.roles || [];
        matrix = res.data?.matrix || [];
        renderGrid();
        renderMatrix();
    }

    async function refresh() {
        setKpiLoading(true);
        try {
            await Promise.all([loadStats(), loadCatalog()]);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    document.getElementById('platRolesSearch')?.addEventListener('input', (e) => {
        search = e.target.value || '';
        const btn = document.getElementById('platRolesClearSearch');
        if (btn) btn.hidden = !search;
        renderGrid();
        renderMatrix();
    });

    document.getElementById('platRolesClearSearch')?.addEventListener('click', () => {
        search = '';
        const input = document.getElementById('platRolesSearch');
        if (input) input.value = '';
        const btn = document.getElementById('platRolesClearSearch');
        if (btn) btn.hidden = true;
        renderGrid();
        renderMatrix();
    });

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
