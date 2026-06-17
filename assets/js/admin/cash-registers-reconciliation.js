/**
 * Cash register reconciliation — review workflow
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crReconRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = CashRegistersUI;

    const state = {
        items: [],
        filter: 'all',
        search: '',
    };

    const els = {
        search: document.getElementById('crReconSearch'),
        filters: document.getElementById('crReconFilters'),
        count: document.getElementById('crReconCount'),
        statPending: document.getElementById('crReconStatPending'),
        statApproved: document.getElementById('crReconStatApproved'),
        statRejected: document.getElementById('crReconStatRejected'),
        statVariance: document.getElementById('crReconStatVariance'),
        modal: document.getElementById('crReconModal'),
        modalForm: document.getElementById('crReconModalForm'),
        modalSummary: document.getElementById('crReconModalSummary'),
        modalTitle: document.getElementById('crReconModalTitle'),
        modalNote: document.getElementById('crReconModalNote'),
        modalNoteLabel: document.getElementById('crReconNoteLabelText'),
        modalSubmit: document.getElementById('crReconModalSubmit'),
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
        if (v >= 500) return 'is-danger';
        if (v > 0) return 'is-warn';
        return 'is-ok';
    }

    function filteredItems() {
        let list = [...state.items];
        const q = state.search.trim().toLowerCase();

        if (state.filter !== 'all') {
            list = list.filter((r) => r.status === state.filter);
        }

        if (q) {
            list = list.filter((r) => {
                const hay = [r.register_name, r.cashier_name, r.store_name, r.status, r.notes]
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

    function updateStats(items) {
        const pending = items.filter((r) => r.status === 'pending');
        const approved = items.filter((r) => r.status === 'approved').length;
        const rejected = items.filter((r) => r.status === 'rejected').length;
        const pendingVariance = pending.reduce((sum, r) => sum + Math.abs(Number(r.difference || 0)), 0);

        const set = (el, val) => { if (el) { el.textContent = val; el.classList.remove('is-loading'); } };

        set(els.statPending, String(pending.length));
        set(els.statApproved, String(approved));
        set(els.statRejected, String(rejected));
        set(els.statVariance, money(pendingVariance));

        if (els.count) {
            els.count.textContent = t('cr_recon_count', String(filteredItems().length));
        }
    }

    function renderCard(r) {
        const diffClass = varianceClass(r.difference);
        const isPending = r.status === 'pending';
        return `
            <article class="cr-recon-card ${diffClass}${isPending ? ' is-pending' : ''}">
                <header class="cr-recon-card__head">
                    <div>
                        <strong>${esc(r.register_name)}</strong>
                        <span class="cr-muted">${esc(r.store_name)} · ${esc(AdminAPI.formatDate(r.created_at))}</span>
                    </div>
                    <span class="cr-badge cr-badge--${statusClass(r.status)}">${esc(statusLabel(r.status))}</span>
                </header>
                <div class="cr-recon-card__grid">
                    <div><span>${esc(t('cr_col_cashier'))}</span><strong>${esc(r.cashier_name || '—')}</strong></div>
                    <div><span>${esc(t('cr_col_expected'))}</span><strong>${esc(money(r.expected_cash))}</strong></div>
                    <div><span>${esc(t('cr_col_physical'))}</span><strong>${esc(money(r.physical_cash))}</strong></div>
                    <div class="cr-recon-card__diff ${diffClass}"><span>${esc(t('cr_col_difference'))}</span><strong>${esc(money(r.difference))}</strong></div>
                </div>
                ${r.notes ? `<p class="cr-recon-card__notes">${esc(r.notes)}</p>` : ''}
                ${isPending ? `
                <footer class="cr-recon-card__foot">
                    <button type="button" class="cr-btn" data-approve="${r.id}"><span class="material-icons-round">check_circle</span>${esc(t('cr_recon_approve'))}</button>
                    <button type="button" class="cr-btn cr-btn--warn" data-reject="${r.id}"><span class="material-icons-round">cancel</span>${esc(t('cr_recon_reject'))}</button>
                </footer>` : `
                <footer class="cr-recon-card__foot cr-recon-card__foot--reviewed">
                    <span class="cr-muted">${r.reviewed_at ? esc(AdminAPI.formatDate(r.reviewed_at)) : ''}</span>
                </footer>`}
            </article>`;
    }

    function renderList() {
        const items = filteredItems();
        updateStats(state.items);

        if (!state.items.length) {
            root.innerHTML = `
                <div class="cr-reg-empty">
                    <span class="material-icons-round">account_balance_wallet</span>
                    <h3>${esc(t('cr_no_data'))}</h3>
                    <p>${esc(t('cr_no_recon_hint'))}</p>
                </div>`;
            return;
        }

        if (!items.length) {
            root.innerHTML = `
                <div class="cr-reg-empty cr-reg-empty--filter">
                    <span class="material-icons-round">search_off</span>
                    <h3>${esc(t('cr_no_data'))}</h3>
                </div>`;
            return;
        }

        root.innerHTML = `<div class="cr-recon-grid">${items.map(renderCard).join('')}</div>`;
        bindActions();
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
        document.body.classList.remove('cr-modal-open');
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
    }

    function initModal() {
        document.querySelectorAll('[data-close-recon-modal]').forEach((el) => {
            el.addEventListener('click', hideModal);
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
            if (e.key === 'Escape') hideModal();
        });
    }

    function initToolbar() {
        els.search?.addEventListener('input', () => {
            state.search = els.search.value;
            renderList();
        });

        els.filters?.querySelectorAll('.cr-reg-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                state.filter = chip.dataset.filter || 'all';
                els.filters.querySelectorAll('.cr-reg-chip').forEach((c) => {
                    c.classList.toggle('is-active', c === chip);
                    c.setAttribute('aria-selected', c === chip ? 'true' : 'false');
                });
                renderList();
            });
        });
    }

    async function load(silent = false) {
        if (!silent) {
            hideError();
            root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
            document.querySelectorAll('.cr-recon-stat__value').forEach((el) => el.classList.add('is-loading'));
        }
        try {
            const res = await AdminAPI.getCashReconciliations();
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(true);
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
    load();
    document.addEventListener('cr:refresh', () => load(true));
});
