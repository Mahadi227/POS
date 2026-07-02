/**
 * Accounting audit logs v1 — financial audit trail, charts, filters
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('accAlRoot');
    if (!root) return;

    const { t, esc, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;
    const PAGE_SIZE = 20;

    const ACTION_LABELS = {
        expense_created: 'al_action_expense_created',
        expense_approved: 'al_action_expense_approved',
        expense_rejected: 'al_action_expense_rejected',
        journal_posted: 'al_action_journal_posted',
        auto_post_sale: 'al_action_auto_post_sale',
        account_created: 'al_action_account_created',
        cash_transaction: 'al_action_cash_transaction',
        cash_account_created: 'al_action_cash_account_created',
        bank_account_created: 'al_action_bank_account_created',
        mobile_wallet_created: 'al_action_mobile_wallet_created',
    };

    const ENTITY_LABELS = {
        expense: 'al_entity_expense',
        journal_entry: 'al_entity_journal_entry',
        sale: 'al_entity_sale',
        account: 'al_entity_account',
        cash_transaction: 'al_entity_cash_transaction',
        cash_account: 'al_entity_cash_account',
        bank_account: 'al_entity_bank_account',
        mobile_account: 'al_entity_mobile_account',
        other: 'al_entity_other',
    };

    const ACTION_COLORS = [
        '#2563eb', '#059669', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#64748b', '#ca8a04', '#db2777', '#4f46e5',
    ];

    const CATEGORY_ACTIONS = {
        journal: ['journal_posted', 'auto_post_sale'],
        expense: ['expense_created', 'expense_approved', 'expense_rejected'],
        treasury: ['cash_transaction', 'cash_account_created', 'bank_account_created', 'mobile_wallet_created'],
        accounts: ['account_created'],
    };

    const state = {
        rows: [],
        stats: {},
        insights: {},
        charts: {},
        actions: [],
        entityTypes: [],
        category: 'all',
        action: 'all',
        entityType: 'all',
        search: '',
        page: 1,
        selected: null,
        searchTimer: null,
        chartInstances: {},
    };

    const els = {
        count: document.getElementById('accAlCount'),
        statTotal: document.getElementById('accAlStatTotal'),
        statJournal: document.getElementById('accAlStatJournal'),
        statExpense: document.getElementById('accAlStatExpense'),
        statTreasury: document.getElementById('accAlStatTreasury'),
        stats: document.getElementById('accAlStats'),
        insightEvents: document.getElementById('accAlInsightEvents'),
        insightUsers: document.getElementById('accAlInsightUsers'),
        insightActions: document.getElementById('accAlInsightActions'),
        insightTop: document.getElementById('accAlInsightTop'),
        search: document.getElementById('accAlSearch'),
        searchClear: document.getElementById('accAlSearchClear'),
        dateFrom: document.getElementById('accAlDateFrom'),
        dateTo: document.getElementById('accAlDateTo'),
        actionFilters: document.getElementById('accAlActionFilters'),
        entityFilters: document.getElementById('accAlEntityFilters'),
        meta: document.getElementById('accAlMeta'),
        pagePrev: document.getElementById('accAlPrev'),
        pageNext: document.getElementById('accAlNext'),
        pageInfo: document.getElementById('accAlPageInfo'),
        exportBtn: document.getElementById('accAlExportBtn'),
        printBtn: document.getElementById('accAlPrintBtn'),
        refreshBtn: document.getElementById('accAlRefreshBtn'),
        detailModal: document.getElementById('accAlDetailModal'),
        detailBody: document.getElementById('accAlDetailBody'),
        detailClose: document.getElementById('accAlDetailClose'),
        actionsEmpty: document.getElementById('accAlActionsEmpty'),
        entitiesEmpty: document.getElementById('accAlEntitiesEmpty'),
        trendEmpty: document.getElementById('accAlTrendEmpty'),
        actionsLegend: document.getElementById('accAlActionsLegend'),
    };

    function locale() {
        return window.ADMIN_CONFIG?.locale || 'fr-FR';
    }

    function actionLabel(action) {
        const key = ACTION_LABELS[action];
        return key ? t(key) : (action || '—').replace(/_/g, ' ');
    }

    function entityLabel(type) {
        const key = ENTITY_LABELS[type] || ENTITY_LABELS.other;
        return key ? t(key) : (type || '—');
    }

    function actionClass(action) {
        if (!action) return 'acc-al-action--default';
        if (action.includes('expense')) return 'acc-al-action--expense';
        if (action.includes('journal') || action.includes('post')) return 'acc-al-action--journal';
        if (action.includes('cash') || action.includes('bank') || action.includes('mobile')) return 'acc-al-action--treasury';
        if (action.includes('account')) return 'acc-al-action--account';
        return 'acc-al-action--default';
    }

    function formatDateTime(d) {
        if (!d) return '—';
        return new Date(d).toLocaleString(locale(), {
            day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
        });
    }

    function formatDayLabel(day) {
        if (!day) return '';
        return new Date(`${day}T12:00:00`).toLocaleDateString(locale(), { day: '2-digit', month: 'short' });
    }

    function formatDetails(details) {
        if (!details) return '—';
        if (typeof details === 'object') {
            return Object.entries(details).map(([k, v]) => `${k}: ${v}`).join(', ');
        }
        return String(details);
    }

    function queryParams() {
        return {
            category: state.category === 'all' ? '' : state.category,
            action: state.action === 'all' ? '' : state.action,
            entity_type: state.entityType === 'all' ? '' : state.entityType,
            search: state.search.trim(),
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
        };
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-al-stat__value, .acc-al-insight__value').forEach((el) => {
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

    function renderActionsDonut(items) {
        const ctx = document.getElementById('accAlActions');
        if (!ctx || !window.Chart) return;
        destroyChart('accAlActions');
        const filtered = (items || []).filter((x) => x.count > 0);
        const hasData = filtered.length > 0;
        if (els.actionsEmpty) els.actionsEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (els.actionsLegend) {
            els.actionsLegend.innerHTML = hasData
                ? filtered.map((item, i) => {
                    const color = ACTION_COLORS[i % ACTION_COLORS.length];
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(actionLabel(item.action))}</span><strong>${esc(String(item.count))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.chartInstances.accAlActions = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map((x) => actionLabel(x.action)),
                datasets: [{
                    data: filtered.map((x) => x.count),
                    backgroundColor: filtered.map((_, i) => ACTION_COLORS[i % ACTION_COLORS.length]),
                    borderWidth: 0,
                }],
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { display: false } } },
        });
    }

    function renderEntitiesChart(items) {
        const ctx = document.getElementById('accAlEntities');
        if (!ctx || !window.Chart) return;
        destroyChart('accAlEntities');
        const filtered = (items || []).filter((x) => x.count > 0);
        const hasData = filtered.length > 0;
        if (els.entitiesEmpty) els.entitiesEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accAlEntities = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => entityLabel(x.entity_type)),
                datasets: [{
                    label: t('al_insight_events'),
                    data: filtered.map((x) => x.count),
                    backgroundColor: 'rgba(37, 99, 235, 0.75)',
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

    function renderTrendChart(items) {
        const ctx = document.getElementById('accAlTrend');
        if (!ctx || !window.Chart) return;
        destroyChart('accAlTrend');
        const rows = items || [];
        const hasData = rows.some((x) => x.count > 0);
        if (els.trendEmpty) els.trendEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.chartInstances.accAlTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: rows.map((x) => formatDayLabel(x.day)),
                datasets: [{
                    label: t('al_insight_events'),
                    data: rows.map((x) => x.count),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    fill: true,
                    tension: 0.35,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: c.grid }, ticks: { color: c.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                    y: { grid: { color: c.grid }, ticks: { color: c.text, stepSize: 1 } },
                },
            },
        });
    }

    function renderHero(stats) {
        if (els.statTotal) els.statTotal.textContent = String(stats.total_events ?? 0);
        if (els.statJournal) els.statJournal.textContent = String(stats.journal_events ?? 0);
        if (els.statExpense) els.statExpense.textContent = String(stats.expense_events ?? 0);
        if (els.statTreasury) els.statTreasury.textContent = String(stats.treasury_events ?? 0);
        document.querySelectorAll('.acc-al-stat__value, .acc-al-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.count) {
            const scope = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
            els.count.textContent = scope;
        }
    }

    function renderInsights(insights) {
        if (els.insightEvents) els.insightEvents.textContent = String(insights.event_count ?? 0);
        if (els.insightUsers) els.insightUsers.textContent = String(insights.unique_users ?? 0);
        if (els.insightActions) els.insightActions.textContent = String(insights.unique_actions ?? 0);
        if (els.insightTop) {
            const top = insights.top_action;
            els.insightTop.textContent = top && top !== '—' ? actionLabel(top) : '—';
        }
    }

    function renderDynamicFilters(actions, entityTypes) {
        if (els.entityFilters) {
            const entityOpts = ['all', ...(entityTypes || [])];
            els.entityFilters.innerHTML = entityOpts.map((et) => {
                const label = et === 'all' ? t('al_filter_all') : entityLabel(et);
                const active = state.entityType === et;
                return `<button type="button" class="acc-al-chip${active ? ' is-active' : ''}" data-entity="${esc(et)}" role="tab" aria-selected="${active}">${esc(label)}</button>`;
            }).join('');
            els.entityFilters.querySelectorAll('.acc-al-chip').forEach((chip) => {
                chip.addEventListener('click', () => {
                    state.entityType = chip.dataset.entity || 'all';
                    state.action = 'all';
                    syncFilterChips();
                    load();
                });
            });
        }

        if (els.actionFilters) {
            const visibleActions = (actions || []).filter((a) => {
                if (state.category === 'all') return true;
                return (CATEGORY_ACTIONS[state.category] || []).includes(a);
            });
            const actionOpts = ['all', ...visibleActions];
            els.actionFilters.innerHTML = actionOpts.map((act) => {
                const label = act === 'all' ? t('al_filter_all') : actionLabel(act);
                const active = state.action === act;
                return `<button type="button" class="acc-al-chip${active ? ' is-active' : ''}" data-action="${esc(act)}" role="tab" aria-selected="${active}">${esc(label)}</button>`;
            }).join('');
            els.actionFilters.querySelectorAll('.acc-al-chip').forEach((chip) => {
                chip.addEventListener('click', () => {
                    state.action = chip.dataset.action || 'all';
                    syncFilterChips();
                    load();
                });
            });
        }
    }

    function syncFilterChips() {
        els.entityFilters?.querySelectorAll('.acc-al-chip').forEach((chip) => {
            const active = chip.dataset.entity === state.entityType;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        els.actionFilters?.querySelectorAll('.acc-al-chip').forEach((chip) => {
            const active = chip.dataset.action === state.action;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
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
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">history</span><p>${esc(t('no_data'))}</p></div>`;
            return;
        }

        root.innerHTML = `
            <div class="acc-al-table-wrap">
                <table class="modern-table acc-table acc-al-table">
                    <thead><tr>
                        <th>${esc(t('al_col_time'))}</th>
                        <th>${esc(t('al_col_action'))}</th>
                        <th>${esc(t('al_col_entity'))}</th>
                        <th>${esc(t('al_col_entity_id'))}</th>
                        <th>${esc(t('al_col_user'))}</th>
                        <th>${esc(t('al_col_ip'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>${rows.map((r) => `
                        <tr>
                            <td>${esc(formatDateTime(r.created_at))}</td>
                            <td><span class="acc-al-action ${actionClass(r.action)}">${esc(actionLabel(r.action))}</span></td>
                            <td>${esc(entityLabel(r.entity_type || 'other'))}</td>
                            <td>${r.entity_id ? `<code>#${esc(String(r.entity_id))}</code>` : '—'}</td>
                            <td>${esc(r.user_name || '—')}</td>
                            <td><code class="acc-al-ip">${esc(r.ip_address || '—')}</code></td>
                            <td><button type="button" class="acc-al-link" data-id="${esc(String(r.id))}">${esc(t('al_view_details'))}</button></td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>
            <div class="acc-al-cards">${rows.map((r) => `
                <article class="acc-al-card">
                    <div class="acc-al-card__head">
                        <span>${esc(formatDateTime(r.created_at))}</span>
                        <span class="acc-al-action ${actionClass(r.action)}">${esc(actionLabel(r.action))}</span>
                    </div>
                    <dl>
                        <div><dt>${esc(t('al_col_entity'))}</dt><dd>${esc(entityLabel(r.entity_type || 'other'))}${r.entity_id ? ` #${esc(String(r.entity_id))}` : ''}</dd></div>
                        <div><dt>${esc(t('al_col_user'))}</dt><dd>${esc(r.user_name || '—')}</dd></div>
                    </dl>
                    <button type="button" class="acc-al-link" data-id="${esc(String(r.id))}">${esc(t('al_view_details'))}</button>
                </article>`).join('')}
            </div>`;

        root.querySelectorAll('.acc-al-link').forEach((btn) => {
            btn.addEventListener('click', () => openDetail(Number(btn.dataset.id)));
        });
    }

    function openDetail(id) {
        const row = state.rows.find((r) => Number(r.id) === id);
        if (!row || !els.detailBody || !els.detailModal) return;
        state.selected = row;
        const detailsJson = row.details
            ? `<pre class="acc-al-json">${esc(JSON.stringify(row.details, null, 2))}</pre>`
            : '—';
        els.detailBody.innerHTML = `
            <dl class="acc-al-detail-grid">
                <div><dt>${esc(t('al_col_time'))}</dt><dd>${esc(formatDateTime(row.created_at))}</dd></div>
                <div><dt>${esc(t('al_col_action'))}</dt><dd><span class="acc-al-action ${actionClass(row.action)}">${esc(actionLabel(row.action))}</span></dd></div>
                <div><dt>${esc(t('al_col_entity'))}</dt><dd>${esc(entityLabel(row.entity_type || 'other'))}</dd></div>
                <div><dt>${esc(t('al_col_entity_id'))}</dt><dd>${row.entity_id ? `<code>#${esc(String(row.entity_id))}</code>` : '—'}</dd></div>
                <div><dt>${esc(t('al_col_user'))}</dt><dd>${esc(row.user_name || '—')}</dd></div>
                <div><dt>${esc(t('al_col_ip'))}</dt><dd><code>${esc(row.ip_address || '—')}</code></dd></div>
                <div class="acc-al-detail-full"><dt>${esc(t('al_detail_payload'))}</dt><dd>${detailsJson}</dd></div>
            </dl>`;
        els.detailModal.hidden = false;
        document.body.classList.add('acc-al-modal-open');
    }

    function closeDetail() {
        if (!els.detailModal) return;
        els.detailModal.hidden = true;
        document.body.classList.remove('acc-al-modal-open');
        state.selected = null;
    }

    function renderCharts(charts) {
        renderActionsDonut(charts?.by_action);
        renderEntitiesChart(charts?.by_entity);
        renderTrendChart(charts?.trend);
    }

    function applyData(data) {
        state.rows = data.rows || [];
        state.stats = data.stats || {};
        state.insights = data.insights || {};
        state.charts = data.charts || {};
        state.actions = data.actions || [];
        state.entityTypes = data.entity_types || [];
        state.page = 1;
        renderHero(state.stats);
        renderInsights(state.insights);
        renderDynamicFilters(state.actions, state.entityTypes);
        renderCharts(state.charts);
        renderTable();
    }

    async function load() {
        hideError();
        setLoading(true);
        root.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getAccounting('audit', queryParams());
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? res.data?.module_ready ?? true);
            applyData(res.data || {});
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyAllCharts();
            root.innerHTML = `<div class="acc-empty"><span class="material-icons-round">history</span><p>${esc(e.message)}</p></div>`;
        }
    }

    function exportData() {
        if (!state.rows.length) return;
        exportCsv(`audit-logs-${els.dateTo?.value || 'export'}.csv`, [
            [t('al_col_time'), t('al_col_action'), t('al_col_entity'), t('al_col_entity_id'), t('al_col_user'), t('al_col_ip'), t('al_detail_payload')],
            ...state.rows.map((r) => [
                r.created_at,
                r.action,
                r.entity_type || '',
                r.entity_id ?? '',
                r.user_name || '',
                r.ip_address || '',
                formatDetails(r.details),
            ]),
        ]);
    }

    els.stats?.querySelectorAll('.acc-al-stat--click').forEach((btn) => {
        btn.addEventListener('click', () => {
            state.category = btn.dataset.statFilter || 'all';
            state.action = 'all';
            state.entityType = 'all';
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

    document.addEventListener('acc:refresh', load);
    document.addEventListener('themechange', () => {
        if (state.charts) renderCharts(state.charts);
    });

    if (els.searchClear) els.searchClear.hidden = true;
    load();
});
