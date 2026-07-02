(function () {
    'use strict';

    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};
    const tenantId = document.getElementById('platTenantId')?.value;
    const MODULE_KEYS = ['pos', 'inventory', 'cash_registers', 'manager', 'warehouse', 'accounting', 'api_access'];

    let detail = null;
    let plans = [];

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function badge(status) {
        return `<span class="plat-badge plat-badge--${esc(status)}">${esc(status)}</span>`;
    }

    function showMsg(text, ok) {
        const el = document.getElementById('platActionMsg');
        if (!el) return;
        el.hidden = false;
        el.textContent = text;
        el.className = 'plat-action-msg ' + (ok ? 'is-success' : 'is-error');
    }

    function renderHeader() {
        const tnt = detail?.tenant;
        if (!tnt) return;
        const sub = detail.subscription || {};
        document.getElementById('platTenantHeader').innerHTML = `
            <div class="plat-detail-title">
                <h2>${esc(tnt.name)} <code>${esc(tnt.slug)}</code></h2>
                <div class="plat-detail-meta">
                    ${badge(tnt.status)}
                    <span>${esc(t('plat_col_plan'))}: <strong>${esc(sub.plan_name || '—')}</strong></span>
                    ${sub.trial_ends_at ? `<span>${esc(t('plat_trial_ends'))}: ${esc(new Date(sub.trial_ends_at).toLocaleString())}</span>` : ''}
                </div>
            </div>
        `;
    }

    function renderUsage() {
        const sub = detail?.subscription || {};
        const usage = sub.usage || {};
        const limits = sub.limits || {};
        document.getElementById('platUsageGrid').innerHTML = `
            <div class="plat-usage-item"><span>${esc(t('plat_col_stores'))}</span><strong>${usage.stores ?? 0} / ${limits.stores ?? '∞'}</strong></div>
            <div class="plat-usage-item"><span>${esc(t('plat_col_users'))}</span><strong>${usage.users ?? 0} / ${limits.users ?? '∞'}</strong></div>
        `;
    }

    function renderModules() {
        const grid = document.getElementById('platModuleGrid');
        const modules = detail?.modules || {};
        const overrides = detail?.module_overrides || {};
        grid.innerHTML = MODULE_KEYS.map((key) => {
            const active = modules[key];
            const hasOverride = Object.prototype.hasOwnProperty.call(overrides, key);
            const overrideVal = hasOverride ? (overrides[key] ? '1' : '0') : 'inherit';
            return `
                <label class="plat-module-row">
                    <span class="plat-module-name">${esc(key)} ${active ? '✓' : '✗'}</span>
                    <select data-module="${esc(key)}" class="plat-select plat-module-select">
                        <option value="inherit" ${overrideVal === 'inherit' ? 'selected' : ''}>${esc(t('plat_module_inherit'))}</option>
                        <option value="1" ${overrideVal === '1' ? 'selected' : ''}>${esc(t('plat_module_on'))}</option>
                        <option value="0" ${overrideVal === '0' ? 'selected' : ''}>${esc(t('plat_module_off'))}</option>
                    </select>
                </label>
            `;
        }).join('');
    }

    function renderFlags() {
        const wrap = document.getElementById('platFeatureFlags');
        const flags = detail?.feature_flags || [];
        if (!flags.length) {
            wrap.innerHTML = `<p>${esc(t('plat_no_data'))}</p>`;
            return;
        }
        wrap.innerHTML = flags.map((f) => `
            <label class="plat-flag-row">
                <input type="checkbox" data-flag="${esc(f.key_name)}" ${f.enabled ? 'checked' : ''}>
                <span><strong>${esc(f.key_name)}</strong> — ${esc(f.description)}</span>
            </label>
        `).join('');
    }

    function renderBilling() {
        const body = document.getElementById('platBillingBody');
        const events = detail?.billing_events || [];
        if (!events.length) {
            body.innerHTML = `<tr><td colspan="3">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }
        body.innerHTML = events.map((e) => `
            <tr>
                <td>${esc(e.type)}</td>
                <td>${esc(e.amount)} ${esc(e.currency)}</td>
                <td>${esc(e.created_at ? new Date(e.created_at).toLocaleString() : '—')}</td>
            </tr>
        `).join('');
    }

    function renderAudit() {
        const body = document.getElementById('platAuditBody');
        const rows = detail?.audit_log || [];
        if (!rows.length) {
            body.innerHTML = `<tr><td colspan="3">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }
        body.innerHTML = rows.map((r) => `
            <tr>
                <td><code>${esc(r.action)}</code></td>
                <td>${esc(r.platform_user_name || r.platform_user_email || '—')}</td>
                <td>${esc(r.created_at ? new Date(r.created_at).toLocaleString() : '—')}</td>
            </tr>
        `).join('');
    }

    function renderUsageMetrics() {
        const grid = document.getElementById('platMetricsGrid');
        if (!grid) return;
        const report = detail?.usage;
        const items = report?.items || [];
        if (!items.length) {
            grid.innerHTML = `<p>${esc(t('plat_no_data'))}</p>`;
            return;
        }
        const period = report.period ? new Date(report.period).toLocaleDateString() : '—';
        grid.innerHTML = `<p class="plat-meta">${esc(t('plat_usage_period'))}: ${esc(period)}</p>` +
            items.map((item) => {
                const limit = item.limit != null ? item.limit : '∞';
                const warn = item.alert_100 ? ' plat-usage-item--over' : (item.alert_80 ? ' plat-usage-item--warn' : '');
                return `<div class="plat-usage-item${warn}">
                    <span>${esc(item.metric)}</span>
                    <strong>${esc(item.used)} / ${esc(limit)}</strong>
                </div>`;
            }).join('');
    }

    function renderPlans() {
        const sel = document.getElementById('platPlanSelect');
        sel.innerHTML = plans.map((p) => `<option value="${esc(p.code)}">${esc(p.name)} (${esc(p.price_monthly)} ${esc(p.currency)})</option>`).join('');
        const current = detail?.subscription?.plan_code;
        if (current) sel.value = current;
    }

    async function loadDetail() {
        const [detailRes, plansRes] = await Promise.all([
            apiGet(`tenants/${tenantId}`),
            apiGet('plans'),
        ]);
        if (detailRes.status !== 'success') throw new Error(detailRes.message || 'load');
        detail = detailRes.data;
        plans = plansRes.data || [];
        renderHeader();
        renderUsage();
        renderModules();
        renderFlags();
        renderUsageMetrics();
        renderBilling();
        renderAudit();
        renderPlans();
        setLastUpdated();
    }

    async function postAction(sub, body) {
        const res = await apiPost(`tenants/${tenantId}/${sub}`, body);
        if (res.status === 'success') {
            showMsg(t('action_success'), true);
            await loadDetail();
        } else {
            showMsg(res.message || t('action_error'), false);
        }
        return res;
    }

    document.getElementById('platBtnSuspend')?.addEventListener('click', () => {
        if (!confirm(t('plat_confirm_suspend'))) return;
        postAction('status', { status: 'suspended' });
    });
    document.getElementById('platBtnRestore')?.addEventListener('click', () => {
        postAction('status', { status: 'active' });
    });
    document.getElementById('platBtnTrial')?.addEventListener('click', () => {
        const days = parseInt(prompt(t('plat_days'), '14'), 10) || 14;
        postAction('trial', { days });
    });
    document.getElementById('platBtnPlan')?.addEventListener('click', () => {
        const planCode = document.getElementById('platPlanSelect')?.value;
        if (planCode) postAction('plan', { plan_code: planCode });
    });
    document.getElementById('platBtnImpersonate')?.addEventListener('click', async () => {
        if (!confirm(t('plat_confirm_impersonate'))) return;
        const res = await postAction('impersonate', {});
        if (res.status === 'success' && res.data?.redirect) {
            window.location.href = res.data.redirect;
        }
    });
    document.getElementById('platBtnSaveModules')?.addEventListener('click', () => {
        const overrides = {};
        document.querySelectorAll('.plat-module-select').forEach((sel) => {
            const key = sel.dataset.module;
            const val = sel.value;
            overrides[key] = val === 'inherit' ? null : val === '1';
        });
        postAction('modules', { overrides });
    });
    document.getElementById('platBtnSaveFlags')?.addEventListener('click', () => {
        const flags = {};
        document.querySelectorAll('#platFeatureFlags input[type=checkbox]').forEach((cb) => {
            flags[cb.dataset.flag] = cb.checked;
        });
        postAction('feature-flags', { flags });
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadDetail().catch(() => showMsg(t('load_error'), false));
    });
    document.addEventListener('plat:refresh', () => loadDetail().catch(() => {}));
})();
