(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    let debounceTimer = null;

    const STATUS_I18N = {
        active: 'plat_licenses_status_active',
        revoked: 'plat_licenses_status_revoked',
        expired: 'plat_licenses_status_expired',
    };

    const TYPE_I18N = {
        cloud: 'plat_licenses_type_cloud',
        on_prem: 'plat_licenses_type_on_prem',
        partner: 'plat_licenses_type_partner',
        trial: 'plat_licenses_type_trial',
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

    function badge(status) {
        const safe = esc(status || 'active');
        return `<span class="plat-badge plat-badge--${safe}">${esc(label(STATUS_I18N, status))}</span>`;
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
        const el = document.getElementById('platLicensesError');
        const text = document.getElementById('platLicensesErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_licenses_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platLicensesError');
        if (el) el.hidden = true;
    }

    function showAlert(msg) {
        const el = document.getElementById('platLicensesAlert');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => {
            el.hidden = true;
        }, 3500);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platLicensesKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platLicensesCount');
        if (!el) return;
        const template = t('plat_licenses_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearButton() {
        const search = document.getElementById('platLicensesSearch')?.value || '';
        const status = document.getElementById('platLicensesStatusFilter')?.value || '';
        const type = document.getElementById('platLicensesTypeFilter')?.value || '';
        const btn = document.getElementById('platLicensesClearFilters');
        if (btn) btn.hidden = !search && !status && !type;
    }

    function listQuery() {
        const params = new URLSearchParams();
        const q = document.getElementById('platLicensesSearch')?.value?.trim();
        const status = document.getElementById('platLicensesStatusFilter')?.value;
        const type = document.getElementById('platLicensesTypeFilter')?.value;
        if (q) params.set('q', q);
        if (status) params.set('status', status);
        if (type) params.set('type', type);
        params.set('per_page', '50');
        const qs = params.toString();
        return qs ? `licenses?${qs}` : 'licenses?per_page=50';
    }

    async function loadStats() {
        const res = await apiGet('licenses/stats');
        if (res.status !== 'success') return;
        const stats = res.data || {};
        document.getElementById('platLicKpiTotal').textContent = String(stats.total ?? 0);
        document.getElementById('platLicKpiActive').textContent = String(stats.active ?? 0);
        document.getElementById('platLicKpiExpiring').textContent = String(stats.expiring ?? 0);
        document.getElementById('platLicKpiRevoked').textContent = String(stats.revoked ?? 0);
    }

    async function loadLicenses() {
        hideError();
        const body = document.getElementById('platLicensesBody');
        const empty = document.getElementById('platLicensesEmpty');
        const wrap = document.querySelector('.plat-licenses-table-wrap');
        if (!body) return;

        body.innerHTML = `<tr class="plat-licenses-loading-row"><td colspan="8">
            <span class="plat-licenses-loading">
                <span class="plat-licenses-spinner" aria-hidden="true"></span>
                ${esc(t('loading'))}…
            </span>
        </td></tr>`;
        if (empty) empty.hidden = true;
        if (wrap) wrap.hidden = false;

        const res = await apiGet(listQuery());
        if (res.status !== 'success') {
            throw new Error(res.message || t('plat_licenses_load_error'));
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
            const tenantLabel = row.tenant_name
                ? esc(row.tenant_name)
                : `<span class="plat-licenses-muted">${esc(t('plat_licenses_unassigned'))}</span>`;
            const seats = row.max_seats != null ? esc(String(row.max_seats)) : '—';
            const canRevoke = row.status === 'active';
            const orgLink = row.tenant_id
                ? `<a class="plat-licenses-action-btn" href="../companies/view.php?id=${encodeURIComponent(row.tenant_id)}" title="${esc(t('plat_licenses_view_org'))}">
                        <span class="material-icons-round" aria-hidden="true">business</span>
                   </a>`
                : '';

            return `<tr>
                <td><code>${esc(row.key_prefix)}…</code></td>
                <td>${tenantLabel}</td>
                <td>${esc(label(TYPE_I18N, row.license_type))}</td>
                <td>${esc(row.plan_code || '—')}</td>
                <td>${seats}</td>
                <td>${badge(row.status)}</td>
                <td>${esc(formatDate(row.expires_at))}</td>
                <td>
                    <div class="plat-licenses-actions">
                        ${orgLink}
                        ${canRevoke ? `<button type="button" class="plat-licenses-action-btn plat-licenses-action-btn--danger plat-lic-revoke-btn" data-id="${esc(String(row.id))}" title="${esc(t('plat_licenses_revoke'))}">
                            <span class="material-icons-round" aria-hidden="true">block</span>
                        </button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');

        body.querySelectorAll('.plat-lic-revoke-btn').forEach((btn) => {
            btn.addEventListener('click', () => revokeLicense(btn.dataset.id));
        });
    }

    async function revokeLicense(id) {
        if (!id || !window.confirm(t('plat_licenses_confirm_revoke'))) return;
        const res = await apiPost(`licenses/${id}/revoke`, {});
        if (res.status === 'success') {
            showAlert(t('action_success'));
            await refresh();
        } else {
            showError(res.message || t('action_error'));
        }
    }

    async function loadIssueFormOptions() {
        const [tenantsRes, plansRes] = await Promise.all([
            apiGet('tenants?per_page=100'),
            apiGet('plans'),
        ]);

        const tenantSel = document.getElementById('platLicIssueTenant');
        if (tenantSel && tenantsRes.status === 'success') {
            const opts = (tenantsRes.data || []).map((row) =>
                `<option value="${esc(String(row.id))}">${esc(row.name)} (${esc(row.slug)})</option>`
            ).join('');
            tenantSel.innerHTML = `<option value="">${esc(t('plat_licenses_tenant_none'))}</option>${opts}`;
        }

        const planSel = document.getElementById('platLicIssuePlan');
        if (planSel && plansRes.status === 'success') {
            const opts = (plansRes.data || []).map((p) =>
                `<option value="${esc(p.code)}">${esc(p.name || p.code)}</option>`
            ).join('');
            planSel.innerHTML = `<option value="">—</option>${opts}`;
        }
    }

    function openIssueDialog() {
        const dialog = document.getElementById('platLicensesIssueDialog');
        if (!dialog) return;
        document.getElementById('platLicensesIssueForm')?.reset();
        loadIssueFormOptions().catch(() => {});
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
    }

    function closeIssueDialog() {
        const dialog = document.getElementById('platLicensesIssueDialog');
        if (!dialog) return;
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    }

    function showRevealDialog(rawKey) {
        const dialog = document.getElementById('platLicensesRevealDialog');
        const code = document.getElementById('platLicensesRevealKey');
        if (!dialog || !code) return;
        code.textContent = rawKey;
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
    }

    async function submitIssue(e) {
        e.preventDefault();
        const btn = document.getElementById('platLicensesIssueSubmit');
        if (btn) btn.disabled = true;

        const tenantVal = document.getElementById('platLicIssueTenant')?.value;
        const seatsVal = document.getElementById('platLicIssueSeats')?.value;
        const expiresVal = document.getElementById('platLicIssueExpires')?.value;

        const payload = {
            license_type: document.getElementById('platLicIssueType')?.value || 'cloud',
            plan_code: document.getElementById('platLicIssuePlan')?.value || null,
            notes: document.getElementById('platLicIssueNotes')?.value?.trim() || null,
        };
        if (tenantVal) payload.tenant_id = parseInt(tenantVal, 10);
        if (seatsVal) payload.max_seats = parseInt(seatsVal, 10);
        if (expiresVal) payload.expires_at = expiresVal;

        try {
            const res = await apiPost('licenses', payload);
            if (res.status !== 'success') {
                showError(res.message || t('action_error'));
                return;
            }
            closeIssueDialog();
            showAlert(t('action_success'));
            if (res.data?.raw_key) {
                showRevealDialog(res.data.raw_key);
            }
            await refresh();
        } catch (err) {
            showError(err.message || t('action_error'));
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function refresh() {
        setKpiLoading(true);
        try {
            await Promise.all([loadStats(), loadLicenses()]);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    document.getElementById('platLicensesIssueOpen')?.addEventListener('click', openIssueDialog);
    document.getElementById('platLicensesIssueClose')?.addEventListener('click', closeIssueDialog);
    document.getElementById('platLicensesIssueCancel')?.addEventListener('click', closeIssueDialog);
    document.getElementById('platLicensesIssueForm')?.addEventListener('submit', submitIssue);

    document.getElementById('platLicensesRevealClose')?.addEventListener('click', () => {
        document.getElementById('platLicensesRevealDialog')?.close?.();
    });

    document.getElementById('platLicensesCopyKey')?.addEventListener('click', async () => {
        const key = document.getElementById('platLicensesRevealKey')?.textContent || '';
        try {
            await navigator.clipboard.writeText(key);
            showAlert(t('plat_licenses_key_copied'));
        } catch (e) {
            showError(t('action_error'));
        }
    });

    document.getElementById('platLicensesSearch')?.addEventListener('input', () => {
        updateClearButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            loadLicenses().catch((e) => showError(e.message));
        }, 300);
    });

    document.getElementById('platLicensesStatusFilter')?.addEventListener('change', () => {
        updateClearButton();
        loadLicenses().catch((e) => showError(e.message));
    });

    document.getElementById('platLicensesTypeFilter')?.addEventListener('change', () => {
        updateClearButton();
        loadLicenses().catch((e) => showError(e.message));
    });

    document.getElementById('platLicensesClearFilters')?.addEventListener('click', () => {
        const search = document.getElementById('platLicensesSearch');
        const status = document.getElementById('platLicensesStatusFilter');
        const type = document.getElementById('platLicensesTypeFilter');
        if (search) search.value = '';
        if (status) status.value = '';
        if (type) type.value = '';
        updateClearButton();
        loadLicenses().catch((e) => showError(e.message));
    });

    document.addEventListener('DOMContentLoaded', () => {
        refresh();
    });

    document.addEventListener('plat:refresh', refresh);
})();
