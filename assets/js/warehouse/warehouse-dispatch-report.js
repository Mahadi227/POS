/**
 * Warehouse — Dispatch Report
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whDsrptTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const CACHE_KEY = 'wh_dispatch_report_v1';

    const STATUS_KEYS = {
        draft: 'wms_status_draft',
        picking: 'wms_status_picking',
        packed: 'wms_status_packed',
        dispatched: 'wms_status_dispatched',
        in_transit: 'wms_status_in_transit',
        delivered: 'wms_status_delivered',
        cancelled: 'wms_status_cancelled',
    };
    const STATUS_ORDER = ['draft', 'picking', 'packed', 'dispatched', 'in_transit', 'delivered', 'cancelled'];
    const STATUS_COLORS = ['#94a3b8', '#d97706', '#2563eb', '#0891b2', '#7c3aed', '#059669', '#dc2626'];

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
        loading: document.getElementById('whDsrptLoading'),
        empty: document.getElementById('whDsrptEmpty'),
        warehouse: document.getElementById('whDsrptWarehouse'),
        search: document.getElementById('whDsrptSearch'),
        status: document.getElementById('whDsrptStatus'),
        dateFrom: document.getElementById('whDsrptDateFrom'),
        dateTo: document.getElementById('whDsrptDateTo'),
        refresh: document.getElementById('whDsrptRefreshBtn'),
        exportCsv: document.getElementById('whDsrptExportCsv'),
        exportExcel: document.getElementById('whDsrptExportExcel'),
        exportPdf: document.getElementById('whDsrptExportPdf'),
        printBtn: document.getElementById('whDsrptPrintBtn'),
        heroMeta: document.getElementById('whDsrptHeroMeta'),
        statTotal: document.getElementById('whDsrptStatTotal'),
        statDraft: document.getElementById('whDsrptStatDraft'),
        statTransit: document.getElementById('whDsrptStatTransit'),
        statDelivered: document.getElementById('whDsrptStatDelivered'),
        statCancelled: document.getElementById('whDsrptStatCancelled'),
        statValue: document.getElementById('whDsrptStatValue'),
        breakdownPanel: document.getElementById('whDsrptBreakdownPanel'),
        statusChips: document.getElementById('whDsrptStatusChips'),
        pagination: document.getElementById('whDsrptPagination'),
        prev: document.getElementById('whDsrptPrev'),
        next: document.getElementById('whDsrptNext'),
        pageMeta: document.getElementById('whDsrptPageMeta'),
        offlineBadge: document.getElementById('whDsrptOfflineBadge'),
        modal: document.getElementById('whDsrptDetailModal'),
        modalClose: document.getElementById('whDsrptDetailClose'),
        modalTitle: document.getElementById('whDsrptDetailTitle'),
        modalSubtitle: document.getElementById('whDsrptDetailSubtitle'),
        modalBody: document.getElementById('whDsrptDetailBody'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return { grid: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)', text: dark ? '#94a3b8' : '#64748b' };
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'delivered' ? 'ok' : (status === 'cancelled' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
    }

    function destinationLabel(row) {
        return row.to_store_name || row.to_warehouse_name || '—';
    }

    function formatDate(iso) {
        if (!iso) return '—';
        try {
            return AdminAPI.formatDate(iso, { dateStyle: 'short', timeStyle: 'short' });
        } catch {
            return iso;
        }
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.wh-dsrpt-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statDraft) els.statDraft.textContent = String(s.draft ?? 0);
        if (els.statTransit) els.statTransit.textContent = String(s.in_transit ?? 0);
        if (els.statDelivered) els.statDelivered.textContent = String(s.delivered ?? 0);
        if (els.statCancelled) els.statCancelled.textContent = String(s.cancelled ?? 0);
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        setStatsLoading(false);
    }

    function renderBreakdown(items) {
        if (!els.breakdownPanel || !els.statusChips) return;
        const list = (items || []).filter((r) => Number(r.count) > 0);
        if (!list.length) {
            els.breakdownPanel.hidden = true;
            return;
        }
        const sorted = [...list].sort((a, b) => {
            const ai = STATUS_ORDER.indexOf(a.status);
            const bi = STATUS_ORDER.indexOf(b.status);
            return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
        });
        const activeStatus = els.status?.value || 'all';
        els.breakdownPanel.hidden = false;
        els.statusChips.innerHTML = sorted.map((r) => {
            const isActive = activeStatus === r.status;
            return `<button type="button" class="wh-dsrpt-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-dsrpt-status-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                if (els.status) els.status.value = chip.dataset.status || 'all';
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
        const trendCtx = document.getElementById('whDsrptChartTrend');
        if (trendCtx && trend.length) {
            chartInstances.trend = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trend.map((d) => d.d),
                    datasets: [
                        { label: t('wh_dsrpt_stat_total'), data: trend.map((d) => d.dispatch_count), borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,0.08)', fill: true, tension: 0.35, yAxisID: 'y' },
                        { label: t('wh_dsrpt_stat_delivered'), data: trend.map((d) => d.delivered), borderColor: '#059669', tension: 0.35, yAxisID: 'y' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: c.text } } },
                    scales: {
                        x: { ticks: { color: c.text }, grid: { color: c.grid } },
                        y: { ticks: { color: c.text }, grid: { color: c.grid } },
                    },
                },
            });
        }

        destroyChart('status');
        const statusCtx = document.getElementById('whDsrptChartStatus');
        const statusData = (breakdown || []).filter((d) => Number(d.count) > 0);
        if (statusCtx && statusData.length) {
            chartInstances.status = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusData.map((d) => statusLabel(d.status)),
                    datasets: [{ data: statusData.map((d) => d.count), backgroundColor: statusData.map((d, i) => STATUS_COLORS[STATUS_ORDER.indexOf(d.status)] ?? STATUS_COLORS[i % STATUS_COLORS.length]) }],
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
            scope: 'report',
            limit: forExport ? 10000 : state.limit,
            offset: forExport ? 0 : (state.page - 1) * state.limit,
            days: 30,
        };
        const wh = els.warehouse?.value?.trim();
        if (wh) params.warehouse_id = wh;
        const q = els.search?.value?.trim();
        if (q) params.q = q;
        const status = els.status?.value?.trim();
        if (status && status !== 'all') params.status = status;
        const from = els.dateFrom?.value?.trim();
        if (from) params.date_from = from;
        const to = els.dateTo?.value?.trim();
        if (to) params.date_to = to;
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
        const whCol = showWarehouseCol() ? `<th>${esc(t('wms_nav_warehouses'))}</th>` : '';
        tableWrap.innerHTML = `<table class="wh-table wh-dsrpt-table"><thead><tr>
            <th>${esc(t('wms_col_dispatch'))}</th>
            ${whCol}
            <th>${esc(t('wms_col_destination'))}</th>
            <th>${esc(t('wms_col_items'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('wms_col_driver'))}</th>
            <th>${esc(t('col_date'))}</th>
            <th>${esc(t('wh_dsrpt_col_created_by'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((d) => {
            const whCell = showWarehouseCol() ? `<td>${esc(d.from_warehouse_name || '—')}</td>` : '';
            return `<tr data-id="${esc(d.id)}" class="wh-dsrpt-row">
            <td><strong>${esc(d.dispatch_number)}</strong></td>
            ${whCell}
            <td>${esc(destinationLabel(d))}</td>
            <td>${Number(d.total_items || 0)}</td>
            <td>${esc(money(d.total_value))}</td>
            <td>${esc(d.driver_name || '—')}</td>
            <td>${esc(formatDate(d.created_at))}</td>
            <td>${esc(d.created_by_name || '—')}</td>
            <td>${statusBadge(d.status)}</td>
            <td><button type="button" class="wh-btn wh-btn--ghost wh-btn--sm wh-dsrpt-view" data-id="${esc(d.id)}">
                <span class="material-icons-round">visibility</span></button></td>
        </tr>`;
        }).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('.wh-dsrpt-row').forEach((row) => {
            row.addEventListener('click', (ev) => {
                if (ev.target.closest('button')) return;
                openDetail(Number(row.dataset.id));
            });
        });
        tableWrap.querySelectorAll('.wh-dsrpt-view').forEach((btn) => {
            btn.addEventListener('click', (ev) => {
                ev.stopPropagation();
                openDetail(Number(btn.dataset.id));
            });
        });
    }

    function renderPagination() {
        const pages = Math.max(1, Math.ceil(state.total / state.limit));
        if (els.pagination) els.pagination.hidden = state.total <= state.limit;
        if (els.prev) els.prev.disabled = state.page <= 1;
        if (els.next) els.next.disabled = state.page >= pages;
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

    async function openDetail(id) {
        try {
            const res = await AdminAPI.getWmsDispatch(id);
            const row = res.data;
            if (!row) return;
            if (els.modalTitle) els.modalTitle.textContent = row.dispatch_number || t('wms_view_details');
            if (els.modalSubtitle) {
                els.modalSubtitle.textContent = [row.from_warehouse_name, destinationLabel(row), statusLabel(row.status)].filter(Boolean).join(' · ');
            }
            if (els.modalBody) {
                const items = row.items || [];
                els.modalBody.innerHTML = `
                    <dl class="wh-dsrpt-detail-grid">
                        <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(row.created_at))}</dd></div>
                        <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(row.total_value))}</dd></div>
                        <div><dt>${esc(t('wms_col_items'))}</dt><dd>${Number(row.total_items || 0)}</dd></div>
                        <div><dt>${esc(t('wms_col_driver'))}</dt><dd>${esc(row.driver_name || '—')}</dd></div>
                        <div><dt>${esc(t('wh_dsrpt_col_created_by'))}</dt><dd>${esc(row.created_by_name || '—')}</dd></div>
                        <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(row.status)}</dd></div>
                    </dl>
                    ${items.length ? `<table class="wh-table wh-dsrpt-items-table"><thead><tr>
                        <th>${esc(t('wms_col_product'))}</th><th>SKU</th><th>${esc(t('wms_col_qty'))}</th><th>${esc(t('wms_col_value'))}</th>
                    </tr></thead><tbody>${items.map((it) => `<tr>
                        <td>${esc(it.product_name)}</td><td>${esc(it.sku || '—')}</td>
                        <td>${Number(it.quantity || 0)}</td>
                        <td>${esc(money((it.quantity || 0) * (it.unit_cost || 0)))}</td>
                    </tr>`).join('')}</tbody></table>` : ''}
                    ${row.notes ? `<p class="wh-dsrpt-detail-notes"><strong>Notes:</strong> ${esc(row.notes)}</p>` : ''}`;
            }
            openModal();
        } catch (err) {
            showError(err.message || t('load_error'));
        }
    }

    async function load(resetPage = false) {
        if (resetPage) state.page = 1;
        hideError();
        if (els.offlineBadge) els.offlineBadge.hidden = true;
        if (els.loading) els.loading.hidden = false;
        setStatsLoading(true);
        try {
            const res = await AdminAPI.getWmsDispatches(buildParams());
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
        const cols = [t('wms_col_dispatch')];
        if (showWarehouseCol()) cols.push(t('wms_nav_warehouses'));
        cols.push(t('wms_col_destination'), t('wms_col_items'), t('wms_col_value'), t('wms_col_driver'), t('col_date'), t('wh_dsrpt_col_created_by'), t('col_status'));
        return cols;
    }

    function exportRow(d) {
        const row = [d.dispatch_number];
        if (showWarehouseCol()) row.push(d.from_warehouse_name);
        row.push(destinationLabel(d), d.total_items, d.total_value, d.driver_name, formatDate(d.created_at), d.created_by_name, statusLabel(d.status));
        return row;
    }

    async function doExport(type) {
        try {
            const res = await AdminAPI.getWmsDispatches(buildParams(true));
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            const rows = res.data || [];
            if (!rows.length) {
                showError(t('wh_dsrpt_empty'));
                return;
            }
            const stamp = new Date().toISOString().slice(0, 10);
            const head = exportHeaders();
            const body = rows.map(exportRow);

            if (type === 'csv') {
                exportCsv(`dispatch-report-${stamp}.csv`, [head, ...body]);
                return;
            }
            if (type === 'excel') {
                const bom = '\uFEFF';
                const tsv = bom + [head, ...body].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join('\t')).join('\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([tsv], { type: 'application/vnd.ms-excel;charset=utf-8' }));
                a.download = `dispatch-report-${stamp}.xls`;
                a.click();
                return;
            }
            if (type === 'pdf' && window.WmsReportExport?.exportPdf) {
                await WmsReportExport.exportPdf({
                    title: t('wh_nav_rpt_dispatch') || 'Dispatch Report',
                    periodLabel: [els.dateFrom?.value, els.dateTo?.value].filter(Boolean).join(' → '),
                    generatedLabel: t('col_date'),
                    locale: window.WH_CONFIG?.locale,
                    head,
                    rows: body,
                    filename: `dispatch-report-${stamp}.pdf`,
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
    [els.status, els.dateFrom, els.dateTo, els.warehouse].forEach((el) => {
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

    loadWarehouseOptions(els.warehouse).then(() => load());
});
