(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    let plans = [];

    const SUB_STATUS_I18N = {
        active: 'plat_sub_status_active',
        trial: 'plat_sub_status_trial',
        past_due: 'plat_sub_status_past_due',
        cancelled: 'plat_sub_status_cancelled',
    };

    const PROVIDER_I18N = {
        manual: 'plat_sub_provider_manual',
        stripe: 'plat_sub_provider_stripe',
        paystack: 'plat_sub_provider_paystack',
        mobile_money: 'plat_sub_provider_mobile',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function subStatusLabel(status) {
        const key = SUB_STATUS_I18N[status];
        return key ? t(key) : (status || '—');
    }

    function providerLabel(provider) {
        if (!provider) return '—';
        const key = PROVIDER_I18N[provider];
        return key ? t(key) : provider;
    }

    function formatMoney(amount, currency) {
        const n = Number(amount);
        if (Number.isNaN(n)) return '—';
        try {
            return new Intl.NumberFormat(cfg.locale || undefined, {
                style: 'currency',
                currency: currency || 'USD',
                maximumFractionDigits: 0,
            }).format(n);
        } catch (e) {
            return `${n} ${currency || ''}`.trim();
        }
    }

    function formatPeriod(start, end) {
        if (!start && !end) return '—';
        const fmt = (v) => {
            if (!v) return '—';
            try {
                return new Date(v).toLocaleDateString(cfg.locale || undefined);
            } catch (e) {
                return v;
            }
        };
        return `${fmt(start)} → ${fmt(end)}`;
    }

    function badge(status) {
        const safe = esc(status || 'active');
        return `<span class="plat-badge plat-badge--${safe}">${esc(subStatusLabel(status))}</span>`;
    }

    function showError(msg) {
        const el = document.getElementById('platSubsError');
        const text = document.getElementById('platSubsErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_subscriptions_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platSubsError');
        if (el) el.hidden = true;
    }

    function showAlert(msg) {
        const el = document.getElementById('platSubsAlert');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => {
            el.hidden = true;
        }, 3500);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platSubsKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function planOptions(selected) {
        if (!plans.length) {
            return `<option value="">—</option>`;
        }
        return plans.map((p) => {
            const code = p.code || '';
            const selectedAttr = code === selected ? ' selected' : '';
            return `<option value="${esc(code)}"${selectedAttr}>${esc(p.name || code)}</option>`;
        }).join('');
    }

    function buildQuery() {
        const q = document.getElementById('platSubsSearch')?.value?.trim() || '';
        const subStatus = document.getElementById('platSubsStatusFilter')?.value || '';
        const plan = document.getElementById('platSubsPlanFilter')?.value || '';
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (subStatus) params.set('sub_status', subStatus);
        if (plan) params.set('plan', plan);
        const qs = params.toString();
        return qs ? `subscriptions?${qs}` : 'subscriptions';
    }

    function hasActiveFilters() {
        return Boolean(
            document.getElementById('platSubsSearch')?.value?.trim()
            || document.getElementById('platSubsStatusFilter')?.value
            || document.getElementById('platSubsPlanFilter')?.value
        );
    }

    function updateClearButton() {
        const btn = document.getElementById('platSubsClearFilters');
        if (btn) btn.hidden = !hasActiveFilters();
    }

    async function loadPlans() {
        const res = await apiGet('plans');
        if (res.status === 'success') {
            plans = res.data || [];
            const filter = document.getElementById('platSubsPlanFilter');
            if (filter) {
                const current = filter.value;
                filter.innerHTML = `<option value="">${esc(t('plat_sub_filter_plan_all'))}</option>`
                    + plans.map((p) => `<option value="${esc(p.code)}">${esc(p.name || p.code)}</option>`).join('');
                filter.value = current;
            }
        }
    }

    async function loadStats() {
        setKpiLoading(true);
        try {
            const res = await apiGet('subscriptions/stats');
            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || t('plat_subscriptions_load_error'));
            }
            const d = res.data;
            document.getElementById('platSubKpiTotal').textContent = String(d.total ?? 0);
            document.getElementById('platSubKpiActive').textContent = String(d.active ?? 0);
            document.getElementById('platSubKpiTrial').textContent = String(d.trial ?? 0);
            document.getElementById('platSubKpiMrr').textContent = formatMoney(d.mrr, 'USD');
        } catch (e) {
            console.error(e);
        } finally {
            setKpiLoading(false);
        }
    }

    async function loadSubscriptions() {
        const body = document.getElementById('platSubsBody');
        const wrap = document.querySelector('.plat-subs-table-wrap');
        const empty = document.getElementById('platSubsEmpty');
        const countEl = document.getElementById('platSubsCount');
        if (!body) return;

        hideError();
        updateClearButton();
        body.innerHTML = `<tr class="plat-subs-loading-row"><td colspan="7">
            <span class="plat-subs-loading"><span class="plat-subs-spinner"></span>${esc(t('loading'))}…</span>
        </td></tr>`;
        if (wrap) wrap.classList.remove('is-empty');
        if (empty) empty.hidden = true;

        try {
            const res = await apiGet(buildQuery());
            if (res.status !== 'success') {
                throw new Error(res.message || t('plat_subscriptions_load_error'));
            }

            const rows = res.data || [];
            const template = t('plat_subscriptions_count');
            if (countEl) {
                countEl.textContent = template.includes('%d')
                    ? template.replace('%d', String(rows.length))
                    : `${rows.length} ${template}`;
            }

            if (!rows.length) {
                body.innerHTML = '';
                if (wrap) wrap.classList.add('is-empty');
                if (empty) empty.hidden = false;
                setLastUpdated?.();
                return;
            }

            body.innerHTML = rows.map((row) => {
                const tenantId = row.tenant_id;
                const viewHref = `../companies/view.php?id=${encodeURIComponent(tenantId)}`;
                const planCode = row.plan_code || '';
                const price = row.price_monthly != null
                    ? `<br><small>${esc(formatMoney(row.price_monthly, row.currency))}</small>`
                    : '';
                return `<tr data-tenant-id="${esc(String(tenantId))}">
                    <td>
                        <a href="${viewHref}"><strong>${esc(row.name)}</strong></a>
                        <br><code>${esc(row.slug)}</code>
                    </td>
                    <td>${esc(row.plan_name || planCode || '—')}${price}</td>
                    <td>${badge(row.subscription_status)}</td>
                    <td><small>${esc(formatPeriod(row.current_period_start, row.current_period_end))}</small></td>
                    <td>${esc(providerLabel(row.payment_provider))}</td>
                    <td>
                        <form class="plat-sub-plan-form" data-tenant-id="${esc(String(tenantId))}">
                            <select aria-label="${esc(t('plat_sub_change_plan'))}">${planOptions(planCode)}</select>
                            <button type="submit" class="plat-sub-plan-btn" title="${esc(t('plat_sub_apply_plan'))}">
                                <span class="material-icons-round" aria-hidden="true">check</span>
                            </button>
                        </form>
                    </td>
                    <td class="plat-col-action">
                        <a class="plat-subs-view-btn" href="${viewHref}">
                            ${esc(t('plat_view_detail'))}
                            <span class="material-icons-round" aria-hidden="true">chevron_right</span>
                        </a>
                    </td>
                </tr>`;
            }).join('');

            body.querySelectorAll('.plat-sub-plan-form').forEach((form) => {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const tenantId = form.dataset.tenantId;
                    const planCode = form.querySelector('select')?.value;
                    const btn = form.querySelector('button');
                    if (!tenantId || !planCode || !btn) return;
                    btn.disabled = true;
                    try {
                        const result = await apiPost(`subscriptions/${tenantId}/plan`, { plan_code: planCode });
                        if (result.status !== 'success') {
                            throw new Error(result.message || t('action_error'));
                        }
                        showAlert(t('action_success'));
                        await refresh();
                    } catch (err) {
                        showError(err.message || t('action_error'));
                    } finally {
                        btn.disabled = false;
                    }
                });
            });

            setLastUpdated?.();
        } catch (e) {
            console.error(e);
            body.innerHTML = `<tr><td colspan="7">${esc(t('load_error'))}</td></tr>`;
            showError(e.message || t('load_error'));
        }
    }

    async function refresh() {
        await Promise.all([loadStats(), loadSubscriptions()]);
    }

    let debounce;
    document.getElementById('platSubsSearch')?.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(loadSubscriptions, 300);
    });
    document.getElementById('platSubsStatusFilter')?.addEventListener('change', loadSubscriptions);
    document.getElementById('platSubsPlanFilter')?.addEventListener('change', loadSubscriptions);
    document.getElementById('platSubsClearFilters')?.addEventListener('click', () => {
        const search = document.getElementById('platSubsSearch');
        const status = document.getElementById('platSubsStatusFilter');
        const plan = document.getElementById('platSubsPlanFilter');
        if (search) search.value = '';
        if (status) status.value = '';
        if (plan) plan.value = '';
        loadSubscriptions();
    });

    document.addEventListener('DOMContentLoaded', async () => {
        await loadPlans();
        const urlPlan = new URLSearchParams(window.location.search).get('plan');
        if (urlPlan) {
            const planFilter = document.getElementById('platSubsPlanFilter');
            if (planFilter) planFilter.value = urlPlan;
        }
        await refresh();
    });
    document.addEventListener('plat:refresh', refresh);
})();
