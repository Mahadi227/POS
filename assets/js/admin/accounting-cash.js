/**
 * Accounting cash management v1 — registers, transactions, charts
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accCmRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;
    const PAGE_SIZE = 15;
    const canManage = Boolean(window.ADMIN_PAGE?.canManage);

    const TYPE_LABELS = {
        deposit: 'cm_type_deposit',
        withdrawal: 'cm_type_withdrawal',
        opening: 'cm_type_opening',
        closing: 'cm_type_closing',
        transfer: 'cm_type_transfer',
        sale: 'cm_type_sale',
        expense: 'cm_type_expense',
    };

    const TYPE_COLORS = {
        deposit: '#059669',
        withdrawal: '#d97706',
        opening: '#2563eb',
        closing: '#64748b',
        transfer: '#7c3aed',
        sale: '#0891b2',
        expense: '#dc2626',
    };

    const CHART_COLORS = ['#059669', '#2563eb', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#64748b'];

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
        count: document.getElementById('accCmCount'),
        statBalance: document.getElementById('accCmStatBalance'),
        statIn: document.getElementById('accCmStatIn'),
        statOut: document.getElementById('accCmStatOut'),
        statNet: document.getElementById('accCmStatNet'),
        stats: document.getElementById('accCmStats'),
        registers: document.getElementById('accCmRegisters'),
        avgBalance: document.getElementById('accCmAvgBalance'),
        inOutRatio: document.getElementById('accCmInOutRatio'),
        topRegister: document.getElementById('accCmTopRegister'),
        registersSection: document.getElementById('accCmRegistersSection'),
        registerCards: document.getElementById('accCmRegisterCards'),
        search: document.getElementById('accCmSearch'),
        searchClear: document.getElementById('accCmSearchClear'),
        dateFrom: document.getElementById('accCmDateFrom'),
        dateTo: document.getElementById('accCmDateTo'),
        typeFilters: document.getElementById('accCmTypeFilters'),
        meta: document.getElementById('accCmMeta'),
        pagePrev: document.getElementById('accCmPrev'),
        pageNext: document.getElementById('accCmNext'),
        pageInfo: document.getElementById('accCmPageInfo'),
        exportBtn: document.getElementById('accCmExportBtn'),
        printBtn: document.getElementById('accCmPrintBtn'),
        refreshBtn: document.getElementById('accCmRefreshBtn'),
        addRegisterBtn: document.getElementById('accCmAddRegisterBtn'),
        addTxBtn: document.getElementById('accCmAddTxBtn'),
        detailModal: document.getElementById('accCmDetailModal'),
        detailBody: document.getElementById('accCmDetailBody'),
        detailClose: document.getElementById('accCmDetailClose'),
        registerModal: document.getElementById('accCmRegisterModal'),
        registerForm: document.getElementById('accCmRegisterForm'),
        registerClose: document.getElementById('accCmRegisterClose'),
        registerCancel: document.getElementById('accCmRegisterCancel'),
        registerSubmit: document.getElementById('accCmRegisterSubmit'),
        txModal: document.getElementById('accCmTxModal'),
        txForm: document.getElementById('accCmTxForm'),
        txRegisterSelect: document.getElementById('accCmTxRegisterSelect'),
        txClose: document.getElementById('accCmTxClose'),
        txCancel: document.getElementById('accCmTxCancel'),
        txSubmit: document.getElementById('accCmTxSubmit'),
        typesEmpty: document.getElementById('accCmTypesEmpty'),
        trendEmpty: document.getElementById('accCmTrendEmpty'),
        registersEmpty: document.getElementById('accCmRegistersEmpty'),
        typesLegend: document.getElementById('accCmTypesLegend'),
    };

    function locale() {
        return window.ADMIN_CONFIG?.locale || 'fr-FR';
    }

    function typeLabel(type) {
        const key = TYPE_LABELS[type];
        return key ? t(key) : (type || '—');
    }

    function typeClass(type) {
        return `acc-cm-type--${type || 'deposit'}`;
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
        document.querySelectorAll('.acc-cm-stat__value, .acc-cm-insight__value').forEach((el) => {
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

    function renderTypesDonut(items) {
        const ctx = document.getElementById('accCmTypes');
        if (!ctx || !window.Chart) return;
        destroyChart('accCmTypes');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.typesEmpty) els.typesEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (els.typesLegend) {
            els.typesLegend.innerHTML = hasData
                ? filtered.map((item) => {
                    const color = TYPE_COLORS[item.type] || '#64748b';
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(typeLabel(item.type))}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.chartInstances.accCmTypes = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map((x) => typeLabel(x.type)),
                datasets: [{
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((x) => TYPE_COLORS[x.type] || '#64748b'),
                    borderWidth: 0,
                }],
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { display: false } } },
        });
    }

    function renderTrendChart(items) {
        const ctx = document.getElementById('accCmTrend');
        if (!ctx || !window.Chart) return;
        destroyChart('accCmTrend');
        const rows = items || [];
        const hasData = rows.some((x) => Number(x.in_amount) > 0 || Number(x.out_amount) > 0);
        if (els.trendEmpty) els.trendEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accCmTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: rows.map((x) => formatDayLabel(x.day)),
                datasets: [
                    {
                        label: t('cash_in'),
                        data: rows.map((x) => x.in_amount),
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5,150,105,0.08)',
                        fill: true,
                        tension: 0.35,
                    },
                    {
                        label: t('cash_out'),
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

    function renderRegistersChart(items) {
        const ctx = document.getElementById('accCmRegistersChart');
        if (!ctx || !window.Chart) return;
        destroyChart('accCmRegistersChart');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.registersEmpty) els.registersEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accCmRegistersChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => x.label),
                datasets: [{
                    label: t('cm_register_balance'),
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((_, i) => CHART_COLORS[i % CHART_COLORS.length]),
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
        if (els.statIn) els.statIn.textContent = money(stats.in_amount);
        if (els.statOut) els.statOut.textContent = money(stats.out_amount);
        if (els.statNet) {
            els.statNet.textContent = money(stats.net_flow);
            els.statNet.classList.toggle('acc-cell--pos', Number(stats.net_flow) >= 0);
            els.statNet.classList.toggle('acc-cell--neg', Number(stats.net_flow) < 0);
        }

        if (els.registers) els.registers.textContent = String(insights.register_count ?? 0);
        if (els.avgBalance) els.avgBalance.textContent = money(insights.avg_balance);
        if (els.inOutRatio) {
            const ratio = Number(insights.in_out_ratio || 0);
            els.inOutRatio.textContent = ratio > 0
                ? `${ratio.toLocaleString(locale(), { maximumFractionDigits: 2 })}×`
                : '—';
        }
        if (els.topRegister) {
            const tr = insights.top_register;
            els.topRegister.textContent = tr && tr !== '—' ? tr : '—';
        }

        document.querySelectorAll('.acc-cm-stat__value, .acc-cm-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.count) {
            const scope = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
            els.count.textContent = `${scope} · ${stats.transaction_count ?? state.rows.length} ${t('cm_tx_count')}`;
        }
    }

    function renderRegisterCards(accounts) {
        if (!els.registerCards || !els.registersSection) return;
        const list = accounts || [];
        els.registersSection.hidden = !list.length;
        if (!list.length) {
            els.registerCards.innerHTML = '';
            return;
        }
        els.registerCards.innerHTML = list.map((a) => `
            <article class="acc-cm-register-card" data-account-id="${esc(String(a.id))}">
                <div class="acc-cm-register-card__head">
                    <span class="material-icons-round" aria-hidden="true">point_of_sale</span>
                    <strong class="acc-cm-register-card__balance">${esc(money(a.current_balance))}</strong>
                </div>
                <h4>${esc(a.name || '—')}</h4>
            </article>`).join('');

        els.registerCards.querySelectorAll('.acc-cm-register-card').forEach((card) => {
            card.addEventListener('click', () => {
                const id = card.dataset.accountId;
                if (!id) return;
                state.type = 'all';
                state.flow = 'all';
                syncFilterChips();
                if (els.search) els.search.value = '';
                state.search = '';
                if (els.searchClear) els.searchClear.hidden = true;
                loadWithRegister(id);
            });
        });
    }

    function syncFilterChips() {
        els.typeFilters?.querySelectorAll('.acc-cm-chip').forEach((chip) => {
            const active = chip.dataset.type === state.type;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function populateTxRegisterSelect() {
        if (!els.txRegisterSelect) return;
        const opts = (state.accounts || []).map((a) =>
            `<option value="${esc(String(a.id))}">${esc(a.name)}</option>`
        ).join('');
        els.txRegisterSelect.innerHTML = `<option value="">—</option>${opts}`;
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
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">payments</span><p>${esc(t('no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="acc-cm-table-wrap">
                <table class="modern-table acc-table acc-cm-table">
                    <thead><tr>
                        <th>${esc(t('cm_col_date'))}</th>
                        <th>${esc(t('cm_col_register'))}</th>
                        <th>${esc(t('cm_col_type'))}</th>
                        <th class="acc-cm-num">${esc(t('cm_col_amount'))}</th>
                        <th class="acc-cm-num">${esc(t('cm_col_balance_after'))}</th>
                        <th>${esc(t('cm_col_reference'))}</th>
                        <th>${esc(t('cm_col_by'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr>
                            <td>${esc(formatDate(r.transaction_date))}</td>
                            <td>${esc(r.register_name || '—')}</td>
                            <td><span class="acc-cm-type ${typeClass(r.transaction_type)}">${esc(typeLabel(r.transaction_type))}</span></td>
                            <td class="acc-cm-num">${esc(money(r.amount))}</td>
                            <td class="acc-cm-num">${esc(r.balance_after != null ? money(r.balance_after) : '—')}</td>
                            <td>${esc(r.reference || '—')}</td>
                            <td>${esc(r.created_by_name || '—')}</td>
                            <td><button type="button" class="acc-cm-link" data-id="${esc(String(r.id))}">${esc(t('cm_view_details'))}</button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-cm-cards">${rows.map((r) => `
                <article class="acc-cm-card">
                    <div class="acc-cm-card__head">
                        <span>${esc(formatDate(r.transaction_date))}</span>
                        <span class="acc-cm-type ${typeClass(r.transaction_type)}">${esc(typeLabel(r.transaction_type))}</span>
                    </div>
                    <h4>${esc(r.register_name || '—')}</h4>
                    <dl>
                        <div><dt>${esc(t('cm_col_amount'))}</dt><dd>${esc(money(r.amount))}</dd></div>
                        <div><dt>${esc(t('cm_col_balance_after'))}</dt><dd>${esc(r.balance_after != null ? money(r.balance_after) : '—')}</dd></div>
                    </dl>
                    <button type="button" class="acc-cm-link" data-id="${esc(String(r.id))}">${esc(t('cm_view_details'))}</button>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('.acc-cm-link').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.id)));
        });
    }

    function openDetail(id) {
        const row = state.rows.find((r) => Number(r.id) === id);
        if (!row || !els.detailBody || !els.detailModal) return;
        state.selected = row;
        els.detailBody.innerHTML = `
            <dl class="acc-cm-detail-grid">
                <div><dt>${esc(t('cm_col_date'))}</dt><dd>${esc(formatDate(row.transaction_date))}</dd></div>
                <div><dt>${esc(t('cm_col_register'))}</dt><dd>${esc(row.register_name || '—')}</dd></div>
                <div><dt>${esc(t('cm_col_type'))}</dt><dd><span class="acc-cm-type ${typeClass(row.transaction_type)}">${esc(typeLabel(row.transaction_type))}</span></dd></div>
                <div><dt>${esc(t('cm_col_amount'))}</dt><dd><strong>${esc(money(row.amount))}</strong></dd></div>
                <div><dt>${esc(t('cm_col_balance_after'))}</dt><dd>${esc(row.balance_after != null ? money(row.balance_after) : '—')}</dd></div>
                <div><dt>${esc(t('cm_col_by'))}</dt><dd>${esc(row.created_by_name || '—')}</dd></div>
                ${row.reference ? `<div><dt>${esc(t('cm_col_reference'))}</dt><dd>${esc(row.reference)}</dd></div>` : ''}
                ${row.notes ? `<div class="acc-cm-detail-full"><dt>${esc(t('cm_col_notes'))}</dt><dd>${esc(row.notes)}</dd></div>` : ''}
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
        const anyOpen = [els.detailModal, els.registerModal, els.txModal].some((m) => m && !m.hidden);
        document.body.classList.toggle('acc-cm-modal-open', anyOpen);
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
        renderTypesDonut(charts?.by_type);
        renderTrendChart(charts?.trend);
        renderRegistersChart(charts?.account_balances);
    }

    function applyData(data) {
        state.rows = data.rows || [];
        state.accounts = data.accounts || [];
        state.stats = data.stats || {};
        state.insights = data.insights || {};
        state.charts = data.charts || {};
        state.page = 1;
        renderHero(state.stats, state.insights);
        renderRegisterCards(state.accounts);
        populateTxRegisterSelect();
        renderCharts(state.charts);
        renderTable();
    }

    async function load(extra = {}) {
        hideError();
        setLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getAccounting('cash', { ...queryParams(), ...extra });
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? res.data?.module_ready ?? true);
            applyData(res.data || {});
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyAllCharts();
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">payments</span><p>${esc(e.message)}</p></div>`;
        }
    }

    function loadWithRegister(accountId) {
        load({ cash_account_id: accountId });
    }

    async function submitRegister(e) {
        e.preventDefault();
        if (!els.registerForm) return;
        const fd = new FormData(els.registerForm);
        const payload = Object.fromEntries(fd.entries());
        payload.opening_balance = parseFloat(payload.opening_balance) || 0;
        if (window.ADMIN_PAGE?.storeId) payload.store_id = window.ADMIN_PAGE.storeId;
        if (els.registerSubmit) els.registerSubmit.disabled = true;
        try {
            const res = await AdminAPI.postAccounting('cash', payload);
            if (res.status !== 'success') throw new Error(res.message);
            closeModal(els.registerModal);
            els.registerForm.reset();
            const balInput = els.registerForm.querySelector('[name="opening_balance"]');
            if (balInput) balInput.value = '0';
            await load();
        } catch (err) {
            showError(err.message || t('load_error'));
        } finally {
            if (els.registerSubmit) els.registerSubmit.disabled = false;
        }
    }

    async function submitTx(e) {
        e.preventDefault();
        if (!els.txForm) return;
        const fd = new FormData(els.txForm);
        const payload = Object.fromEntries(fd.entries());
        payload.amount = parseFloat(payload.amount);
        payload.cash_account_id = parseInt(payload.cash_account_id, 10);
        if (window.ADMIN_PAGE?.storeId) payload.store_id = window.ADMIN_PAGE.storeId;
        if (els.txSubmit) els.txSubmit.disabled = true;
        try {
            const res = await AdminAPI.postAccounting('cash', payload, 'transaction');
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
        exportCsv(`cash-management-${els.dateTo?.value || 'export'}.csv`, [
            [t('cm_col_date'), t('cm_col_register'), t('cm_col_type'), t('cm_col_amount'), t('cm_col_balance_after'), t('cm_col_reference'), t('cm_col_notes'), t('cm_col_by')],
            ...state.rows.map((r) => [
                r.transaction_date,
                r.register_name,
                r.transaction_type,
                r.amount,
                r.balance_after ?? '',
                r.reference || '',
                r.notes || '',
                r.created_by_name || '',
            ]),
        ]);
    }

    els.stats?.querySelectorAll('.acc-cm-stat--click').forEach((btn) => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.statFilter || 'all';
            state.flow = filter === 'all' ? 'all' : filter;
            state.type = 'all';
            syncFilterChips();
            load();
        });
    });

    els.typeFilters?.querySelectorAll('.acc-cm-chip').forEach((chip) => {
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
        els.addRegisterBtn?.addEventListener('click', () => openModal(els.registerModal));
        els.addTxBtn?.addEventListener('click', () => {
            populateTxRegisterSelect();
            openModal(els.txModal);
        });
        els.registerForm?.addEventListener('submit', submitRegister);
        els.txForm?.addEventListener('submit', submitTx);
        els.registerClose?.addEventListener('click', () => closeModal(els.registerModal));
        els.registerCancel?.addEventListener('click', () => closeModal(els.registerModal));
        els.txClose?.addEventListener('click', () => closeModal(els.txModal));
        els.txCancel?.addEventListener('click', () => closeModal(els.txModal));
        els.registerModal?.addEventListener('click', (e) => {
            if (e.target === els.registerModal) closeModal(els.registerModal);
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
