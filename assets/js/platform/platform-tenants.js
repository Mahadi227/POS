(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    const STATUS_I18N = {
        trial: 'plat_status_trial',
        active: 'plat_status_active',
        suspended: 'plat_status_suspended',
        cancelled: 'plat_status_cancelled',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function statusLabel(status) {
        const key = STATUS_I18N[status] || '';
        return key ? t(key) : (status || '—');
    }

    function badge(status) {
        const safe = esc(status || 'active');
        const cls = `plat-badge plat-badge--${safe}`;
        return `<span class="${cls}">${esc(statusLabel(status))}</span>`;
    }

    function formatDate(value) {
        if (!value) return '—';
        try {
            return new Date(value).toLocaleDateString(cfg.locale || undefined);
        } catch (e) {
            return '—';
        }
    }

    function showError(msg) {
        const banner = document.getElementById('platTenantsError');
        const text = document.getElementById('platTenantsErrorText');
        if (!banner || !text) return;
        text.textContent = msg || t('plat_tenants_load_error');
        banner.hidden = false;
    }

    function hideError() {
        const banner = document.getElementById('platTenantsError');
        if (banner) banner.hidden = true;
    }

    function setLoading(isLoading) {
        const body = document.getElementById('platTenantsBody');
        const wrap = document.querySelector('.plat-tenants-table-wrap');
        const empty = document.getElementById('platTenantsEmpty');
        if (!body) return;

        if (isLoading) {
            if (wrap) wrap.classList.remove('is-empty');
            if (empty) empty.hidden = true;
            body.innerHTML = `<tr class="plat-tenants-loading-row"><td colspan="8">
                <span class="plat-tenants-loading">
                    <span class="plat-tenants-spinner" aria-hidden="true"></span>
                    ${esc(t('loading'))}…
                </span>
            </td></tr>`;
        }
    }

    function updateCount(total) {
        const el = document.getElementById('platTenantsCount');
        if (!el) return;
        const template = t('plat_tenants_count');
        el.textContent = template.includes('%d')
            ? template.replace('%d', String(total))
            : `${total} ${template}`;
    }

    function hasActiveFilters() {
        const q = document.getElementById('platSearchInput')?.value?.trim() || '';
        const status = document.getElementById('platStatusFilter')?.value || '';
        return Boolean(q || status);
    }

    function updateClearButton() {
        const btn = document.getElementById('platClearFilters');
        if (btn) btn.hidden = !hasActiveFilters();
    }

    function buildQuery() {
        const q = document.getElementById('platSearchInput')?.value?.trim() || '';
        const status = document.getElementById('platStatusFilter')?.value || '';
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        if (status) params.set('status', status);
        const qs = params.toString();
        return qs ? `tenants?${qs}` : 'tenants';
    }

    function bindRowNavigation() {
        document.querySelectorAll('#platTenantsBody tr.plat-row-link').forEach((row) => {
            row.addEventListener('click', (e) => {
                if (e.target.closest('a')) return;
                const href = row.dataset.href;
                if (href) window.location.href = href;
            });
        });
    }

    async function loadTenants() {
        const body = document.getElementById('platTenantsBody');
        const wrap = document.querySelector('.plat-tenants-table-wrap');
        const empty = document.getElementById('platTenantsEmpty');
        if (!body || !apiGet) return;

        hideError();
        setLoading(true);
        updateClearButton();

        try {
            const res = await apiGet(buildQuery());
            if (res.status !== 'success') {
                throw new Error(res.message || t('plat_tenants_load_error'));
            }

            const rows = res.data || [];
            updateCount(rows.length);

            if (!rows.length) {
                body.innerHTML = '';
                if (wrap) wrap.classList.add('is-empty');
                if (empty) empty.hidden = false;
                setLastUpdated();
                return;
            }

            if (wrap) wrap.classList.remove('is-empty');
            if (empty) empty.hidden = true;

            body.innerHTML = rows.map((row) => {
                const href = `view.php?id=${encodeURIComponent(row.id)}`;
                return `<tr class="plat-row-link" data-href="${href}" tabindex="0">
                    <td><a href="${href}">${esc(row.name)}</a></td>
                    <td><code>${esc(row.slug)}</code></td>
                    <td>${badge(row.status)}</td>
                    <td>${esc(row.plan_name || row.plan_code || '—')}</td>
                    <td class="plat-col-num">${esc(row.store_count)}</td>
                    <td class="plat-col-num">${esc(row.user_count)}</td>
                    <td>${esc(formatDate(row.created_at))}</td>
                    <td class="plat-col-action">
                        <a class="plat-tenants-view-btn" href="${href}">
                            ${esc(t('plat_view_detail'))}
                            <span class="material-icons-round" aria-hidden="true">chevron_right</span>
                        </a>
                    </td>
                </tr>`;
            }).join('');

            bindRowNavigation();
            setLastUpdated();
        } catch (e) {
            console.error(e);
            if (wrap) wrap.classList.remove('is-empty');
            if (empty) empty.hidden = true;
            body.innerHTML = `<tr><td colspan="8">${esc(t('load_error'))}</td></tr>`;
            showError(e.message || t('load_error'));
        }
    }

    let debounce;
    document.getElementById('platSearchInput')?.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(loadTenants, 300);
    });
    document.getElementById('platStatusFilter')?.addEventListener('change', loadTenants);

    document.getElementById('platClearFilters')?.addEventListener('click', () => {
        const search = document.getElementById('platSearchInput');
        const status = document.getElementById('platStatusFilter');
        if (search) search.value = '';
        if (status) status.value = '';
        loadTenants();
    });

    document.getElementById('platTenantsBody')?.addEventListener('keydown', (e) => {
        const row = e.target.closest('tr.plat-row-link');
        if (!row || (e.key !== 'Enter' && e.key !== ' ')) return;
        e.preventDefault();
        const href = row.dataset.href;
        if (href) window.location.href = href;
    });

    document.addEventListener('DOMContentLoaded', loadTenants);
    document.addEventListener('plat:refresh', loadTenants);
})();
