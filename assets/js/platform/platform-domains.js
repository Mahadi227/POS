(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    let debounceTimer = null;

    const KIND_I18N = {
        subdomain: 'plat_domains_kind_subdomain',
        custom: 'plat_domains_kind_custom',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function kindLabel(kind) {
        const key = KIND_I18N[kind];
        return key ? t(key) : kind;
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
        const el = document.getElementById('platDomainsError');
        const text = document.getElementById('platDomainsErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_domains_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platDomainsError');
        if (el) el.hidden = true;
    }

    function showAlert(msg) {
        const el = document.getElementById('platDomainsAlert');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => { el.hidden = true; }, 3500);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platDomainsKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platDomainsCount');
        if (!el) return;
        const template = t('plat_domains_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearButton() {
        const search = document.getElementById('platDomainsSearch')?.value || '';
        const kind = document.getElementById('platDomainsKindFilter')?.value || '';
        const verified = document.getElementById('platDomainsVerifiedFilter')?.value || '';
        const btn = document.getElementById('platDomainsClearFilters');
        if (btn) btn.hidden = !search && !kind && !verified;
    }

    function listQuery() {
        const params = new URLSearchParams();
        const q = document.getElementById('platDomainsSearch')?.value?.trim();
        const kind = document.getElementById('platDomainsKindFilter')?.value;
        const verified = document.getElementById('platDomainsVerifiedFilter')?.value;
        if (q) params.set('q', q);
        if (kind) params.set('kind', kind);
        if (verified) params.set('verified', verified);
        params.set('per_page', '50');
        const qs = params.toString();
        return qs ? `domains?${qs}` : 'domains?per_page=50';
    }

    function verifiedBadge(isVerified) {
        const verified = Number(isVerified) === 1;
        const cls = verified ? 'plat-domains-status--verified' : 'plat-domains-status--pending';
        const label = verified ? t('plat_domains_status_verified') : t('plat_domains_status_pending');
        return `<span class="plat-domains-status ${cls}">${esc(label)}</span>`;
    }

    async function verifyDomain(id) {
        if (!window.confirm(t('plat_domains_confirm_verify'))) return;
        const res = await apiPost(`domains/${id}/verify`, {});
        if (res.status === 'success') {
            showAlert(t('action_success'));
            await refresh();
        } else {
            showError(res.message || t('action_error'));
        }
    }

    async function loadStats() {
        const res = await apiGet('domains/stats');
        if (res.status !== 'success') return;
        const stats = res.data || {};
        document.getElementById('platDomKpiTotal').textContent = String(stats.total ?? 0);
        document.getElementById('platDomKpiSub').textContent = String(stats.subdomain ?? 0);
        document.getElementById('platDomKpiCustom').textContent = String(stats.custom ?? 0);
        document.getElementById('platDomKpiPending').textContent = String(stats.pending ?? 0);
    }

    async function loadDomains() {
        hideError();
        const body = document.getElementById('platDomainsBody');
        const empty = document.getElementById('platDomainsEmpty');
        const wrap = document.querySelector('.plat-domains-table-wrap');
        if (!body) return;

        body.innerHTML = `<tr class="plat-domains-loading-row"><td colspan="6">
            <span class="plat-domains-loading">
                <span class="plat-domains-spinner" aria-hidden="true"></span>
                ${esc(t('loading'))}…
            </span>
        </td></tr>`;
        if (empty) empty.hidden = true;
        if (wrap) wrap.hidden = false;

        const res = await apiGet(listQuery());
        if (res.status !== 'success') {
            throw new Error(res.message || t('plat_domains_load_error'));
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
            const kindCls = row.kind === 'custom' ? ' plat-domains-kind--custom' : '';
            const canVerify = Number(row.is_verified) !== 1;
            const orgHref = row.tenant_id
                ? `../companies/view.php?id=${encodeURIComponent(row.tenant_id)}`
                : null;

            return `<tr>
                <td><code>${esc(row.hostname)}</code></td>
                <td>
                    <strong>${esc(row.tenant_name || '—')}</strong>
                    ${row.tenant_slug ? `<br><span class="plat-domains-muted">${esc(row.tenant_slug)}</span>` : ''}
                </td>
                <td><span class="plat-domains-kind${kindCls}">${esc(kindLabel(row.kind))}</span></td>
                <td>${verifiedBadge(row.is_verified)}</td>
                <td>${esc(formatDateTime(row.created_at))}</td>
                <td>
                    <div class="plat-domains-actions">
                        ${orgHref ? `<a class="plat-domains-action-btn" href="${orgHref}" title="${esc(t('plat_domains_view_org'))}">
                            <span class="material-icons-round" aria-hidden="true">business</span>
                        </a>` : ''}
                        ${canVerify ? `<button type="button" class="plat-domains-action-btn plat-domains-action-btn--primary plat-dom-verify-btn" data-id="${esc(String(row.id))}">
                            ${esc(t('plat_domains_verify'))}
                        </button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');

        body.querySelectorAll('.plat-dom-verify-btn').forEach((btn) => {
            btn.addEventListener('click', () => verifyDomain(btn.dataset.id));
        });
    }

    async function refresh() {
        setKpiLoading(true);
        try {
            await Promise.all([loadStats(), loadDomains()]);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    document.getElementById('platDomainsSearch')?.addEventListener('input', () => {
        updateClearButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => loadDomains().catch((e) => showError(e.message)), 300);
    });

    ['platDomainsKindFilter', 'platDomainsVerifiedFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => {
            updateClearButton();
            loadDomains().catch((e) => showError(e.message));
        });
    });

    document.getElementById('platDomainsClearFilters')?.addEventListener('click', () => {
        const search = document.getElementById('platDomainsSearch');
        const kind = document.getElementById('platDomainsKindFilter');
        const verified = document.getElementById('platDomainsVerifiedFilter');
        if (search) search.value = '';
        if (kind) kind.value = '';
        if (verified) verified.value = '';
        updateClearButton();
        loadDomains().catch((e) => showError(e.message));
    });

    document.addEventListener('DOMContentLoaded', refresh);
    document.addEventListener('plat:refresh', refresh);
})();
