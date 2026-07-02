/**
 * Accounting chart of accounts v1 — GL accounts, balances, charts
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accCoaRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;
    const PAGE_SIZE = 20;
    const canManage = Boolean(window.ADMIN_PAGE?.canManage);

    const TYPE_LABELS = {
        asset: 'coa_type_asset',
        liability: 'coa_type_liability',
        equity: 'coa_type_equity',
        revenue: 'coa_type_revenue',
        expense: 'coa_type_expense',
    };

    const TYPE_COLORS = {
        asset: '#059669',
        liability: '#d97706',
        equity: '#2563eb',
        revenue: '#0891b2',
        expense: '#dc2626',
    };

    const state = {
        rows: [],
        stats: {},
        insights: {},
        charts: {},
        type: 'all',
        scope: 'all',
        search: '',
        page: 1,
        selected: null,
        searchTimer: null,
        chartInstances: {},
    };

    const els = {
        count: document.getElementById('accCoaCount'),
        statAccounts: document.getElementById('accCoaStatAccounts'),
        statAssets: document.getElementById('accCoaStatAssets'),
        statLiabilities: document.getElementById('accCoaStatLiabilities'),
        statEquity: document.getElementById('accCoaStatEquity'),
        stats: document.getElementById('accCoaStats'),
        insightAccounts: document.getElementById('accCoaInsightAccounts'),
        insightSystem: document.getElementById('accCoaInsightSystem'),
        insightCustom: document.getElementById('accCoaInsightCustom'),
        insightTop: document.getElementById('accCoaInsightTop'),
        search: document.getElementById('accCoaSearch'),
        searchClear: document.getElementById('accCoaSearchClear'),
        dateFrom: document.getElementById('accCoaDateFrom'),
        dateTo: document.getElementById('accCoaDateTo'),
        typeFilters: document.getElementById('accCoaTypeFilters'),
        scopeFilters: document.getElementById('accCoaScopeFilters'),
        meta: document.getElementById('accCoaMeta'),
        pagePrev: document.getElementById('accCoaPrev'),
        pageNext: document.getElementById('accCoaNext'),
        pageInfo: document.getElementById('accCoaPageInfo'),
        exportBtn: document.getElementById('accCoaExportBtn'),
        printBtn: document.getElementById('accCoaPrintBtn'),
        refreshBtn: document.getElementById('accCoaRefreshBtn'),
        addBtn: document.getElementById('accCoaAddBtn'),
        detailModal: document.getElementById('accCoaDetailModal'),
        detailBody: document.getElementById('accCoaDetailBody'),
        detailClose: document.getElementById('accCoaDetailClose'),
        formModal: document.getElementById('accCoaFormModal'),
        form: document.getElementById('accCoaForm'),
        formClose: document.getElementById('accCoaFormClose'),
        formCancel: document.getElementById('accCoaFormCancel'),
        formSubmit: document.getElementById('accCoaFormSubmit'),
        formType: document.getElementById('accCoaFormType'),
        formNormal: document.getElementById('accCoaFormNormal'),
        formParent: document.getElementById('accCoaFormParent'),
        byTypeEmpty: document.getElementById('accCoaByTypeEmpty'),
        countEmpty: document.getElementById('accCoaCountEmpty'),
        topEmpty: document.getElementById('accCoaTopEmpty'),
        byTypeLegend: document.getElementById('accCoaByTypeLegend'),
    };

    function locale() {
        return window.ADMIN_CONFIG?.locale || 'fr-FR';
    }

    function typeLabel(type) {
        const key = TYPE_LABELS[type];
        return key ? t(key) : (type || '—');
    }

    function typeClass(type) {
        return `acc-coa-type--${type || 'asset'}`;
    }

    function normalLabel(nb) {
        return nb === 'credit' ? t('coa_normal_credit') : t('coa_normal_debit');
    }

    function queryParams() {
        return {
            type: state.type === 'all' ? '' : state.type,
            scope: state.scope === 'all' ? '' : state.scope,
            search: state.search.trim(),
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
        };
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-coa-stat__value, .acc-coa-insight__value').forEach((el) => {
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

    function renderByTypeDonut(items) {
        const ctx = document.getElementById('accCoaByType');
        if (!ctx || !window.Chart) return;
        destroyChart('accCoaByType');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.byTypeEmpty) els.byTypeEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (els.byTypeLegend) {
            els.byTypeLegend.innerHTML = hasData
                ? filtered.map((item) => {
                    const color = TYPE_COLORS[item.type] || '#64748b';
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(typeLabel(item.type))}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.chartInstances.accCoaByType = new Chart(ctx, {
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

    function renderCountChart(items) {
        const ctx = document.getElementById('accCoaCount');
        if (!ctx || !window.Chart) return;
        destroyChart('accCoaCount');
        const filtered = items || [];
        const hasData = filtered.some((x) => x.count > 0);
        if (els.countEmpty) els.countEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accCoaCount = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => typeLabel(x.type)),
                datasets: [{
                    label: t('coa_insight_accounts'),
                    data: filtered.map((x) => x.count),
                    backgroundColor: filtered.map((x) => TYPE_COLORS[x.type] || '#64748b'),
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: c.text } },
                    y: { grid: { color: c.grid }, ticks: { color: c.text, stepSize: 1 } },
                },
            },
        });
    }

    function renderTopChart(items) {
        const ctx = document.getElementById('accCoaTop');
        if (!ctx || !window.Chart) return;
        destroyChart('accCoaTop');
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.topEmpty) els.topEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accCoaTop = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => x.label),
                datasets: [{
                    label: t('coa_col_balance'),
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((x) => TYPE_COLORS[x.type] || '#059669'),
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
                    y: { grid: { display: false }, ticks: { color: c.text, font: { size: 10 } } },
                },
            },
        });
    }

    function renderHero(stats) {
        if (els.statAccounts) els.statAccounts.textContent = String(stats.total_accounts ?? 0);
        if (els.statAssets) els.statAssets.textContent = money(stats.asset_balance);
        if (els.statLiabilities) els.statLiabilities.textContent = money(stats.liability_balance);
        if (els.statEquity) els.statEquity.textContent = money(stats.equity_balance);

        document.querySelectorAll('.acc-coa-stat__value, .acc-coa-insight__value').forEach((el) => el.classList.remove('is-loading'));

        const asOf = els.dateTo?.value || '';
        if (els.count) {
            const scope = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
            const asOfLabel = asOf ? `${t('coa_balance_as_of')} ${formatDate(asOf)}` : '';
            els.count.textContent = `${scope}${asOfLabel ? ` · ${asOfLabel}` : ''}`;
        }
    }

    function renderInsights(insights) {
        if (els.insightAccounts) els.insightAccounts.textContent = String(insights.account_count ?? 0);
        if (els.insightSystem) els.insightSystem.textContent = String(insights.system_count ?? 0);
        if (els.insightCustom) els.insightCustom.textContent = String(insights.custom_count ?? 0);
        if (els.insightTop) {
            const top = insights.top_account;
            els.insightTop.textContent = top && top !== '—' ? top : '—';
            els.insightTop.classList.toggle('acc-coa-insight__value--truncate', true);
        }
    }

    function formatDate(d) {
        if (!d) return '—';
        return new Date(`${d}T12:00:00`).toLocaleDateString(locale(), { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function syncFilterChips() {
        els.typeFilters?.querySelectorAll('.acc-coa-chip').forEach((chip) => {
            const active = chip.dataset.type === state.type;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        els.scopeFilters?.querySelectorAll('.acc-coa-chip').forEach((chip) => {
            const active = chip.dataset.scope === state.scope;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function populateParentSelect() {
        if (!els.formParent) return;
        const parents = (state.rows || []).filter((a) => a.account_subtype === 'header' || !a.parent_id);
        const opts = parents.map((a) =>
            `<option value="${esc(String(a.id))}">${esc(a.code)} — ${esc(a.name)}</option>`
        ).join('');
        els.formParent.innerHTML = `<option value="">${esc(t('coa_no_parent'))}</option>${opts}`;
    }

    function syncNormalFromType() {
        if (!els.formType || !els.formNormal) return;
        const type = els.formType.value;
        const debitTypes = type === 'asset' || type === 'expense';
        els.formNormal.value = debitTypes ? 'debit' : 'credit';
    }

    function paginatedRows() {
        const rows = state.rows;
        const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        if (state.page > totalPages) state.page = totalPages;
        const start = (state.page - 1) * PAGE_SIZE;
        return { rows: rows.slice(start, start + PAGE_SIZE), totalPages, total: rows.length };
    }

    function rowClass(row) {
        if (row.account_subtype === 'header') return 'acc-coa-row--header';
        if (row.parent_id) return 'acc-coa-row--child';
        return '';
    }

    function renderTable() {
        const { rows, totalPages, total } = paginatedRows();
        if (els.pageInfo) els.pageInfo.textContent = `${state.page} / ${totalPages}`;
        if (els.pagePrev) els.pagePrev.disabled = state.page <= 1;
        if (els.pageNext) els.pageNext.disabled = state.page >= totalPages;
        if (els.meta) els.meta.textContent = total ? `${total} ${t('records')}` : t('no_data');

        if (!rows.length) {
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">account_tree</span><p>${esc(t('no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="acc-coa-table-wrap">
                <table class="modern-table acc-table acc-coa-table">
                    <thead><tr>
                        <th>${esc(t('coa_col_code'))}</th>
                        <th>${esc(t('coa_col_name'))}</th>
                        <th>${esc(t('coa_col_type'))}</th>
                        <th>${esc(t('coa_col_subtype'))}</th>
                        <th>${esc(t('coa_col_normal'))}</th>
                        <th class="acc-coa-num">${esc(t('coa_col_balance'))}</th>
                        <th>${esc(t('coa_col_system'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr class="${rowClass(r)}">
                            <td><code>${esc(r.code)}</code></td>
                            <td>${r.parent_id ? '<span class="acc-coa-indent" aria-hidden="true"></span>' : ''}${esc(r.name)}</td>
                            <td><span class="acc-coa-type ${typeClass(r.account_type)}">${esc(typeLabel(r.account_type))}</span></td>
                            <td>${esc(r.account_subtype || '—')}</td>
                            <td>${esc(normalLabel(r.normal_balance))}</td>
                            <td class="acc-coa-num">${esc(money(r.balance))}</td>
                            <td>${Number(r.is_system) ? `<span class="acc-coa-sys">${esc(t('coa_system_yes'))}</span>` : esc(t('coa_system_no'))}</td>
                            <td><button type="button" class="acc-coa-link" data-id="${esc(String(r.id))}">${esc(t('coa_view_details'))}</button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-coa-cards">${rows.map((r) => `
                <article class="acc-coa-card ${rowClass(r)}">
                    <div class="acc-coa-card__head">
                        <code>${esc(r.code)}</code>
                        <span class="acc-coa-type ${typeClass(r.account_type)}">${esc(typeLabel(r.account_type))}</span>
                    </div>
                    <h4>${esc(r.name)}</h4>
                    <dl>
                        <div><dt>${esc(t('coa_col_balance'))}</dt><dd>${esc(money(r.balance))}</dd></div>
                        <div><dt>${esc(t('coa_col_normal'))}</dt><dd>${esc(normalLabel(r.normal_balance))}</dd></div>
                    </dl>
                    <button type="button" class="acc-coa-link" data-id="${esc(String(r.id))}">${esc(t('coa_view_details'))}</button>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('.acc-coa-link').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.id)));
        });
    }

    function openDetail(id) {
        const row = state.rows.find((r) => Number(r.id) === id);
        if (!row || !els.detailBody || !els.detailModal) return;
        state.selected = row;
        const parentLabel = row.parent_code
            ? `${row.parent_code} — ${row.parent_name}`
            : (row.parent_name || '—');
        els.detailBody.innerHTML = `
            <dl class="acc-coa-detail-grid">
                <div><dt>${esc(t('coa_col_code'))}</dt><dd><code>${esc(row.code)}</code></dd></div>
                <div><dt>${esc(t('coa_col_name'))}</dt><dd><strong>${esc(row.name)}</strong></dd></div>
                <div><dt>${esc(t('coa_col_type'))}</dt><dd><span class="acc-coa-type ${typeClass(row.account_type)}">${esc(typeLabel(row.account_type))}</span></dd></div>
                <div><dt>${esc(t('coa_col_subtype'))}</dt><dd>${esc(row.account_subtype || '—')}</dd></div>
                <div><dt>${esc(t('coa_col_normal'))}</dt><dd>${esc(normalLabel(row.normal_balance))}</dd></div>
                <div><dt>${esc(t('coa_col_balance'))}</dt><dd><strong>${esc(money(row.balance))}</strong></dd></div>
                <div><dt>${esc(t('coa_col_system'))}</dt><dd>${esc(Number(row.is_system) ? t('coa_system_yes') : t('coa_system_no'))}</dd></div>
                <div><dt>${esc(t('coa_form_parent'))}</dt><dd>${esc(parentLabel)}</dd></div>
                ${row.description ? `<div class="acc-coa-detail-full"><dt>${esc(t('coa_form_description'))}</dt><dd>${esc(row.description)}</dd></div>` : ''}
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
        const anyOpen = [els.detailModal, els.formModal].some((m) => m && !m.hidden);
        document.body.classList.toggle('acc-coa-modal-open', anyOpen);
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
        renderByTypeDonut(charts?.by_type);
        renderCountChart(charts?.count_by_type);
        renderTopChart(charts?.top_accounts);
    }

    function applyData(data) {
        state.rows = data.rows || [];
        state.stats = data.stats || {};
        state.insights = data.insights || {};
        state.charts = data.charts || {};
        state.page = 1;
        renderHero(state.stats);
        renderInsights(state.insights);
        populateParentSelect();
        renderCharts(state.charts);
        renderTable();
    }

    async function load() {
        hideError();
        setLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getAccounting('accounts', queryParams());
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? res.data?.module_ready ?? true);
            applyData(res.data || {});
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyAllCharts();
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">account_tree</span><p>${esc(e.message)}</p></div>`;
        }
    }

    async function submitAccount(e) {
        e.preventDefault();
        if (!els.form) return;
        const fd = new FormData(els.form);
        const payload = Object.fromEntries(fd.entries());
        if (!payload.parent_id) delete payload.parent_id;
        else payload.parent_id = parseInt(payload.parent_id, 10);
        if (window.ADMIN_PAGE?.storeId) payload.store_id = window.ADMIN_PAGE.storeId;
        if (els.formSubmit) els.formSubmit.disabled = true;
        try {
            const res = await AdminAPI.postAccounting('accounts', payload);
            if (res.status !== 'success') throw new Error(res.message);
            closeModal(els.formModal);
            els.form.reset();
            syncNormalFromType();
            await load();
        } catch (err) {
            showError(err.message || t('load_error'));
        } finally {
            if (els.formSubmit) els.formSubmit.disabled = false;
        }
    }

    function exportData() {
        if (!state.rows.length) return;
        exportCsv(`chart-of-accounts-${els.dateTo?.value || 'export'}.csv`, [
            [t('coa_col_code'), t('coa_col_name'), t('coa_col_type'), t('coa_col_subtype'), t('coa_col_normal'), t('coa_col_balance'), t('coa_col_system')],
            ...state.rows.map((r) => [
                r.code,
                r.name,
                r.account_type,
                r.account_subtype || '',
                r.normal_balance,
                r.balance,
                Number(r.is_system) ? t('coa_system_yes') : t('coa_system_no'),
            ]),
        ]);
    }

    els.stats?.querySelectorAll('.acc-coa-stat--click').forEach((btn) => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.statFilter || 'all';
            state.type = filter;
            syncFilterChips();
            load();
        });
    });

    els.typeFilters?.querySelectorAll('.acc-coa-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            state.type = chip.dataset.type || 'all';
            syncFilterChips();
            load();
        });
    });

    els.scopeFilters?.querySelectorAll('.acc-coa-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            state.scope = chip.dataset.scope || 'all';
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
        els.addBtn?.addEventListener('click', () => {
            populateParentSelect();
            syncNormalFromType();
            openModal(els.formModal);
        });
        els.form?.addEventListener('submit', submitAccount);
        els.formType?.addEventListener('change', syncNormalFromType);
        els.formClose?.addEventListener('click', () => closeModal(els.formModal));
        els.formCancel?.addEventListener('click', () => closeModal(els.formModal));
        els.formModal?.addEventListener('click', (e) => {
            if (e.target === els.formModal) closeModal(els.formModal);
        });
    }

    document.addEventListener('acc:refresh', load);
    document.addEventListener('themechange', () => {
        if (state.charts) renderCharts(state.charts);
    });

    if (els.searchClear) els.searchClear.hidden = true;
    load();
});
