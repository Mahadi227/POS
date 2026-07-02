/**
 * Accounting mobile money v1 — wallets, transactions, charts
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accMmRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;
    const PAGE_SIZE = 15;
    const canManage = Boolean(window.ADMIN_PAGE?.canManage);

    const PROVIDER_LABELS = {
        mtn: 'mm_provider_mtn',
        orange: 'mm_provider_orange',
        moov: 'mm_provider_moov',
        airtel: 'mm_provider_airtel',
        vodafone: 'mm_provider_vodafone',
        other: 'mm_provider_other',
    };

    const PROVIDER_COLORS = {
        mtn: '#ffcc00',
        orange: '#ff6600',
        moov: '#0066cc',
        airtel: '#ed1c24',
        vodafone: '#e60000',
        other: '#64748b',
    };

    const state = {
        rows: [],
        accounts: [],
        stats: {},
        insights: {},
        charts: {},
        direction: 'all',
        provider: 'all',
        search: '',
        page: 1,
        selected: null,
        searchTimer: null,
        chartInstances: {},
    };

    const els = {
        count: document.getElementById('accMmCount'),
        statBalance: document.getElementById('accMmStatBalance'),
        statIn: document.getElementById('accMmStatIn'),
        statOut: document.getElementById('accMmStatOut'),
        statNet: document.getElementById('accMmStatNet'),
        stats: document.getElementById('accMmStats'),
        wallets: document.getElementById('accMmWallets'),
        avgBalance: document.getElementById('accMmAvgBalance'),
        inOutRatio: document.getElementById('accMmInOutRatio'),
        topProvider: document.getElementById('accMmTopProvider'),
        walletSection: document.getElementById('accMmWalletsSection'),
        walletCards: document.getElementById('accMmWalletCards'),
        search: document.getElementById('accMmSearch'),
        searchClear: document.getElementById('accMmSearchClear'),
        dateFrom: document.getElementById('accMmDateFrom'),
        dateTo: document.getElementById('accMmDateTo'),
        directionFilters: document.getElementById('accMmDirectionFilters'),
        providerFilters: document.getElementById('accMmProviderFilters'),
        meta: document.getElementById('accMmMeta'),
        pagePrev: document.getElementById('accMmPrev'),
        pageNext: document.getElementById('accMmNext'),
        pageInfo: document.getElementById('accMmPageInfo'),
        exportBtn: document.getElementById('accMmExportBtn'),
        printBtn: document.getElementById('accMmPrintBtn'),
        refreshBtn: document.getElementById('accMmRefreshBtn'),
        addWalletBtn: document.getElementById('accMmAddWalletBtn'),
        addTxBtn: document.getElementById('accMmAddTxBtn'),
        detailModal: document.getElementById('accMmDetailModal'),
        detailBody: document.getElementById('accMmDetailBody'),
        detailClose: document.getElementById('accMmDetailClose'),
        walletModal: document.getElementById('accMmWalletModal'),
        walletForm: document.getElementById('accMmWalletForm'),
        walletClose: document.getElementById('accMmWalletClose'),
        walletCancel: document.getElementById('accMmWalletCancel'),
        walletSubmit: document.getElementById('accMmWalletSubmit'),
        txModal: document.getElementById('accMmTxModal'),
        txForm: document.getElementById('accMmTxForm'),
        txWalletSelect: document.getElementById('accMmTxWalletSelect'),
        txClose: document.getElementById('accMmTxClose'),
        txCancel: document.getElementById('accMmTxCancel'),
        txSubmit: document.getElementById('accMmTxSubmit'),
        providerEmpty: document.getElementById('accMmProviderEmpty'),
        trendEmpty: document.getElementById('accMmTrendEmpty'),
        walletsEmpty: document.getElementById('accMmWalletsEmpty'),
        providerLegend: document.getElementById('accMmProviderLegend'),
    };

    function locale() {
        return window.ADMIN_CONFIG?.locale || 'fr-FR';
    }

    function providerLabel(key) {
        const k = PROVIDER_LABELS[key];
        return k ? t(k) : (key || '—');
    }

    function directionLabel(dir) {
        return dir === 'out' ? t('mm_direction_out') : t('mm_direction_in');
    }

    function directionClass(dir) {
        return dir === 'out' ? 'acc-mm-dir--out' : 'acc-mm-dir--in';
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
            direction: state.direction === 'all' ? '' : state.direction,
            provider: state.provider === 'all' ? '' : state.provider,
            search: state.search.trim(),
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
        };
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-mm-stat__value, .acc-mm-insight__value').forEach((el) => {
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

    function renderProviderDonut(items) {
        const ctx = document.getElementById('accMmProvider');
        if (!ctx || !window.Chart) return;
        destroyChart('accMmProvider');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.providerEmpty) els.providerEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (els.providerLegend) {
            els.providerLegend.innerHTML = hasData
                ? filtered.map((item) => {
                    const color = PROVIDER_COLORS[item.provider] || '#64748b';
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(providerLabel(item.provider))}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.chartInstances.accMmProvider = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map((x) => providerLabel(x.provider)),
                datasets: [{
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((x) => PROVIDER_COLORS[x.provider] || '#64748b'),
                    borderWidth: 0,
                }],
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { display: false } } },
        });
    }

    function renderTrendChart(items) {
        const ctx = document.getElementById('accMmTrend');
        if (!ctx || !window.Chart) return;
        destroyChart('accMmTrend');
        const rows = items || [];
        const hasData = rows.some((x) => Number(x.in_amount) > 0 || Number(x.out_amount) > 0);
        if (els.trendEmpty) els.trendEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accMmTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: rows.map((x) => formatDayLabel(x.day)),
                datasets: [
                    {
                        label: t('mm_stat_in'),
                        data: rows.map((x) => x.in_amount),
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5,150,105,0.08)',
                        fill: true,
                        tension: 0.35,
                    },
                    {
                        label: t('mm_stat_out'),
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

    function renderWalletsChart(items) {
        const ctx = document.getElementById('accMmWalletsChart');
        if (!ctx || !window.Chart) return;
        destroyChart('accMmWalletsChart');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.walletsEmpty) els.walletsEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accMmWalletsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => x.label),
                datasets: [{
                    label: t('mm_wallet_balance'),
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((x) => PROVIDER_COLORS[x.provider] || 'rgba(5,150,105,0.75)'),
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

        if (els.wallets) els.wallets.textContent = String(insights.wallet_count ?? 0);
        if (els.avgBalance) els.avgBalance.textContent = money(insights.avg_balance);
        if (els.inOutRatio) {
            const ratio = Number(insights.in_out_ratio || 0);
            els.inOutRatio.textContent = ratio > 0
                ? `${ratio.toLocaleString(locale(), { maximumFractionDigits: 2 })}×`
                : '—';
        }
        if (els.topProvider) {
            const tp = insights.top_provider;
            els.topProvider.textContent = tp && tp !== '—' ? providerLabel(tp) : '—';
        }

        document.querySelectorAll('.acc-mm-stat__value, .acc-mm-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.count) {
            const scope = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
            els.count.textContent = `${scope} · ${stats.transaction_count ?? state.rows.length} ${t('mm_tx_count')}`;
        }
    }

    function renderWalletCards(accounts) {
        if (!els.walletCards || !els.walletSection) return;
        const list = accounts || [];
        els.walletSection.hidden = !list.length;
        if (!list.length) {
            els.walletCards.innerHTML = '';
            return;
        }
        els.walletCards.innerHTML = list.map((a) => `
            <article class="acc-mm-wallet-card" data-wallet-id="${esc(String(a.id))}">
                <div class="acc-mm-wallet-card__head">
                    <span class="acc-mm-provider acc-mm-provider--${esc(a.provider)}">${esc(providerLabel(a.provider))}</span>
                    <strong class="acc-mm-wallet-card__balance">${esc(money(a.current_balance))}</strong>
                </div>
                <h4>${esc(a.label || '—')}</h4>
                ${a.phone_number ? `<p class="acc-mm-wallet-card__phone">${esc(a.phone_number)}</p>` : ''}
            </article>`).join('');

        els.walletCards.querySelectorAll('.acc-mm-wallet-card').forEach((card) => {
            card.addEventListener('click', () => {
                const id = card.dataset.walletId;
                if (!id) return;
                state.provider = 'all';
                state.direction = 'all';
                syncFilterChips();
                if (els.search) els.search.value = '';
                state.search = '';
                if (els.searchClear) els.searchClear.hidden = true;
                loadWithWallet(id);
            });
        });
    }

    function syncFilterChips() {
        els.directionFilters?.querySelectorAll('.acc-mm-chip').forEach((chip) => {
            const active = chip.dataset.direction === state.direction;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        els.providerFilters?.querySelectorAll('.acc-mm-chip').forEach((chip) => {
            const active = chip.dataset.provider === state.provider;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function populateTxWalletSelect() {
        if (!els.txWalletSelect) return;
        const opts = (state.accounts || []).map((a) =>
            `<option value="${esc(String(a.id))}">${esc(a.label)} (${esc(providerLabel(a.provider))})</option>`
        ).join('');
        els.txWalletSelect.innerHTML = `<option value="">—</option>${opts}`;
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
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">smartphone</span><p>${esc(t('no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="acc-mm-table-wrap">
                <table class="modern-table acc-table acc-mm-table">
                    <thead><tr>
                        <th>${esc(t('mm_col_date'))}</th>
                        <th>${esc(t('mm_col_wallet'))}</th>
                        <th>${esc(t('mm_col_provider'))}</th>
                        <th>${esc(t('mm_col_direction'))}</th>
                        <th class="acc-mm-num">${esc(t('mm_col_amount'))}</th>
                        <th>${esc(t('mm_col_reference'))}</th>
                        <th>${esc(t('mm_col_by'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr>
                            <td>${esc(formatDate(r.transaction_date))}</td>
                            <td>${esc(r.wallet_label || '—')}</td>
                            <td><span class="acc-mm-provider acc-mm-provider--${esc(r.provider)}">${esc(providerLabel(r.provider))}</span></td>
                            <td><span class="acc-mm-dir ${directionClass(r.direction)}">${esc(directionLabel(r.direction))}</span></td>
                            <td class="acc-mm-num">${esc(money(r.amount))}</td>
                            <td>${esc(r.reference || r.external_ref || '—')}</td>
                            <td>${esc(r.created_by_name || '—')}</td>
                            <td><button type="button" class="acc-mm-link" data-id="${esc(String(r.id))}">${esc(t('mm_view_details'))}</button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-mm-cards">${rows.map((r) => `
                <article class="acc-mm-card">
                    <div class="acc-mm-card__head">
                        <span>${esc(formatDate(r.transaction_date))}</span>
                        <span class="acc-mm-dir ${directionClass(r.direction)}">${esc(directionLabel(r.direction))}</span>
                    </div>
                    <h4>${esc(r.wallet_label || '—')}</h4>
                    <dl>
                        <div><dt>${esc(t('mm_col_provider'))}</dt><dd>${esc(providerLabel(r.provider))}</dd></div>
                        <div><dt>${esc(t('mm_col_amount'))}</dt><dd>${esc(money(r.amount))}</dd></div>
                    </dl>
                    <button type="button" class="acc-mm-link" data-id="${esc(String(r.id))}">${esc(t('mm_view_details'))}</button>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('.acc-mm-link').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.id)));
        });
    }

    function openDetail(id) {
        const row = state.rows.find((r) => Number(r.id) === id);
        if (!row || !els.detailBody || !els.detailModal) return;
        state.selected = row;
        els.detailBody.innerHTML = `
            <dl class="acc-mm-detail-grid">
                <div><dt>${esc(t('mm_col_date'))}</dt><dd>${esc(formatDate(row.transaction_date))}</dd></div>
                <div><dt>${esc(t('mm_col_wallet'))}</dt><dd>${esc(row.wallet_label || '—')}</dd></div>
                <div><dt>${esc(t('mm_col_provider'))}</dt><dd><span class="acc-mm-provider acc-mm-provider--${esc(row.provider)}">${esc(providerLabel(row.provider))}</span></dd></div>
                <div><dt>${esc(t('mm_col_direction'))}</dt><dd><span class="acc-mm-dir ${directionClass(row.direction)}">${esc(directionLabel(row.direction))}</span></dd></div>
                <div><dt>${esc(t('mm_col_amount'))}</dt><dd><strong>${esc(money(row.amount))}</strong></dd></div>
                <div><dt>${esc(t('mm_col_by'))}</dt><dd>${esc(row.created_by_name || '—')}</dd></div>
                ${row.reference ? `<div><dt>${esc(t('mm_col_reference'))}</dt><dd>${esc(row.reference)}</dd></div>` : ''}
                ${row.external_ref ? `<div><dt>${esc(t('mm_col_external_ref'))}</dt><dd><code>${esc(row.external_ref)}</code></dd></div>` : ''}
                ${row.phone_number ? `<div><dt>${esc(t('mm_form_phone'))}</dt><dd>${esc(row.phone_number)}</dd></div>` : ''}
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
        const anyOpen = [els.detailModal, els.walletModal, els.txModal].some((m) => m && !m.hidden);
        document.body.classList.toggle('acc-mm-modal-open', anyOpen);
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
        renderProviderDonut(charts?.by_provider);
        renderTrendChart(charts?.trend);
        renderWalletsChart(charts?.wallet_balances);
    }

    function applyData(data) {
        state.rows = data.rows || [];
        state.accounts = data.accounts || [];
        state.stats = data.stats || {};
        state.insights = data.insights || {};
        state.charts = data.charts || {};
        state.page = 1;
        renderHero(state.stats, state.insights);
        renderWalletCards(state.accounts);
        populateTxWalletSelect();
        renderCharts(state.charts);
        renderTable();
    }

    async function load(extra = {}) {
        hideError();
        setLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getAccounting('mobile-money', { ...queryParams(), ...extra });
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? res.data?.module_ready ?? true);
            applyData(res.data || {});
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyAllCharts();
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">smartphone</span><p>${esc(e.message)}</p></div>`;
        }
    }

    function loadWithWallet(walletId) {
        load({ wallet_id: walletId });
    }

    async function submitWallet(e) {
        e.preventDefault();
        if (!els.walletForm) return;
        const fd = new FormData(els.walletForm);
        const payload = Object.fromEntries(fd.entries());
        payload.current_balance = parseFloat(payload.current_balance) || 0;
        if (window.ADMIN_PAGE?.storeId) payload.store_id = window.ADMIN_PAGE.storeId;
        if (els.walletSubmit) els.walletSubmit.disabled = true;
        try {
            const res = await AdminAPI.postAccounting('mobile-money', payload);
            if (res.status !== 'success') throw new Error(res.message);
            closeModal(els.walletModal);
            els.walletForm.reset();
            const balInput = els.walletForm.querySelector('[name="current_balance"]');
            if (balInput) balInput.value = '0';
            await load();
        } catch (err) {
            showError(err.message || t('load_error'));
        } finally {
            if (els.walletSubmit) els.walletSubmit.disabled = false;
        }
    }

    async function submitTx(e) {
        e.preventDefault();
        if (!els.txForm) return;
        const fd = new FormData(els.txForm);
        const payload = Object.fromEntries(fd.entries());
        payload.amount = parseFloat(payload.amount);
        payload.mobile_account_id = parseInt(payload.mobile_account_id, 10);
        if (window.ADMIN_PAGE?.storeId) payload.store_id = window.ADMIN_PAGE.storeId;
        if (els.txSubmit) els.txSubmit.disabled = true;
        try {
            const res = await AdminAPI.postAccounting('mobile-money', payload, 'transaction');
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
        exportCsv(`mobile-money-${els.dateTo?.value || 'export'}.csv`, [
            [t('mm_col_date'), t('mm_col_wallet'), t('mm_col_provider'), t('mm_col_direction'), t('mm_col_amount'), t('mm_col_reference'), t('mm_col_external_ref'), t('mm_col_by')],
            ...state.rows.map((r) => [
                r.transaction_date,
                r.wallet_label,
                r.provider,
                r.direction,
                r.amount,
                r.reference || '',
                r.external_ref || '',
                r.created_by_name || '',
            ]),
        ]);
    }

    els.stats?.querySelectorAll('.acc-mm-stat--click').forEach((btn) => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.statFilter || 'all';
            if (filter === 'all') {
                state.direction = 'all';
            } else {
                state.direction = filter;
            }
            syncFilterChips();
            load();
        });
    });

    els.directionFilters?.querySelectorAll('.acc-mm-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            state.direction = chip.dataset.direction || 'all';
            syncFilterChips();
            load();
        });
    });

    els.providerFilters?.querySelectorAll('.acc-mm-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            state.provider = chip.dataset.provider || 'all';
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
        els.addWalletBtn?.addEventListener('click', () => openModal(els.walletModal));
        els.addTxBtn?.addEventListener('click', () => {
            populateTxWalletSelect();
            openModal(els.txModal);
        });
        els.walletForm?.addEventListener('submit', submitWallet);
        els.txForm?.addEventListener('submit', submitTx);
        els.walletClose?.addEventListener('click', () => closeModal(els.walletModal));
        els.walletCancel?.addEventListener('click', () => closeModal(els.walletModal));
        els.txClose?.addEventListener('click', () => closeModal(els.txModal));
        els.txCancel?.addEventListener('click', () => closeModal(els.txModal));
        els.walletModal?.addEventListener('click', (e) => {
            if (e.target === els.walletModal) closeModal(els.walletModal);
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
