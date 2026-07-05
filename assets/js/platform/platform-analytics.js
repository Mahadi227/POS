(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    let statsCurrency = 'EUR';

    const SUB_STATUS_I18N = {
        active: 'plat_status_active',
        trial: 'plat_status_trial',
        past_due: 'plat_sub_status_past_due',
        cancelled: 'plat_sub_status_cancelled',
    };

    const METRIC_I18N = {
        api_calls: 'plat_analytics_metric_api',
        stores: 'plat_analytics_metric_stores',
        users: 'plat_analytics_metric_users',
        sales: 'plat_analytics_metric_sales',
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

    function formatMoney(amount, currency) {
        const n = Number(amount);
        if (Number.isNaN(n)) return '—';
        try {
            return new Intl.NumberFormat(cfg.locale || undefined, {
                style: 'currency',
                currency: currency || statsCurrency || 'EUR',
                maximumFractionDigits: 0,
            }).format(n);
        } catch (e) {
            return `${n} ${currency || ''}`.trim();
        }
    }

    function formatMonth(ym) {
        if (!ym) return '—';
        try {
            const [y, m] = ym.split('-');
            const d = new Date(Number(y), Number(m) - 1, 1);
            return d.toLocaleDateString(cfg.locale || undefined, { month: 'short', year: '2-digit' });
        } catch (e) {
            return ym;
        }
    }

    function showError(msg) {
        const el = document.getElementById('platAnalyticsError');
        const text = document.getElementById('platAnalyticsErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_analytics_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platAnalyticsError');
        if (el) el.hidden = true;
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platAnalyticsKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function renderKpis(stats) {
        statsCurrency = stats.currency || 'EUR';
        document.getElementById('platAnKpiTenants').textContent = String(stats.tenants ?? 0);
        document.getElementById('platAnKpiMrr').textContent = formatMoney(stats.mrr, statsCurrency);
        document.getElementById('platAnKpiRevenue').textContent = formatMoney(stats.revenue, statsCurrency);
        document.getElementById('platAnKpiSubs').textContent = String(stats.subscriptions ?? 0);
        document.getElementById('platAnKpiStores').textContent = String(stats.stores ?? 0);
        document.getElementById('platAnKpiUsers').textContent = String(stats.users ?? 0);
    }

    function renderBarChart(containerId, rows, valueKey, revenue) {
        const el = document.getElementById(containerId);
        if (!el) return;

        if (!rows?.length) {
            el.innerHTML = `<p class="plat-analytics-muted">${esc(t('plat_no_data'))}</p>`;
            return;
        }

        const max = Math.max(1, ...rows.map((r) => Number(r[valueKey]) || 0));
        el.innerHTML = rows.map((row) => {
            const val = Number(row[valueKey]) || 0;
            const pct = Math.round((val / max) * 100);
            const display = valueKey === 'amount' ? formatMoney(val, statsCurrency) : String(val);
            const colCls = revenue ? ' plat-analytics-bar-col--revenue' : '';
            return `<div class="plat-analytics-bar-col${colCls}">
                <span class="plat-analytics-bar__value">${esc(display)}</span>
                <div class="plat-analytics-bar" style="height:${Math.max(4, pct * 1.2)}px" title="${esc(display)}"></div>
                <span class="plat-analytics-bar__label">${esc(formatMonth(row.month))}</span>
            </div>`;
        }).join('');
    }

    function renderPlanBreakdown(rows) {
        const el = document.getElementById('platAnPlanBreakdown');
        if (!el) return;

        if (!rows?.length) {
            el.innerHTML = `<p class="plat-analytics-muted">${esc(t('plat_no_data'))}</p>`;
            return;
        }

        const max = Math.max(1, ...rows.map((r) => Number(r.count) || 0));
        el.innerHTML = rows.map((row) => {
            const cnt = Number(row.count) || 0;
            const pct = Math.round((cnt / max) * 100);
            const name = row.name || row.code || '—';
            return `<div class="plat-analytics-row">
                <div class="plat-analytics-row__head">
                    <span>${esc(name)}</span>
                    <strong>${esc(String(cnt))}</strong>
                </div>
                <div class="plat-analytics-row__bar"><span style="width:${pct}%"></span></div>
            </div>`;
        }).join('');
    }

    function renderSubStatus(status) {
        const el = document.getElementById('platAnSubStatus');
        if (!el) return;

        const keys = ['active', 'trial', 'past_due', 'cancelled'];
        el.innerHTML = keys.map((key) => {
            const val = status?.[key] ?? 0;
            return `<div class="plat-analytics-stat">
                <strong>${esc(String(val))}</strong>
                <span>${esc(label(SUB_STATUS_I18N, key))}</span>
            </div>`;
        }).join('');
    }

    function renderUsage(rows) {
        const el = document.getElementById('platAnUsage');
        if (!el) return;

        if (!rows?.length) {
            el.innerHTML = `<p class="plat-analytics-muted">${esc(t('plat_no_data'))}</p>`;
            return;
        }

        el.innerHTML = rows.map((row) => `<div class="plat-analytics-stat">
            <strong>${esc(String(row.total ?? 0))}</strong>
            <span>${esc(label(METRIC_I18N, row.metric) || row.metric)}</span>
        </div>`).join('');
    }

    function statusBadge(status) {
        const safe = esc(status || 'active');
        return `<span class="plat-badge plat-badge--${safe}">${esc(status || '—')}</span>`;
    }

    function renderTopTenants(rows) {
        const body = document.getElementById('platAnTopTenants');
        if (!body) return;

        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="5" class="plat-analytics-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }

        body.innerHTML = rows.map((row) => {
            const href = `../companies/view.php?id=${encodeURIComponent(row.id)}`;
            return `<tr>
                <td>
                    <strong>${esc(row.name || '—')}</strong>
                    ${row.slug ? `<br><span class="plat-analytics-muted">${esc(row.slug)}</span>` : ''}
                </td>
                <td>${statusBadge(row.status)}</td>
                <td>${esc(String(row.store_count ?? 0))}</td>
                <td>${esc(String(row.user_count ?? 0))}</td>
                <td>
                    <a class="plat-analytics-action-btn" href="${href}" title="${esc(t('plat_view_detail'))}">
                        <span class="material-icons-round" aria-hidden="true">open_in_new</span>
                    </a>
                </td>
            </tr>`;
        }).join('');
    }

    async function refresh() {
        hideError();
        setKpiLoading(true);

        try {
            const res = await apiGet('analytics/overview');
            if (res.status !== 'success') {
                throw new Error(res.message || t('plat_analytics_load_error'));
            }

            const stats = res.data?.stats || {};
            const overview = res.data?.overview || {};

            renderKpis(stats);
            renderBarChart('platAnGrowthChart', overview.tenant_growth, 'count', false);
            renderBarChart('platAnRevenueChart', overview.revenue_trend, 'amount', true);
            renderPlanBreakdown(overview.plan_breakdown);
            renderSubStatus(overview.subscription_status);
            renderUsage(overview.usage_metrics);
            renderTopTenants(overview.top_tenants);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
