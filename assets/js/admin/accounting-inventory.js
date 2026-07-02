/**
 * Accounting inventory v1 — stock valuation, categories, losses
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('accInvHeroStats')) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = AccountingUI;

    const CATEGORY_COLORS = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#ca8a04', '#64748b'];
    const COMPOSITION_COLORS = { store: '#2563eb', warehouse: '#7c3aed' };

    const state = { period: 'month', data: null, charts: {} };

    const els = {
        periodLabel: document.getElementById('accInvPeriodLabel'),
        storeScope: document.getElementById('accInvStoreScope'),
        statTotal: document.getElementById('accInvStatTotal'),
        statStore: document.getElementById('accInvStatStore'),
        statWarehouse: document.getElementById('accInvStatWarehouse'),
        statLosses: document.getElementById('accInvStatLosses'),
        skus: document.getElementById('accInvSkus'),
        units: document.getElementById('accInvUnits'),
        lowStock: document.getElementById('accInvLowStock'),
        warehouseShare: document.getElementById('accInvWarehouseShare'),
        dateFrom: document.getElementById('accInvDateFrom'),
        dateTo: document.getElementById('accInvDateTo'),
        periodTabs: document.getElementById('accInvPeriod'),
        exportBtn: document.getElementById('accInvExportBtn'),
        printBtn: document.getElementById('accInvPrintBtn'),
        refreshBtn: document.getElementById('accInvRefreshBtn'),
        detailRoot: document.getElementById('accInvDetailRoot'),
        compositionEmpty: document.getElementById('accInvCompositionEmpty'),
        categoriesEmpty: document.getElementById('accInvCategoriesEmpty'),
        topProductsEmpty: document.getElementById('accInvTopProductsEmpty'),
        lossTrendEmpty: document.getElementById('accInvLossTrendEmpty'),
        compositionLegend: document.getElementById('accInvCompositionLegend'),
        categoriesLegend: document.getElementById('accInvCategoriesLegend'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: dark ? 'rgba(148,163,184,0.12)' : 'rgba(0,0,0,0.06)',
            text: dark ? '#9ca3af' : '#6b7280',
        };
    }

    function locale() {
        return window.ADMIN_CONFIG?.locale || 'fr-FR';
    }

    function pct(n) {
        return `${Number(n || 0).toLocaleString(locale(), { maximumFractionDigits: 1 })}%`;
    }

    function compositionLabel(key) {
        const map = { store: t('inv_stat_store'), warehouse: t('inv_stat_warehouse') };
        return map[key] || key;
    }

    function periodRange(period) {
        const to = new Date();
        const from = new Date(to);
        if (period === 'week') {
            from.setDate(to.getDate() - 6);
        } else if (period === 'quarter') {
            from.setMonth(Math.floor(to.getMonth() / 3) * 3, 1);
        } else if (period === 'year') {
            from.setMonth(0, 1);
        } else {
            from.setDate(1);
        }
        const fmt = (d) => d.toISOString().slice(0, 10);
        return { from: fmt(from), to: fmt(to) };
    }

    function periodLabelText() {
        const from = els.dateFrom?.value;
        const to = els.dateTo?.value;
        if (!from || !to) return '—';
        const f = new Date(`${from}T12:00:00`).toLocaleDateString(locale(), { day: 'numeric', month: 'short', year: 'numeric' });
        const tDate = new Date(`${to}T12:00:00`).toLocaleDateString(locale(), { day: 'numeric', month: 'short', year: 'numeric' });
        return `${f} — ${tDate}`;
    }

    function setLoading(on) {
        document.querySelectorAll('.acc-inv-stat__value, .acc-inv-insight__value').forEach((el) => {
            el.classList.toggle('is-loading', on);
        });
    }

    function destroyChart(id) {
        if (state.charts[id]) {
            state.charts[id].destroy();
            delete state.charts[id];
        }
    }

    function destroyAllCharts() {
        Object.keys(state.charts).forEach(destroyChart);
    }

    function renderDonut(id, items, labelFn, colorFn, legendEl, emptyEl) {
        const ctx = document.getElementById(id);
        if (!ctx || !window.Chart) return;
        destroyChart(id);
        const filtered = (items || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (emptyEl) emptyEl.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (legendEl) {
            legendEl.innerHTML = hasData
                ? filtered.map((item, i) => {
                    const color = typeof colorFn === 'function' ? colorFn(item, i) : colorFn[i % colorFn.length];
                    const label = typeof labelFn === 'function' ? labelFn(item) : labelFn;
                    return `<li><span class="acc-chart-legend__dot" style="background:${color}"></span><span>${esc(label)}</span><strong>${esc(money(item.amount))}</strong></li>`;
                }).join('')
                : '';
        }
        if (!hasData) return;

        state.charts[id] = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: filtered.map(labelFn),
                datasets: [{
                    data: filtered.map((x) => x.amount),
                    backgroundColor: filtered.map((item, i) => (typeof colorFn === 'function' ? colorFn(item, i) : colorFn[i % colorFn.length])),
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: { legend: { display: false } },
            },
        });
    }

    function renderTopProductsChart(rows) {
        const ctx = document.getElementById('accInvTopProducts');
        if (!ctx || !window.Chart) return;
        destroyChart('accInvTopProducts');
        const filtered = (rows || []).filter((x) => Number(x.amount) > 0);
        const hasData = filtered.length > 0;
        if (els.topProductsEmpty) els.topProductsEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.charts.accInvTopProducts = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: filtered.map((x) => x.label),
                datasets: [{
                    label: t('inv_col_value'),
                    data: filtered.map((x) => x.amount),
                    backgroundColor: 'rgba(37,99,235,0.75)',
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(ctx) { return money(ctx.parsed.x); },
                        },
                    },
                },
                scales: {
                    x: { grid: { color: c.grid }, ticks: { color: c.text } },
                    y: { grid: { display: false }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderLossTrend(rows) {
        const ctx = document.getElementById('accInvLossTrend');
        if (!ctx || !window.Chart) return;
        destroyChart('accInvLossTrend');
        const hasData = rows?.some((x) => Number(x.amount) > 0);
        if (els.lossTrendEmpty) els.lossTrendEmpty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;

        const c = chartColors();
        state.charts.accInvLossTrend = new Chart(ctx, {
            type: 'line',
            data: {
                labels: (rows || []).map((x) => {
                    const d = new Date(`${x.day}T12:00:00`);
                    return d.toLocaleDateString(locale(), { day: '2-digit', month: 'short' });
                }),
                datasets: [{
                    label: t('inv_stat_losses'),
                    data: (rows || []).map((x) => x.amount),
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220,38,38,0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(ctx) { return money(ctx.parsed.y); },
                        },
                    },
                },
                scales: {
                    x: { grid: { color: c.grid }, ticks: { color: c.text, maxRotation: 0, autoSkip: true } },
                    y: { grid: { color: c.grid }, ticks: { color: c.text } },
                },
            },
        });
    }

    function productTable(title, rows, columns) {
        if (!rows?.length) {
            return `<section class="acc-inv-section">
                <h4 class="acc-inv-section__title">${esc(title)}</h4>
                <p class="acc-inv-empty">${esc(t('no_data'))}</p>
            </section>`;
        }
        const head = columns.map((c) => `<th${c.class ? ` class="${c.class}"` : ''}>${esc(c.label)}</th>`).join('');
        const body = rows.map((r) => `<tr>${columns.map((c) => `<td${c.class ? ` class="${c.class}"` : ''}>${c.render(r)}</td>`).join('')}</tr>`).join('');
        const cards = rows.map((r) => `
            <article class="acc-inv-row-card">
                <div class="acc-inv-row-card__head">
                    <code>${esc(r.sku)}</code>
                    <strong>${esc(money(r.value))}</strong>
                </div>
                <p>${esc(r.name)}</p>
                <dl>${columns.filter((c) => c.card).map((c) => `<div><dt>${esc(c.label)}</dt><dd>${c.render(r)}</dd></div>`).join('')}</dl>
            </article>`).join('');

        return `<section class="acc-inv-section">
            <h4 class="acc-inv-section__title">${esc(title)}</h4>
            <div class="acc-inv-table-wrap">
                <table class="modern-table acc-table acc-inv-table">
                    <thead><tr>${head}</tr></thead>
                    <tbody>${body}</tbody>
                </table>
            </div>
            <div class="acc-inv-cards">${cards}</div>
        </section>`;
    }

    function renderDetail(data) {
        if (!els.detailRoot) return;

        const topCols = [
            { label: t('inv_col_sku'), render: (r) => `<code>${esc(r.sku)}</code>` },
            { label: t('inv_col_product'), render: (r) => esc(r.name) },
            { label: t('inv_col_qty'), class: 'acc-inv-num', render: (r) => esc(String(r.quantity)), card: true },
            { label: t('inv_col_cost'), class: 'acc-inv-num', render: (r) => esc(money(r.cost)), card: true },
            { label: t('inv_col_value'), class: 'acc-inv-num', render: (r) => esc(money(r.value)) },
        ];
        const lowCols = [
            { label: t('inv_col_sku'), render: (r) => `<code>${esc(r.sku)}</code>` },
            { label: t('inv_col_product'), render: (r) => esc(r.name) },
            { label: t('inv_col_qty'), class: 'acc-inv-num', render: (r) => esc(String(r.quantity)), card: true },
            { label: t('inv_col_min'), class: 'acc-inv-num', render: (r) => esc(String(r.min_level)), card: true },
            { label: t('inv_col_value'), class: 'acc-inv-num', render: (r) => esc(money(r.value)) },
        ];

        els.detailRoot.innerHTML = `
            <div class="acc-inv-loss-cards">
                <article class="acc-inv-loss-card acc-inv-loss-card--warn">
                    <span>${esc(t('inv_damaged'))}</span>
                    <strong>${esc(money(data.damaged_losses))}</strong>
                </article>
                <article class="acc-inv-loss-card acc-inv-loss-card--neg">
                    <span>${esc(t('inv_expired'))}</span>
                    <strong>${esc(money(data.expired_losses))}</strong>
                </article>
                <article class="acc-inv-loss-card">
                    <span>${esc(t('inv_insight_loss_ratio'))}</span>
                    <strong>${esc(pct(data.insights?.loss_ratio))}</strong>
                </article>
            </div>
            <div class="acc-inv-detail-grid">
                ${productTable(t('inv_section_top'), data.top_products, topCols)}
                ${productTable(t('inv_section_low_stock'), data.low_stock, lowCols)}
            </div>`;
    }

    function renderHero(data) {
        if (els.statTotal) els.statTotal.textContent = money(data.total_value);
        if (els.statStore) els.statStore.textContent = money(data.inventory_value);
        if (els.statWarehouse) els.statWarehouse.textContent = money(data.warehouse_value);
        if (els.statLosses) els.statLosses.textContent = money(data.total_losses);

        const ins = data.insights || {};
        if (els.skus) els.skus.textContent = String(ins.sku_count ?? 0);
        if (els.units) els.units.textContent = Number(ins.units_on_hand ?? 0).toLocaleString(locale());
        if (els.lowStock) els.lowStock.textContent = String(ins.low_stock_count ?? 0);
        if (els.warehouseShare) els.warehouseShare.textContent = pct(ins.warehouse_share);

        document.querySelectorAll('.acc-inv-stat__value, .acc-inv-insight__value').forEach((el) => el.classList.remove('is-loading'));
        if (els.periodLabel) els.periodLabel.textContent = periodLabelText();
        if (els.storeScope) {
            els.storeScope.textContent = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
        }
    }

    function renderCharts(data) {
        const charts = data.charts || {};
        renderDonut(
            'accInvComposition',
            data.composition,
            (x) => compositionLabel(x.key),
            (x) => COMPOSITION_COLORS[x.key] || '#64748b',
            els.compositionLegend,
            els.compositionEmpty,
        );
        renderDonut(
            'accInvCategories',
            (charts.by_category || []).map((x) => ({ category: x.category, amount: x.amount })),
            (x) => x.category || '—',
            (_, i) => CATEGORY_COLORS[i % CATEGORY_COLORS.length],
            els.categoriesLegend,
            els.categoriesEmpty,
        );
        renderTopProductsChart(charts.top_products);
        renderLossTrend(charts.loss_trend);
    }

    function queryParams() {
        return {
            from: els.dateFrom?.value || '',
            to: els.dateTo?.value || '',
        };
    }

    async function load() {
        hideError();
        setLoading(true);
        if (els.detailRoot) {
            els.detailRoot.innerHTML = `<div class="acc-loading">${esc(t('loading'))}</div>`;
        }
        try {
            const res = await AdminAPI.getAccounting('inventory', queryParams());
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.module_ready ?? true);
            state.data = res.data || {};
            renderHero(state.data);
            renderCharts(state.data);
            renderDetail(state.data);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyAllCharts();
            if (els.detailRoot) {
                els.detailRoot.innerHTML = `<div class="acc-empty"><span class="material-icons-round">inventory_2</span><p>${esc(e.message)}</p></div>`;
            }
        }
    }

    function exportData() {
        if (!state.data) return;
        const d = state.data;
        const rows = [
            [t('nav_inventory'), periodLabelText()],
            [],
            [t('inv_stat_total'), d.total_value],
            [t('inv_stat_store'), d.inventory_value],
            [t('inv_stat_warehouse'), d.warehouse_value],
            [t('inv_damaged'), d.damaged_losses],
            [t('inv_expired'), d.expired_losses],
            [t('inv_stat_losses'), d.total_losses],
            [],
            [t('inv_col_sku'), t('inv_col_product'), t('inv_col_qty'), t('inv_col_cost'), t('inv_col_value')],
        ];
        (d.top_products || []).forEach((r) => rows.push([r.sku, r.name, r.quantity, r.cost, r.value]));
        exportCsv(`inventory-accounting-${els.dateTo?.value || 'export'}.csv`, rows);
    }

    function applyPeriod(period) {
        state.period = period;
        const range = periodRange(period);
        if (els.dateFrom) els.dateFrom.value = range.from;
        if (els.dateTo) els.dateTo.value = range.to;
        els.periodTabs?.querySelectorAll('.acc-inv-chip').forEach((chip) => {
            const active = chip.dataset.period === period;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        load();
    }

    els.periodTabs?.querySelectorAll('.acc-inv-chip').forEach((chip) => {
        chip.addEventListener('click', () => applyPeriod(chip.dataset.period || 'month'));
    });

    els.dateFrom?.addEventListener('change', () => {
        els.periodTabs?.querySelectorAll('.acc-inv-chip').forEach((c) => c.classList.remove('is-active'));
        load();
    });
    els.dateTo?.addEventListener('change', () => {
        els.periodTabs?.querySelectorAll('.acc-inv-chip').forEach((c) => c.classList.remove('is-active'));
        load();
    });

    els.refreshBtn?.addEventListener('click', load);
    els.exportBtn?.addEventListener('click', exportData);
    els.printBtn?.addEventListener('click', () => window.print());

    document.addEventListener('acc:refresh', load);
    document.addEventListener('themechange', () => {
        if (state.data) renderCharts(state.data);
    });

    load();
});
