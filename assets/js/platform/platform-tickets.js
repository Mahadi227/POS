(function () {
    'use strict';

    const cfg = window.PLATFORM_CONFIG || {};
    const { apiGet, apiPost, t, setLastUpdated } = window.PlatformAPI || {};

    let debounceTimer = null;
    let agents = [];
    let tenants = [];
    let activeTicketId = 0;

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

    const CATEGORY_I18N = {
        billing: 'plat_support_cat_billing',
        technical: 'plat_support_cat_technical',
        onboarding: 'plat_support_cat_onboarding',
        account: 'plat_support_cat_account',
        other: 'plat_support_cat_other',
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
        const el = document.getElementById('platTicketsError');
        const text = document.getElementById('platTicketsErrorText');
        if (!el || !text) return;
        text.textContent = msg || t('plat_tickets_load_error');
        el.hidden = false;
    }

    function hideError() {
        const el = document.getElementById('platTicketsError');
        if (el) el.hidden = true;
    }

    function showAlert(msg) {
        const el = document.getElementById('platTicketsAlert');
        if (!el) return;
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => { el.hidden = true; }, 3500);
    }

    function setKpiLoading(loading) {
        document.querySelectorAll('#platTicketsKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function updateCount(total) {
        const el = document.getElementById('platTicketsCount');
        if (!el) return;
        const template = t('plat_tickets_count');
        el.textContent = template.includes('%d') ? template.replace('%d', String(total)) : `${total}`;
    }

    function updateClearBtn() {
        const search = document.getElementById('platTicketsSearch')?.value || '';
        const status = document.getElementById('platTicketsStatusFilter')?.value || '';
        const priority = document.getElementById('platTicketsPriorityFilter')?.value || '';
        const btn = document.getElementById('platTicketsClearFilters');
        if (btn) btn.hidden = !search && !status && !priority;
    }

    function pill(type, value) {
        const safe = esc(value || '');
        return `<span class="plat-tickets-pill plat-tickets-pill--${safe}">${esc(label(type === 'status' ? STATUS_I18N : PRIORITY_I18N, value))}</span>`;
    }

    function populateSelectOptions(select, items, noneLabel, selected) {
        if (!select) return;
        const opts = [`<option value="">${esc(noneLabel)}</option>`];
        items.forEach((item) => {
            const sel = String(selected) === String(item.id) ? ' selected' : '';
            opts.push(`<option value="${item.id}"${sel}>${esc(item.name)}</option>`);
        });
        select.innerHTML = opts.join('');
    }

    function populateFormSelects() {
        populateSelectOptions(
            document.getElementById('platTicketsCreateTenant'),
            tenants,
            t('plat_support_tenant_none'),
            ''
        );
        populateSelectOptions(
            document.getElementById('platTicketsCreateAssignee'),
            agents,
            t('plat_support_assignee_none'),
            ''
        );
    }

    function renderKpis(stats) {
        document.getElementById('platTktKpiTotal').textContent = String(stats.total ?? 0);
        document.getElementById('platTktKpiOpen').textContent = String(stats.open ?? 0);
        document.getElementById('platTktKpiProgress').textContent = String(stats.in_progress ?? 0);
        document.getElementById('platTktKpiWaiting').textContent = String(stats.waiting ?? 0);
        document.getElementById('platTktKpiResolved').textContent = String(stats.resolved_month ?? 0);
    }

    function renderTickets(rows, total) {
        const body = document.getElementById('platTicketsBody');
        const empty = document.getElementById('platTicketsEmpty');
        if (!body) return;

        updateCount(total ?? rows.length);

        if (!rows?.length) {
            body.innerHTML = '';
            if (empty) empty.hidden = false;
            return;
        }

        if (empty) empty.hidden = true;
        body.innerHTML = rows.map((row) => {
            const tenantCell = row.tenant_name
                ? `<strong>${esc(row.tenant_name)}</strong>${row.tenant_slug ? `<br><span class="plat-tickets-muted">${esc(row.tenant_slug)}</span>` : ''}`
                : `<span class="plat-tickets-muted">—</span>`;

            return `<tr>
                <td>
                    <strong>${esc(row.ticket_number || '—')}</strong>
                    <br><span class="plat-tickets-muted">${esc(row.subject || '')}</span>
                </td>
                <td>${tenantCell}</td>
                <td>${pill('status', row.status)}</td>
                <td>${pill('priority', row.priority)}</td>
                <td>${esc(label(CATEGORY_I18N, row.category))}</td>
                <td>${esc(row.assignee_name || '—')}</td>
                <td>${esc(formatDateTime(row.updated_at || row.created_at))}</td>
                <td>
                    <button type="button" class="plat-tickets-open-btn" data-open-ticket="${esc(String(row.id))}" title="${esc(t('plat_tickets_open_detail'))}">
                        <span class="material-icons-round" aria-hidden="true">open_in_new</span>
                    </button>
                </td>
            </tr>`;
        }).join('');

        body.querySelectorAll('[data-open-ticket]').forEach((btn) => {
            btn.addEventListener('click', () => openDrawer(Number(btn.getAttribute('data-open-ticket'))));
        });
    }

    function ticketsQuery() {
        const params = new URLSearchParams();
        const q = document.getElementById('platTicketsSearch')?.value?.trim();
        const status = document.getElementById('platTicketsStatusFilter')?.value;
        const priority = document.getElementById('platTicketsPriorityFilter')?.value;
        if (q) params.set('q', q);
        if (status) params.set('status', status);
        if (priority) params.set('priority', priority);
        params.set('per_page', '100');
        return `support/tickets?${params.toString()}`;
    }

    async function loadTickets() {
        hideError();
        try {
            const res = await apiGet(ticketsQuery());
            if (res.status !== 'success') throw new Error(res.message || t('plat_tickets_load_error'));

            const payload = res.data || {};
            const rows = payload.tickets || [];
            const meta = payload.meta || {};
            agents = meta.agents || agents;
            tenants = meta.tenants || tenants;
            populateFormSelects();

            if (meta.stats) renderKpis(meta.stats);
            renderTickets(rows, payload.total ?? rows.length);
            setLastUpdated?.();
        } catch (e) {
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    function fillStatusSelect(select, selected) {
        if (!select) return;
        select.innerHTML = ['open', 'in_progress', 'waiting', 'resolved', 'closed'].map((s) => {
            const sel = s === selected ? ' selected' : '';
            return `<option value="${s}"${sel}>${esc(label(STATUS_I18N, s))}</option>`;
        }).join('');
    }

    function renderReplies(replies) {
        const el = document.getElementById('platTicketsReplies');
        if (!el) return;

        if (!replies?.length) {
            el.innerHTML = `<p class="plat-tickets-muted">${esc(t('plat_tickets_no_replies'))}</p>`;
            return;
        }

        el.innerHTML = replies.map((reply) => {
            const internalCls = Number(reply.is_internal) === 1 ? ' is-internal' : '';
            return `<article class="plat-tickets-reply${internalCls}">
                <div class="plat-tickets-reply__head">
                    <strong>${esc(reply.author_name || '—')}</strong>
                    <span>${esc(formatDateTime(reply.created_at))}</span>
                </div>
                <p>${esc(reply.message || '')}</p>
            </article>`;
        }).join('');
    }

    async function openDrawer(ticketId) {
        if (!ticketId) return;
        activeTicketId = ticketId;

        const drawer = document.getElementById('platTicketsDrawer');
        drawer.hidden = false;

        document.getElementById('platTicketsDrawerNumber').textContent = t('loading') + '…';
        document.getElementById('platTicketsDrawerTitle').textContent = t('plat_tickets_detail_title');
        document.getElementById('platTicketsDrawerMeta').textContent = '';
        document.getElementById('platTicketsDrawerDesc').textContent = '—';
        document.getElementById('platTicketsReplies').innerHTML = `<p class="plat-tickets-muted">${esc(t('loading'))}…</p>`;

        try {
            const res = await apiGet(`support/${ticketId}`);
            if (res.status !== 'success') throw new Error(res.message || t('plat_tickets_load_error'));

            const ticket = res.data || {};
            document.getElementById('platTicketsDrawerNumber').textContent = ticket.ticket_number || '—';
            document.getElementById('platTicketsDrawerTitle').textContent = ticket.subject || '—';

            const metaParts = [
                ticket.tenant_name ? `${ticket.tenant_name}` : null,
                ticket.creator_name ? `${t('plat_tickets_creator')}: ${ticket.creator_name}` : null,
                `${t('plat_tickets_created')}: ${formatDateTime(ticket.created_at)}`,
            ].filter(Boolean);
            document.getElementById('platTicketsDrawerMeta').textContent = metaParts.join(' · ');

            document.getElementById('platTicketsDrawerDesc').textContent = ticket.description || t('plat_no_data');

            const statusSel = document.getElementById('platTicketsDrawerStatus');
            const assigneeSel = document.getElementById('platTicketsDrawerAssignee');
            fillStatusSelect(statusSel, ticket.status);
            populateSelectOptions(assigneeSel, agents, t('plat_support_assignee_none'), ticket.assigned_to || '');

            renderReplies(ticket.replies || []);
        } catch (e) {
            showError(e.message || t('load_error'));
            drawer.hidden = true;
        }
    }

    function closeDrawer() {
        document.getElementById('platTicketsDrawer').hidden = true;
        activeTicketId = 0;
        document.getElementById('platTicketsReplyMessage').value = '';
        document.getElementById('platTicketsReplyInternal').checked = false;
    }

    function openCreateModal() {
        document.getElementById('platTicketsCreateModal').hidden = false;
    }

    function closeCreateModal() {
        document.getElementById('platTicketsCreateModal').hidden = true;
        document.getElementById('platTicketsCreateForm')?.reset();
    }

    document.getElementById('platTicketsAddOpen')?.addEventListener('click', openCreateModal);
    document.querySelectorAll('[data-close-modal]').forEach((el) => {
        el.addEventListener('click', closeCreateModal);
    });
    document.querySelectorAll('[data-close-drawer]').forEach((el) => {
        el.addEventListener('click', closeDrawer);
    });

    document.getElementById('platTicketsCreateForm')?.addEventListener('submit', async (e) => {
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
            closeCreateModal();
            showAlert(t('action_success'));
            await loadTickets();
        } catch (err) {
            showError(err.message || t('action_error'));
        }
    });

    document.getElementById('platTicketsDrawerStatus')?.addEventListener('change', async (e) => {
        if (!activeTicketId) return;
        try {
            const res = await apiPost(`support/${activeTicketId}/status`, { status: e.target.value });
            if (res.status !== 'success') throw new Error(t('action_error'));
            showAlert(t('action_success'));
            await loadTickets();
        } catch (err) {
            showError(err.message || t('action_error'));
        }
    });

    document.getElementById('platTicketsDrawerAssignee')?.addEventListener('change', async (e) => {
        if (!activeTicketId) return;
        try {
            const res = await apiPost(`support/${activeTicketId}/assign`, {
                assigned_to: e.target.value ? Number(e.target.value) : null,
            });
            if (res.status !== 'success') throw new Error(t('action_error'));
            showAlert(t('action_success'));
            await loadTickets();
            await openDrawer(activeTicketId);
        } catch (err) {
            showError(err.message || t('action_error'));
        }
    });

    document.getElementById('platTicketsReplyForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!activeTicketId) return;

        const message = document.getElementById('platTicketsReplyMessage')?.value?.trim();
        const isInternal = document.getElementById('platTicketsReplyInternal')?.checked;

        try {
            const res = await apiPost(`support/${activeTicketId}/reply`, { message, is_internal: isInternal });
            if (res.status !== 'success') throw new Error(res.message || t('action_error'));
            document.getElementById('platTicketsReplyMessage').value = '';
            document.getElementById('platTicketsReplyInternal').checked = false;
            showAlert(t('action_success'));
            await openDrawer(activeTicketId);
            await loadTickets();
        } catch (err) {
            showError(err.message || t('action_error'));
        }
    });

    document.getElementById('platTicketsSearch')?.addEventListener('input', () => {
        updateClearBtn();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadTickets, 300);
    });

    document.getElementById('platTicketsStatusFilter')?.addEventListener('change', () => {
        updateClearBtn();
        loadTickets();
    });

    document.getElementById('platTicketsPriorityFilter')?.addEventListener('change', () => {
        updateClearBtn();
        loadTickets();
    });

    document.getElementById('platTicketsClearFilters')?.addEventListener('click', () => {
        document.getElementById('platTicketsSearch').value = '';
        document.getElementById('platTicketsStatusFilter').value = '';
        document.getElementById('platTicketsPriorityFilter').value = '';
        updateClearBtn();
        loadTickets();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeCreateModal();
            closeDrawer();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        setKpiLoading(true);
        loadTickets();
    });
    document.addEventListener('plat:refresh', loadTickets);
})();
