/**
 * Cash register reconciliation — filters, stats, grid/table, review workflow
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crReconRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = CashRegistersUI;

    const PAGE_SIZE = 12;
    const VARIANCE_TOLERANCE = 500;

    const state = {
        items: [],
        filter: 'all',
        search: '',
        view: localStorage.getItem('cr-recon-view') || 'grid',
        page: 1,
        dateFrom: '',
        dateTo: '',
    };

    const els = {
        search: document.getElementById('crReconSearch'),
        searchClear: document.getElementById('crReconSearchClear'),
        dateFrom: document.getElementById('crReconDateFrom'),
        dateTo: document.getElementById('crReconDateTo'),
        filters: document.getElementById('crReconFilters'),
        count: document.getElementById('crReconCount'),
        pendingBadge: document.getElementById('crReconPendingBadge'),
        statPending: document.getElementById('crReconStatPending'),
        statApproved: document.getElementById('crReconStatApproved'),
        statRejected: document.getElementById('crReconStatRejected'),
        statVariance: document.getElementById('crReconStatVariance'),
        statToday: document.getElementById('crReconStatToday'),
        meta: document.getElementById('crReconMeta'),
        pagePrev: document.getElementById('crReconPrev'),
        pageNext: document.getElementById('crReconNext'),
        pageInfo: document.getElementById('crReconPageInfo'),
        exportBtn: document.getElementById('crReconExportBtn'),
        refreshBtn: document.getElementById('crReconRefreshBtn'),
        modal: document.getElementById('crReconModal'),
        modalForm: document.getElementById('crReconModalForm'),
        modalSummary: document.getElementById('crReconModalSummary'),
        modalTitle: document.getElementById('crReconModalTitle'),
        modalNote: document.getElementById('crReconModalNote'),
        modalNoteLabel: document.getElementById('crReconNoteLabelText'),
        modalSubmit: document.getElementById('crReconModalSubmit'),
        detailModal: document.getElementById('crReconDetailModal'),
        detailBody: document.getElementById('crReconDetailBody'),
        detailClose: document.getElementById('crReconDetailClose'),
    };

    function statusLabel(status) {
        const map = {
            pending: t('status_pending'),
            approved: t('status_approved'),
            rejected: t('status_rejected'),
        };
        return map[status] || status;
    }

    function statusClass(status) {
        if (status === 'approved') return 'ok';
        if (status === 'rejected') return 'off';
        return 'warn';
    }

    function varianceClass(diff) {
        const v = Math.abs(Number(diff || 0));
        if (v >= VARIANCE_TOLERANCE) return 'is-danger';
        if (v > 0) return 'is-warn';
        return 'is-ok';
    }

    function isToday(dateStr) {
        if (!dateStr) return false;
        const d = new Date(dateStr);
        const now = new Date();
        return d.getFullYear() === now.getFullYear()
            && d.getMonth() === now.getMonth()
            && d.getDate() === now.getDate();
    }

    function inDateRange(createdAt) {
        if (!createdAt) return true;
        const day = String(createdAt).slice(0, 10);
        if (state.dateFrom && day < state.dateFrom) return false;
        if (state.dateTo && day > state.dateTo) return false;
        return true;
    }

    function filteredItems() {
        let list = [...state.items];
        const q = state.search.trim().toLowerCase();

        if (state.filter !== 'all') {
            list = list.filter((r) => r.status === state.filter);
        }

        list = list.filter((r) => inDateRange(r.created_at));

        if (q) {
            list = list.filter((r) => {
                const hay = [r.register_name, r.register_code, r.cashier_name, r.store_name, r.status, r.notes, r.review_note]
                    .map((v) => String(v ?? '').toLowerCase()).join(' ');
                return hay.includes(q);
            });
        }

        return list.sort((a, b) => {
            if (a.status === 'pending' && b.status !== 'pending') return -1;
            if (b.status === 'pending' && a.status !== 'pending') return 1;
            return new Date(b.created_at || 0) - new Date(a.created_at || 0);
        });
    }

    function paginatedItems() {
        const all = filteredItems();
        const totalPages = Math.max(1, Math.ceil(all.length / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        const start = (state.page - 1) * PAGE_SIZE;
        return { items: all.slice(start, start + PAGE_SIZE), total: all.length, totalPages };
    }

    function updateStats(allItems) {
        const filtered = filteredItems();
        const pending = allItems.filter((r) => r.status === 'pending');
        const approved = allItems.filter((r) => r.status === 'approved').length;
        const rejected = allItems.filter((r) => r.status === 'rejected').length;
        const pendingVariance = pending.reduce((sum, r) => sum + Math.abs(Number(r.difference || 0)), 0);
        const todayCount = filtered.filter((r) => isToday(r.created_at)).length;

        const set = (el, val) => { if (el) { el.textContent = val; el.classList.remove('is-loading'); } };

        set(els.statPending, String(pending.length));
        set(els.statApproved, String(approved));
        set(els.statRejected, String(rejected));
        set(els.statVariance, money(pendingVariance));
        set(els.statToday, String(todayCount));

        if (els.pendingBadge) {
            if (pending.length > 0) {
                els.pendingBadge.hidden = false;
                els.pendingBadge.textContent = String(pending.length);
            } else {
                els.pendingBadge.hidden = true;
            }
        }

        if (els.count) {
            els.count.textContent = t('cr_recon_count', String(filtered.length));
        }
    }

    function updatePagination(total, totalPages) {
        if (els.meta) {
            els.meta.textContent = t('cr_recon_table_summary', String(total), String(state.page), String(totalPages));
        }
        if (els.pageInfo) els.pageInfo.textContent = `${state.page} / ${totalPages}`;
        if (els.pagePrev) els.pagePrev.disabled = state.page <= 1;
        if (els.pageNext) els.pageNext.disabled = state.page >= totalPages;
    }

    function actionButtons(r) {
        if (r.status !== 'pending') return '';
        return `
            <button type="button" class="cr-btn cr-btn--sm" data-approve="${r.id}"><span class="material-icons-round">check_circle</span>${esc(t('cr_recon_approve'))}</button>
            <button type="button" class="cr-btn cr-btn--sm cr-btn--warn" data-reject="${r.id}"><span class="material-icons-round">cancel</span>${esc(t('cr_recon_reject'))}</button>`;
    }

    function varianceBadge(r) {
        const cls = varianceClass(r.difference);
        const high = Math.abs(Number(r.difference || 0)) >= VARIANCE_TOLERANCE;
        return `<span class="cr-recon-variance ${cls}">${esc(money(r.difference))}${high ? ` <em class="cr-recon-variance__tag">${esc(t('cr_recon_high_variance'))}</em>` : ''}</span>`;
    }

    function renderCard(r) {
        const diffClass = varianceClass(r.difference);
        const isPending = r.status === 'pending';
        const code = r.register_code ? ` · ${esc(r.register_code)}` : '';
        return `
            <article class="cr-recon-card ${diffClass}${isPending ? ' is-pending' : ''}">
                <header class="cr-recon-card__head">
                    <div class="cr-recon-card__icon"><span class="material-icons-round">account_balance_wallet</span></div>
                    <div class="cr-recon-card__title">
                        <strong>${esc(r.register_name)}</strong>
                        <span class="cr-muted">${esc(r.store_name)}${code} · ${esc(AdminAPI.formatDate(r.created_at))}</span>
                    </div>
                    <span class="cr-badge cr-badge--${statusClass(r.status)}">${esc(statusLabel(r.status))}</span>
                </header>
                <div class="cr-recon-card__grid">
                    <div><span>${esc(t('cr_col_cashier'))}</span><strong>${esc(r.cashier_name || '—')}</strong></div>
                    <div><span>${esc(t('cr_col_expected'))}</span><strong>${esc(money(r.expected_cash))}</strong></div>
                    <div><span>${esc(t('cr_col_physical'))}</span><strong>${esc(money(r.physical_cash))}</strong></div>
                    <div class="cr-recon-card__diff ${diffClass}"><span>${esc(t('cr_col_difference'))}</span>${varianceBadge(r)}</div>
                </div>
                ${r.notes ? `<p class="cr-recon-card__notes">${esc(r.notes)}</p>` : ''}
                <footer class="cr-recon-card__foot">
                    <button type="button" class="cr-btn cr-btn--ghost cr-btn--sm" data-detail="${r.id}">${esc(t('cr_view_details'))}</button>
                    ${actionButtons(r)}
                </footer>
                ${!isPending && r.reviewed_at ? `<p class="cr-recon-card__reviewed">${esc(t('cr_recon_reviewed_by'))}: ${esc(r.reviewer_name || '—')} · ${esc(AdminAPI.formatDate(r.reviewed_at))}</p>` : ''}
            </article>`;
    }

    function renderTableRow(r) {
        const isPending = r.status === 'pending';
        return `
            <tr class="cr-recon-table__row ${isPending ? 'is-pending' : ''} ${varianceClass(r.difference)}">
                <td><strong>${esc(r.register_name)}</strong>${r.register_code ? `<span class="cr-muted">${esc(r.register_code)}</span>` : ''}</td>
                <td>${esc(r.store_name)}</td>
                <td>${esc(r.cashier_name || '—')}</td>
                <td>${esc(money(r.expected_cash))}</td>
                <td>${esc(money(r.physical_cash))}</td>
                <td>${varianceBadge(r)}</td>
                <td><span class="cr-badge cr-badge--${statusClass(r.status)}">${esc(statusLabel(r.status))}</span></td>
                <td class="cr-muted">${esc(AdminAPI.formatDate(r.created_at))}</td>
                <td class="cr-recon-table__actions">
                    <button type="button" class="cr-btn cr-btn--ghost cr-btn--sm" data-detail="${r.id}">${esc(t('cr_view_details'))}</button>
                    ${actionButtons(r)}
                </td>
            </tr>`;
    }

    function renderMobileRow(r) {
        const isPending = r.status === 'pending';
        return `
            <article class="cr-recon-list__item ${isPending ? 'is-pending' : ''} ${varianceClass(r.difference)}">
                <div class="cr-recon-list__main">
                    <strong>${esc(r.register_name)}</strong>
                    <span class="cr-badge cr-badge--${statusClass(r.status)}">${esc(statusLabel(r.status))}</span>
                </div>
                <div class="cr-recon-list__meta">${esc(r.cashier_name || '—')} · ${esc(AdminAPI.formatDate(r.created_at))}</div>
                <div class="cr-recon-list__amounts">
                    <span>${esc(t('cr_col_difference'))}: ${varianceBadge(r)}</span>
                </div>
                <div class="cr-recon-list__actions">
                    <button type="button" class="cr-btn cr-btn--ghost cr-btn--sm" data-detail="${r.id}">${esc(t('cr_view_details'))}</button>
                    ${actionButtons(r)}
                </div>
            </article>`;
    }

    function renderGrid(items) {
        return `<div class="cr-recon-grid">${items.map(renderCard).join('')}</div>`;
    }

    function renderTable(items) {
        return `
            <div class="cr-recon-table-wrap">
                <table class="modern-table cr-recon-table">
                    <thead><tr>
                        <th>${esc(t('cr_col_register'))}</th>
                        <th>${esc(t('cr_branch'))}</th>
                        <th>${esc(t('cr_col_cashier'))}</th>
                        <th>${esc(t('cr_col_expected'))}</th>
                        <th>${esc(t('cr_col_physical'))}</th>
                        <th>${esc(t('cr_col_difference'))}</th>
                        <th>${esc(t('col_status'))}</th>
                        <th>${esc(t('col_date'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${items.map(renderTableRow).join('')}</tbody>
                </table>
            </div>
            <div class="cr-recon-list">${items.map(renderMobileRow).join('')}</div>`;
    }

    function renderList() {
        const { items, total, totalPages } = paginatedItems();
        updateStats(state.items);
        updatePagination(total, totalPages);

        if (!state.items.length) {
            root.innerHTML = `
                <div class="cr-reg-empty">
                    <span class="material-icons-round">account_balance_wallet</span>
                    <h3>${esc(t('cr_no_data'))}</h3>
                    <p>${esc(t('cr_no_recon_hint'))}</p>
                </div>`;
            return;
        }

        if (!total) {
            root.innerHTML = `
                <div class="cr-reg-empty cr-reg-empty--filter">
                    <span class="material-icons-round">search_off</span>
                    <h3>${esc(t('cr_no_data'))}</h3>
                    <p>${esc(t('clear_search'))}</p>
                </div>`;
            return;
        }

        root.innerHTML = state.view === 'table' ? renderTable(items) : renderGrid(items);
        bindActions();
    }

    function showDetail(recon) {
        if (!recon || !els.detailModal || !els.detailBody) return;
        const diffClass = varianceClass(recon.difference);
        const regLink = recon.register_id
            ? `<a href="register_details.php?id=${encodeURIComponent(recon.register_id)}" class="cr-link">${esc(t('cr_register_details'))}</a>`
            : '—';

        els.detailBody.innerHTML = `
            <dl class="cr-recon-detail-grid">
                <div><dt>${esc(t('col_status'))}</dt><dd><span class="cr-badge cr-badge--${statusClass(recon.status)}">${esc(statusLabel(recon.status))}</span></dd></div>
                <div><dt>${esc(t('cr_col_register'))}</dt><dd><strong>${esc(recon.register_name)}</strong>${recon.register_code ? ` (${esc(recon.register_code)})` : ''}</dd></div>
                <div><dt>${esc(t('cr_branch'))}</dt><dd>${esc(recon.store_name)}</dd></div>
                <div><dt>${esc(t('cr_col_cashier'))}</dt><dd>${esc(recon.cashier_name || '—')}</dd></div>
                <div><dt>${esc(t('col_date'))}</dt><dd>${esc(AdminAPI.formatDate(recon.created_at))}</dd></div>
                <div><dt>${esc(t('cr_recon_session'))}</dt><dd>#${esc(recon.session_id)}</dd></div>
                <div><dt>${esc(t('cr_col_expected'))}</dt><dd>${esc(money(recon.expected_cash))}</dd></div>
                <div><dt>${esc(t('cr_col_physical'))}</dt><dd>${esc(money(recon.physical_cash))}</dd></div>
                <div class="${diffClass}"><dt>${esc(t('cr_col_difference'))}</dt><dd>${varianceBadge(recon)}</dd></div>
                ${recon.reviewer_name ? `<div><dt>${esc(t('cr_recon_reviewed_by'))}</dt><dd>${esc(recon.reviewer_name)}${recon.reviewed_at ? ` · ${esc(AdminAPI.formatDate(recon.reviewed_at))}` : ''}</dd></div>` : ''}
                ${recon.notes ? `<div class="cr-recon-detail-grid--full"><dt>${esc(t('cr_recon_note_label'))}</dt><dd>${esc(recon.notes)}</dd></div>` : ''}
                ${recon.review_note ? `<div class="cr-recon-detail-grid--full"><dt>${esc(t('cr_recon_reviewed_by'))}</dt><dd>${esc(recon.review_note)}</dd></div>` : ''}
            </dl>
            <div class="cr-recon-detail-modal__foot">${regLink}</div>`;

        els.detailModal.hidden = false;
        document.body.classList.add('cr-modal-open');
    }

    function hideDetail() {
        if (!els.detailModal) return;
        els.detailModal.hidden = true;
        if (!document.querySelector('.cr-modal:not([hidden]), .cr-modal-overlay:not([hidden])')) {
            document.body.classList.remove('cr-modal-open');
        }
    }

    function showModal(recon, decision) {
        if (!els.modal || !recon) return;
        els.modalTitle.textContent = decision === 'approved'
            ? t('cr_recon_modal_approve')
            : t('cr_recon_modal_reject');
        document.getElementById('crReconModalId').value = recon.id;
        document.getElementById('crReconModalDecision').value = decision;
        els.modalNote.value = '';
        els.modalNote.required = decision === 'rejected';
        els.modalNoteLabel.textContent = decision === 'rejected'
            ? t('cr_recon_note_required')
            : t('cr_recon_note_optional');
        els.modalSubmit.className = decision === 'approved' ? 'cr-btn' : 'cr-btn cr-btn--warn';
        els.modalSubmit.innerHTML = decision === 'approved'
            ? `<span class="material-icons-round">check_circle</span>${esc(t('cr_recon_approve'))}`
            : `<span class="material-icons-round">cancel</span>${esc(t('cr_recon_reject'))}`;

        const diffClass = varianceClass(recon.difference);
        els.modalSummary.innerHTML = `
            <div class="cr-recon-modal__row"><span>${esc(t('cr_col_register'))}</span><strong>${esc(recon.register_name)}</strong></div>
            <div class="cr-recon-modal__row"><span>${esc(t('cr_branch'))}</span><strong>${esc(recon.store_name)}</strong></div>
            <div class="cr-recon-modal__row"><span>${esc(t('cr_col_cashier'))}</span><strong>${esc(recon.cashier_name || '—')}</strong></div>
            <div class="cr-recon-modal__amounts">
                <div><span>${esc(t('cr_col_expected'))}</span><strong>${esc(money(recon.expected_cash))}</strong></div>
                <div><span>${esc(t('cr_col_physical'))}</span><strong>${esc(money(recon.physical_cash))}</strong></div>
                <div class="${diffClass}"><span>${esc(t('cr_col_difference'))}</span><strong>${esc(money(recon.difference))}</strong></div>
            </div>`;

        els.modal.hidden = false;
        els.modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('cr-modal-open');
        els.modalNote.focus();
    }

    function hideModal() {
        if (!els.modal) return;
        els.modal.hidden = true;
        els.modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.cr-modal:not([hidden])') && els.detailModal?.hidden) {
            document.body.classList.remove('cr-modal-open');
        }
    }

    function bindActions() {
        root.querySelectorAll('[data-approve]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const recon = state.items.find((r) => r.id === Number(btn.dataset.approve));
                showModal(recon, 'approved');
            });
        });
        root.querySelectorAll('[data-reject]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const recon = state.items.find((r) => r.id === Number(btn.dataset.reject));
                showModal(recon, 'rejected');
            });
        });
        root.querySelectorAll('[data-detail]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const recon = state.items.find((r) => r.id === Number(btn.dataset.detail));
                showDetail(recon);
            });
        });
    }

    function exportToCsv() {
        const items = filteredItems();
        if (!items.length) return;
        const headers = [
            t('col_date'), t('cr_col_register'), t('cr_branch'), t('cr_col_cashier'),
            t('cr_col_expected'), t('cr_col_physical'), t('cr_col_difference'), t('col_status'),
        ];
        const rows = items.map((r) => [
            AdminAPI.formatDate(r.created_at),
            r.register_name,
            r.store_name,
            r.cashier_name || '',
            r.expected_cash,
            r.physical_cash,
            r.difference,
            r.status,
        ]);
        exportCsv(`cash-reconciliation-${new Date().toISOString().slice(0, 10)}.csv`, [headers, ...rows]);
    }

    function syncSearchClear() {
        if (!els.searchClear) return;
        els.searchClear.classList.toggle('visible', !!state.search.trim());
    }

    function setFilter(filter) {
        state.filter = filter;
        state.page = 1;
        els.filters?.querySelectorAll('.cr-reg-chip').forEach((c) => {
            const active = (c.dataset.filter || 'all') === filter;
            c.classList.toggle('is-active', active);
            c.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        renderList();
    }

    function initModal() {
        document.querySelectorAll('[data-close-recon-modal]').forEach((el) => {
            el.addEventListener('click', hideModal);
        });
        els.detailClose?.addEventListener('click', hideDetail);
        els.detailModal?.addEventListener('click', (e) => {
            if (e.target === els.detailModal) hideDetail();
        });

        els.modalForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(els.modalForm);
            const id = Number(fd.get('recon_id'));
            const decision = fd.get('decision');
            const note = String(fd.get('note') || '').trim();
            els.modalSubmit.disabled = true;
            try {
                const res = decision === 'approved'
                    ? await AdminAPI.approveCashReconciliation(id, note)
                    : await AdminAPI.rejectCashReconciliation(id, note);
                if (res.status !== 'success') throw new Error(res.message);
                hideModal();
                await load(true);
            } catch (err) {
                showError(err.message || t('error'));
            }
            els.modalSubmit.disabled = false;
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideModal();
                hideDetail();
            }
        });
    }

    function initToolbar() {
        state.dateFrom = els.dateFrom?.value || '';
        state.dateTo = els.dateTo?.value || '';

        els.search?.addEventListener('input', () => {
            state.search = els.search.value;
            state.page = 1;
            syncSearchClear();
            renderList();
        });

        els.searchClear?.addEventListener('click', () => {
            state.search = '';
            if (els.search) els.search.value = '';
            state.page = 1;
            syncSearchClear();
            renderList();
        });

        const onDateChange = () => {
            state.dateFrom = els.dateFrom?.value || '';
            state.dateTo = els.dateTo?.value || '';
            state.page = 1;
            load(true);
        };
        els.dateFrom?.addEventListener('change', onDateChange);
        els.dateTo?.addEventListener('change', onDateChange);

        els.filters?.querySelectorAll('.cr-reg-chip').forEach((chip) => {
            chip.addEventListener('click', () => setFilter(chip.dataset.filter || 'all'));
        });

        document.querySelectorAll('.cr-reg-view-btn').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.view === state.view);
            btn.addEventListener('click', () => {
                state.view = btn.dataset.view || 'grid';
                localStorage.setItem('cr-recon-view', state.view);
                document.querySelectorAll('.cr-reg-view-btn').forEach((b) => {
                    b.classList.toggle('is-active', b.dataset.view === state.view);
                });
                renderList();
            });
        });

        document.querySelectorAll('[data-stat-filter]').forEach((btn) => {
            btn.addEventListener('click', () => setFilter(btn.dataset.statFilter || 'all'));
        });

        els.pagePrev?.addEventListener('click', () => {
            if (state.page > 1) { state.page -= 1; renderList(); }
        });
        els.pageNext?.addEventListener('click', () => {
            const { totalPages } = paginatedItems();
            if (state.page < totalPages) { state.page += 1; renderList(); }
        });

        els.exportBtn?.addEventListener('click', exportToCsv);
        els.refreshBtn?.addEventListener('click', () => load());
    }

    async function load(silent = false) {
        if (!silent) {
            hideError();
            root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
            document.querySelectorAll('.cr-recon-stat__value').forEach((el) => el.classList.add('is-loading'));
        }
        try {
            const params = { limit: 500 };
            if (state.dateFrom) params.from = state.dateFrom;
            if (state.dateTo) params.to = state.dateTo;
            if (state.search.trim()) params.q = state.search.trim();

            const res = await AdminAPI.getCashReconciliations(params);
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            state.items = res.data || [];
            renderList();
            updateLastUpdated();
        } catch (e) {
            console.error(e);
            if (!silent) {
                showError(e.message || t('load_error'));
                root.innerHTML = `<div class="cr-reg-empty"><span class="material-icons-round">error_outline</span><p>${esc(e.message)}</p></div>`;
            }
        }
    }

    initModal();
    initToolbar();
    syncSearchClear();
    load();
    document.addEventListener('cr:refresh', () => load(true));
});
