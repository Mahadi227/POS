/**
 * Accounting bank accounts v1 — balances, transactions, charts
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accBkRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;
    const PAGE_SIZE = 15;
    const canManage = Boolean(window.ADMIN_PAGE?.canManage);

    const TYPE_LABELS = {
        deposit: 'bk_type_deposit',
        withdrawal: 'bk_type_withdrawal',
        transfer: 'bk_type_transfer',
        fee: 'bk_type_fee',
        reconciliation: 'bk_type_reconciliation',
    };

    const TYPE_COLORS = {
        deposit: '#059669',
        withdrawal: '#d97706',
        transfer: '#2563eb',
        fee: '#dc2626',
        reconciliation: '#64748b',
    };

    const BANK_CHART_COLORS = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#ca8a04', '#64748b'];

    const state = {
        rows: [],
        accounts: [],
        stats: {},
        insights: {},
        charts: {},
        type: 'all',
        flow: 'all',
        search: '',
        page: 1,
        selected: null,
        searchTimer: null,
        chartInstances: {},
    };

    const els = {
        count: document.getElementById('accBkCount'),
        statBalance: document.getElementById('accBkStatBalance'),
        statDeposits: document.getElementById('accBkStatDeposits'),
        statWithdrawals: document.getElementById('accBkStatWithdrawals'),
        statNet: document.getElementById('accBkStatNet'),
        stats: document.getElementById('accBkStats'),
        accountsInsight: document.getElementById('accBkAccounts'),
        avgBalance: document.getElementById('accBkAvgBalance'),
        inOutRatio: document.getElementById('accBkInOutRatio'),
        topBank: document.getElementById('accBkTopBank'),
        accountsSection: document.getElementById('accBkAccountsSection'),
        accountCards: document.getElementById('accBkAccountCards'),
        search: document.getElementById('accBkSearch'),
        searchClear: document.getElementById('accBkSearchClear'),
        dateFrom: document.getElementById('accBkDateFrom'),
        dateTo: document.getElementById('accBkDateTo'),
        typeFilters: document.getElementById('accBkTypeFilters'),
        meta: document.getElementById('accBkMeta'),
        pagePrev: document.getElementById('accBkPrev'),
        pageNext: document.getElementById('accBkNext'),
        pageInfo: document.getElementById('accBkPageInfo'),
        exportBtn: document.getElementById('accBkExportBtn'),
        printBtn: document.getElementById('accBkPrintBtn'),
        refreshBtn: document.getElementById('accBkRefreshBtn'),
        addAccountBtn: document.getElementById('accBkAddAccountBtn'),
        addTxBtn: document.getElementById('accBkAddTxBtn'),
        detailModal: document.getElementById('accBkDetailModal'),
        detailBody: document.getElementById('accBkDetailBody'),
        detailClose: document.getElementById('accBkDetailClose'),
        accountModal: document.getElementById('accBkAccountModal'),
        accountForm: document.getElementById('accBkAccountForm'),
        accountClose: document.getElementById('accBkAccountClose'),
        accountCancel: document.getElementById('accBkAccountCancel'),
        accountSubmit: document.getElementById('accBkAccountSubmit'),
        txModal: document.getElementById('accBkTxModal'),
        txForm: document.getElementById('accBkTxForm'),
        txAccountSelect: document.getElementById('accBkTxAccountSelect'),
        txClose: document.getElementById('accBkTxClose'),
        txCancel: document.getElementById('accBkTxCancel'),
        txSubmit: document.getElementById('accBkTxSubmit'),
        banksEmpty: document.getElementById('accBkBanksEmpty'),
        trendEmpty: document.getElementById('accBkTrendEmpty'),
        accountsEmpty: document.getElementById('accBkAccountsEmpty'),
        banksLegend: document.getElementById('accBkBanksLegend'),
    };

    function locale() {
        return window.ADMIN_CONFIG?.locale || 'fr-FR';
    }

    function typeLabel(type) {
        const key = TYPE_LABELS[type];
        return key ? t(key) : (type || '—');
    }

    function typeClass(type) {
        return `acc-bk-type--${type || 'deposit'}`;
    }

    function formatDate(d) {
        if (!d) return '—';
        return new Date(`${d}T12:00:00`).toLocaleDateString(locale(), { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function formatDayLabel(day) {
        if (!day) return '';
        return new Date(`${day}T12:00:00`).toLocaleDateString(locale(), { day: '2-digit', month: 'short' });
    }

    function queryParams() {
        return {
            type: state.type === 'all' ? '' : state.type,
            flow: state.flow === 'all' ? '' : state.flow,
            search: state.search.trim(),
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
        };
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-bk-stat__value, .acc-bk-insight__value').forEach((el) => {
            el.classList.toggle('is-loading', on);
        });
    }

    function destroyChart(id) {
        if (state.chartInstances[id]) {
            state.chartInstances[id].destroy();
            delete state.chartInstances[id];
        }
    }

    function destroyAllCharts() {
        Object.keys(state.chartInstances).forEach(destroyChart);
    }

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: dark ? 'rgba(148,163,184,0.12)' : 'rgba(0,0,0,0.06)',
            text: dark ? '#9ca3af' : '#6b7280',
        };
    }

    function baseLineOptions() {
        const c = chartColors();
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: c.grid }, ticks: { color: c.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                y: { grid: { color: c.grid }, ticks: { color: c.text } },
            },
        };
    }

    function renderBanksDonut(items) {
        const ctx = document.getElementById('accBkBanks');
        if (!ctx || !window.Chart) return;
        destroyChart('accBkBanks');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.banksEmpty) els.banksEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (els.banksLegend) {
            els.banksLegend.innerHTML = hasData
                ? filtered.map((item, i) => {
                    const color = BANK_CHART_COLORS[i % BANK_CHART_COLORS.length];
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(item.bank)}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.chartInstances.accBkBanks = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map((x) => x.bank),
                datasets: [{
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((_, i) => BANK_CHART_COLORS[i % BANK_CHART_COLORS.length]),
                    borderWidth: 0,
                }],
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { display: false } } },
        });
    }

    function renderTrendChart(items) {
        const ctx = document.getElementById('accBkTrend');
        if (!ctx || !window.Chart) return;
        destroyChart('accBkTrend');
        const rows = items || [];
        const hasData = rows.some((x) => Number(x.in_amount) > 0 || Number(x.out_amount) > 0);
        if (els.trendEmpty) els.trendEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accBkTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: rows.map((x) => formatDayLabel(x.day)),
                datasets: [
                    {
                        label: t('bk_stat_deposits'),
                        data: rows.map((x) => x.in_amount),
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5,150,105,0.08)',
                        fill: true,
                        tension: 0.35,
                    },
                    {
                        label: t('bk_stat_withdrawals'),
                        data: rows.map((x) => x.out_amount),
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217,119,6,0.08)',
                        fill: true,
                        tension: 0.35,
                    },
                ],
            },
            options: {
                ...baseLineOptions(),
                plugins: {
                    legend: { display: true, position: 'top', labels: { color: c.text, boxWidth: 12 } },
                },
            },
        });
    }

    function renderAccountsChart(items) {
        const ctx = document.getElementById('accBkAccountsChart');
        if (!ctx || !window.Chart) return;
        destroyChart('accBkAccountsChart');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.accountsEmpty) els.accountsEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accBkAccountsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => x.label),
                datasets: [{
                    label: t('bk_account_balance'),
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((_, i) => BANK_CHART_COLORS[i % BANK_CHART_COLORS.length]),
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: c.grid }, ticks: { color: c.text } },
                    y: { grid: { display: false }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderHero(stats, insights) {
        if (els.statBalance) els.statBalance.textContent = money(stats.total_balance);
        if (els.statDeposits) els.statDeposits.textContent = money(stats.in_amount);
        if (els.statWithdrawals) els.statWithdrawals.textContent = money(stats.out_amount);
        if (els.statNet) {
            els.statNet.textContent = money(stats.net_flow);
            els.statNet.classList.toggle('acc-cell--pos', Number(stats.net_flow) >= 0);
            els.statNet.classList.toggle('acc-cell--neg', Number(stats.net_flow) < 0);
        }

        if (els.accountsInsight) els.accountsInsight.textContent = String(insights.account_count ?? 0);
        if (els.avgBalance) els.avgBalance.textContent = money(insights.avg_balance);
        if (els.inOutRatio) {
            const ratio = Number(insights.in_out_ratio || 0);
            els.inOutRatio.textContent = ratio > 0
                ? `${ratio.toLocaleString(locale(), { maximumFractionDigits: 2 })}×`
                : '—';
        }
        if (els.topBank) {
            const tb = insights.top_bank;
            els.topBank.textContent = tb && tb !== '—' ? tb : '—';
        }

        document.querySelectorAll('.acc-bk-stat__value, .acc-bk-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.count) {
            const scope = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
            els.count.textContent = `${scope} · ${stats.transaction_count ?? state.rows.length} ${t('bk_tx_count')}`;
        }
    }

    function renderAccountCards(accounts) {
        if (!els.accountCards || !els.accountsSection) return;
        const list = accounts || [];
        els.accountsSection.hidden = !list.length;
        if (!list.length) {
            els.accountCards.innerHTML = '';
            return;
        }
        els.accountCards.innerHTML = list.map((a) => `
            <article class="acc-bk-account-card" data-account-id="${esc(String(a.id))}">
                <div class="acc-bk-account-card__head">
                    <span class="acc-bk-bank">${esc(a.bank_name)}</span>
                    <strong class="acc-bk-account-card__balance">${esc(money(a.current_balance))}</strong>
                </div>
                <h4>${esc(a.account_name || '—')}</h4>
                ${a.account_number ? `<p class="acc-bk-account-card__number">${esc(a.account_number)}</p>` : ''}
                <span class="acc-bk-currency">${esc(a.currency || 'FCFA')}</span>
            </article>`).join('');

        els.accountCards.querySelectorAll('.acc-bk-account-card').forEach((card) => {
            card.addEventListener('click', () => {
                const id = card.dataset.accountId;
                if (!id) return;
                state.type = 'all';
                state.flow = 'all';
                syncFilterChips();
                if (els.search) els.search.value = '';
                state.search = '';
                if (els.searchClear) els.searchClear.hidden = true;
                loadWithAccount(id);
            });
        });
    }

    function syncFilterChips() {
        els.typeFilters?.querySelectorAll('.acc-bk-chip').forEach((chip) => {
            const active = chip.dataset.type === state.type;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function populateTxAccountSelect() {
        if (!els.txAccountSelect) return;
        const opts = (state.accounts || []).map((a) =>
            `<option value="${esc(String(a.id))}">${esc(a.account_name)} — ${esc(a.bank_name)}</option>`
        ).join('');
        els.txAccountSelect.innerHTML = `<option value="">—</option>${opts}`;
    }

    function paginatedRows() {
        const rows = state.rows;
        const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        const start = (state.page - 1) * PAGE_SIZE;
        return { rows: rows.slice(start, start + PAGE_SIZE), totalPages, total: rows.length };
    }

    function renderTable() {
        const { rows, totalPages, total } = paginatedRows();
        if (els.pageInfo) els.pageInfo.textContent = `${state.page} / ${totalPages}`;
        if (els.pagePrev) els.pagePrev.disabled = state.page <= 1;
        if (els.pageNext) els.pageNext.disabled = state.page >= totalPages;
        if (els.meta) els.meta.textContent = total ? `${total} ${t('records')}` : t('no_data');

        if (!rows.length) {
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">account_balance</span><p>${esc(t('no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="acc-bk-table-wrap">
                <table class="modern-table acc-table acc-bk-table">
                    <thead><tr>
                        <th>${esc(t('bk_col_date'))}</th>
                        <th>${esc(t('bk_col_account'))}</th>
                        <th>${esc(t('bk_col_bank'))}</th>
                        <th>${esc(t('bk_col_type'))}</th>
                        <th class="acc-bk-num">${esc(t('bk_col_amount'))}</th>
                        <th>${esc(t('bk_col_reference'))}</th>
                        <th>${esc(t('bk_col_reconciled'))}</th>
                        <th>${esc(t('bk_col_by'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr>
                            <td>${esc(formatDate(r.transaction_date))}</td>
                            <td>${esc(r.account_name || '—')}</td>
                            <td>${esc(r.bank_name || '—')}</td>
                            <td><span class="acc-bk-type ${typeClass(r.transaction_type)}">${esc(typeLabel(r.transaction_type))}</span></td>
                            <td class="acc-bk-num">${esc(money(r.amount))}</td>
                            <td>${esc(r.reference || '—')}</td>
                            <td>${esc(Number(r.reconciled) ? t('bk_reconciled_yes') : t('bk_reconciled_no'))}</td>
                            <td>${esc(r.created_by_name || '—')}</td>
                            <td><button type="button" class="acc-bk-link" data-id="${esc(String(r.id))}">${esc(t('bk_view_details'))}</button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-bk-cards">${rows.map((r) => `
                <article class="acc-bk-card">
                    <div class="acc-bk-card__head">
                        <span>${esc(formatDate(r.transaction_date))}</span>
                        <span class="acc-bk-type ${typeClass(r.transaction_type)}">${esc(typeLabel(r.transaction_type))}</span>
                    </div>
                    <h4>${esc(r.account_name || '—')}</h4>
                    <p class="acc-bk-card__bank">${esc(r.bank_name || '—')}</p>
                    <dl>
                        <div><dt>${esc(t('bk_col_amount'))}</dt><dd>${esc(money(r.amount))}</dd></div>
                        <div><dt>${esc(t('bk_col_reconciled'))}</dt><dd>${esc(Number(r.reconciled) ? t('bk_reconciled_yes') : t('bk_reconciled_no'))}</dd></div>
                    </dl>
                    <button type="button" class="acc-bk-link" data-id="${esc(String(r.id))}">${esc(t('bk_view_details'))}</button>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('.acc-bk-link').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.id)));
        });
    }

    function openDetail(id) {
        const row = state.rows.find((r) => Number(r.id) === id);
        if (!row || !els.detailBody || !els.detailModal) return;
        state.selected = row;
        els.detailBody.innerHTML = `
            <dl class="acc-bk-detail-grid">
                <div><dt>${esc(t('bk_col_date'))}</dt><dd>${esc(formatDate(row.transaction_date))}</dd></div>
                <div><dt>${esc(t('bk_col_account'))}</dt><dd>${esc(row.account_name || '—')}</dd></div>
                <div><dt>${esc(t('bk_col_bank'))}</dt><dd>${esc(row.bank_name || '—')}</dd></div>
                <div><dt>${esc(t('bk_col_type'))}</dt><dd><span class="acc-bk-type ${typeClass(row.transaction_type)}">${esc(typeLabel(row.transaction_type))}</span></dd></div>
                <div><dt>${esc(t('bk_col_amount'))}</dt><dd><strong>${esc(money(row.amount))}</strong></dd></div>
                <div><dt>${esc(t('bk_col_reconciled'))}</dt><dd>${esc(Number(row.reconciled) ? t('bk_reconciled_yes') : t('bk_reconciled_no'))}</dd></div>
                <div><dt>${esc(t('bk_col_by'))}</dt><dd>${esc(row.created_by_name || '—')}</dd></div>
                ${row.reference ? `<div class="acc-bk-detail-full"><dt>${esc(t('bk_col_reference'))}</dt><dd>${esc(row.reference)}</dd></div>` : ''}
                ${row.account_number ? `<div><dt>${esc(t('bk_form_account_number'))}</dt><dd><code>${esc(row.account_number)}</code></dd></div>` : ''}
            </dl>`;
        els.detailModal.hidden = false;
        updateModalBodyLock();
    }

    function closeDetail() {
        if (!els.detailModal) return;
        els.detailModal.hidden = true;
        updateModalBodyLock();
        state.selected = null;
    }

    function updateModalBodyLock() {
        const anyOpen = [els.detailModal, els.accountModal, els.txModal].some((m) => m && !m.hidden);
        document.body.classList.toggle('acc-bk-modal-open', anyOpen);
    }

    function openModal(modal) {
        if (!modal) return;
        modal.hidden = false;
        updateModalBodyLock();
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        updateModalBodyLock();
    }

    function renderCharts(charts) {
        renderBanksDonut(charts?.by_bank);
        renderTrendChart(charts?.trend);
        renderAccountsChart(charts?.account_balances);
    }

    function applyData(data) {
        state.rows = data.rows || [];
        state.accounts = data.accounts || [];
        state.stats = data.stats || {};
        state.insights = data.insights || {};
        state.charts = data.charts || {};
        state.page = 1;
        renderHero(state.stats, state.insights);
        renderAccountCards(state.accounts);
        populateTxAccountSelect();
        renderCharts(state.charts);
        renderTable();
    }

    async function load(extra = {}) {
        hideError();
        setLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getAccounting('banks', { ...queryParams(), ...extra });
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? res.data?.module_ready ?? true);
            applyData(res.data || {});
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyAllCharts();
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">account_balance</span><p>${esc(e.message)}</p></div>`;
        }
    }

    function loadWithAccount(accountId) {
        load({ bank_account_id: accountId });
    }

    async function submitAccount(e) {
        e.preventDefault();
        if (!els.accountForm) return;
        const fd = new FormData(els.accountForm);
        const payload = Object.fromEntries(fd.entries());
        payload.opening_balance = parseFloat(payload.opening_balance) || 0;
        if (window.ADMIN_PAGE?.storeId) payload.store_id = window.ADMIN_PAGE.storeId;
        if (els.accountSubmit) els.accountSubmit.disabled = true;
        try {
            const res = await AdminAPI.postAccounting('banks', payload);
            if (res.status !== 'success') throw new Error(res.message);
            closeModal(els.accountModal);
            els.accountForm.reset();
            const curInput = els.accountForm.querySelector('[name="currency"]');
            if (curInput) curInput.value = 'FCFA';
            const balInput = els.accountForm.querySelector('[name="opening_balance"]');
            if (balInput) balInput.value = '0';
            await load();
        } catch (err) {
            showError(err.message || t('load_error'));
        } finally {
            if (els.accountSubmit) els.accountSubmit.disabled = false;
        }
    }

    async function submitTx(e) {
        e.preventDefault();
        if (!els.txForm) return;
        const fd = new FormData(els.txForm);
        const payload = Object.fromEntries(fd.entries());
        payload.amount = parseFloat(payload.amount);
        payload.bank_account_id = parseInt(payload.bank_account_id, 10);
        payload.reconciled = fd.get('reconciled') ? 1 : 0;
        if (window.ADMIN_PAGE?.storeId) payload.store_id = window.ADMIN_PAGE.storeId;
        if (els.txSubmit) els.txSubmit.disabled = true;
        try {
            const res = await AdminAPI.postAccounting('banks', payload, 'transaction');
            if (res.status !== 'success') throw new Error(res.message);
            closeModal(els.txModal);
            els.txForm.reset();
            const dateInput = els.txForm.querySelector('[name="transaction_date"]');
            if (dateInput) dateInput.value = new Date().toISOString().slice(0, 10);
            await load();
        } catch (err) {
            showError(err.message || t('load_error'));
        } finally {
            if (els.txSubmit) els.txSubmit.disabled = false;
        }
    }

    function exportData() {
        if (!state.rows.length) return;
        exportCsv(`bank-accounts-${els.dateTo?.value || 'export'}.csv`, [
            [t('bk_col_date'), t('bk_col_account'), t('bk_col_bank'), t('bk_col_type'), t('bk_col_amount'), t('bk_col_reference'), t('bk_col_reconciled'), t('bk_col_by')],
            ...state.rows.map((r) => [
                r.transaction_date,
                r.account_name,
                r.bank_name,
                r.transaction_type,
                r.amount,
                r.reference || '',
                Number(r.reconciled) ? t('bk_reconciled_yes') : t('bk_reconciled_no'),
                r.created_by_name || '',
            ]),
        ]);
    }

    els.stats?.querySelectorAll('.acc-bk-stat--click').forEach((btn) => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.statFilter || 'all';
            state.flow = filter === 'all' ? 'all' : filter;
            state.type = 'all';
            syncFilterChips();
            load();
        });
    });

    els.typeFilters?.querySelectorAll('.acc-bk-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            state.type = chip.dataset.type || 'all';
            state.flow = 'all';
            syncFilterChips();
            load();
        });
    });

    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => {
            state.search = els.search.value;
            if (els.searchClear) els.searchClear.hidden = !state.search;
            load();
        }, 300);
    });

    els.searchClear?.addEventListener('click', () => {
        if (els.search) els.search.value = '';
        state.search = '';
        els.searchClear.hidden = true;
        load();
    });

    els.dateFrom?.addEventListener('change', load);
    els.dateTo?.addEventListener('change', load);
    els.pagePrev?.addEventListener('click', () => { state.page -= 1; renderTable(); });
    els.pageNext?.addEventListener('click', () => { state.page += 1; renderTable(); });
    els.refreshBtn?.addEventListener('click', load);
    els.exportBtn?.addEventListener('click', exportData);
    els.printBtn?.addEventListener('click', () => window.print());
    els.detailClose?.addEventListener('click', closeDetail);
    els.detailModal?.addEventListener('click', (e) => {
        if (e.target === els.detailModal) closeDetail();
    });

    if (canManage) {
        els.addAccountBtn?.addEventListener('click', () => openModal(els.accountModal));
        els.addTxBtn?.addEventListener('click', () => {
            populateTxAccountSelect();
            openModal(els.txModal);
        });
        els.accountForm?.addEventListener('submit', submitAccount);
        els.txForm?.addEventListener('submit', submitTx);
        els.accountClose?.addEventListener('click', () => closeModal(els.accountModal));
        els.accountCancel?.addEventListener('click', () => closeModal(els.accountModal));
        els.txClose?.addEventListener('click', () => closeModal(els.txModal));
        els.txCancel?.addEventListener('click', () => closeModal(els.txModal));
        els.accountModal?.addEventListener('click', (e) => {
            if (e.target === els.accountModal) closeModal(els.accountModal);
        });
        els.txModal?.addEventListener('click', (e) => {
            if (e.target === els.txModal) closeModal(els.txModal);
        });
    }

    document.addEventListener('acc:refresh', load);
    document.addEventListener('themechange', () => {
        if (state.charts) renderCharts(state.charts);
    });

    if (els.searchClear) els.searchClear.hidden = true;
    load();
});
