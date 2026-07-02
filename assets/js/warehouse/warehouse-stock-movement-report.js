/**
 * Warehouse — Stock Movement Report
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whSmrtTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const CACHE_KEY = 'wh_stock_movement_report_v1';

    const TYPE_KEYS = {
        purchase: 'wms_mov_purchase',
        sale: 'wms_mov_sale',
        transfer_in: 'wms_mov_transfer_in',
        transfer_out: 'wms_mov_transfer_out',
        return_in: 'wms_mov_return_in',
        return_out: 'wms_mov_return_out',
        adjustment: 'wms_mov_adjustment',
        damaged: 'wms_mov_damaged',
        expired: 'wms_mov_expired',
        lost: 'wms_mov_lost',
        manual: 'wms_mov_manual',
        dispatch_out: 'wms_mov_dispatch_out',
        receipt_in: 'wms_mov_receipt_in',
    };

    const IN_TYPES = ['receipt_in', 'transfer_in', 'purchase', 'return_in', 'adjustment', 'manual'];
    const OUT_TYPES = ['dispatch_out', 'transfer_out', 'sale', 'return_out', 'damaged', 'expired', 'lost'];

    const state = {
        page: 1,
        limit: 50,
        total: 0,
        items: [],
        summary: null,
        breakdown: [],
        chart: null,
        searchTimer: null,
    };

    const chartInstances = {};

    const els = {
        loading: document.getElementById('whSmrtLoading'),
        empty: document.getElementById('whSmrtEmpty'),
        warehouse: document.getElementById('whSmrtWarehouse'),
        search: document.getElementById('whSmrtSearch'),
        type: document.getElementById('whSmrtType'),
        dateFrom: document.getElementById('whSmrtDateFrom'),
        dateTo: document.getElementById('whSmrtDateTo'),
        refresh: document.getElementById('whSmrtRefreshBtn'),
        exportCsv: document.getElementById('whSmrtExportCsv'),
        exportExcel: document.getElementById('whSmrtExportExcel'),
        exportPdf: document.getElementById('whSmrtExportPdf'),
        printBtn: document.getElementById('whSmrtPrintBtn'),
        heroMeta: document.getElementById('whSmrtHeroMeta'),
        statTotal: document.getElementById('whSmrtStatTotal'),
        statIn: document.getElementById('whSmrtStatIn'),
        statOut: document.getElementById('whSmrtStatOut'),
        statValue: document.getElementById('whSmrtStatValue'),
        breakdownPanel: document.getElementById('whSmrtBreakdownPanel'),
        typeChips: document.getElementById('whSmrtTypeChips'),
        pagination: document.getElementById('whSmrtPagination'),
        prev: document.getElementById('whSmrtPrev'),
        next: document.getElementById('whSmrtNext'),
        pageMeta: document.getElementById('whSmrtPageMeta'),
        offlineBadge: document.getElementById('whSmrtOfflineBadge'),
        modal: document.getElementById('whSmrtDetailModal'),
        modalClose: document.getElementById('whSmrtDetailClose'),
        modalTitle: document.getElementById('whSmrtDetailTitle'),
        modalSubtitle: document.getElementById('whSmrtDetailSubtitle'),
        modalBody: document.getElementById('whSmrtDetailBody'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return { grid: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)', text: dark ? '#94a3b8' : '#64748b' };
    }

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function typeBadge(type) {
        let cls = 'idle';
        if (IN_TYPES.includes(type)) cls = 'ok';
        else if (OUT_TYPES.includes(type)) cls = 'off';
        return `<span class="cr-badge cr-badge--${cls}">${esc(typeLabel(type))}</span>`;
    }

    function qtyCell(qty) {
        const n = Number(qty || 0);
        const cls = n > 0 ? 'wh-smrt-qty--in' : (n < 0 ? 'wh-smrt-qty--out' : '');
        const prefix = n > 0 ? '+' : '';
        return `<span class="wh-smrt-qty ${cls}">${prefix}${n.toLocaleString()}</span>`;
    }

    function previousStock(row) {
        const bal = Number(row.balance_after ?? 0);
        const qty = Number(row.quantity ?? 0);
        return bal - qty;
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return new Date(iso).toLocaleString(window.WH_CONFIG?.locale || 'fr-FR', {
                day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
        } catch {
            return iso;
        }
    }

    function formatRef(row) {
        const parts = [];
        if (row.reference_type) parts.push(row.reference_type);
        if (row.reference_id) parts.push(`#${row.reference_id}`);
        return parts.length ? parts.join(' ') : '—';
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-smrt-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statIn) els.statIn.textContent = Number(s.stock_in ?? 0).toLocaleString();
        if (els.statOut) els.statOut.textContent = Number(s.stock_out ?? 0).toLocaleString();
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        setStatsLoading(false);
    }

    function renderBreakdown(breakdown) {
        if (!els.breakdownPanel || !els.typeChips) return;
        const items = breakdown || [];
        if (!items.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        els.breakdownPanel.hidden = false;
        const activeType = els.type?.value || 'all';
        els.typeChips.innerHTML = items.map((b) => {
            const type = b.movement_type || 'unknown';
            const active = activeType === type ? ' is-active' : '';
            return `<button type="button" class="wh-smrt-type-chip${active}" data-type="${esc(type)}">
                <span>${esc(typeLabel(type))}</span>
                <strong>${Number(b.movement_count ?? 0).toLocaleString()}</strong>
            </button>`;
        }).join('');
        els.typeChips.querySelectorAll('.wh-smrt-type-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                const type = chip.dataset.type || 'all';
                if (els.type) els.type.value = els.type.value === type ? 'all' : type;
                load(true);
            });
        });
    }

    function destroyChart(id) {
        if (chartInstances[id]) {
            chartInstances[id].destroy();
            delete chartInstances[id];
        }
    }

    function renderCharts(chart, breakdown) {
        if (!window.Chart) return;
        const c = chartColors();
        const trend = chart?.trend || [];

        destroyChart('trend');
        const trendCtx = document.getElementById('whSmrtChartTrend');
        if (trendCtx && trend.length) {
            chartInstances.trend = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trend.map((d) => d.d),
                    datasets: [
                        { label: t('wh_smrt_stat_in'), data: trend.map((d) => d.stock_in), borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,0.08)', fill: true, tension: 0.35 },
                        { label: t('wh_smrt_stat_out'), data: trend.map((d) => d.stock_out), borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.06)', fill: true, tension: 0.35 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: c.text } } },
                    scales: {
                        x: { ticks: { color: c.text, maxRotation: 0 }, grid: { color: c.grid } },
                        y: { ticks: { color: c.text }, grid: { color: c.grid } },
                    },
                },
            });
        }

        destroyChart('types');
        const typeCtx = document.getElementById('whSmrtChartTypes');
        const typeData = breakdown || [];
        if (typeCtx && typeData.length) {
            chartInstances.types = new Chart(typeCtx, {
                type: 'doughnut',
                data: {
                    labels: typeData.map((d) => typeLabel(d.movement_type)),
                    datasets: [{ data: typeData.map((d) => d.movement_count), backgroundColor: ['#0d9488', '#2563eb', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#65a30d', '#ea580c', '#64748b'] }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: c.text, boxWidth: 12 } } },
                },
            });
        }
    }

    function buildParams(forExport = false) {
        const params = {
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
            days: 30,
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        const q = els.search?.value?.trim();
        if (q) params.q = q;
        const type = els.type?.value?.trim();
        if (type && type !== 'all') params.type = type;
        const from = els.dateFrom?.value?.trim();
        if (from) params.from = from;
        const to = els.dateTo?.value?.trim();
        if (to) params.to = to;
        return params;
    }

    function showWarehouseCol() {
        return !els.warehouse?.value?.trim();
    }

    function renderTable(items) {
        if (!items.length) {
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = false;
            return;
        }
        if (els.empty) els.empty.hidden = true;
        const whCol = showWarehouseCol() ? `<th>${esc(t('wh_ledger_col_warehouse'))}</th>` : '';
        tableWrap.innerHTML = `<table class="wh-table wh-smrt-table"><thead><tr>
            <th>${esc(t('wh_ledger_col_date'))}</th>
            <th>${esc(t('wms_col_reference'))}</th>
            <th>${esc(t('wh_ledger_col_product'))}</th>
            ${whCol}
            <th>${esc(t('wh_ledger_col_type'))}</th>
            <th>${esc(t('wh_ledger_col_qty'))}</th>
            <th>${esc(t('wh_smrt_col_prev'))}</th>
            <th>${esc(t('wh_ledger_col_balance'))}</th>
            <th>${esc(t('wh_ledger_col_user'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((r) => {
            const whCell = showWarehouseCol() ? `<td>${esc(r.warehouse_name || '—')}</td>` : '';
            return `<tr data-id="${esc(r.id)}" class="wh-smrt-row">
            <td class="wh-smrt-date">${esc(formatDate(r.created_at))}</td>
            <td class="wh-smrt-ref">${esc(formatRef(r))}</td>
            <td><strong>${esc(r.product_name)}</strong><br><code class="wms-sku">${esc(r.sku || '—')}</code></td>
            ${whCell}
            <td>${typeBadge(r.movement_type)}</td>
            <td>${qtyCell(r.quantity)}</td>
            <td>${Number(previousStock(r)).toLocaleString()}</td>
            <td>${Number(r.balance_after ?? 0).toLocaleString()}</td>
            <td>${esc(r.created_by_name || '—')}</td>
            <td><button type="button" class="wh-btn wh-btn--ghost wh-btn--sm wh-smrt-view" data-id="${esc(r.id)}" title="${esc(t('wms_view_details'))}">
                <span class="material-icons-round">visibility</span></button></td>
        </tr>`;
        }).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('.wh-smrt-row').forEach((row) => {
            row.addEventListener('click', (ev) => {
                if (ev.target.closest('button')) return;
                const item = state.items.find((i) => String(i.id) === String(row.dataset.id));
                if (item) openDetail(item);
            });
        });
        tableWrap.querySelectorAll('.wh-smrt-view').forEach((btn) => {
            btn.addEventListener('click', (ev) => {
                ev.stopPropagation();
                const item = state.items.find((i) => String(i.id) === String(btn.dataset.id));
                if (item) openDetail(item);
            });
        });
    }

    function renderPagination() {
        const totalPages = Math.max(1, Math.ceil(state.total / state.limit));
        const show = state.total > state.limit;
        if (els.pagination) els.pagination.hidden = !show;
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= totalPages;
        if (els.pageMeta) {
            const from = state.total ? (state.page - 1) * state.limit + 1 : 0;
            const to = Math.min(state.page * state.limit, state.total);
            els.pageMeta.textContent = `${from}–${to} / ${state.total} ${t('records')}`;
        }
    }

    function saveCache(payload) {
        try {
            localStorage.setItem(CACHE_KEY, JSON.stringify({ saved_at: Date.now(), ...payload }));
        } catch (_) { /* quota */ }
    }

    function loadCache() {
        try {
            const raw = localStorage.getItem(CACHE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    }

    function applyCached(cached) {
        if (!cached) return false;
        state.items = cached.data || [];
        state.total = cached.total || 0;
        state.summary = cached.summary;
        state.breakdown = cached.breakdown || [];
        state.chart = cached.chart;
        if (els.offlineBadge) els.offlineBadge.hidden = false;
        renderStats(state.summary);
        renderBreakdown(state.breakdown);
        renderCharts(state.chart, state.breakdown);
        renderTable(state.items);
        renderPagination();
        return true;
    }

    function closeModal() {
        if (!els.modal) return;
        els.modal.classList.remove('is-open');
        els.modal.setAttribute('aria-hidden', 'true');
    }

    function openModal() {
        if (!els.modal) return;
        els.modal.classList.add('is-open');
        els.modal.setAttribute('aria-hidden', 'false');
    }

    function openDetail(row) {
        if (els.modalTitle) els.modalTitle.textContent = row.product_name || t('wh_ledger_details');
        if (els.modalSubtitle) {
            els.modalSubtitle.textContent = [row.sku, typeLabel(row.movement_type), row.warehouse_name].filter(Boolean).join(' · ');
        }
        if (els.modalBody) {
            els.modalBody.innerHTML = `
                <dl class="wh-smrt-detail-grid">
                    <div><dt>${esc(t('wh_ledger_col_date'))}</dt><dd>${esc(formatDate(row.created_at))}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_type'))}</dt><dd>${typeBadge(row.movement_type)}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_qty'))}</dt><dd>${qtyCell(row.quantity)}</dd></div>
                    <div><dt>${esc(t('wh_smrt_col_prev'))}</dt><dd>${Number(previousStock(row)).toLocaleString()}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_balance'))}</dt><dd>${Number(row.balance_after ?? 0).toLocaleString()}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_value'))}</dt><dd>${esc(money(row.stock_value))}</dd></div>
                    <div><dt>${esc(t('wms_nav_warehouses'))}</dt><dd>${esc(row.warehouse_name || '—')}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_reference'))}</dt><dd>${esc(formatRef(row))}</dd></div>
                    <div><dt>${esc(t('wh_ledger_col_user'))}</dt><dd>${esc(row.created_by_name || '—')}</dd></div>
                </dl>
                ${row.notes ? `<p class="wh-smrt-detail-notes"><strong>${esc(t('wh_ledger_col_notes'))}:</strong> ${esc(row.notes)}</p>` : ''}`;
        }
        openModal();
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        if (els.offlineBadge) els.offlineBadge.hidden = true;
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const params = buildParams();
            const wh = params.warehouse_id || null;
            delete params.warehouse_id;
            const res = await AdminAPI.getWmsMovements(wh, params);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            setMigrationHint(res.module_ready !== false);
            state.items = res.data || [];
            state.total = Number(res.total ?? state.items.length);
            state.summary = res.summary || null;
            state.breakdown = res.breakdown || [];
            state.chart = res.chart || null;
            renderStats(state.summary);
            renderBreakdown(state.breakdown);
            renderCharts(state.chart, state.breakdown);
            renderTable(state.items);
            renderPagination();
            const whName = els.warehouse?.selectedOptions?.[0]?.text || t('wh_all_warehouses');
            if (els.heroMeta) {
                els.heroMeta.textContent = `${whName} · ${state.total} ${t('records')} · ${money(state.summary?.total_value ?? 0)}`;
            }
            updateLastUpdated();
            saveCache({
                data: state.items,
                total: state.total,
                summary: state.summary,
                breakdown: state.breakdown,
                chart: state.chart,
            });
        } catch (err) {
            const cached = loadCache();
            if (applyCached(cached)) return;
            showError(err.message || t('load_error'));
            tableWrap.innerHTML = '';
            if (els.empty) els.empty.hidden = true;
            if (els.breakdownPanel) els.breakdownPanel.hidden = true;
        } finally {
            if (els.loading) els.loading.hidden = true;
        }
    }

    function exportHeaders() {
        const cols = [
            t('wh_ledger_col_date'), t('wms_col_reference'), t('wh_ledger_col_product'), 'SKU',
        ];
        if (showWarehouseCol()) cols.push(t('wh_ledger_col_warehouse'));
        cols.push(
            t('wh_ledger_col_type'), t('wh_ledger_col_qty'), t('wh_smrt_col_prev'),
            t('wh_ledger_col_balance'), t('wh_ledger_col_value'), t('wh_ledger_col_user'),
        );
        return cols;
    }

    function exportRow(r) {
        const row = [
            formatDate(r.created_at), formatRef(r), r.product_name, r.sku,
        ];
        if (showWarehouseCol()) row.push(r.warehouse_name);
        row.push(
            typeLabel(r.movement_type), r.quantity, previousStock(r),
            r.balance_after, r.stock_value, r.created_by_name || '',
        );
        return row;
    }

    async function fetchExportRows() {
        const params = buildParams(true);
        const wh = params.warehouse_id || null;
        delete params.warehouse_id;
        const res = await AdminAPI.getWmsMovements(wh, params);
        if (res.status !== 'success') throw new Error(res.message || t('load_error'));
        return res.data || [];
    }

    async function doExport(type) {
        try {
            const rows = await fetchExportRows();
            if (!rows.length) {
                showError(t('wh_smrt_empty'));
                return;
            }
            const stamp = new Date().toISOString().slice(0, 10);
            const head = exportHeaders();
            const body = rows.map(exportRow);

            if (type === 'csv') {
                exportCsv(`stock-movement-report-${stamp}.csv`, [head, ...body]);
                return;
            }
            if (type === 'excel') {
                const bom = '\uFEFF';
                const tsv = bom + [head, ...body].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join('\t')).join('\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([tsv], { type: 'application/vnd.ms-excel;charset=utf-8' }));
                a.download = `stock-movement-report-${stamp}.xls`;
                a.click();
                return;
            }
            if (type === 'pdf' && window.WmsReportExport?.exportPdf) {
                await WmsReportExport.exportPdf({
                    title: t('wh_nav_rpt_movements') || 'Stock Movement Report',
                    periodLabel: [els.dateFrom?.value, els.dateTo?.value].filter(Boolean).join(' → '),
                    generatedLabel: t('wh_ledger_col_date'),
                    locale: window.WH_CONFIG?.locale,
                    head,
                    rows: body,
                    filename: `stock-movement-report-${stamp}.pdf`,
                });
                return;
            }
            if (type === 'print') window.print();
        } catch (err) {
            showError(err.message || t('load_error'));
        }
    }

    els.refresh?.addEventListener('click', () => load());
    els.prev?.addEventListener('click', () => { if (state.page > 1) { state.page -= 1; load(); } });
    els.next?.addEventListener('click', () => { state.page += 1; load(); });
    els.search?.addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => load(true), 350);
    });
    [els.type, els.dateFrom, els.dateTo, els.warehouse].forEach((el) => {
        el?.addEventListener('change', () => load(true));
    });
    els.exportCsv?.addEventListener('click', () => doExport('csv'));
    els.exportExcel?.addEventListener('click', () => doExport('excel'));
    els.exportPdf?.addEventListener('click', () => doExport('pdf'));
    els.printBtn?.addEventListener('click', () => doExport('print'));
    els.modalClose?.addEventListener('click', closeModal);
    els.modal?.querySelector('[data-close-modal]')?.addEventListener('click', closeModal);
    document.addEventListener('wh:refresh', () => load());
    window.addEventListener('online', () => load());

    const q = new URLSearchParams(window.location.search).get('q');
    if (q && els.search) els.search.value = q;

    loadWarehouseOptions(els.warehouse).then(() => load());
});
