(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const intCfg = window.PLATFORM_INTEGRATIONS || {};
    const { apiGet, apiPut, t, setLastUpdated } = window.PlatformAPI || {};

    let providers = [];
    let connections = [];
    let activeTab = 'providers';
    let debounce = null;

    const CAT_I18N = {
        payments: 'plat_int_cat_payments',
        communications: 'plat_int_cat_communications',
        developer: 'plat_int_cat_developer',
        analytics: 'plat_int_cat_analytics',
        shipping: 'plat_int_cat_shipping',
        other: 'plat_int_cat_other',
    };

    const PROV_STATUS_I18N = {
        enabled: 'plat_int_status_enabled',
        disabled: 'plat_int_status_disabled',
    };

    const CONN_STATUS_I18N = {
        connected: 'plat_int_conn_connected',
        disconnected: 'plat_int_conn_disconnected',
        pending: 'plat_int_conn_pending',
        error: 'plat_int_conn_error',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function label(map, key) {
        return map[key] ? t(map[key]) : key;
    }

    function fmt(v) {
        if (!v) return '—';
        try { return new Date(v).toLocaleString(cfg.locale); } catch (e) { return '—'; }
    }

    function showError(msg) {
        const el = document.getElementById('platIntError');
        document.getElementById('platIntErrorText').textContent = msg || t('plat_int_load_error');
        el.hidden = false;
        document.getElementById('platIntAlert').hidden = true;
    }

    function hideError() {
        const el = document.getElementById('platIntError');
        if (el) el.hidden = true;
    }

    function showSuccess(msg) {
        const el = document.getElementById('platIntAlert');
        el.textContent = msg;
        el.hidden = false;
        hideError();
        clearTimeout(showSuccess._t);
        showSuccess._t = setTimeout(() => { el.hidden = true; }, 4000);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platIntKpiGrid .plat-kpi-card').forEach((c) => {
            c.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platIntCount');
        if (!el) return;
        const template = t('plat_int_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function statusPill(status, map) {
        const cls = status === 'connected' || status === 'enabled' ? 'success'
            : (status === 'failed' || status === 'error' || status === 'disabled' ? 'failed' : 'neutral');
        return `<span class="plat-gov-pill plat-gov-pill--${cls}">${esc(label(map, status))}</span>`;
    }

    function renderKpis(stats) {
        document.getElementById('platIntKpiProviders').textContent = String(stats?.providers ?? 0);
        document.getElementById('platIntKpiEnabled').textContent = String(stats?.enabled ?? 0);
        document.getElementById('platIntKpiConnections').textContent = String(stats?.connections ?? 0);
        document.getElementById('platIntKpiConnected').textContent = String(stats?.connected ?? 0);
        document.getElementById('platIntKpiTenants').textContent = String(stats?.tenants ?? 0);
        updateCount(stats?.providers ?? 0);
    }

    function filterProviders() {
        const q = (document.getElementById('platIntProviderSearch')?.value || '').trim().toLowerCase();
        const cat = document.getElementById('platIntProviderCategory')?.value || '';
        const status = document.getElementById('platIntProviderStatus')?.value || '';

        return providers.filter((p) => {
            if (cat && p.category !== cat) return false;
            if (status && p.status !== status) return false;
            if (q) {
                const hay = `${p.name} ${p.slug} ${p.short_description}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });
    }

    function filterConnections() {
        const q = (document.getElementById('platIntConnSearch')?.value || '').trim().toLowerCase();
        const status = document.getElementById('platIntConnStatus')?.value || '';
        const cat = document.getElementById('platIntConnCategory')?.value || '';

        return connections.filter((c) => {
            if (status && c.status !== status) return false;
            if (cat && c.provider_category !== cat) return false;
            if (q) {
                const hay = `${c.provider_name} ${c.tenant_name} ${c.external_ref || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });
    }

    function providerCard(p) {
        const color = p.brand_color || '#6366f1';
        const disabled = p.status === 'disabled';
        const toggleBtn = intCfg.canManage
            ? `<button type="button" class="plat-int-btn${disabled ? ' plat-int-btn--primary' : ''}" data-prov-toggle="${p.id}" data-status="${disabled ? 'enabled' : 'disabled'}">
                ${esc(t(disabled ? 'plat_int_enable' : 'plat_int_disable'))}
               </button>`
            : statusPill(p.status, PROV_STATUS_I18N);

        const official = Number(p.is_official) === 1
            ? `<span class="plat-int-official"><span class="material-icons-round" style="font-size:14px">verified</span>${esc(t('plat_int_official'))}</span>`
            : '';

        const connTpl = t('plat_int_connections_count');
        const activeTpl = t('plat_int_active_count');
        const connLabel = connTpl.includes('%d') ? connTpl.replace('%d', String(p.connection_count || 0)) : `${p.connection_count || 0}`;
        const activeLabel = activeTpl.includes('%d') ? activeTpl.replace('%d', String(p.active_count || 0)) : `${p.active_count || 0}`;

        return `<article class="plat-int-card${disabled ? ' is-disabled' : ''}">
            <div class="plat-int-card__head">
                <span class="plat-int-card__icon" style="background:${esc(color)}">
                    <span class="material-icons-round">${esc(p.icon || 'hub')}</span>
                </span>
                <div class="plat-int-card__meta">
                    <h3 class="plat-int-card__name">${esc(p.name)}</h3>
                    <p class="plat-int-card__cat">${esc(label(CAT_I18N, p.category))}</p>
                </div>
            </div>
            <p class="plat-int-card__desc">${esc(p.short_description || '')}</p>
            <div class="plat-int-card__stats">
                <span>${esc(connLabel)}</span>
                <span><strong>${esc(activeLabel)}</strong></span>
            </div>
            <div class="plat-int-card__foot">
                ${official}
                ${toggleBtn}
            </div>
        </article>`;
    }

    function renderProviders() {
        const grid = document.getElementById('platIntProviderGrid');
        const list = filterProviders();
        if (!list.length) {
            grid.innerHTML = `<p class="plat-int-empty">${esc(t('plat_no_data'))}</p>`;
            return;
        }
        grid.innerHTML = list.map(providerCard).join('');

        grid.querySelectorAll('[data-prov-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => toggleProvider(
                parseInt(btn.dataset.provToggle, 10),
                btn.dataset.status
            ));
        });
    }

    function renderConnections() {
        const body = document.getElementById('platIntConnBody');
        const list = filterConnections();

        if (!list.length) {
            body.innerHTML = `<tr><td colspan="6" class="plat-gov-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }

        body.innerHTML = list.map((c) => {
            const color = c.provider_color || '#6366f1';
            const err = c.error_message ? `<small class="plat-int-error-msg">${esc(c.error_message)}</small>` : '';
            const orgLink = c.tenant_id
                ? `<a href="companies/view.php?id=${c.tenant_id}" class="plat-int-btn">${esc(t('plat_int_view_org'))}</a>`
                : '';
            const adminActions = intCfg.canManage && c.status !== 'connected'
                ? `<button type="button" class="plat-int-btn plat-int-btn--primary" data-conn-connect="${c.id}">${esc(t('plat_int_conn_connected'))}</button>`
                : (intCfg.canManage && c.status === 'connected'
                    ? `<button type="button" class="plat-int-btn" data-conn-disconnect="${c.id}">${esc(t('plat_int_conn_disconnected'))}</button>`
                    : '');

            return `<tr>
                <td>
                    <div class="plat-int-provider-cell">
                        <span class="plat-int-mini-icon" style="background:${esc(color)}">
                            <span class="material-icons-round">${esc(c.provider_icon || 'hub')}</span>
                        </span>
                        <div>
                            <strong>${esc(c.provider_name)}</strong>
                            <small class="plat-gov-muted">${esc(label(CAT_I18N, c.provider_category))}</small>
                        </div>
                    </div>
                </td>
                <td>${esc(c.tenant_name || '—')}${err}</td>
                <td>${statusPill(c.status, CONN_STATUS_I18N)}</td>
                <td><code>${esc(c.external_ref || '—')}</code></td>
                <td>${esc(fmt(c.last_sync_at || c.updated_at))}</td>
                <td><div class="plat-int-actions">${orgLink}${adminActions}</div></td>
            </tr>`;
        }).join('');

        body.querySelectorAll('[data-conn-connect]').forEach((btn) => {
            btn.addEventListener('click', () => setConnectionStatus(parseInt(btn.dataset.connConnect, 10), 'connected'));
        });
        body.querySelectorAll('[data-conn-disconnect]').forEach((btn) => {
            btn.addEventListener('click', () => setConnectionStatus(parseInt(btn.dataset.connDisconnect, 10), 'disconnected'));
        });
    }

    async function loadDashboard() {
        setKpiLoading(true);
        hideError();
        try {
            const res = await apiGet('integrations/dashboard');
            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || t('plat_int_load_error'));
            }
            providers = res.data.providers || [];
            connections = res.data.recent || [];
            renderKpis(res.data.stats || {});
            renderProviders();
            renderConnections();
            setLastUpdated();
        } catch (e) {
            console.error(e);
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    async function toggleProvider(id, status) {
        try {
            const res = await apiPut(`integrations/${id}/status`, { type: 'provider', status });
            if (res.status !== 'success') throw new Error(res.message || t('action_error'));
            showSuccess(t('action_success'));
            await loadDashboard();
        } catch (e) {
            showError(e.message || t('action_error'));
        }
    }

    async function setConnectionStatus(id, status) {
        try {
            const res = await apiPut(`integrations/${id}/status`, { type: 'connection', status });
            if (res.status !== 'success') throw new Error(res.message || t('action_error'));
            showSuccess(t('action_success'));
            await loadDashboard();
        } catch (e) {
            showError(e.message || t('action_error'));
        }
    }

    function switchTab(tab) {
        activeTab = tab;
        document.querySelectorAll('.plat-int-tab').forEach((el) => {
            const isActive = el.dataset.tab === tab;
            el.classList.toggle('is-active', isActive);
            el.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        document.getElementById('platIntProvidersPanel').hidden = tab !== 'providers';
        document.getElementById('platIntConnectionsPanel').hidden = tab !== 'connections';
    }

    function initTabs() {
        document.querySelectorAll('.plat-int-tab').forEach((btn) => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });
    }

    function initFilters() {
        const debouncedProv = () => {
            clearTimeout(debounce);
            debounce = setTimeout(renderProviders, 180);
        };
        const debouncedConn = () => {
            clearTimeout(debounce);
            debounce = setTimeout(renderConnections, 180);
        };

        document.getElementById('platIntProviderSearch')?.addEventListener('input', debouncedProv);
        document.getElementById('platIntProviderCategory')?.addEventListener('change', renderProviders);
        document.getElementById('platIntProviderStatus')?.addEventListener('change', renderProviders);

        document.getElementById('platIntConnSearch')?.addEventListener('input', debouncedConn);
        document.getElementById('platIntConnStatus')?.addEventListener('change', renderConnections);
        document.getElementById('platIntConnCategory')?.addEventListener('change', renderConnections);
    }

    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        initFilters();
        loadDashboard();
    });
    document.addEventListener('plat:refresh', loadDashboard);
})();
