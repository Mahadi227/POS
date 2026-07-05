(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    let debounceTimer = null;
    let statsCurrency = 'EUR';

    const TYPE_I18N = {
        invoice: 'plat_billing_type_invoice',
        payment: 'plat_billing_type_payment',
        refund: 'plat_billing_type_refund',
        failed: 'plat_billing_type_failed',
        checkout: 'plat_billing_type_checkout',
        subscription_updated: 'plat_billing_type_subscription_updated',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function typeLabel(type) {
        const key = TYPE_I18N[type];
        return key ? t(key) : (type || '—');
    }

    function formatMoney(amount, currency) {
        const n = Number(amount);
        if (Number.isNaN(n)) return '—';
        try {
            return new Intl.NumberFormat(cfg.locale || undefined, {
                style: 'currency',
                currency: currency || statsCurrency || 'EUR',
                maximumFractionDigits: 2,
            }).format(n);
        } catch (e) {
            return `${n} ${currency || ''}`.trim();
        }
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
        const el = document.getElementById('platBillingError');
        const text = document.getElementById('platBillingErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_billing_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platBillingError');
        if (el) el.hidden = true;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platBillingKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platBillingCount');
        if (!el) return;
        const template = t('plat_billing_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearButton() {
        const search = document.getElementById('platBillingSearch')?.value || '';
        const type = document.getElementById('platBillingTypeFilter')?.value || '';
        const btn = document.getElementById('platBillingClearFilters');
        if (btn) btn.hidden = !search && !type;
    }

    function listQuery() {
        const params = new URLSearchParams();
        const q = document.getElementById('platBillingSearch')?.value?.trim();
        const type = document.getElementById('platBillingTypeFilter')?.value;
        if (q) params.set('q', q);
        if (type) params.set('type', type);
        params.set('per_page', '50');
        const qs = params.toString();
        return qs ? `billing?${qs}` : 'billing?per_page=50';
    }

    function typeBadge(type) {
        const safe = esc(type || '');
        const cls = ['payment', 'failed', 'refund', 'checkout'].includes(type)
            ? ` plat-billing-type--${type}`
            : '';
        return `<span class="plat-billing-type${cls}">${esc(typeLabel(type))}</span>`;
    }

    async function loadStats() {
        const res = await apiGet('billing/stats');
        if (res.status !== 'success') return;
        const stats = res.data || {};
        statsCurrency = stats.currency || 'EUR';
        document.getElementById('platBillKpiTotal').textContent = String(stats.total ?? 0);
        document.getElementById('platBillKpiPayments').textContent = String(stats.payments ?? 0);
        document.getElementById('platBillKpiCollected').textContent = formatMoney(stats.collected, statsCurrency);
        document.getElementById('platBillKpiFailed').textContent = String(stats.failed ?? 0);
    }

    async function loadEvents() {
        hideError();
        const body = document.getElementById('platBillingBody');
        const empty = document.getElementById('platBillingEmpty');
        const wrap = document.querySelector('.plat-billing-table-wrap');
        if (!body) return;

        body.innerHTML = `<tr class="plat-billing-loading-row"><td colspan="6">
            <span class="plat-billing-loading">
                <span class="plat-billing-spinner" aria-hidden="true"></span>
                ${esc(t('loading'))}…
            </span>
        </td></tr>`;
        if (empty) empty.hidden = true;
        if (wrap) wrap.hidden = false;

        const res = await apiGet(listQuery());
        if (res.status !== 'success') {
            throw new Error(res.message || t('plat_billing_load_error'));
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
            const amount = Number(row.amount);
            const amountCls = !amount ? ' plat-billing-amount--zero' : '';
            const ref = row.external_id || row.metadata?.plan_code || '—';
            const orgHref = row.tenant_id
                ? `../companies/view.php?id=${encodeURIComponent(row.tenant_id)}`
                : null;

            return `<tr>
                <td>${esc(formatDateTime(row.created_at))}</td>
                <td>
                    <strong>${esc(row.tenant_name || '—')}</strong>
                    ${row.tenant_slug ? `<br><span class="plat-billing-muted">${esc(row.tenant_slug)}</span>` : ''}
                </td>
                <td>${typeBadge(row.type)}</td>
                <td><span class="plat-billing-amount${amountCls}">${esc(formatMoney(row.amount, row.currency))}</span></td>
                <td><span class="plat-billing-ref" title="${esc(ref)}">${esc(ref)}</span></td>
                <td>
                    ${orgHref ? `<a class="plat-billing-view-btn" href="${orgHref}">
                        <span class="material-icons-round" aria-hidden="true">business</span>
                        ${esc(t('plat_billing_view_org'))}
                    </a>` : ''}
                </td>
            </tr>`;
        }).join('');
    }

    async function refresh() {
        setKpiLoading(true);
        try {
            await Promise.all([loadStats(), loadEvents()]);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    document.getElementById('platBillingSearch')?.addEventListener('input', () => {
        updateClearButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            loadEvents().catch((e) => showError(e.message));
        }, 300);
    });

    document.getElementById('platBillingTypeFilter')?.addEventListener('change', () => {
        updateClearButton();
        loadEvents().catch((e) => showError(e.message));
    });

    document.getElementById('platBillingClearFilters')?.addEventListener('click', () => {
        const search = document.getElementById('platBillingSearch');
        const type = document.getElementById('platBillingTypeFilter');
        if (search) search.value = '';
        if (type) type.value = '';
        updateClearButton();
        loadEvents().catch((e) => showError(e.message));
    });

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
