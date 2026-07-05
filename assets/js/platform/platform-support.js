(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    let debounceTimer = null;
    let agents = [];
    let tenants = [];

    const STATUS_I18N = {
        open: 'plat_support_status_open',
        in_progress: 'plat_support_status_in_progress',
        waiting: 'plat_support_status_waiting',
        resolved: 'plat_support_status_resolved',
        closed: 'plat_support_status_closed',
    };

    const PRIORITY_I18N = {
        low: 'plat_support_priority_low',
        normal: 'plat_support_priority_normal',
        high: 'plat_support_priority_high',
        urgent: 'plat_support_priority_urgent',
    };

    const REASON_I18N = {
        suspended: 'plat_support_reason_suspended',
        trial_ending: 'plat_support_reason_trial_ending',
        past_due: 'plat_support_reason_past_due',
    };

    const ACTION_I18N = {
        'tenant.impersonate_start': 'plat_support_action_impersonate_start',
        'tenant.impersonate_end': 'plat_support_action_impersonate_end',
        'tenant.suspend': 'plat_support_action_suspend',
        'tenant.restore': 'plat_support_action_restore',
        'tenant.extend_trial': 'plat_support_action_extend_trial',
        'tenant.change_plan': 'plat_support_action_change_plan',
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

    function formatDateTime(value) {
        if (!value) return '—';
        try {
            return new Date(value).toLocaleString(cfg.locale || undefined);
        } catch (e) {
            return '—';
        }
    }

    function showError(msg) {
        const el = document.getElementById('platSupportError');
        const text = document.getElementById('platSupportErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_support_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platSupportError');
        if (el) el.hidden = true;
    }

    function showAlert(msg) {
        const el = document.getElementById('platSupportAlert');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => { el.hidden = true; }, 3500);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platSupportKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(n) {
        const el = document.getElementById('platSupportCount');
        if (!el) return;
        const template = t('plat_support_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(n)) : `${n}`;
    }

    function updateClearBtn() {
        const search = document.getElementById('platSupportSearch')?.value || '';
        const status = document.getElementById('platSupportStatusFilter')?.value || '';
        const priority = document.getElementById('platSupportPriorityFilter')?.value || '';
        const btn = document.getElementById('platSupportClearFilters');
        if (btn) btn.hidden = !search && !status && !priority;
    }

    function statusPill(status) {
        const safe = esc(status || 'open');
        return `<span class="plat-support-badge-pill plat-support-badge-pill--${safe}">${esc(label(STATUS_I18N, status))}</span>`;
    }

    function priorityPill(priority) {
        const safe = esc(priority || 'normal');
        return `<span class="plat-support-badge-pill plat-support-badge-pill--${safe}">${esc(label(PRIORITY_I18N, priority))}</span>`;
    }

    function companyHref(id) {
        return `../companies/view.php?id=${encodeURIComponent(id)}`;
    }

    function renderAttention(rows) {
        const body = document.getElementById('platSupportAttention');
        if (!body) return;

        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="4" class="plat-support-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }

        body.innerHTML = rows.map((row) => {
            const reason = label(REASON_I18N, row.reason);
            const detail = row.detail ? ` — ${esc(row.detail)}` : '';
            return `<tr>
                <td>
                    <strong>${esc(row.name || '—')}</strong>
                    ${row.slug ? `<br><span class="plat-support-muted">${esc(row.slug)}</span>` : ''}
                </td>
                <td>${esc(row.status || '—')}</td>
                <td><span class="plat-support-reason">${esc(reason)}</span>${detail}</td>
                <td>
                    <a class="plat-support-action-link" href="${companyHref(row.id)}" title="${esc(t('plat_view_detail'))}">
                        <span class="material-icons-round" aria-hidden="true">open_in_new</span>
                    </a>
                </td>
            </tr>`;
        }).join('');
    }

    function renderActions(rows) {
        const el = document.getElementById('platSupportActions');
        if (!el) return;

        if (!rows?.length) {
            el.innerHTML = `<p class="plat-support-muted">${esc(t('plat_no_data'))}</p>`;
            return;
        }

        el.innerHTML = rows.map((row) => {
            const action = label(ACTION_I18N, row.action) || row.action;
            const who = row.platform_user_name || '—';
            const org = row.tenant_name ? ` · ${row.tenant_name}` : '';
            return `<div class="plat-support-action-item">
                <strong>${esc(action)}</strong>
                <span>${esc(who)}${esc(org)} · ${esc(formatDateTime(row.created_at))}</span>
            </div>`;
        }).join('');
    }

    function renderTickets(rows) {
        const body = document.getElementById('platSupportTickets');
        if (!body) return;

        if (!rows?.length) {
            body.innerHTML = `<tr><td colspan="7" class="plat-support-muted">${esc(t('plat_no_data'))}</td></tr>`;
            return;
        }

        body.innerHTML = rows.map((row) => {
            const tenantCell = row.tenant_name
                ? `<strong>${esc(row.tenant_name)}</strong>${row.tenant_slug ? `<br><span class="plat-support-muted">${esc(row.tenant_slug)}</span>` : ''}`
                : `<span class="plat-support-muted">—</span>`;

            const statusSelect = `<select class="plat-support-status-select" data-id="${esc(String(row.id))}" aria-label="${esc(t('plat_support_change_status'))}">
                ${['open', 'in_progress', 'waiting', 'resolved', 'closed'].map((s) =>
                    `<option value="${s}"${row.status === s ? ' selected' : ''}>${esc(label(STATUS_I18N, s))}</option>`
                ).join('')}
            </select>`;

            return `<tr>
                <td>
                    <strong>${esc(row.ticket_number || '—')}</strong>
                    <br><span class="plat-support-muted">${esc(row.subject || '')}</span>
                </td>
                <td>${tenantCell}</td>
                <td>${statusSelect}</td>
                <td>${priorityPill(row.priority)}</td>
                <td>${esc(row.assignee_name || '—')}</td>
                <td>${esc(formatDateTime(row.updated_at || row.created_at))}</td>
                <td>
                    <div class="plat-support-row-actions">
                        ${row.tenant_id ? `<a class="plat-support-action-link" href="${companyHref(row.tenant_id)}" title="${esc(t('plat_view_detail'))}">
                            <span class="material-icons-round" aria-hidden="true">business</span>
                        </a>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');

        body.querySelectorAll('.plat-support-status-select').forEach((sel) => {
            sel.addEventListener('change', async () => {
                const id = sel.getAttribute('data-id');
                const status = sel.value;
                try {
                    const res = await apiPost(`support/${id}/status`, { status });
                    if (res.status !== 'success') throw new Error(t('action_error'));
                    showAlert(t('action_success'));
                    loadTickets();
                    refreshDashboard();
                } catch (e) {
                    showError(e.message || t('action_error'));
                }
            });
        });
    }

    function populateSelects() {
        const tenantSel = document.getElementById('platSupportTenant');
        const assigneeSel = document.getElementById('platSupportAssignee');
        if (tenantSel) {
            const current = tenantSel.value;
            tenantSel.innerHTML = `<option value="">${esc(t('plat_support_tenant_none'))}</option>` +
                tenants.map((tn) => `<option value="${tn.id}">${esc(tn.name)} (${esc(tn.slug)})</option>`).join('');
            if (current) tenantSel.value = current;
        }
        if (assigneeSel) {
            const current = assigneeSel.value;
            assigneeSel.innerHTML = `<option value="">${esc(t('plat_support_assignee_none'))}</option>` +
                agents.map((a) => `<option value="${a.id}">${esc(a.name)}</option>`).join('');
            if (current) assigneeSel.value = current;
        }
    }

    function ticketsQuery() {
        const params = new URLSearchParams();
        const q = document.getElementById('platSupportSearch')?.value?.trim();
        const status = document.getElementById('platSupportStatusFilter')?.value;
        const priority = document.getElementById('platSupportPriorityFilter')?.value;
        if (q) params.set('q', q);
        if (status) params.set('status', status);
        if (priority) params.set('priority', priority);
        params.set('per_page', '50');
        return `support/tickets?${params.toString()}`;
    }

    async function loadTickets() {
        try {
            const res = await apiGet(ticketsQuery());
            if (res.status !== 'success') throw new Error(res.message || t('plat_support_load_error'));
            const payload = res.data || {};
            const rows = Array.isArray(payload) ? payload : (payload.tickets || []);
            renderTickets(rows);
            updateCount(rows.length);
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    async function refreshDashboard() {
        hideError();
        setKpiLoading(true);

        try {
            const res = await apiGet('support/dashboard');
            if (res.status !== 'success') throw new Error(res.message || t('plat_support_load_error'));

            const data = res.data || {};
            const stats = data.stats || {};

            document.getElementById('platSupKpiOpen').textContent = String(stats.open ?? 0);
            document.getElementById('platSupKpiProgress').textContent = String(stats.in_progress ?? 0);
            document.getElementById('platSupKpiWaiting').textContent = String(stats.waiting ?? 0);
            document.getElementById('platSupKpiResolved').textContent = String(stats.resolved_month ?? 0);
            document.getElementById('platSupKpiAttention').textContent = String(stats.attention ?? 0);

            agents = data.agents || [];
            tenants = data.tenants || [];
            populateSelects();
            renderAttention(data.attention_queue || []);
            renderActions(data.recent_actions || []);
            renderTickets(data.recent_tickets || []);
            updateCount((data.recent_tickets || []).length);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    function openModal() {
        document.getElementById('platSupportModal').hidden = false;
        document.getElementById('platSupportSubject')?.focus();
    }

    function closeModal() {
        document.getElementById('platSupportModal').hidden = true;
        document.getElementById('platSupportForm')?.reset();
    }

    document.getElementById('platSupportAddOpen')?.addEventListener('click', openModal);
    document.getElementById('platSupportModalClose')?.addEventListener('click', closeModal);
    document.getElementById('platSupportModalBackdrop')?.addEventListener('click', closeModal);
    document.getElementById('platSupportModalCancel')?.addEventListener('click', closeModal);

    document.getElementById('platSupportForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const body = {
            subject: form.subject?.value?.trim(),
            description: form.description?.value?.trim(),
            priority: form.priority?.value,
            category: form.category?.value,
            tenant_id: form.tenant_id?.value ? Number(form.tenant_id.value) : null,
            assigned_to: form.assigned_to?.value ? Number(form.assigned_to.value) : null,
        };

        try {
            const res = await apiPost('support/tickets', body);
            if (res.status !== 'success') throw new Error(res.message || t('action_error'));
            closeModal();
            showAlert(t('action_success'));
            await refreshDashboard();
            await loadTickets();
        } catch (err) {
            showError(err.message || t('action_error'));
        }
    });

    document.getElementById('platSupportSearch')?.addEventListener('input', () => {
        updateClearBtn();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadTickets, 300);
    });

    document.getElementById('platSupportStatusFilter')?.addEventListener('change', () => {
        updateClearBtn();
        loadTickets();
    });

    document.getElementById('platSupportPriorityFilter')?.addEventListener('change', () => {
        updateClearBtn();
        loadTickets();
    });

    document.getElementById('platSupportClearFilters')?.addEventListener('click', () => {
        const search = document.getElementById('platSupportSearch');
        const status = document.getElementById('platSupportStatusFilter');
        const priority = document.getElementById('platSupportPriorityFilter');
        if (search) search.value = '';
        if (status) status.value = '';
        if (priority) priority.value = '';
        updateClearBtn();
        loadTickets();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });

    document.addEventListener('DOMContentLoaded', refreshDashboard);
    document.addEventListener('plat:refresh', refreshDashboard);
})();
