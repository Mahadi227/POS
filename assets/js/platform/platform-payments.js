(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    let debounceTimer = null;
    let statsCurrency = 'EUR';

    const STATUS_I18N = {
        confirmed: 'plat_payments_status_confirmed',
        pending: 'plat_payments_status_pending',
        failed: 'plat_payments_status_failed',
        refund: 'plat_payments_status_refund',
    };

    const PROVIDER_I18N = {
        stripe: 'plat_sub_provider_stripe',
        paystack: 'plat_sub_provider_paystack',
        mobile_money: 'plat_sub_provider_mobile',
        manual: 'plat_sub_provider_manual',
        mobile_wave: 'Mobile Wave',
        mobile_orange: 'Orange Money',
        mobile_mtn: 'MTN MoMo',
        mobile_moov: 'Moov Money',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function statusLabel(status) {
        const key = STATUS_I18N[status];
        return key ? t(key) : (status || '—');
    }

    function providerLabel(provider) {
        const key = PROVIDER_I18N[provider];
        if (key && key.startsWith('plat_')) return t(key);
        if (key) return key;
        return provider || t('plat_sub_provider_manual');
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
        const el = document.getElementById('platPaymentsError');
        const text = document.getElementById('platPaymentsErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_payments_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platPaymentsError');
        if (el) el.hidden = true;
    }

    function showAlert(msg) {
        const el = document.getElementById('platPaymentsAlert');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => { el.hidden = true; }, 3500);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platPaymentsKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platPaymentsCount');
        if (!el) return;
        const template = t('plat_payments_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearButton() {
        const search = document.getElementById('platPaymentsSearch')?.value || '';
        const status = document.getElementById('platPaymentsStatusFilter')?.value || '';
        const provider = document.getElementById('platPaymentsProviderFilter')?.value || '';
        const btn = document.getElementById('platPaymentsClearFilters');
        if (btn) btn.hidden = !search && !status && !provider;
    }

    function listQuery() {
        const params = new URLSearchParams();
        const q = document.getElementById('platPaymentsSearch')?.value?.trim();
        const status = document.getElementById('platPaymentsStatusFilter')?.value;
        const provider = document.getElementById('platPaymentsProviderFilter')?.value;
        if (q) params.set('q', q);
        if (status) params.set('status', status);
        if (provider) params.set('provider', provider);
        params.set('per_page', '50');
        const qs = params.toString();
        return qs ? `payments?${qs}` : 'payments?per_page=50';
    }

    function providerBadge(provider) {
        const safe = esc(provider || 'manual');
        const cls = ['stripe', 'paystack', 'mobile_money', 'mobile_wave'].includes(provider)
            ? ` plat-payments-provider--${provider}`
            : '';
        return `<span class="plat-payments-provider${cls}">${esc(providerLabel(provider))}</span>`;
    }

    function statusBadge(status) {
        const safe = esc(status || 'pending');
        return `<span class="plat-payments-status plat-payments-status--${safe}">${esc(statusLabel(status))}</span>`;
    }

    async function loadStats() {
        const res = await apiGet('payments/stats');
        if (res.status !== 'success') return;
        const stats = res.data || {};
        statsCurrency = stats.currency || 'EUR';
        document.getElementById('platPayKpiTotal').textContent = String(stats.total ?? 0);
        document.getElementById('platPayKpiConfirmed').textContent = String(stats.confirmed ?? 0);
        document.getElementById('platPayKpiPending').textContent = String(stats.pending ?? 0);
        document.getElementById('platPayKpiCollected').textContent = formatMoney(stats.collected, statsCurrency);
    }

    async function confirmMobileMoney(id) {
        if (!window.confirm(t('plat_payments_confirm_prompt'))) return;
        const res = await apiPost(`payments/mobile-money/${id}/confirm`, {});
        if (res.status === 'success') {
            showAlert(t('action_success'));
            await refresh();
        } else {
            showError(res.message || t('action_error'));
        }
    }

    async function loadPayments() {
        hideError();
        const body = document.getElementById('platPaymentsBody');
        const empty = document.getElementById('platPaymentsEmpty');
        const wrap = document.querySelector('.plat-payments-table-wrap');
        if (!body) return;

        body.innerHTML = `<tr class="plat-payments-loading-row"><td colspan="8">
            <span class="plat-payments-loading">
                <span class="plat-payments-spinner" aria-hidden="true"></span>
                ${esc(t('loading'))}…
            </span>
        </td></tr>`;
        if (empty) empty.hidden = true;
        if (wrap) wrap.hidden = false;

        const res = await apiGet(listQuery());
        if (res.status !== 'success') {
            throw new Error(res.message || t('plat_payments_load_error'));
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
            const orgHref = row.tenant_id
                ? `../companies/view.php?id=${encodeURIComponent(row.tenant_id)}`
                : null;
            const canConfirm = row.source === 'mobile_money' && row.payment_status === 'pending';
            const ref = row.reference || '—';

            return `<tr>
                <td>${esc(formatDateTime(row.created_at))}</td>
                <td>
                    <strong>${esc(row.tenant_name || '—')}</strong>
                    ${row.tenant_slug ? `<br><span class="plat-payments-muted">${esc(row.tenant_slug)}</span>` : ''}
                </td>
                <td>${providerBadge(row.provider)}</td>
                <td>${esc(row.plan_code || '—')}</td>
                <td>${esc(formatMoney(row.amount, row.currency))}</td>
                <td>${statusBadge(row.payment_status)}</td>
                <td><span class="plat-payments-ref" title="${esc(ref)}">${esc(ref)}</span></td>
                <td>
                    <div class="plat-payments-actions">
                        ${orgHref ? `<a class="plat-payments-action-btn" href="${orgHref}" title="${esc(t('plat_payments_view_org'))}">
                            <span class="material-icons-round" aria-hidden="true">business</span>
                        </a>` : ''}
                        ${canConfirm ? `<button type="button" class="plat-payments-action-btn plat-payments-action-btn--primary plat-pay-confirm-btn" data-id="${esc(String(row.src_id))}">
                            ${esc(t('plat_payments_confirm'))}
                        </button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');

        body.querySelectorAll('.plat-pay-confirm-btn').forEach((btn) => {
            btn.addEventListener('click', () => confirmMobileMoney(btn.dataset.id));
        });
    }

    async function refresh() {
        setKpiLoading(true);
        try {
            await Promise.all([loadStats(), loadPayments()]);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    document.getElementById('platPaymentsSearch')?.addEventListener('input', () => {
        updateClearButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadPayments().catch((e) => showError(e.message)), 300);
    });

    document.getElementById('platPaymentsStatusFilter')?.addEventListener('change', () => {
        updateClearButton();
        loadPayments().catch((e) => showError(e.message));
    });

    document.getElementById('platPaymentsProviderFilter')?.addEventListener('change', () => {
        updateClearButton();
        loadPayments().catch((e) => showError(e.message));
    });

    document.getElementById('platPaymentsClearFilters')?.addEventListener('click', () => {
        const search = document.getElementById('platPaymentsSearch');
        const status = document.getElementById('platPaymentsStatusFilter');
        const provider = document.getElementById('platPaymentsProviderFilter');
        if (search) search.value = '';
        if (status) status.value = '';
        if (provider) provider.value = '';
        updateClearButton();
        loadPayments().catch((e) => showError(e.message));
    });

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
