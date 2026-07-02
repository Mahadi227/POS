/**
 * Accounting expenses — list, filters, create, approve/reject workflow
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accExpRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;
    const canApprove = !!window.ADMIN_PAGE?.canApprove;
    const PAGE_SIZE = 15;

    const CATEGORY_LABELS = {
        rent: 'exp_cat_rent',
        utilities: 'exp_cat_utilities',
        salaries: 'exp_cat_salaries',
        supplies: 'exp_cat_supplies',
        transport: 'exp_cat_transport',
        marketing: 'exp_cat_marketing',
        maintenance: 'exp_cat_maintenance',
        misc: 'exp_cat_misc',
    };

    const state = {
        rows: [],
        stats: {},
        categories: [],
        status: 'all',
        category: '',
        search: '',
        page: 1,
        selected: null,
        searchTimer: null,
    };

    const els = {
        count: document.getElementById('accExpCount'),
        statTotal: document.getElementById('accExpStatTotal'),
        statPending: document.getElementById('accExpStatPending'),
        statApproved: document.getElementById('accExpStatApproved'),
        statRejected: document.getElementById('accExpStatRejected'),
        stats: document.getElementById('accExpStats'),
        search: document.getElementById('accExpSearch'),
        searchClear: document.getElementById('accExpSearchClear'),
        dateFrom: document.getElementById('accExpDateFrom'),
        dateTo: document.getElementById('accExpDateTo'),
        statusFilters: document.getElementById('accExpStatusFilters'),
        categoryFilters: document.getElementById('accExpCategoryFilters'),
        meta: document.getElementById('accExpMeta'),
        pagePrev: document.getElementById('accExpPrev'),
        pageNext: document.getElementById('accExpNext'),
        pageInfo: document.getElementById('accExpPageInfo'),
        exportBtn: document.getElementById('accExpExportBtn'),
        refreshBtn: document.getElementById('accExpRefreshBtn'),
        newBtn: document.getElementById('accExpNewBtn'),
        formModal: document.getElementById('accExpFormModal'),
        form: document.getElementById('accExpForm'),
        formClose: document.getElementById('accExpFormClose'),
        formCancel: document.getElementById('accExpFormCancel'),
        formSubmit: document.getElementById('accExpFormSubmit'),
        detailModal: document.getElementById('accExpDetailModal'),
        detailBody: document.getElementById('accExpDetailBody'),
        detailClose: document.getElementById('accExpDetailClose'),
        detailActions: document.getElementById('accExpDetailActions'),
        approveBtn: document.getElementById('accExpApproveBtn'),
        rejectBtn: document.getElementById('accExpRejectBtn'),
    };

    function categoryLabel(cat) {
        const key = CATEGORY_LABELS[cat];
        return key ? t(key) : (cat || '—');
    }

    function paymentLabel(method) {
        const map = {
            cash: t('exp_payment_cash'),
            bank: t('exp_payment_bank'),
            mobile_money: t('exp_payment_mobile'),
        };
        return map[method] || method || '—';
    }

    function statusLabel(status) {
        const map = {
            pending: t('status_pending'),
            approved: t('status_approved'),
            rejected: t('status_rejected'),
        };
        return map[status] || status;
    }

    function statusClass(status) {
        if (status === 'approved') return 'acc-exp-status--approved';
        if (status === 'rejected') return 'acc-exp-status--rejected';
        return 'acc-exp-status--pending';
    }

    function formatDate(d) {
        if (!d) return '—';
        return new Date(`${d}T12:00:00`).toLocaleDateString(window.ADMIN_CONFIG?.locale || 'fr-FR', {
            day: '2-digit', month: 'short', year: 'numeric',
        });
    }

    function queryParams() {
        return {
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
            status: state.status === 'all' ? '' : state.status,
            category: state.category || '',
            q: state.search.trim() || '',
        };
    }

    function setStatsLoading(on) {
        document.querySelectorAll('.acc-exp-stat__value').forEach((el) => el.classList.toggle('is-loading', on));
    }

    function renderStats(stats) {
        if (els.statTotal) els.statTotal.textContent = money(stats.total_amount);
        if (els.statPending) els.statPending.textContent = money(stats.pending_amount);
        if (els.statApproved) els.statApproved.textContent = money(stats.approved_amount);
        if (els.statRejected) els.statRejected.textContent = money(stats.rejected_amount);
        document.querySelectorAll('.acc-exp-stat__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.count) {
            els.count.textContent = t('exp_table_summary', stats.total_count || 0, money(stats.total_amount || 0));
        }
    }

    function renderCategoryFilters(categories) {
        if (!els.categoryFilters) return;
        const items = ['', ...categories.filter(Boolean)];
        els.categoryFilters.innerHTML = items.map((cat, i) => `
            <button type="button" class="acc-exp-chip${i === 0 && !state.category ? ' is-active' : (state.category === cat ? ' is-active' : '')}"
                data-category="${esc(cat)}" role="tab">${esc(cat ? categoryLabel(cat) : t('exp_filter_all'))}</button>
        `).join('');
        els.categoryFilters.hidden = categories.length === 0;
    }

    function paginatedRows() {
        const start = (state.page - 1) * PAGE_SIZE;
        return state.rows.slice(start, start + PAGE_SIZE);
    }

    function totalPages() {
        return Math.max(1, Math.ceil(state.rows.length / PAGE_SIZE));
    }

    function updatePagination() {
        const pages = totalPages();
        if (state.page > pages) state.page = pages;
        if (els.pageInfo) els.pageInfo.textContent = `${state.page} / ${pages}`;
        if (els.pagePrev) els.pagePrev.disabled = state.page <= 1;
        if (els.pageNext) els.pageNext.disabled = state.page >= pages;
        if (els.meta) {
            const showing = paginatedRows().length;
            els.meta.textContent = showing
                ? `${showing} / ${state.rows.length} ${t('records')}`
                : t('no_data');
        }
    }

    function renderTable() {
        const rows = paginatedRows();
        if (!rows.length) {
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">receipt_long</span><p>${esc(t('cr_no_data'))}</p></div>`;
            updatePagination();
            return;
        }

        root.innerHTML = `
            <div class="acc-exp-table-wrap">
                <table class="modern-table acc-table acc-exp-table">
                    <thead><tr>
                        <th>${esc(t('exp_col_date'))}</th>
                        <th>${esc(t('exp_col_category'))}</th>
                        <th>${esc(t('exp_col_description'))}</th>
                        <th>${esc(t('exp_col_amount'))}</th>
                        <th>${esc(t('exp_col_payment'))}</th>
                        <th>${esc(t('exp_col_status'))}</th>
                        <th>${esc(t('exp_col_submitted_by'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr data-id="${r.id}">
                            <td>${esc(formatDate(r.expense_date))}</td>
                            <td><span class="acc-exp-cat">${esc(categoryLabel(r.category))}</span></td>
                            <td class="acc-exp-desc">${esc(r.description || '—')}</td>
                            <td><strong>${esc(money(r.amount))}</strong></td>
                            <td>${esc(paymentLabel(r.payment_method))}</td>
                            <td><span class="acc-exp-status ${statusClass(r.status)}">${esc(statusLabel(r.status))}</span></td>
                            <td>${esc(r.created_by_name || '—')}</td>
                            <td><button type="button" class="acc-exp-view-btn" data-id="${r.id}" title="${esc(t('exp_view_details'))}">
                                <span class="material-icons-round">visibility</span></button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-exp-cards">${rows.map((r) => `
                <article class="acc-exp-card" data-id="${r.id}">
                    <header class="acc-exp-card__head">
                        <span class="acc-exp-status ${statusClass(r.status)}">${esc(statusLabel(r.status))}</span>
                        <strong>${esc(money(r.amount))}</strong>
                    </header>
                    <p class="acc-exp-card__cat">${esc(categoryLabel(r.category))}</p>
                    <p class="acc-exp-card__desc">${esc(r.description || '—')}</p>
                    <footer class="acc-exp-card__foot">
                        <span>${esc(formatDate(r.expense_date))}</span>
                        <span>${esc(paymentLabel(r.payment_method))}</span>
                        <button type="button" class="acc-exp-view-btn" data-id="${r.id}">${esc(t('exp_view_details'))}</button>
                    </footer>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('.acc-exp-view-btn').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.id)));
        });
        updatePagination();
    }

    function openModal(el) {
        if (!el) return;
        el.hidden = false;
        document.body.classList.add('acc-exp-modal-open');
    }

    function closeModal(el) {
        if (!el) return;
        el.hidden = true;
        if (!document.querySelector('.acc-exp-modal-overlay:not([hidden])')) {
            document.body.classList.remove('acc-exp-modal-open');
        }
    }

    function openDetail(id) {
        const row = state.rows.find((r) => Number(r.id) === id);
        if (!row) return;
        state.selected = row;
        const showActions = canApprove && row.status === 'pending';
        if (els.detailBody) {
            els.detailBody.innerHTML = `
                <dl class="acc-exp-detail-grid">
                    <div><dt>${esc(t('exp_col_date'))}</dt><dd>${esc(formatDate(row.expense_date))}</dd></div>
                    <div><dt>${esc(t('exp_col_category'))}</dt><dd>${esc(categoryLabel(row.category))}</dd></div>
                    <div><dt>${esc(t('exp_col_amount'))}</dt><dd><strong>${esc(money(row.amount))}</strong></dd></div>
                    <div><dt>${esc(t('exp_col_payment'))}</dt><dd>${esc(paymentLabel(row.payment_method))}</dd></div>
                    <div><dt>${esc(t('exp_col_status'))}</dt><dd><span class="acc-exp-status ${statusClass(row.status)}">${esc(statusLabel(row.status))}</span></dd></div>
                    <div><dt>${esc(t('exp_col_submitted_by'))}</dt><dd>${esc(row.created_by_name || '—')}</dd></div>
                    ${row.approver_name ? `<div><dt>${esc(t('exp_approved_by'))}</dt><dd>${esc(row.approver_name)}</dd></div>` : ''}
                    ${row.journal_entry_id ? `<div><dt>${esc(t('exp_journal_ref'))}</dt><dd>#${esc(String(row.journal_entry_id))}</dd></div>` : ''}
                    <div class="acc-exp-detail-grid__full"><dt>${esc(t('exp_col_description'))}</dt><dd>${esc(row.description || '—')}</dd></div>
                </dl>`;
        }
        if (els.detailActions) els.detailActions.hidden = !showActions;
        openModal(els.detailModal);
    }

    async function load() {
        hideError();
        setStatsLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getAccounting('expenses', queryParams());
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            state.rows = res.data?.rows || [];
            state.stats = res.data?.stats || {};
            state.categories = res.data?.categories || [];
            state.page = 1;
            renderStats(state.stats);
            renderCategoryFilters(state.categories);
            renderTable();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<div class="acc-empty"><p>${esc(e.message)}</p></div>`;
        }
    }

    async function submitExpense(e) {
        e.preventDefault();
        const fd = new FormData(els.form);
        const payload = Object.fromEntries(fd.entries());
        payload.amount = parseFloat(payload.amount);
        if (window.ADMIN_PAGE?.storeId) payload.store_id = window.ADMIN_PAGE.storeId;
        els.formSubmit.disabled = true;
        try {
            const res = await AdminAPI.postAccounting('expenses', payload);
            if (res.status !== 'success') throw new Error(res.message);
            closeModal(els.formModal);
            els.form.reset();
            const dateInput = els.form.querySelector('[name="expense_date"]');
            if (dateInput) dateInput.value = new Date().toISOString().slice(0, 10);
            await load();
        } catch (err) {
            showError(err.message || t('error'));
        } finally {
            els.formSubmit.disabled = false;
        }
    }

    async function processExpense(action) {
        if (!state.selected) return;
        const id = state.selected.id;
        els.approveBtn.disabled = true;
        els.rejectBtn.disabled = true;
        try {
            const res = await AdminAPI.postAccounting('expenses', {}, `${action}/${id}`);
            if (res.status !== 'success') throw new Error(res.message);
            closeModal(els.detailModal);
            state.selected = null;
            await load();
        } catch (err) {
            showError(err.message || t('error'));
        } finally {
            els.approveBtn.disabled = false;
            els.rejectBtn.disabled = false;
        }
    }

    function exportData() {
        if (!state.rows.length) return;
        const headers = [
            t('exp_col_date'), t('exp_col_category'), t('exp_col_description'),
            t('exp_col_amount'), t('exp_col_payment'), t('exp_col_status'), t('exp_col_submitted_by'),
        ];
        const rows = state.rows.map((r) => [
            r.expense_date,
            categoryLabel(r.category),
            r.description || '',
            r.amount,
            paymentLabel(r.payment_method),
            statusLabel(r.status),
            r.created_by_name || '',
        ]);
        exportCsv(`expenses-${els.dateFrom?.value || 'export'}.csv`, [headers, ...rows]);
    }

    els.stats?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-stat-filter]');
        if (!btn) return;
        const filter = btn.dataset.statFilter;
        state.status = filter === 'all' ? 'all' : filter;
        els.statusFilters?.querySelectorAll('.acc-exp-chip').forEach((chip) => {
            const active = chip.dataset.status === state.status;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        load();
    });

    els.statusFilters?.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-status]');
        if (!chip) return;
        state.status = chip.dataset.status;
        els.statusFilters.querySelectorAll('.acc-exp-chip').forEach((c) => {
            const active = c.dataset.status === state.status;
            c.classList.toggle('is-active', active);
            c.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        load();
    });

    els.categoryFilters?.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-category]');
        if (!chip) return;
        state.category = chip.dataset.category;
        els.categoryFilters.querySelectorAll('.acc-exp-chip').forEach((c) => {
            c.classList.toggle('is-active', c.dataset.category === state.category);
        });
        load();
    });

    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => {
            state.search = els.search.value;
            state.page = 1;
            load();
        }, 350);
    });

    els.searchClear?.addEventListener('click', () => {
        if (els.search) els.search.value = '';
        state.search = '';
        load();
    });

    [els.dateFrom, els.dateTo].forEach((input) => {
        input?.addEventListener('change', () => { state.page = 1; load(); });
    });

    els.pagePrev?.addEventListener('click', () => {
        if (state.page > 1) { state.page -= 1; renderTable(); }
    });
    els.pageNext?.addEventListener('click', () => {
        if (state.page < totalPages()) { state.page += 1; renderTable(); }
    });

    els.exportBtn?.addEventListener('click', exportData);
    els.refreshBtn?.addEventListener('click', load);
    els.newBtn?.addEventListener('click', () => openModal(els.formModal));
    els.form?.addEventListener('submit', submitExpense);
    els.formClose?.addEventListener('click', () => closeModal(els.formModal));
    els.formCancel?.addEventListener('click', () => closeModal(els.formModal));
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.approveBtn?.addEventListener('click', () => processExpense('approve'));
    els.rejectBtn?.addEventListener('click', () => processExpense('reject'));

    [els.formModal, els.detailModal].forEach((modal) => {
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(modal);
        });
    });

    document.addEventListener('acc:refresh', load);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal(els.formModal);
            closeModal(els.detailModal);
        }
    });

    load();
});
