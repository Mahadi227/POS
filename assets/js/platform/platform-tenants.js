(function () {
    'use strict';

    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function badge(status) {
        const cls = `plat-badge plat-badge--${esc(status || 'active')}`;
        return `<span class="${cls}">${esc(status || '—')}</span>`;
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

    async function loadTenants() {
        const body = document.getElementById('platTenantsBody');
        if (!body || !apiGet) return;
        try {
            const res = await apiGet(buildQuery());
            const rows = res.data || [];
            if (!rows.length) {
                body.innerHTML = `<tr><td colspan="8">${esc(t('no_data'))}</td></tr>`;
                return;
            }
            body.innerHTML = rows.map((row) => `
                <tr class="plat-row-link" data-href="tenant.php?id=${encodeURIComponent(row.id)}">
                    <td><a href="tenant.php?id=${encodeURIComponent(row.id)}">${esc(row.name)}</a></td>
                    <td><code>${esc(row.slug)}</code></td>
                    <td>${badge(row.status)}</td>
                    <td>${esc(row.plan_name || row.plan_code || '—')}</td>
                    <td>${esc(row.store_count)}</td>
                    <td>${esc(row.user_count)}</td>
                    <td>${esc(row.created_at ? new Date(row.created_at).toLocaleDateString() : '—')}</td>
                    <td><a class="plat-link-btn" href="tenant.php?id=${encodeURIComponent(row.id)}">${esc(t('plat_view_detail'))}</a></td>
                </tr>
            `).join('');
            setLastUpdated();
        } catch (e) {
            body.innerHTML = `<tr><td colspan="8">${esc(t('load_error'))}</td></tr>`;
        }
    }

    let debounce;
    document.getElementById('platSearchInput')?.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(loadTenants, 300);
    });
    document.getElementById('platStatusFilter')?.addEventListener('change', loadTenants);

    document.addEventListener('DOMContentLoaded', loadTenants);
    document.addEventListener('plat:refresh', loadTenants);
})();
