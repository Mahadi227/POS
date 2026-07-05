(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPut, t, setLastUpdated } = window.PlatformAPI || {};

    let allPlans = [];
    let search = '';
    let statusFilter = '';

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
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

    function limitLabel(value) {
        if (value === null || value === undefined || value === '') {
            return t('plat_plans_unlimited');
        }
        return String(value);
    }

    function showError(msg) {
        const el = document.getElementById('platPlansError');
        const text = document.getElementById('platPlansErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_plans_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platPlansError');
        if (el) el.hidden = true;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platPlansKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platPlansCount');
        if (!el) return;
        const template = t('plat_plans_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n} plans`;
    }

    function updateClearButton() {
        const btn = document.getElementById('platPlansClearFilters');
        if (!btn) return;
        btn.hidden = !search && !statusFilter;
    }

    function filteredPlans() {
        const q = search.trim().toLowerCase();
        return allPlans.filter((plan) => {
            if (statusFilter === 'active' && !plan.is_active) return false;
            if (statusFilter === 'inactive' && plan.is_active) return false;
            if (!q) return true;
            const hay = `${plan.name || ''} ${plan.code || ''}`.toLowerCase();
            return hay.includes(q);
        });
    }

    function renderModules(modules) {
        if (!modules || typeof modules !== 'object') {
            return `<span class="plat-plans-muted">${esc(t('plat_no_data'))}</span>`;
        }
        const keys = Object.keys(modules).sort();
        if (!keys.length) {
            return `<span class="plat-plans-muted">${esc(t('plat_no_data'))}</span>`;
        }
        return keys.map((key) => {
            const on = !!modules[key];
            return `<span class="plat-plan-module-chip${on ? '' : ' is-off'}">${esc(key)}</span>`;
        }).join('');
    }

    function renderPlanCard(plan) {
        const active = !!plan.is_active;
        const statusCls = active ? 'plat-plans-status--active' : 'plat-plans-status--inactive';
        const statusLabel = active ? t('plat_plans_status_active') : t('plat_plans_status_inactive');
        const subsHref = `../subscriptions/index.php?plan=${encodeURIComponent(plan.code || '')}`;

        return `
            <article class="plat-plan-card${active ? '' : ' is-inactive'}">
                <div class="plat-plan-card__head">
                    <div>
                        <h3 class="plat-plan-card__title">${esc(plan.name)}</h3>
                        <span class="plat-plan-card__code">${esc(plan.code)}</span>
                    </div>
                    <span class="plat-plans-status ${statusCls}">${esc(statusLabel)}</span>
                </div>
                <div class="plat-plan-card__price">
                    ${esc(formatMoney(plan.price_monthly, plan.currency))}
                    <small>/mo</small>
                </div>
                <div class="plat-plan-card__limits">
                    <span class="plat-plan-card__limit">
                        <span class="material-icons-round" aria-hidden="true">store</span>
                        ${esc(t('plat_col_stores'))}: ${esc(limitLabel(plan.max_stores))}
                    </span>
                    <span class="plat-plan-card__limit">
                        <span class="material-icons-round" aria-hidden="true">group</span>
                        ${esc(t('plat_col_users'))}: ${esc(limitLabel(plan.max_users))}
                    </span>
                </div>
                <div class="plat-plan-card__modules" aria-label="${esc(t('plat_modules'))}">
                    ${renderModules(plan.modules)}
                </div>
                <div class="plat-plan-card__footer">
                    <span class="plat-plan-card__subs">
                        ${esc(t('plat_plans_col_subscribers'))}:
                        <strong>${esc(String(plan.subscriber_count ?? 0))}</strong>
                    </span>
                    <div class="plat-plan-card__actions">
                        <button type="button" class="plat-plan-card__edit" data-plan-id="${esc(String(plan.id))}">
                            <span class="material-icons-round" aria-hidden="true">edit</span>
                            ${esc(t('plat_plans_edit'))}
                        </button>
                        <a class="plat-plan-card__link" href="${subsHref}">
                            ${esc(t('plat_plans_view_subs'))}
                            <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </article>
        `;
    }

    function renderGrid() {
        const grid = document.getElementById('platPlansGrid');
        const empty = document.getElementById('platPlansEmpty');
        if (!grid) return;

        const rows = filteredPlans();
        updateCount(rows.length);

        if (!rows.length) {
            grid.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }

        if (empty) empty.hidden = true;
        grid.innerHTML = rows.map(renderPlanCard).join('');
    }

    function renderStats(stats) {
        document.getElementById('platPlansKpiTotal').textContent = String(stats.total ?? 0);
        document.getElementById('platPlansKpiActive').textContent = String(stats.active ?? 0);
        document.getElementById('platPlansKpiSubs').textContent = String(stats.subscribers ?? 0);
        document.getElementById('platPlansKpiMrr').textContent = formatMoney(stats.mrr, 'USD');
    }

    async function loadPlans() {
        hideError();
        setKpiLoading(true);

        const grid = document.getElementById('platPlansGrid');
        if (grid) {
            grid.innerHTML = `
                <div class="plat-plans-loading">
                    <span class="plat-plans-spinner" aria-hidden="true"></span>
                    ${esc(t('loading'))}…
                </div>
            `;
        }

        const [catalogRes, statsRes] = await Promise.all([
            apiGet('plans/catalog'),
            apiGet('plans/stats'),
        ]);

        if (catalogRes.status !== 'success') {
            throw new Error(catalogRes.message || t('plat_plans_load_error'));
        }

        allPlans = catalogRes.data || [];
        if (statsRes.status === 'success') {
            renderStats(statsRes.data || {});
        }
        setKpiLoading(false);
        renderGrid();
        setLastUpdated?.();
    }

    document.getElementById('platPlansSearch')?.addEventListener('input', (e) => {
        search = e.target.value || '';
        updateClearButton();
        renderGrid();
    });

    document.getElementById('platPlansStatusFilter')?.addEventListener('change', (e) => {
        statusFilter = e.target.value || '';
        updateClearButton();
        renderGrid();
    });

    document.getElementById('platPlansClearFilters')?.addEventListener('click', () => {
        search = '';
        statusFilter = '';
        const searchEl = document.getElementById('platPlansSearch');
        const statusEl = document.getElementById('platPlansStatusFilter');
        if (searchEl) searchEl.value = '';
        if (statusEl) statusEl.value = '';
        updateClearButton();
        renderGrid();
    });

    const modal = document.getElementById('platPlanModal');
    const form = document.getElementById('platPlanEditForm');
    const errorEl = document.getElementById('platPlanEditError');
    let editingPlan = null;

    function openModal(plan) {
        if (!modal || !plan) return;
        editingPlan = plan;
        document.getElementById('platPlanEditId').value = String(plan.id);
        document.getElementById('platPlanEditCode').value = plan.code || '';
        document.getElementById('platPlanEditName').value = plan.name || '';
        document.getElementById('platPlanEditPrice').value = plan.price_monthly ?? 0;
        document.getElementById('platPlanEditCurrency').value = plan.currency || 'EUR';
        document.getElementById('platPlanEditStores').value = plan.max_stores ?? '';
        document.getElementById('platPlanEditUsers').value = plan.max_users ?? '';
        document.getElementById('platPlanEditActive').checked = !!plan.is_active;
        if (errorEl) {
            errorEl.hidden = true;
            errorEl.textContent = '';
        }
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!modal) return;
        modal.hidden = true;
        editingPlan = null;
        document.body.style.overflow = '';
    }

    document.getElementById('platPlansGrid')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-plan-id]');
        if (!btn) return;
        const id = Number(btn.getAttribute('data-plan-id'));
        const plan = allPlans.find((p) => Number(p.id) === id);
        if (plan) openModal(plan);
    });

    modal?.querySelectorAll('[data-close-modal]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!editingPlan) return;

        const saveBtn = document.getElementById('platPlanEditSave');
        if (saveBtn) saveBtn.disabled = true;
        if (errorEl) errorEl.hidden = true;

        const storesVal = document.getElementById('platPlanEditStores')?.value;
        const usersVal = document.getElementById('platPlanEditUsers')?.value;

        const payload = {
            name: document.getElementById('platPlanEditName')?.value || '',
            price_monthly: Number(document.getElementById('platPlanEditPrice')?.value || 0),
            currency: (document.getElementById('platPlanEditCurrency')?.value || 'EUR').toUpperCase(),
            max_stores: storesVal === '' ? null : Number(storesVal),
            max_users: usersVal === '' ? null : Number(usersVal),
            is_active: document.getElementById('platPlanEditActive')?.checked ? 1 : 0,
        };

        try {
            const res = await apiPut(`plans/${editingPlan.id}`, payload);
            if (res.status !== 'success') {
                throw new Error(res.message || t('plat_plans_edit_error'));
            }
            closeModal();
            await loadPlans();
            hideError();
            alert(t('plat_plans_edit_success'));
        } catch (err) {
            if (errorEl) {
                errorEl.textContent = err.message || t('plat_plans_edit_error');
                errorEl.hidden = false;
            }
        } finally {
            if (saveBtn) saveBtn.disabled = false;
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadPlans().catch((e) => {
            setKpiLoading(false);
            showError(e.message || t('load_error'));
        });
    });

    document.addEventListener('plat:refresh', () => {
        loadPlans().catch(() => {});
    });
})();
