/**
 * Accounting journal entries — list, filters, manual posting
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accJeRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;
    const canManage = !!window.ADMIN_PAGE?.canManage;
    const PAGE_SIZE = 12;

    const REF_LABELS = {
        manual: 'je_ref_manual',
        sale: 'je_ref_sale',
        expense: 'je_ref_expense',
        payment: 'je_ref_payment',
        purchase: 'je_ref_purchase',
        inventory: 'je_ref_inventory',
    };

    const STATUS_LABELS = {
        posted: 'je_status_posted',
        draft: 'je_status_draft',
        void: 'je_status_void',
    };

    const state = {
        rows: [],
        stats: {},
        referenceTypes: [],
        accounts: [],
        status: 'all',
        reference: '',
        source: '',
        search: '',
        page: 1,
        selected: null,
        searchTimer: null,
    };

    const els = {
        count: document.getElementById('accJeCount'),
        statVolume: document.getElementById('accJeStatVolume'),
        statEntries: document.getElementById('accJeStatEntries'),
        statManual: document.getElementById('accJeStatManual'),
        statAuto: document.getElementById('accJeStatAuto'),
        stats: document.getElementById('accJeStats'),
        search: document.getElementById('accJeSearch'),
        searchClear: document.getElementById('accJeSearchClear'),
        dateFrom: document.getElementById('accJeDateFrom'),
        dateTo: document.getElementById('accJeDateTo'),
        statusFilters: document.getElementById('accJeStatusFilters'),
        refFilters: document.getElementById('accJeRefFilters'),
        meta: document.getElementById('accJeMeta'),
        pagePrev: document.getElementById('accJePrev'),
        pageNext: document.getElementById('accJeNext'),
        pageInfo: document.getElementById('accJePageInfo'),
        exportBtn: document.getElementById('accJeExportBtn'),
        refreshBtn: document.getElementById('accJeRefreshBtn'),
        newBtn: document.getElementById('accJeNewBtn'),
        formModal: document.getElementById('accJeFormModal'),
        form: document.getElementById('accJeForm'),
        formClose: document.getElementById('accJeFormClose'),
        formCancel: document.getElementById('accJeFormCancel'),
        formSubmit: document.getElementById('accJeFormSubmit'),
        linesRoot: document.getElementById('accJeLines'),
        addLineBtn: document.getElementById('accJeAddLine'),
        balance: document.getElementById('accJeBalance'),
        balanceText: document.getElementById('accJeBalanceText'),
        detailModal: document.getElementById('accJeDetailModal'),
        detailBody: document.getElementById('accJeDetailBody'),
        detailClose: document.getElementById('accJeDetailClose'),
        detailOk: document.getElementById('accJeDetailOk'),
    };

    function refLabel(ref) {
        const key = REF_LABELS[ref || 'manual'];
        return key ? t(key) : (ref || t('je_ref_manual'));
    }

    function statusLabel(status) {
        const key = STATUS_LABELS[status];
        return key ? t(key) : status;
    }

    function statusClass(status) {
        if (status === 'posted') return 'acc-je-status--posted';
        if (status === 'void') return 'acc-je-status--void';
        return 'acc-je-status--draft';
    }

    function formatDate(d) {
        if (!d) return '—';
        return new Date(`${d}T12:00:00`).toLocaleDateString(window.ADMIN_CONFIG?.locale || 'fr-FR', {
            day: '2-digit', month: 'short', year: 'numeric',
        });
    }

    function queryParams() {
        const params = {
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
            status: state.status === 'all' ? '' : state.status,
            q: state.search.trim() || '',
        };
        if (state.source === 'manual' || state.source === 'auto') {
            params.source = state.source;
        } else if (state.reference) {
            params.reference_type = state.reference;
        }
        return params;
    }

    function setStatsLoading(on) {
        document.querySelectorAll('.acc-je-stat__value').forEach((el) => el.classList.toggle('is-loading', on));
    }

    function renderStats(stats) {
        if (els.statVolume) els.statVolume.textContent = money(stats.total_volume);
        if (els.statEntries) els.statEntries.textContent = String(stats.total_count ?? 0);
        if (els.statManual) els.statManual.textContent = String(stats.manual_count ?? 0);
        if (els.statAuto) els.statAuto.textContent = String(stats.auto_count ?? 0);
        document.querySelectorAll('.acc-je-stat__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.count) {
            els.count.textContent = t('je_table_summary', stats.total_count || 0, money(stats.total_volume || 0));
        }
    }

    function renderRefFilters(types) {
        if (!els.refFilters) return;
        const items = ['', ...types.filter((x) => x && x !== 'manual')];
        if (!items.includes('manual') && types.length) {
            // manual always available as filter
        }
        const chips = [''].concat(types.length ? types : ['manual', 'sale', 'expense']);
        const unique = [...new Set(chips)];
        els.refFilters.innerHTML = unique.map((ref) => `
            <button type="button" class="acc-je-chip${(!state.reference && !state.source && ref === '') || state.reference === ref ? ' is-active' : ''}"
                data-reference="${esc(ref)}" role="tab">${esc(ref ? refLabel(ref) : t('je_filter_all'))}</button>
        `).join('');
        els.refFilters.hidden = unique.length <= 1;
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
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">menu_book</span><p>${esc(t('cr_no_data'))}</p></div>`;
            updatePagination();
            return;
        }

        root.innerHTML = `
            <div class="acc-je-table-wrap">
                <table class="modern-table acc-table acc-je-table">
                    <thead><tr>
                        <th>${esc(t('je_col_date'))}</th>
                        <th>${esc(t('je_col_entry_no'))}</th>
                        <th>${esc(t('je_col_description'))}</th>
                        <th>${esc(t('je_col_reference'))}</th>
                        <th>${esc(t('je_col_debit'))}</th>
                        <th>${esc(t('je_col_credit'))}</th>
                        <th>${esc(t('je_col_status'))}</th>
                        <th>${esc(t('je_col_created_by'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr data-id="${r.id}">
                            <td>${esc(formatDate(r.entry_date))}</td>
                            <td><code class="acc-je-code">${esc(r.entry_no)}</code></td>
                            <td class="acc-je-desc">${esc(r.description || '—')}</td>
                            <td><span class="acc-je-ref">${esc(refLabel(r.reference_type || 'manual'))}</span></td>
                            <td><strong>${esc(money(r.total_debit))}</strong></td>
                            <td>${esc(money(r.total_credit))}</td>
                            <td><span class="acc-je-status ${statusClass(r.status)}">${esc(statusLabel(r.status))}</span></td>
                            <td>${esc(r.created_by_name || '—')}</td>
                            <td><button type="button" class="acc-je-view-btn" data-id="${r.id}" title="${esc(t('je_view_details'))}">
                                <span class="material-icons-round">visibility</span></button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-je-cards">${rows.map((r) => `
                <article class="acc-je-card" data-id="${r.id}">
                    <header class="acc-je-card__head">
                        <code>${esc(r.entry_no)}</code>
                        <span class="acc-je-status ${statusClass(r.status)}">${esc(statusLabel(r.status))}</span>
                    </header>
                    <p class="acc-je-card__desc">${esc(r.description || '—')}</p>
                    <dl class="acc-je-card__meta">
                        <div><dt>${esc(t('je_col_date'))}</dt><dd>${esc(formatDate(r.entry_date))}</dd></div>
                        <div><dt>${esc(t('je_col_reference'))}</dt><dd>${esc(refLabel(r.reference_type || 'manual'))}</dd></div>
                        <div><dt>${esc(t('je_col_debit'))}</dt><dd><strong>${esc(money(r.total_debit))}</strong></dd></div>
                    </dl>
                    <footer class="acc-je-card__foot">
                        <button type="button" class="acc-je-view-btn" data-id="${r.id}">${esc(t('je_view_details'))}</button>
                    </footer>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('.acc-je-view-btn').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.id)));
        });
        updatePagination();
    }

    function openModal(el) {
        if (!el) return;
        el.hidden = false;
        document.body.classList.add('acc-je-modal-open');
    }

    function closeModal(el) {
        if (!el) return;
        el.hidden = true;
        if (!document.querySelector('.acc-je-modal-overlay:not([hidden])')) {
            document.body.classList.remove('acc-je-modal-open');
        }
    }

    function accountOptions(selectedId = '') {
        return state.accounts.map((a) => {
            const label = `${a.code} — ${a.name}`;
            const sel = Number(a.id) === Number(selectedId) ? ' selected' : '';
            return `<option value="${a.id}"${sel}>${esc(label)}</option>`;
        }).join('');
    }

    function createLineRow(data = {}) {
        const row = document.createElement('div');
        row.className = 'acc-je-line';
        row.innerHTML = `
            <label class="acc-je-field acc-je-field--account">
                <span>${esc(t('je_form_account'))}</span>
                <select class="acc-je-line-account" required>
                    <option value="">—</option>
                    ${accountOptions(data.account_id)}
                </select>
            </label>
            <label class="acc-je-field">
                <span>${esc(t('je_form_debit'))}</span>
                <input type="number" class="acc-je-line-debit" min="0" step="0.01" value="${data.debit || ''}" placeholder="0">
            </label>
            <label class="acc-je-field">
                <span>${esc(t('je_form_credit'))}</span>
                <input type="number" class="acc-je-line-credit" min="0" step="0.01" value="${data.credit || ''}" placeholder="0">
            </label>
            <label class="acc-je-field acc-je-field--memo">
                <span>${esc(t('je_form_memo'))}</span>
                <input type="text" class="acc-je-line-memo" value="${esc(data.memo || '')}" placeholder="…">
            </label>
            <button type="button" class="acc-je-line-remove" title="${esc(t('je_form_remove_line'))}">
                <span class="material-icons-round">delete_outline</span>
            </button>`;
        row.querySelector('.acc-je-line-remove')?.addEventListener('click', () => {
            if (els.linesRoot.querySelectorAll('.acc-je-line').length > 2) {
                row.remove();
                updateBalance();
            }
        });
        row.querySelectorAll('input, select').forEach((input) => {
            input.addEventListener('input', updateBalance);
            input.addEventListener('change', updateBalance);
        });
        return row;
    }

    function initFormLines() {
        if (!els.linesRoot) return;
        els.linesRoot.innerHTML = '';
        els.linesRoot.appendChild(createLineRow({ debit: '' }));
        els.linesRoot.appendChild(createLineRow({ credit: '' }));
        updateBalance();
    }

    function lineTotals() {
        let debit = 0;
        let credit = 0;
        els.linesRoot?.querySelectorAll('.acc-je-line').forEach((row) => {
            debit += parseFloat(row.querySelector('.acc-je-line-debit')?.value || 0) || 0;
            credit += parseFloat(row.querySelector('.acc-je-line-credit')?.value || 0) || 0;
        });
        return { debit, credit };
    }

    function updateBalance() {
        const { debit, credit } = lineTotals();
        const balanced = Math.abs(debit - credit) < 0.005 && debit > 0;
        if (els.balance) {
            els.balance.dataset.state = balanced ? 'ok' : 'error';
        }
        if (els.balanceText) {
            els.balanceText.textContent = balanced
                ? t('je_balance_ok', money(debit))
                : t('je_balance_error', money(debit), money(credit));
        }
        if (els.formSubmit) els.formSubmit.disabled = !balanced;
    }

    function collectLines() {
        const lines = [];
        els.linesRoot?.querySelectorAll('.acc-je-line').forEach((row) => {
            const accountId = parseInt(row.querySelector('.acc-je-line-account')?.value || '0', 10);
            const debit = parseFloat(row.querySelector('.acc-je-line-debit')?.value || 0) || 0;
            const credit = parseFloat(row.querySelector('.acc-je-line-credit')?.value || 0) || 0;
            const memo = row.querySelector('.acc-je-line-memo')?.value || '';
            if (!accountId || (debit <= 0 && credit <= 0)) return;
            lines.push({ account_id: accountId, debit, credit, memo });
        });
        return lines;
    }

    function openDetail(id) {
        const row = state.rows.find((r) => Number(r.id) === id);
        if (!row) return;
        state.selected = row;
        const lines = row.lines || [];
        if (els.detailBody) {
            els.detailBody.innerHTML = `
                <dl class="acc-je-detail-grid">
                    <div><dt>${esc(t('je_col_entry_no'))}</dt><dd><code>${esc(row.entry_no)}</code></dd></div>
                    <div><dt>${esc(t('je_col_date'))}</dt><dd>${esc(formatDate(row.entry_date))}</dd></div>
                    <div><dt>${esc(t('je_col_reference'))}</dt><dd>${esc(refLabel(row.reference_type || 'manual'))}</dd></div>
                    <div><dt>${esc(t('je_col_status'))}</dt><dd><span class="acc-je-status ${statusClass(row.status)}">${esc(statusLabel(row.status))}</span></dd></div>
                    <div><dt>${esc(t('je_col_created_by'))}</dt><dd>${esc(row.created_by_name || '—')}</dd></div>
                    <div class="acc-je-detail-grid__full"><dt>${esc(t('je_col_description'))}</dt><dd>${esc(row.description || '—')}</dd></div>
                </dl>
                <div class="acc-je-lines-table-wrap">
                    <table class="modern-table acc-table">
                        <thead><tr>
                            <th>${esc(t('je_form_account'))}</th>
                            <th>${esc(t('je_form_debit'))}</th>
                            <th>${esc(t('je_form_credit'))}</th>
                            <th>${esc(t('je_form_memo'))}</th>
                        </tr></thead>
                        <tbody>${lines.map((l) => `
                            <tr>
                                <td><code>${esc(l.account_code)}</code> ${esc(l.account_name)}</td>
                                <td>${Number(l.debit) > 0 ? esc(money(l.debit)) : '—'}</td>
                                <td>${Number(l.credit) > 0 ? esc(money(l.credit)) : '—'}</td>
                                <td>${esc(l.memo || '—')}</td>
                            </tr>`).join('')}
                        </tbody>
                        <tfoot><tr>
                            <th>${esc(t('je_col_debit'))} / ${esc(t('je_col_credit'))}</th>
                            <th>${esc(money(row.total_debit))}</th>
                            <th>${esc(money(row.total_credit))}</th>
                            <th></th>
                        </tr></tfoot>
                    </table>
                </div>`;
        }
        openModal(els.detailModal);
    }

    async function loadAccounts() {
        try {
            const res = await AdminAPI.getAccounting('accounts');
            if (res.status === 'success') {
                const data = res.data;
                const list = Array.isArray(data) ? data : (data?.rows || []);
                state.accounts = list.filter((a) => a.account_subtype !== 'header');
            }
        } catch {
            state.accounts = [];
        }
    }

    async function load() {
        hideError();
        setStatsLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getAccounting('journal', queryParams());
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            state.rows = res.data?.rows || [];
            state.stats = res.data?.stats || {};
            state.referenceTypes = res.data?.reference_types || [];
            state.page = 1;
            renderStats(state.stats);
            renderRefFilters(state.referenceTypes);
            renderTable();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<div class="acc-empty"><p>${esc(e.message)}</p></div>`;
        }
    }

    async function submitJournal(e) {
        e.preventDefault();
        const lines = collectLines();
        if (lines.length < 2) return;
        const fd = new FormData(els.form);
        const payload = {
            entry_date: fd.get('entry_date'),
            description: fd.get('description'),
            reference_type: 'manual',
            lines,
        };
        if (window.ADMIN_PAGE?.storeId) payload.store_id = window.ADMIN_PAGE.storeId;
        els.formSubmit.disabled = true;
        try {
            const res = await AdminAPI.postAccounting('journal', payload);
            if (res.status !== 'success') throw new Error(res.message);
            closeModal(els.formModal);
            els.form.reset();
            initFormLines();
            await load();
        } catch (err) {
            showError(err.message || t('error'));
            updateBalance();
        }
    }

    function exportData() {
        if (!state.rows.length) return;
        const headers = [
            t('je_col_date'), t('je_col_entry_no'), t('je_col_description'),
            t('je_col_reference'), t('je_col_debit'), t('je_col_credit'),
            t('je_col_status'), t('je_col_created_by'),
        ];
        const rows = state.rows.map((r) => [
            r.entry_date,
            r.entry_no,
            r.description || '',
            refLabel(r.reference_type || 'manual'),
            r.total_debit,
            r.total_credit,
            statusLabel(r.status),
            r.created_by_name || '',
        ]);
        exportCsv(`journal-${els.dateFrom?.value || 'export'}.csv`, [headers, ...rows]);
    }

    function setSourceFilter(source) {
        state.source = source;
        state.reference = '';
        state.page = 1;
        els.refFilters?.querySelectorAll('.acc-je-chip').forEach((c) => c.classList.remove('is-active'));
        const allChip = els.refFilters?.querySelector('[data-reference=""]');
        allChip?.classList.add('is-active');
        load();
    }

    els.stats?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-stat-filter]');
        if (!btn) return;
        const filter = btn.dataset.statFilter;
        if (filter === 'all') setSourceFilter('');
        else setSourceFilter(filter);
    });

    els.statusFilters?.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-status]');
        if (!chip) return;
        state.status = chip.dataset.status;
        els.statusFilters.querySelectorAll('.acc-je-chip').forEach((c) => {
            const active = c.dataset.status === state.status;
            c.classList.toggle('is-active', active);
            c.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        state.page = 1;
        load();
    });

    els.refFilters?.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-reference]');
        if (!chip) return;
        state.reference = chip.dataset.reference;
        state.source = '';
        els.refFilters.querySelectorAll('.acc-je-chip').forEach((c) => {
            c.classList.toggle('is-active', c.dataset.reference === state.reference);
        });
        state.page = 1;
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
    els.newBtn?.addEventListener('click', () => {
        initFormLines();
        openModal(els.formModal);
    });
    els.addLineBtn?.addEventListener('click', () => {
        els.linesRoot?.appendChild(createLineRow());
        updateBalance();
    });
    els.form?.addEventListener('submit', submitJournal);
    els.formClose?.addEventListener('click', () => closeModal(els.formModal));
    els.formCancel?.addEventListener('click', () => closeModal(els.formModal));
    els.detailClose?.addEventListener('click', () => closeModal(els.detailModal));
    els.detailOk?.addEventListener('click', () => closeModal(els.detailModal));

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

    (async () => {
        if (canManage) await loadAccounts();
        await load();
    })();
});
