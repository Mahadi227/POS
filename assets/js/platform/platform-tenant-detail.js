(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};
    const tenantId = document.getElementById('platTenantId')?.value;
    const MODULE_KEYS = ['pos', 'inventory', 'cash_registers', 'manager', 'warehouse', 'accounting', 'api_access'];

    const TENANT_STATUS_I18N = {
        trial: 'plat_status_trial',
        active: 'plat_status_active',
        suspended: 'plat_status_suspended',
        cancelled: 'plat_status_cancelled',
    };

    const SUB_STATUS_I18N = {
        active: 'plat_sub_status_active',
        trial: 'plat_sub_status_trial',
        past_due: 'plat_sub_status_past_due',
        cancelled: 'plat_sub_status_cancelled',
    };

    let detail = null;
    let plans = [];

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function label(map, status) {
        const key = map[status];
        return key ? t(key) : (status || '—');
    }

    function badge(status, map) {
        const safe = esc(status || 'active');
        return `<span class="plat-badge plat-badge--${safe}">${esc(label(map, status))}</span>`;
    }

    function formatDate(value) {
        if (!value) return '—';
        try {
            return new Date(value).toLocaleString(cfg.locale || undefined);
        } catch (e) {
            return '—';
        }
    }

    function showError(msg) {
        const el = document.getElementById('platCompanyError');
        const text = document.getElementById('platCompanyErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_company_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platCompanyError');
        if (el) el.hidden = true;
    }

    function showAlert(msg) {
        const el = document.getElementById('platCompanyAlert');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => {
            el.hidden = true;
        }, 3500);
    }

    function setActionsDisabled(disabled) {
        document.querySelectorAll('.plat-company-actions button, .plat-company-actions select').forEach((el) => {
            el.disabled = disabled;
        });
    }

    function renderHeader() {
        const header = document.getElementById('platTenantHeader');
        const tnt = detail?.tenant;
        if (!header || !tnt) return;

        const sub = detail.subscription || {};
        const subStatus = sub.subscription_status || sub.status;

        header.innerHTML = `
            <div class="plat-detail-title">
                <div class="plat-company-badge">
                    <span class="material-icons-round" aria-hidden="true">business</span>
                    ${esc(t('plat_company_badge'))}
                </div>
                <h2>${esc(tnt.name)} <code>${esc(tnt.slug)}</code></h2>
                <div class="plat-detail-meta">
                    ${badge(tnt.status, TENANT_STATUS_I18N)}
                    ${subStatus ? badge(subStatus, SUB_STATUS_I18N) : ''}
                    <span>${esc(t('plat_col_plan'))}: <strong>${esc(sub.plan_name || sub.plan_code || '—')}</strong></span>
                    ${sub.trial_ends_at ? `<span>${esc(t('plat_trial_ends'))}: <strong>${esc(formatDate(sub.trial_ends_at))}</strong></span>` : ''}
                    <span>${esc(t('plat_col_users'))}: <strong>${esc(String(tnt.user_count ?? '—'))}</strong></span>
                </div>
            </div>
        `;
    }

    function renderUsage() {
        const sub = detail?.subscription || {};
        const usage = sub.usage || {};
        const limits = sub.limits || {};
        document.getElementById('platUsageGrid').innerHTML = `
            <div class="plat-usage-item">
                <span>${esc(t('plat_col_stores'))}</span>
                <strong>${esc(String(usage.stores ?? 0))} / ${esc(String(limits.stores ?? '∞'))}</strong>
            </div>
            <div class="plat-usage-item">
                <span>${esc(t('plat_col_users'))}</span>
                <strong>${esc(String(usage.users ?? 0))} / ${esc(String(limits.users ?? '∞'))}</strong>
            </div>
        `;
    }

    function renderStores() {
        const wrap = document.getElementById('platStoresList');
        if (!wrap) return;
        const stores = detail?.stores || [];
        if (!stores.length) {
            wrap.innerHTML = `<p class="plat-company-muted">${esc(t('plat_no_data'))}</p>`;
            return;
        }
        wrap.innerHTML = stores.map((s) => `
            <div class="plat-company-store-row">
                <strong>${esc(s.name || '—')}</strong>
                <span class="plat-company-muted">${esc(s.currency || '')}</span>
            </div>
        `).join('');
    }

    function renderModules() {
        const grid = document.getElementById('platModuleGrid');
        const modules = detail?.modules || {};
        const overrides = detail?.module_overrides || {};
        grid.innerHTML = MODULE_KEYS.map((key) => {
            const active = modules[key];
            const hasOverride = Object.prototype.hasOwnProperty.call(overrides, key);
            const overrideVal = hasOverride ? (overrides[key] ? '1' : '0') : 'inherit';
            const statusCls = active ? 'is-on' : 'is-off';
            const statusText = active ? 'ON' : 'OFF';
            return `
                <label class="plat-module-row">
                    <span class="plat-module-name">
                        ${esc(key)}
                        <span class="plat-module-status ${statusCls}">${statusText}</span>
                    </span>
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
            wrap.innerHTML = `<p class="plat-company-muted">${esc(t('plat_no_data'))}</p>`;
            return;
        }
        wrap.innerHTML = flags.map((f) => `
            <label class="plat-flag-row">
                <span><strong>${esc(f.key_name)}</strong> — ${esc(f.description)}</span>
                <input type="checkbox" data-flag="${esc(f.key_name)}" ${f.enabled ? 'checked' : ''}>
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
                <td>${esc(formatDate(e.created_at))}</td>
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
                <td>${esc(formatDate(r.created_at))}</td>
            </tr>
        `).join('');
    }

    function renderUsageMetrics() {
        const grid = document.getElementById('platMetricsGrid');
        if (!grid) return;
        const report = detail?.usage;
        const items = report?.items || [];
        if (!items.length) {
            grid.innerHTML = `<p class="plat-company-muted">${esc(t('plat_no_data'))}</p>`;
            return;
        }
        const period = report.period ? new Date(report.period).toLocaleDateString(cfg.locale || undefined) : '—';
        grid.innerHTML = `<p class="plat-company-muted">${esc(t('plat_usage_period'))}: ${esc(period)}</p>`
            + items.map((item) => {
                const limit = item.limit != null ? item.limit : '∞';
                const warn = item.alert_100 ? ' plat-usage-item--over' : (item.alert_80 ? ' plat-usage-item--warn' : '');
                return `<div class="plat-usage-item${warn}">
                    <span>${esc(item.metric)}</span>
                    <strong>${esc(String(item.used))} / ${esc(String(limit))}</strong>
                </div>`;
            }).join('');
    }

    function renderPlans() {
        const sel = document.getElementById('platPlanSelect');
        if (!sel) return;
        sel.innerHTML = plans.map((p) =>
            `<option value="${esc(p.code)}">${esc(p.name)} (${esc(p.price_monthly)} ${esc(p.currency)})</option>`
        ).join('');
        const current = detail?.subscription?.plan_code;
        if (current) sel.value = current;
    }

    async function loadDetail() {
        if (!tenantId) return;
        hideError();

        const [detailRes, plansRes] = await Promise.all([
            apiGet(`tenants/${tenantId}`),
            apiGet('plans'),
        ]);

        if (detailRes.status !== 'success') {
            throw new Error(detailRes.message || t('plat_company_load_error'));
        }

        detail = detailRes.data;
        plans = plansRes.data || [];
        renderHeader();
        renderUsage();
        renderStores();
        renderModules();
        renderFlags();
        renderUsageMetrics();
        renderBilling();
        renderAudit();
        renderPlans();
        setLastUpdated?.();
    }

    async function postAction(sub, body) {
        setActionsDisabled(true);
        try {
            const res = await apiPost(`tenants/${tenantId}/${sub}`, body);
            if (res.status === 'success') {
                showAlert(t('action_success'));
                await loadDetail();
            } else {
                showError(res.message || t('action_error'));
            }
            return res;
        } catch (e) {
            showError(e.message || t('action_error'));
            return { status: 'error' };
        } finally {
            setActionsDisabled(false);
        }
    }

    document.getElementById('platBtnSuspend')?.addEventListener('click', () => {
        if (!window.confirm(t('plat_confirm_suspend'))) return;
        postAction('status', { status: 'suspended' });
    });
    document.getElementById('platBtnRestore')?.addEventListener('click', () => {
        postAction('status', { status: 'active' });
    });
    document.getElementById('platBtnTrial')?.addEventListener('click', () => {
        const days = parseInt(window.prompt(t('plat_days'), '14'), 10) || 14;
        postAction('trial', { days });
    });
    document.getElementById('platBtnPlan')?.addEventListener('click', () => {
        const planCode = document.getElementById('platPlanSelect')?.value;
        if (planCode) postAction('plan', { plan_code: planCode });
    });
    document.getElementById('platBtnImpersonate')?.addEventListener('click', async () => {
        if (!window.confirm(t('plat_confirm_impersonate'))) return;
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
        loadDetail().catch((e) => showError(e.message || t('load_error')));
    });
    document.addEventListener('plat:refresh', () => {
        loadDetail().catch(() => {});
    });
})();
