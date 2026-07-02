/**
 * Cash transfers — filters, stats, workflow (approve / complete), new transfer modal
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crTrRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = CashRegistersUI;
    const PAGE_SIZE = 15;

    const state = { items: [], status: 'all', search: '', page: 1 };

    const els = {
        search: document.getElementById('crTrSearch'),
        searchClear: document.getElementById('crTrSearchClear'),
        dateFrom: document.getElementById('crTrDateFrom'),
        dateTo: document.getElementById('crTrDateTo'),
        statusFilters: document.getElementById('crTrStatusFilters'),
        stats: document.getElementById('crTrStats'),
        count: document.getElementById('crTrCount'),
        pendingBadge: document.getElementById('crTrPendingBadge'),
        statPending: document.getElementById('crTrStatPending'),
        statApproved: document.getElementById('crTrStatApproved'),
        statCompleted: document.getElementById('crTrStatCompleted'),
        statAmount: document.getElementById('crTrStatAmount'),
        meta: document.getElementById('crTrMeta'),
        pagePrev: document.getElementById('crTrPrev'),
        pageNext: document.getElementById('crTrNext'),
        pageInfo: document.getElementById('crTrPageInfo'),
        newBtn: document.getElementById('crTrNewBtn'),
        refreshBtn: document.getElementById('crTrRefreshBtn'),
        modal: document.getElementById('crTrModal'),
        modalForm: document.getElementById('crTrModalForm'),
        modalAmount: document.getElementById('crTrAmount'),
        modalReason: document.getElementById('crTrReason'),
        modalSubmit: document.getElementById('crTrModalSubmit'),
    };

    function statusLabel(status) {
        const map = {
            pending: t('cr_filter_pending'),
            approved: t('cr_filter_approved'),
            completed: t('status_completed'),
        };
        return map[status] || status;
    }

    function statusClass(status) {
        if (status === 'completed') return 'ok';
        if (status === 'approved') return 'info';
        if (status === 'pending') return 'warn';
        return 'neutral';
    }

    function transferTypeLabel(type) {
        const map = { register_to_safe: t('cr_tr_type_register_to_safe') };
        return map[type] || String(type || '—').replace(/_/g, ' ');
    }

    function filteredItems() {
        let list = [...state.items];
        const q = state.search.trim().toLowerCase();
        if (state.status !== 'all') list = list.filter((tr) => tr.status === state.status);
        if (q) {
            list = list.filter((tr) => {
                const hay = [tr.transfer_type, tr.reason, tr.from_register_name, tr.to_register_name, tr.requested_by_name, tr.status]
                    .map((v) => String(v ?? '').toLowerCase()).join(' ');
                return hay.includes(q);
            });
        }
        return list.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
    }

    function paginated() {
        const all = filteredItems();
        const totalPages = Math.max(1, Math.ceil(all.length / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        const start = (state.page - 1) * PAGE_SIZE;
        return { all, pageItems: all.slice(start, start + PAGE_SIZE), totalPages };
    }

    function updateStats() {
        const all = state.items;
        const pending = all.filter((tr) => tr.status === 'pending');
        const approved = all.filter((tr) => tr.status === 'approved');
        const completed = all.filter((tr) => tr.status === 'completed');
        const openAmount = [...pending, ...approved].reduce((s, tr) => s + Number(tr.amount || 0), 0);

        const set = (el, val) => { if (el) { el.textContent = val; el.classList.remove('is-loading'); } };
        set(els.statPending, String(pending.length));
        set(els.statApproved, String(approved.length));
        set(els.statCompleted, String(completed.length));
        set(els.statAmount, money(openAmount));

        if (els.pendingBadge) {
            if (pending.length) {
                els.pendingBadge.hidden = false;
                els.pendingBadge.textContent = String(pending.length);
            } else {
                els.pendingBadge.hidden = true;
            }
        }
        if (els.count) els.count.textContent = filteredItems().length ? `${filteredItems().length}` : t('cr_no_data');
    }

    function actionButtons(tr) {
        const parts = [];
        if (tr.status === 'pending') {
            parts.push(`<button type="button" class="cr-btn cr-btn--sm" data-tapprove="${tr.id}">${esc(t('cr_recon_approve'))}</button>`);
        }
        if (tr.status === 'approved') {
            parts.push(`<button type="button" class="cr-btn cr-btn--sm" data-tcomplete="${tr.id}">${esc(t('cr_tr_complete'))}</button>`);
        }
        return parts.join(' ');
    }

    function renderTable() {
        const { all, pageItems, totalPages } = paginated();
        updateStats();

        if (els.meta) els.meta.textContent = t('cr_tr_table_summary', all.length, state.page, totalPages);
        if (els.pageInfo) els.pageInfo.textContent = `${state.page} / ${totalPages}`;
        if (els.pagePrev) els.pagePrev.disabled = state.page <= 1;
        if (els.pageNext) els.pageNext.disabled = state.page >= totalPages;

        if (!pageItems.length) {
            root.innerHTML = `<div class="cr-data-empty"><span class="material-icons-round">sync_alt</span><p>${esc(t('cr_no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="cr-data-table-wrap">
                <table class="modern-table cr-data-table">
                    <thead><tr>
                        <th>${esc(t('col_date'))}</th>
                        <th>${esc(t('cr_transfer_type'))}</th>
                        <th>${esc(t('cr_col_register'))}</th>
                        <th>${esc(t('cr_amount'))}</th>
                        <th>${esc(t('col_status'))}</th>
                        <th>${esc(t('cr_reason'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${pageItems.map((tr) => `
                        <tr>
                            <td>${esc(AdminAPI.formatDate(tr.created_at))}</td>
                            <td>${esc(transferTypeLabel(tr.transfer_type))}</td>
                            <td>${esc(tr.from_register_name || '—')}</td>
                            <td><strong>${esc(money(tr.amount))}</strong></td>
                            <td><span class="cr-data-status cr-data-status--${statusClass(tr.status)}">${esc(statusLabel(tr.status))}</span></td>
                            <td>${esc(tr.reason || '—')}</td>
                            <td class="cr-data-actions">${actionButtons(tr)}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="cr-data-list">${pageItems.map((tr) => `
                <article class="cr-data-list__item cr-data-list__item--stack">
                    <div class="cr-data-list__main">
                        <strong>${esc(transferTypeLabel(tr.transfer_type))}</strong>
                        <span>${esc(tr.from_register_name || '—')} · ${esc(AdminAPI.formatDate(tr.created_at))}</span>
                        <span class="cr-data-status cr-data-status--${statusClass(tr.status)}">${esc(statusLabel(tr.status))}</span>
                    </div>
                    <div class="cr-data-list__side">
                        <strong>${esc(money(tr.amount))}</strong>
                        <div class="cr-data-actions">${actionButtons(tr)}</div>
                    </div>
                </article>`).join('')}
            </div>`;

        bindActions();
    }

    function bindActions() {
        root.querySelectorAll('[data-tapprove]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                try {
                    const res = await AdminAPI.approveCashTransfer(Number(btn.dataset.tapprove));
                    if (res.status === 'success') load(); else showError(res.message || t('error'));
                } catch (e) { showError(e.message); }
                finally { btn.disabled = false; }
            });
        });
        root.querySelectorAll('[data-tcomplete]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                btn.disabled = true;
                try {
                    const res = await AdminAPI.completeCashTransfer(Number(btn.dataset.tcomplete));
                    if (res.status === 'success') load(); else showError(res.message || t('error'));
                } catch (e) { showError(e.message); }
                finally { btn.disabled = false; }
            });
        });
    }

    function setStatus(status) {
        state.status = status;
        state.page = 1;
        els.statusFilters?.querySelectorAll('.cr-reg-chip').forEach((chip) => {
            const active = chip.dataset.status === status;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function openModal() {
        if (!els.modal) return;
        els.modal.hidden = false;
        els.modalAmount?.focus();
    }

    function closeModal() {
        if (!els.modal) return;
        els.modal.hidden = true;
        els.modalForm?.reset();
    }

    function initToolbar() {
        els.statusFilters?.querySelectorAll('[data-status]').forEach((chip) => {
            chip.addEventListener('click', () => { setStatus(chip.dataset.status || 'all'); renderTable(); });
        });
        els.stats?.querySelectorAll('[data-stat-filter]').forEach((btn) => {
            btn.addEventListener('click', () => { setStatus(btn.dataset.statFilter || 'all'); renderTable(); });
        });
        els.search?.addEventListener('input', () => { state.search = els.search.value; els.searchClear?.classList.toggle('visible', !!els.search.value); state.page = 1; renderTable(); });
        els.searchClear?.addEventListener('click', () => {
            if (els.search) els.search.value = '';
            els.searchClear?.classList.remove('visible');
            state.search = '';
            state.page = 1;
            renderTable();
        });
        els.dateFrom?.addEventListener('change', () => load());
        els.dateTo?.addEventListener('change', () => load());
        els.newBtn?.addEventListener('click', openModal);
        els.refreshBtn?.addEventListener('click', () => load());
        els.pagePrev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; renderTable(); } });
        els.pageNext?.addEventListener('click', () => { state.page += 1; renderTable(); });
        els.modal?.querySelectorAll('[data-close-modal]').forEach((el) => el.addEventListener('click', closeModal));
        els.modalForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const amount = parseFloat(els.modalAmount?.value || '0');
            if (amount <= 0) { showError(t('error')); return; }
            els.modalSubmit.disabled = true;
            try {
                const res = await AdminAPI.createCashTransfer({
                    store_id: window.ADMIN_PAGE?.storeId,
                    transfer_type: 'register_to_safe',
                    amount,
                    reason: els.modalReason?.value?.trim() || '',
                });
                if (res.status === 'success') { closeModal(); load(); }
                else showError(res.message || t('error'));
            } catch (err) { showError(err.message); }
            finally { els.modalSubmit.disabled = false; }
        });
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        document.querySelectorAll('#crTrStats .cr-data-stat__value').forEach((el) => el.classList.add('is-loading'));
        try {
            const res = await AdminAPI.getCashTransfers({
                from: els.dateFrom?.value || undefined,
                to: els.dateTo?.value || undefined,
                q: state.search.trim() || undefined,
            });
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            state.items = res.data || [];
            state.page = 1;
            renderTable();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<div class="cr-data-empty"><span class="material-icons-round">error_outline</span><p>${esc(e.message)}</p></div>`;
        }
    }

    initToolbar();
    load();
    document.addEventListener('cr:refresh', load);
});
