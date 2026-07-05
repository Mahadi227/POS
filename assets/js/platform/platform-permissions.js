(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    let allPermissions = [];
    let debounceTimer = null;

    const ROLE_I18N = {
        platform_admin: 'plat_role_platform_admin',
        support: 'plat_role_support',
    };

    const CAP_I18N = {
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

    const CAT_I18N = {
        core: 'plat_perm_cat_core',
        billing: 'plat_perm_cat_billing',
        product: 'plat_perm_cat_product',
        operations: 'plat_perm_cat_operations',
        security: 'plat_perm_cat_security',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function label(map, key) {
        const i18n = map[key];
        return i18n ? t(i18n) : (key || '—');
    }

    function showError(msg) {
        const el = document.getElementById('platPermsError');
        const text = document.getElementById('platPermsErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_perms_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platPermsError');
        if (el) el.hidden = true;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platPermsKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platPermsCount');
        if (!el) return;
        const template = t('plat_perms_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearButton() {
        const search = document.getElementById('platPermsSearch')?.value || '';
        const category = document.getElementById('platPermsCategoryFilter')?.value || '';
        const action = document.getElementById('platPermsActionFilter')?.value || '';
        const btn = document.getElementById('platPermsClearFilters');
        if (btn) btn.hidden = !search && !category && !action;
    }

    function filteredPermissions() {
        const q = document.getElementById('platPermsSearch')?.value?.trim().toLowerCase() || '';
        const category = document.getElementById('platPermsCategoryFilter')?.value || '';
        const action = document.getElementById('platPermsActionFilter')?.value || '';

        return allPermissions.filter((perm) => {
            if (category && perm.category !== category) return false;
            if (action && perm.action !== action) return false;
            if (!q) return true;
            const cap = label(CAP_I18N, perm.capability).toLowerCase();
            return (perm.key || '').toLowerCase().includes(q)
                || (perm.capability || '').toLowerCase().includes(q)
                || cap.includes(q);
        });
    }

    function renderRoleChips(roles) {
        if (!roles?.length) {
            return `<span class="plat-perms-muted">—</span>`;
        }
        return roles.map((role) => {
            const cls = role === 'support' ? ' plat-perms-role-chip--support' : '';
            return `<span class="plat-perms-role-chip${cls}">${esc(label(ROLE_I18N, role))}</span>`;
        }).join('');
    }

    function renderTable() {
        const body = document.getElementById('platPermsBody');
        const empty = document.getElementById('platPermsEmpty');
        const wrap = document.querySelector('.plat-perms-table-wrap');
        if (!body) return;

        const rows = filteredPermissions();
        updateCount(rows.length);

        if (!rows.length) {
            body.innerHTML = '';
            if (wrap) wrap.hidden = true;
            if (empty) empty.hidden = false;
            return;
        }

        if (wrap) wrap.hidden = false;
        if (empty) empty.hidden = true;

        body.innerHTML = rows.map((perm) => {
            const icon = perm.icon || 'lock';
            const actionCls = perm.action === 'manage' ? 'plat-perms-action--manage' : 'plat-perms-action--view';
            const actionLabel = perm.action === 'manage'
                ? t('plat_perms_action_manage')
                : t('plat_perms_action_view');

            return `<tr>
                <td><code class="plat-perms-key">${esc(perm.key)}</code></td>
                <td>
                    <span class="plat-perms-cap">
                        <span class="material-icons-round" aria-hidden="true">${esc(icon)}</span>
                        ${esc(label(CAP_I18N, perm.capability))}
                    </span>
                </td>
                <td><span class="plat-perms-action ${actionCls}">${esc(actionLabel)}</span></td>
                <td><span class="plat-perms-category">${esc(label(CAT_I18N, perm.category))}</span></td>
                <td><div class="plat-perms-roles">${renderRoleChips(perm.roles)}</div></td>
            </tr>`;
        }).join('');
    }

    async function loadStats() {
        const res = await apiGet('permissions/stats');
        if (res.status !== 'success') return;
        const stats = res.data || {};
        document.getElementById('platPermKpiTotal').textContent = String(stats.permissions ?? 0);
        document.getElementById('platPermKpiCats').textContent = String(stats.categories ?? 0);
        document.getElementById('platPermKpiView').textContent = String(stats.view ?? 0);
        document.getElementById('platPermKpiManage').textContent = String(stats.manage ?? 0);
    }

    async function loadCatalog() {
        hideError();
        const body = document.getElementById('platPermsBody');
        if (body) {
            body.innerHTML = `<tr><td colspan="5">
                <span class="plat-perms-loading">
                    <span class="plat-perms-spinner" aria-hidden="true"></span>
                    ${esc(t('loading'))}…
                </span>
            </td></tr>`;
        }

        const res = await apiGet('permissions/catalog');
        if (res.status !== 'success') {
            throw new Error(res.message || t('plat_perms_load_error'));
        }

        allPermissions = res.data?.permissions || [];
        renderTable();
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

    document.getElementById('platPermsSearch')?.addEventListener('input', () => {
        updateClearButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(renderTable, 200);
    });

    ['platPermsCategoryFilter', 'platPermsActionFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => {
            updateClearButton();
            renderTable();
        });
    });

    document.getElementById('platPermsClearFilters')?.addEventListener('click', () => {
        const search = document.getElementById('platPermsSearch');
        const category = document.getElementById('platPermsCategoryFilter');
        const action = document.getElementById('platPermsActionFilter');
        if (search) search.value = '';
        if (category) category.value = '';
        if (action) action.value = '';
        updateClearButton();
        renderTable();
    });

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
