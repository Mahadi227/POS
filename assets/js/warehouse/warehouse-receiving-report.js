/**
 * Warehouse — Receiving Report (GRN)
 */
document.addEventListener('DOMContentLoaded', () => {
    const tableWrap = document.getElementById('whRcptTableWrap');
    if (!tableWrap) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv, loadWarehouseOptions } = WarehouseUI;
    const CACHE_KEY = 'wh_receiving_report_v1';

    const STATUS_KEYS = {
        pending: 'wms_status_pending',
        inspecting: 'wms_status_inspecting',
        accepted: 'wms_status_accepted',
        completed: 'wms_status_completed',
        rejected: 'wms_status_rejected',
    };
    const STATUS_ORDER = ['pending', 'inspecting', 'accepted', 'completed', 'rejected'];
    const STATUS_COLORS = ['#d97706', '#2563eb', '#0891b2', '#059669', '#dc2626'];

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
        loading: document.getElementById('whRcptLoading'),
        empty: document.getElementById('whRcptEmpty'),
        warehouse: document.getElementById('whRcptWarehouse'),
        search: document.getElementById('whRcptSearch'),
        status: document.getElementById('whRcptStatus'),
        dateFrom: document.getElementById('whRcptDateFrom'),
        dateTo: document.getElementById('whRcptDateTo'),
        refresh: document.getElementById('whRcptRefreshBtn'),
        exportCsv: document.getElementById('whRcptExportCsv'),
        exportExcel: document.getElementById('whRcptExportExcel'),
        exportPdf: document.getElementById('whRcptExportPdf'),
        printBtn: document.getElementById('whRcptPrintBtn'),
        heroMeta: document.getElementById('whRcptHeroMeta'),
        statTotal: document.getElementById('whRcptStatTotal'),
        statPipeline: document.getElementById('whRcptStatPipeline'),
        statCompleted: document.getElementById('whRcptStatCompleted'),
        statRejected: document.getElementById('whRcptStatRejected'),
        statValue: document.getElementById('whRcptStatValue'),
        statItems: document.getElementById('whRcptStatItems'),
        breakdownPanel: document.getElementById('whRcptBreakdownPanel'),
        statusChips: document.getElementById('whRcptStatusChips'),
        pagination: document.getElementById('whRcptPagination'),
        prev: document.getElementById('whRcptPrev'),
        next: document.getElementById('whRcptNext'),
        pageMeta: document.getElementById('whRcptPageMeta'),
        offlineBadge: document.getElementById('whRcptOfflineBadge'),
        modal: document.getElementById('whRcptDetailModal'),
        modalClose: document.getElementById('whRcptDetailClose'),
        modalTitle: document.getElementById('whRcptDetailTitle'),
        modalSubtitle: document.getElementById('whRcptDetailSubtitle'),
        modalBody: document.getElementById('whRcptDetailBody'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return { grid: dark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)', text: dark ? '#94a3b8' : '#64748b' };
    }

    function statusLabel(status) {
        return t(STATUS_KEYS[status] || status) || status || '—';
    }

    function statusBadge(status) {
        const cls = status === 'completed' ? 'ok' : (status === 'rejected' ? 'off' : 'warn');
        return `<span class="cr-badge cr-badge--${cls}">${esc(statusLabel(status))}</span>`;
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
        document.querySelectorAll('.wh-rcpt-stat__value').forEach((el) => el.classList.toggle('is-loading', loading));
    }

    function renderStats(summary) {
        const s = summary || {};
        if (els.statTotal) els.statTotal.textContent = String(s.total ?? 0);
        if (els.statPipeline) els.statPipeline.textContent = String(s.pending ?? 0);
        if (els.statCompleted) els.statCompleted.textContent = String(s.completed ?? 0);
        if (els.statRejected) els.statRejected.textContent = String(s.rejected ?? 0);
        if (els.statValue) els.statValue.textContent = money(s.total_value ?? 0);
        if (els.statItems) els.statItems.textContent = Number(s.total_items ?? 0).toLocaleString();
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
            return `<button type="button" class="wh-rcpt-status-chip${isActive ? ' is-active' : ''}" data-status="${esc(r.status)}">
                <span>${esc(statusLabel(r.status))}</span>
                <strong>${Number(r.count || 0)}</strong>
            </button>`;
        }).join('');
        els.statusChips.querySelectorAll('.wh-rcpt-status-chip').forEach((chip) => {
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
        const trendCtx = document.getElementById('whRcptChartTrend');
        if (trendCtx && trend.length) {
            chartInstances.trend = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trend.map((d) => d.d),
                    datasets: [
                        { label: t('wh_rcpt_stat_total'), data: trend.map((d) => d.receipt_count), borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,0.08)', fill: true, tension: 0.35, yAxisID: 'y' },
                        { label: t('wh_rcpt_stat_value'), data: trend.map((d) => d.total_value), borderColor: '#2563eb', tension: 0.35, yAxisID: 'y1' },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { color: c.text } } },
                    scales: {
                        x: { ticks: { color: c.text }, grid: { color: c.grid } },
                        y: { position: 'left', ticks: { color: c.text }, grid: { color: c.grid } },
                        y1: { position: 'right', ticks: { color: c.text }, grid: { display: false } },
                    },
                },
            });
        }

        destroyChart('status');
        const statusCtx = document.getElementById('whRcptChartStatus');
        const statusData = (breakdown || []).filter((d) => Number(d.count) > 0);
        if (statusCtx && statusData.length) {
            chartInstances.status = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusData.map((d) => statusLabel(d.status)),
                    datasets: [{ data: statusData.map((d) => d.count), backgroundColor: statusData.map((d, i) => STATUS_COLORS[STATUS_ORDER.indexOf(d.status)] || STATUS_COLORS[i % STATUS_COLORS.length]) }],
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
        tableWrap.innerHTML = `<table class="wh-table wh-rcpt-table"><thead><tr>
            <th>${esc(t('wms_col_grn'))}</th>
            ${whCol}
            <th>${esc(t('wms_col_supplier'))}</th>
            <th>${esc(t('wms_col_items'))}</th>
            <th>${esc(t('wms_col_value'))}</th>
            <th>${esc(t('col_date'))}</th>
            <th>${esc(t('wh_rcpt_col_received_by'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th></th>
        </tr></thead><tbody>${items.map((r) => {
            const whCell = showWarehouseCol() ? `<td>${esc(r.warehouse_name || '—')}</td>` : '';
            return `<tr data-id="${esc(r.id)}" class="wh-rcpt-row">
            <td><strong>${esc(r.grn_number)}</strong></td>
            ${whCell}
            <td>${esc(r.supplier_name || '—')}</td>
            <td>${Number(r.total_items || 0)}</td>
            <td>${esc(money(r.total_value))}</td>
            <td>${esc(formatDate(r.received_at))}</td>
            <td>${esc(r.received_by_name || '—')}</td>
            <td>${statusBadge(r.status)}</td>
            <td><button type="button" class="wh-btn wh-btn--ghost wh-btn--sm wh-rcpt-view" data-id="${esc(r.id)}">
                <span class="material-icons-round">visibility</span></button></td>
        </tr>`;
        }).join('')}</tbody></table>`;

        tableWrap.querySelectorAll('.wh-rcpt-row').forEach((row) => {
            row.addEventListener('click', (ev) => {
                if (ev.target.closest('button')) return;
                openDetail(Number(row.dataset.id));
            });
        });
        tableWrap.querySelectorAll('.wh-rcpt-view').forEach((btn) => {
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
            const res = await AdminAPI.getWmsReceipt(id);
            const row = res.data;
            if (!row) return;
            if (els.modalTitle) els.modalTitle.textContent = row.grn_number || t('wms_view_details');
            if (els.modalSubtitle) {
                els.modalSubtitle.textContent = [row.warehouse_name, row.supplier_name, statusLabel(row.status)].filter(Boolean).join(' · ');
            }
            if (els.modalBody) {
                const items = row.items || [];
                els.modalBody.innerHTML = `
                    <dl class="wh-rcpt-detail-grid">
                        <div><dt>${esc(t('col_date'))}</dt><dd>${esc(formatDate(row.received_at))}</dd></div>
                        <div><dt>${esc(t('wms_col_value'))}</dt><dd>${esc(money(row.total_value))}</dd></div>
                        <div><dt>${esc(t('wms_col_items'))}</dt><dd>${Number(row.total_items || 0)}</dd></div>
                        <div><dt>${esc(t('wh_rcpt_col_received_by'))}</dt><dd>${esc(row.received_by_name || '—')}</dd></div>
                        <div><dt>${esc(t('col_status'))}</dt><dd>${statusBadge(row.status)}</dd></div>
                    </dl>
                    ${items.length ? `<table class="wh-table wh-rcpt-items-table"><thead><tr>
                        <th>${esc(t('wms_col_product'))}</th><th>SKU</th><th>${esc(t('wms_col_qty'))}</th><th>${esc(t('wms_col_value'))}</th>
                    </tr></thead><tbody>${items.map((it) => `<tr>
                        <td>${esc(it.product_name)}</td><td>${esc(it.sku || '—')}</td>
                        <td>${Number(it.quantity_received ?? 0)}</td>
                        <td>${esc(money((it.quantity_received || 0) * (it.unit_cost || 0)))}</td>
                    </tr>`).join('')}</tbody></table>` : ''}
                    ${row.notes ? `<p class="wh-rcpt-detail-notes"><strong>Notes:</strong> ${esc(row.notes)}</p>` : ''}`;
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
            const res = await AdminAPI.getWmsReceipts(buildParams());
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
        const cols = [t('wms_col_grn')];
        if (showWarehouseCol()) cols.push(t('wms_nav_warehouses'));
        cols.push(t('wms_col_supplier'), t('wms_col_items'), t('wms_col_value'), t('col_date'), t('wh_rcpt_col_received_by'), t('col_status'));
        return cols;
    }

    function exportRow(r) {
        const row = [r.grn_number];
        if (showWarehouseCol()) row.push(r.warehouse_name);
        row.push(r.supplier_name, r.total_items, r.total_value, formatDate(r.received_at), r.received_by_name, statusLabel(r.status));
        return row;
    }

    async function doExport(type) {
        try {
            const res = await AdminAPI.getWmsReceipts(buildParams(true));
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            const rows = res.data || [];
            if (!rows.length) {
                showError(t('wh_rcpt_empty'));
                return;
            }
            const stamp = new Date().toISOString().slice(0, 10);
            const head = exportHeaders();
            const body = rows.map(exportRow);

            if (type === 'csv') {
                exportCsv(`receiving-report-${stamp}.csv`, [head, ...body]);
                return;
            }
            if (type === 'excel') {
                const bom = '\uFEFF';
                const tsv = bom + [head, ...body].map((r) => r.map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join('\t')).join('\n');
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([tsv], { type: 'application/vnd.ms-excel;charset=utf-8' }));
                a.download = `receiving-report-${stamp}.xls`;
                a.click();
                return;
            }
            if (type === 'pdf' && window.WmsReportExport?.exportPdf) {
                await WmsReportExport.exportPdf({
                    title: t('wh_nav_rpt_receiving') || 'Receiving Report',
                    periodLabel: [els.dateFrom?.value, els.dateTo?.value].filter(Boolean).join(' → '),
                    generatedLabel: t('col_date'),
                    locale: window.WH_CONFIG?.locale,
                    head,
                    rows: body,
                    filename: `receiving-report-${stamp}.pdf`,
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
