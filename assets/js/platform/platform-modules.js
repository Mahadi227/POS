(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    let allModules = [];
    let search = '';

    const MODULE_I18N = {
        pos: 'plat_mod_pos',
        inventory: 'plat_mod_inventory',
        cash_registers: 'plat_mod_cash_registers',
        manager: 'plat_mod_manager',
        warehouse: 'plat_mod_warehouse',
        accounting: 'plat_mod_accounting',
        api_access: 'plat_mod_api_access',
        white_label: 'plat_mod_white_label',
    };

    const WORKSPACE_I18N = {
        cashier: 'plat_mod_ws_cashier',
        admin: 'plat_mod_ws_admin',
        cash_registers: 'plat_mod_ws_cash_registers',
        manager: 'plat_mod_ws_manager',
        warehouse: 'plat_mod_ws_warehouse',
        accounting: 'plat_mod_ws_accounting',
        api: 'plat_mod_ws_api',
        branding: 'plat_mod_ws_branding',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function moduleLabel(key) {
        const i18n = MODULE_I18N[key];
        return i18n ? t(i18n) : key;
    }

    function workspaceLabel(ws) {
        const i18n = WORKSPACE_I18N[ws];
        return i18n ? t(i18n) : ws;
    }

    function showError(msg) {
        const el = document.getElementById('platModulesError');
        const text = document.getElementById('platModulesErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_modules_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platModulesError');
        if (el) el.hidden = true;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platModulesKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platModulesCount');
        if (!el) return;
        const template = t('plat_modules_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function filteredModules() {
        const q = search.trim().toLowerCase();
        if (!q) return allModules;
        return allModules.filter((mod) => {
            const label = moduleLabel(mod.key).toLowerCase();
            return mod.key.toLowerCase().includes(q) || label.includes(q) || (mod.workspace || '').includes(q);
        });
    }

    function renderPlanChips(plans) {
        if (!plans?.length) {
            return `<span class="plat-modules-muted">${esc(t('plat_no_data'))}</span>`;
        }
        return plans.map((p) => {
            const cls = p.included ? 'is-included' : 'is-excluded';
            const title = p.included ? t('plat_modules_plan_included') : t('plat_modules_plan_excluded');
            return `<span class="plat-module-plan-chip ${cls}" title="${esc(title)}">${esc(p.name || p.code)}</span>`;
        }).join('');
    }

    function renderModuleCard(mod) {
        const icon = mod.icon || 'extension';
        return `
            <article class="plat-module-card">
                <div class="plat-module-card__head">
                    <div class="plat-module-card__icon" aria-hidden="true">
                        <span class="material-icons-round">${esc(icon)}</span>
                    </div>
                    <div>
                        <h3 class="plat-module-card__title">${esc(moduleLabel(mod.key))}</h3>
                        <span class="plat-module-card__key">${esc(mod.key)}</span>
                    </div>
                </div>
                <span class="plat-module-card__workspace">
                    <span class="material-icons-round" aria-hidden="true">open_in_new</span>
                    ${esc(workspaceLabel(mod.workspace))}
                </span>
                <div class="plat-module-card__plans" aria-label="${esc(t('plat_modules_col_plans'))}">
                    ${renderPlanChips(mod.plans)}
                </div>
                <div class="plat-module-card__stats">
                    <div class="plat-module-stat">
                        <strong>${esc(String(mod.tenants_on_plan ?? 0))}</strong>
                        <span>${esc(t('plat_modules_tenants_plan'))}</span>
                    </div>
                    <div class="plat-module-stat">
                        <strong>${esc(String(mod.overrides_on ?? 0))}</strong>
                        <span>${esc(t('plat_modules_overrides_on'))}</span>
                    </div>
                    <div class="plat-module-stat">
                        <strong>${esc(String(mod.overrides_off ?? 0))}</strong>
                        <span>${esc(t('plat_modules_overrides_off'))}</span>
                    </div>
                </div>
            </article>
        `;
    }

    function renderGrid() {
        const grid = document.getElementById('platModulesGrid');
        const empty = document.getElementById('platModulesEmpty');
        if (!grid) return;

        const rows = filteredModules();
        updateCount(rows.length);

        if (!rows.length) {
            grid.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }

        if (empty) empty.hidden = true;
        grid.innerHTML = rows.map(renderModuleCard).join('');
    }

    async function loadStats() {
        const res = await apiGet('modules/stats');
        if (res.status !== 'success') return;
        const stats = res.data || {};
        document.getElementById('platModKpiTotal').textContent = String(stats.modules ?? 0);
        document.getElementById('platModKpiPlans').textContent = String(stats.plans ?? 0);
        document.getElementById('platModKpiOverrides').textContent = String(stats.overrides ?? 0);
        document.getElementById('platModKpiTenants').textContent = String(stats.tenants ?? 0);
    }

    async function loadCatalog() {
        hideError();
        const grid = document.getElementById('platModulesGrid');
        if (grid) {
            grid.innerHTML = `
                <div class="plat-modules-loading">
                    <span class="plat-modules-spinner" aria-hidden="true"></span>
                    ${esc(t('loading'))}…
                </div>
            `;
        }

        const res = await apiGet('modules/catalog');
        if (res.status !== 'success') {
            throw new Error(res.message || t('plat_modules_load_error'));
        }

        allModules = res.data?.modules || [];
        renderGrid();
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

    document.getElementById('platModulesSearch')?.addEventListener('input', (e) => {
        search = e.target.value || '';
        const btn = document.getElementById('platModulesClearSearch');
        if (btn) btn.hidden = !search;
        renderGrid();
    });

    document.getElementById('platModulesClearSearch')?.addEventListener('click', () => {
        search = '';
        const input = document.getElementById('platModulesSearch');
        if (input) input.value = '';
        const btn = document.getElementById('platModulesClearSearch');
        if (btn) btn.hidden = true;
        renderGrid();
    });

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
