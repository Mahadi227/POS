(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const bkCfg = window.PLATFORM_BACKUPS || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    let rows = [];
    let tenants = [];
    let debounce = null;

    const STATUS_I18N = {
        pending: 'plat_backups_status_pending',
        running: 'plat_backups_status_running',
        completed: 'plat_backups_status_completed',
        failed: 'plat_backups_status_failed',
    };

    const SCOPE_I18N = {
        full: 'plat_backups_scope_full',
        schema: 'plat_backups_scope_schema',
        tenant: 'plat_backups_scope_tenant',
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

    function fmtSize(bytes) {
        const n = Number(bytes) || 0;
        if (n <= 0) return '—';
        if (n < 1024) return `${n} B`;
        if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
        if (n < 1024 * 1024 * 1024) return `${(n / (1024 * 1024)).toFixed(1)} MB`;
        return `${(n / (1024 * 1024 * 1024)).toFixed(2)} GB`;
    }

    function apiUrl(path) {
        const [base, query] = String(path).split('?');
        const url = `${cfg.apiBase}?request=platform/${base}`;
        return query ? `${url}&${query}` : url;
    }

    async function apiDelete(path) {
        const res = await fetch(apiUrl(path), { method: 'DELETE', credentials: 'same-origin' });
        return res.json();
    }

    function showError(msg) {
        const el = document.getElementById('platBackupsError');
        document.getElementById('platBackupsErrorText').textContent = msg || t('plat_backups_load_error');
        el.hidden = false;
        document.getElementById('platBackupsAlert').hidden = true;
    }

    function showSuccess(msg) {
        const el = document.getElementById('platBackupsAlert');
        el.textContent = msg;
        el.hidden = false;
        document.getElementById('platBackupsError').hidden = true;
        clearTimeout(showSuccess._t);
        showSuccess._t = setTimeout(() => { el.hidden = true; }, 4000);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platBackupsKpiGrid .plat-kpi-card').forEach((c) => {
            c.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platBackupsCount');
        if (!el) return;
        const template = t('plat_backups_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function statusPill(status) {
        const cls = status === 'completed' ? 'success' : (status === 'failed' ? 'failed' : 'neutral');
        return `<span class="plat-gov-pill plat-gov-pill--${cls}">${esc(label(STATUS_I18N, status))}</span>`;
    }

    function renderKpis(stats) {
        document.getElementById('platBkKpiTotal').textContent = String(stats?.total ?? 0);
        document.getElementById('platBkKpiCompleted').textContent = String(stats?.completed ?? 0);
        document.getElementById('platBkKpiFailed').textContent = String(stats?.failed ?? 0);
        document.getElementById('platBkKpiToday').textContent = String(stats?.today ?? 0);
        document.getElementById('platBkKpiStorage').textContent = fmtSize(stats?.total_size ?? 0);
        updateCount(stats?.total ?? 0);
    }

    function filterRows() {
        const q = (document.getElementById('platBackupsSearch')?.value || '').trim().toLowerCase();
        const status = document.getElementById('platBackupsStatusFilter')?.value || '';
        const scope = document.getElementById('platBackupsScopeFilter')?.value || '';

        return rows.filter((r) => {
            if (status && r.status !== status) return false;
            if (scope && r.scope !== scope) return false;
            if (q) {
                const hay = `${r.label} ${r.tenant_name || ''} ${r.triggered_by_name || ''}`.toLowerCase();
                if (!hay.includes(q)) return false;
            }
            return true;
        });
    }

    function renderTable(list) {
        const body = document.getElementById('platBackupsBody');
        if (!list.length) {
            body.innerHTML = `<tr><td colspan="8" class="plat-gov-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }

        body.innerHTML = list.map((r) => {
            const err = r.error_message ? `<small class="plat-backups-error-msg">${esc(r.error_message)}</small>` : '';
            const dl = r.status === 'completed'
                ? `<a href="${esc(apiUrl(`backups/${r.id}/download`))}" class="plat-backups-btn" download><span class="material-icons-round">download</span>${esc(t('plat_backups_download'))}</a>`
                : '';
            const del = bkCfg.canManage
                ? `<button type="button" class="plat-backups-btn plat-backups-btn--danger" data-delete="${r.id}"><span class="material-icons-round">delete</span>${esc(t('plat_backups_delete'))}</button>`
                : '';

            return `<tr>
                <td><strong>${esc(r.label)}</strong>${err}</td>
                <td>${esc(label(SCOPE_I18N, r.scope))}</td>
                <td>${esc(r.tenant_name || '—')}</td>
                <td>${statusPill(r.status)}</td>
                <td>${esc(fmtSize(r.size_bytes))}</td>
                <td>${esc(r.triggered_by_name || '—')}</td>
                <td>${esc(fmt(r.created_at))}</td>
                <td><div class="plat-backups-actions">${dl}${del}</div></td>
            </tr>`;
        }).join('');

        body.querySelectorAll('[data-delete]').forEach((btn) => {
            btn.addEventListener('click', () => deleteBackup(parseInt(btn.dataset.delete, 10)));
        });
    }

    async function loadBackups() {
        setKpiLoading(true);
        hideError?.();
        try {
            const res = await apiGet('backups/dashboard');
            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || t('plat_backups_load_error'));
            }
            rows = res.data.recent || [];
            renderKpis(res.data.stats || {});
            renderTable(filterRows());
            setLastUpdated();
        } catch (e) {
            console.error(e);
            showError(e.message || t('load_error'));
            renderTable([]);
        } finally {
            setKpiLoading(false);
        }
    }

    async function loadTenants() {
        if (!bkCfg.canManage) return;
        try {
            const res = await apiGet('tenants?per_page=100');
            tenants = res.data || [];
            const sel = document.getElementById('platBkTenant');
            if (!sel) return;
            sel.innerHTML = `<option value="">${esc(t('plat_backups_select_tenant'))}</option>`;
            tenants.forEach((tn) => {
                const o = document.createElement('option');
                o.value = tn.id;
                o.textContent = tn.name || tn.slug || `#${tn.id}`;
                sel.appendChild(o);
            });
        } catch (e) {
            console.warn('tenants load', e);
        }
    }

    async function runBackup(e) {
        e.preventDefault();
        const btn = document.getElementById('platBackupsRunBtn');
        const scope = document.getElementById('platBkScope')?.value || 'full';
        const labelVal = document.getElementById('platBkLabel')?.value?.trim() || '';
        const tenantId = document.getElementById('platBkTenant')?.value || '';

        if (scope === 'tenant' && !tenantId) {
            showError(t('plat_backups_select_tenant'));
            return;
        }

        if (btn) btn.disabled = true;
        try {
            const body = { scope, label: labelVal };
            if (scope === 'tenant') body.tenant_id = parseInt(tenantId, 10);

            const res = await apiPost('backups', body);
            if (res.status !== 'success') {
                throw new Error(res.message || t('plat_backups_create_error'));
            }
            document.getElementById('platBackupsDialog')?.close();
            showSuccess(t('plat_backups_create_success'));
            await loadBackups();
        } catch (err) {
            showError(err.message || t('plat_backups_create_error'));
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function deleteBackup(id) {
        if (!confirm(t('plat_backups_confirm_delete'))) return;
        try {
            const res = await apiDelete(`backups/${id}`);
            if (res.status !== 'success') throw new Error(res.message || t('action_error'));
            showSuccess(t('action_success'));
            await loadBackups();
        } catch (e) {
            showError(e.message || t('action_error'));
        }
    }

    function initDialog() {
        const dialog = document.getElementById('platBackupsDialog');
        const scopeSel = document.getElementById('platBkScope');
        const tenantWrap = document.getElementById('platBkTenantWrap');

        document.getElementById('platBackupsCreateOpen')?.addEventListener('click', () => {
            document.getElementById('platBkLabel').value = '';
            scopeSel.value = 'full';
            tenantWrap.hidden = true;
            dialog?.showModal();
        });

        document.getElementById('platBackupsDialogClose')?.addEventListener('click', () => dialog?.close());
        document.getElementById('platBackupsDialogCancel')?.addEventListener('click', () => dialog?.close());
        document.getElementById('platBackupsForm')?.addEventListener('submit', runBackup);

        scopeSel?.addEventListener('change', () => {
            tenantWrap.hidden = scopeSel.value !== 'tenant';
        });
    }

    function initFilters() {
        const rerender = () => renderTable(filterRows());
        document.getElementById('platBackupsSearch')?.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(rerender, 180);
        });
        document.getElementById('platBackupsStatusFilter')?.addEventListener('change', rerender);
        document.getElementById('platBackupsScopeFilter')?.addEventListener('change', rerender);
    }

    function hideError() {
        const el = document.getElementById('platBackupsError');
        if (el) el.hidden = true;
    }

    document.addEventListener('DOMContentLoaded', () => {
        initDialog();
        initFilters();
        loadTenants();
        loadBackups();
    });
    document.addEventListener('plat:refresh', loadBackups);
})();
