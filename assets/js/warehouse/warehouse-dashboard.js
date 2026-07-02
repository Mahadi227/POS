/**
 * Warehouse portal dashboard v2 — hero KPIs, period charts, status, alerts, CSV export
 */
document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('whDashHeroStats')) return;

    const { t, esc, money, pct, defaultCurrency, showError, hideError, setMigrationHint, updateLastUpdated, exportCsv } = WarehouseUI;

    const state = { period: 'week', data: null, currency: defaultCurrency(), useCustomDates: false };
    let movementChart;
    let capacityChart;

    const els = {
        periodTabs: document.getElementById('whDashPeriod'),
        dateFrom: document.getElementById('whDashDateFrom'),
        dateTo: document.getElementById('whDashDateTo'),
    };

    const ACTION_KEYS = {
        warehouse_created: 'wms_log_warehouse_created',
        warehouse_updated: 'wms_log_warehouse_updated',
        warehouse_deleted: 'wms_log_warehouse_deleted',
        location_created: 'wms_log_location_created',
        transfer_requested: 'wms_log_transfer_requested',
        transfer_approved: 'wms_log_transfer_approved',
        transfer_rejected: 'wms_log_transfer_rejected',
        transfer_received: 'wms_log_transfer_received',
        dispatch_created: 'wms_log_dispatch_created',
        dispatch_out: 'wms_log_dispatch_out',
        request_created: 'wms_log_request_created',
        request_approved: 'wms_log_request_approved',
        request_rejected: 'wms_log_request_rejected',
        batch_created: 'wms_log_batch_created',
        batch_status_updated: 'wms_log_batch_status_updated',
        audit_created: 'wms_log_audit_created',
        audit_submitted: 'wms_log_audit_submitted',
        audit_approved: 'wms_log_audit_approved',
        audit_rejected: 'wms_log_audit_rejected',
        low_stock: 'wms_log_low_stock',
        damaged_stock: 'wms_log_damaged_stock',
        expired_product: 'wms_log_expired_product',
        incoming_delivery: 'wms_log_incoming_delivery',
        purchase_received: 'wms_log_purchase_received',
        warehouse_full: 'wms_log_warehouse_full',
    };

    const KPI_MAP = {
        whHeroValue: (k, ctx) => formatInventoryValue(k, ctx),
        whHeroReceiving: (k) => k.today_receiving,
        whHeroDispatch: (k) => k.today_dispatch,
        whHeroApprovals: (k) => k.pending_approvals,
        whKpiReceiving: (k) => k.period_receiving ?? k.today_receiving,
        whKpiDispatch: (k) => k.period_dispatch ?? k.today_dispatch,
        whKpiTransfers: (k) => k.period_transfers ?? k.today_transfers,
        whKpiRequests: (k) => k.pending_requests,
        whKpiDeliveries: (k) => k.pending_deliveries,
        whKpiApprovals: (k) => k.pending_approvals,
        whKpiLow: (k) => k.low_stock,
        whKpiOut: (k) => k.out_of_stock,
        whKpiDamaged: (k) => k.damaged_products,
        whKpiExpired: (k) => k.expired_products,
        whKpiExpiring: (k) => k.expiring_soon,
        whKpiCapacity: (k) => pct(k.warehouse_capacity),
        whKpiTotalWh: (k) => k.total_warehouses,
        whKpiActiveWh: (k) => k.active_warehouses,
        whKpiProducts: (k) => k.total_products,
        whKpiPendingXfer: (k) => k.pending_transfers,
        whKpiIncoming: (k) => k.incoming_shipments,
        whKpiOutgoing: (k) => k.outgoing_shipments,
    };

    function formatInventoryValue(kpis, ctx) {
        const breakdown = ctx?.currency_breakdown || [];
        if (ctx?.is_multi_currency && breakdown.length > 1) {
            return breakdown.map((row) => money(row.stock_value, row.currency)).join(' · ');
        }
        const code = ctx?.currency || defaultCurrency();
        return money(kpis.inventory_value, code);
    }

    function renderCurrencyBadge(currency, isMulti) {
        const el = document.getElementById('whHeroCurrency');
        if (!el) return;
        if (isMulti) {
            el.hidden = false;
            el.textContent = t('wh_dash_currency_multi');
        } else if (currency) {
            el.hidden = false;
            el.textContent = currency;
        } else {
            el.hidden = true;
        }
    }

    function renderCurrencyBreakdown(data) {
        const panel = document.getElementById('whCurrencyPanel');
        const list = document.getElementById('whCurrencyList');
        const hint = document.getElementById('whCurrencyHint');
        const breakdown = data?.currency_breakdown || [];
        if (!panel || !list) return;
        const show = breakdown.length > 1 || (data?.is_multi_currency && breakdown.length > 0);
        panel.hidden = !show;
        if (!show) return;
        if (hint) {
            hint.textContent = data.is_multi_currency ? t('wh_dash_currency_multi') : '';
        }
        list.innerHTML = breakdown.map((row) => `
            <li class="wh-currency-item">
                <div class="wh-currency-item__main">
                    <strong>${esc(row.store_name || '—')}</strong>
                    <span class="wh-muted">${esc(row.country || '—')} · ${esc(row.currency || '')}</span>
                </div>
                <div class="wh-currency-item__meta">
                    <span class="wh-currency-item__value">${esc(money(row.stock_value, row.currency))}</span>
                    <span class="wh-muted">${Number(row.warehouse_count || 0)} WH</span>
                </div>
            </li>`).join('');
    }

    function actionLabel(action) {
        if (!action) return '—';
        const key = ACTION_KEYS[action];
        return key ? (t(key) || action) : action.replace(/_/g, ' ');
    }

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: dark ? 'rgba(148,163,184,0.12)' : 'rgba(0,0,0,0.06)',
            text: dark ? '#9ca3af' : '#6b7280',
        };
    }

    function periodRange(period) {
        const to = new Date();
        const from = new Date(to);
        if (period === 'month') {
            from.setDate(to.getDate() - 29);
        } else if (period === 'year') {
            from.setDate(to.getDate() - 89);
        } else if (period === 'all') {
            from.setDate(to.getDate() - 364);
        } else {
            from.setDate(to.getDate() - 6);
        }
        const fmt = (d) => d.toISOString().slice(0, 10);
        return { from: fmt(from), to: fmt(to) };
    }

    function periodLabelText(data) {
        if (state.useCustomDates) {
            const from = els.dateFrom?.value;
            const to = els.dateTo?.value;
            if (!from || !to) return '—';
            const loc = window.WH_CONFIG?.locale || 'fr-FR';
            const f = new Date(`${from}T12:00:00`).toLocaleDateString(loc, { day: 'numeric', month: 'short', year: 'numeric' });
            const tEnd = new Date(`${to}T12:00:00`).toLocaleDateString(loc, { day: 'numeric', month: 'short', year: 'numeric' });
            return `${f} — ${tEnd}`;
        }
        const labels = {
            week: t('wh_dash_period_week'),
            month: t('wh_dash_period_month'),
            year: t('wh_dash_period_year'),
            all: t('wh_dash_period_all'),
        };
        const base = labels[state.period] || state.period;
        const days = data?.chart_days;
        return days ? `${base} · ${days}d` : base;
    }

    function setLoading(on) {
        document.querySelectorAll('.wh-kpi__value, .wh-dash-stat__value').forEach((el) => {
            el.classList.toggle('is-loading', on);
        });
    }

    function destroyCharts() {
        movementChart?.destroy();
        capacityChart?.destroy();
        movementChart = null;
        capacityChart = null;
    }

    function renderHero(kpis, ctx) {
        Object.entries(KPI_MAP).forEach(([id, fn]) => {
            const el = document.getElementById(id);
            if (!el) return;
            const val = fn(kpis, ctx);
            el.textContent = typeof val === 'number' ? String(val) : val;
            el.classList.remove('is-loading');
        });
        renderCurrencyBadge(ctx?.currency, ctx?.is_multi_currency);
    }

    function renderStockAlert(kpis) {
        const alert = document.getElementById('whDashStockAlert');
        const text = document.getElementById('whDashStockAlertText');
        if (!alert) return;

        const low = Number(kpis.low_stock || 0);
        const out = Number(kpis.out_of_stock || 0);
        const expired = Number(kpis.expired_products || 0);
        const total = low + out + expired;
        let msg = '';

        if (total > 0 && text) {
            const raw = t('wh_dash_alert_stock');
            if (raw && raw !== 'wh_dash_alert_stock') {
                msg = raw
                    .replace('%1', String(total))
                    .replace('%2', String(low))
                    .replace('%3', String(out));
                text.textContent = msg;
            }
        } else if (text) {
            text.textContent = '';
        }
        alert.hidden = !(total > 0 && msg.trim());
    }

    function renderMovementChart(data) {
        const ctx = document.getElementById('whMovementChart');
        const empty = document.getElementById('whMovementEmpty');
        if (!ctx || !window.Chart) return;
        movementChart?.destroy();
        const labels = data?.labels || [];
        const incoming = data?.incoming || [];
        const outgoing = data?.outgoing || [];
        const hasData = incoming.some((n) => Number(n) > 0) || outgoing.some((n) => Number(n) > 0);
        if (empty) empty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;
        const c = chartColors();
        movementChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: t('wh_chart_incoming'), data: incoming, borderColor: '#0d9488', backgroundColor: 'rgba(13,148,136,0.08)', fill: true, tension: 0.35 },
                    { label: t('wh_chart_outgoing'), data: outgoing, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.06)', fill: true, tension: 0.35 },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    x: { grid: { color: c.grid }, ticks: { color: c.text, maxRotation: 0 } },
                    y: { grid: { color: c.grid }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderCapacityChart(data) {
        const ctx = document.getElementById('whCapacityChart');
        const empty = document.getElementById('whCapacityEmpty');
        if (!ctx || !window.Chart) return;
        capacityChart?.destroy();
        const labels = data?.labels || [];
        const usage = data?.usage || [];
        const hasData = usage.some((n) => Number(n) > 0);
        if (empty) empty.hidden = hasData;
        ctx.style.display = hasData ? 'block' : 'none';
        if (!hasData) return;
        const c = chartColors();
        capacityChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{ label: t('wh_kpi_capacity'), data: usage, backgroundColor: '#2563eb', borderRadius: 6 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: c.text, maxRotation: 45 } },
                    y: { max: 100, grid: { color: c.grid }, ticks: { color: c.text } },
                },
            },
        });
    }

    function renderWarehouseStatus(items) {
        const root = document.getElementById('whStatusList');
        const meta = document.getElementById('whStatusMeta');
        if (!root) return;
        const list = Array.isArray(items) ? items : [];
        if (meta) meta.textContent = `${list.length} ${t('wh_kpi_total_wh').toLowerCase()}`;
        if (!list.length) {
            root.innerHTML = `<p class="wh-muted">${esc(t('no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<div class="wh-status-list">${list.map((w) => {
            const usage = Number(w.capacity_usage || 0);
            const badge = usage > 90 ? 'danger' : (usage > 75 ? 'warn' : 'ok');
            return `<div class="wh-status-item">
                <div class="wh-status-item__main">
                    <strong>${esc(w.name || '')}</strong>
                    <span class="wh-muted">${esc(w.code || '')} · ${esc(w.type || '')}</span>
                </div>
                <span class="wh-status-item__value">${esc(money(w.stock_value, w.currency))}</span>
                <span class="wh-status-item__currency">${esc(w.currency || '')}</span>
                <span class="wh-status-badge wh-status-badge--${badge}">${usage}%</span>
            </div>`;
        }).join('')}</div>`;
    }

    function renderTasks(tasks, summary) {
        const root = document.getElementById('whTasksList');
        const meta = document.getElementById('whTaskSummary');
        if (meta && summary) {
            meta.textContent = `${summary.due_today || 0} ${t('wh_task_due_today')}`;
        }
        if (!root) return;
        if (!tasks?.length) {
            root.innerHTML = `<p class="wh-muted">${esc(t('no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<ul class="wh-task-list">${tasks.map((task) => `
            <li class="wh-task wh-task--${esc(task.status)}">
                <strong>${esc(task.title)}</strong>
                <span>${esc(task.task_type)} · ${esc(task.status)}</span>
            </li>`).join('')}</ul>`;
    }

    function renderActivity(rows) {
        const root = document.getElementById('whActivityList');
        if (!root) return;
        if (!rows?.length) {
            root.innerHTML = `<p class="wh-muted">${esc(t('no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<ul class="wh-activity-list wh-activity-list--rich">${rows.slice(0, 12).map((r) => `
            <li>
                <span class="material-icons-round">history</span>
                <div>
                    <strong>${esc(actionLabel(r.action))}</strong>
                    <span class="wh-muted">${esc(r.user_name || '')}${r.user_name ? ' · ' : ''}${esc(AdminAPI.formatDate(r.created_at))}</span>
                </div>
            </li>`).join('')}</ul>`;
    }

    function renderNotifications(rows) {
        const root = document.getElementById('whNotifList');
        if (!root) return;
        if (!rows?.length) {
            root.innerHTML = `<p class="wh-muted">${esc(t('no_data'))}</p>`;
            return;
        }
        root.innerHTML = `<ul class="wh-notif-list">${rows.map((n) => `
            <li class="wh-notif wh-notif--${esc(n.severity || 'info')}">
                <strong>${esc(n.title || '')}</strong>
                <p>${esc(n.message || '')}</p>
            </li>`).join('')}</ul>`;
    }

    function exportDashboardCsv() {
        const kpis = state.data?.kpis;
        if (!kpis) return;
        const rows = [
            [t('wh_nav_dashboard'), ''],
            [t('wh_kpi_inventory_value'), formatInventoryValue(kpis, state.data)],
            [t('wh_kpi_receiving'), kpis.today_receiving],
            [t('wh_kpi_dispatch'), kpis.today_dispatch],
            [t('wh_kpi_transfers'), kpis.today_transfers],
            [t('wh_kpi_pending_requests'), kpis.pending_requests],
            [t('wh_kpi_low_stock'), kpis.low_stock],
            [t('wh_kpi_out_stock'), kpis.out_of_stock],
            [t('wh_kpi_damaged'), kpis.damaged_products],
            [t('wh_kpi_expired'), kpis.expired_products],
            [t('wh_kpi_capacity'), pct(kpis.warehouse_capacity)],
        ];
        exportCsv(`warehouse-dashboard-${new Date().toISOString().slice(0, 10)}.csv`, rows);
    }

    async function load() {
        hideError();
        setLoading(true);
        try {
            const query = {
                from: els.dateFrom?.value || '',
                to: els.dateTo?.value || '',
            };
            const res = await AdminAPI.getWarehousePortalDashboard(state.period, query);
            if (res.status !== 'success') throw new Error(res.message);
            const data = res.data || {};
            state.data = data;
            state.currency = data.currency || defaultCurrency();
            if (data.currency && window.WH_CONFIG) {
                window.WH_CONFIG.currency = data.currency;
                if (window.ADMIN_PAGE) window.ADMIN_PAGE.currency = data.currency;
            }
            setMigrationHint(res.module_ready ?? data.module_ready ?? true);

            const scopeParts = [];
            if (window.WH_PAGE?.warehouseName) scopeParts.push(window.WH_PAGE.warehouseName);
            if (window.WH_PAGE?.storeName) scopeParts.push(window.WH_PAGE.storeName);
            if (data.currency && !data.is_multi_currency) scopeParts.push(data.currency);
            const scopeEl = document.getElementById('whHeroScope');
            if (scopeEl) {
                scopeEl.textContent = scopeParts.length ? scopeParts.join(' · ') : t('dash_all_stores');
            }
            const periodEl = document.getElementById('whDashPeriodLabel');
            if (periodEl) periodEl.textContent = periodLabelText(data);

            renderHero(data.kpis || {}, data);
            renderCurrencyBreakdown(data);
            renderStockAlert(data.kpis || {});
            renderMovementChart(data.charts?.movements);
            renderCapacityChart(data.charts?.capacity);
            renderWarehouseStatus(data.warehouse_status);
            renderTasks(data.tasks, data.task_summary);
            renderActivity(data.recent_activities);
            renderNotifications(data.recent_notifications);
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            destroyCharts();
        } finally {
            setLoading(false);
        }
    }

    function setPeriod(period) {
        state.period = period || 'week';
        state.useCustomDates = false;
        const range = periodRange(state.period);
        if (els.dateFrom) els.dateFrom.value = range.from;
        if (els.dateTo) els.dateTo.value = range.to;
        els.periodTabs?.querySelectorAll('.wh-dash-chip').forEach((chip) => {
            const active = chip.dataset.period === state.period;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        load();
    }

    els.periodTabs?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-period]');
        if (!btn) return;
        setPeriod(btn.dataset.period);
    });

    [els.dateFrom, els.dateTo].forEach((input) => {
        input?.addEventListener('change', () => {
            if (els.dateFrom && els.dateTo && els.dateFrom.value > els.dateTo.value) {
                if (input === els.dateFrom) els.dateTo.value = els.dateFrom.value;
                else els.dateFrom.value = els.dateTo.value;
            }
            state.useCustomDates = true;
            els.periodTabs?.querySelectorAll('.wh-dash-chip').forEach((chip) => {
                chip.classList.remove('is-active');
                chip.setAttribute('aria-selected', 'false');
            });
            load();
        });
    });

    document.getElementById('whDashExportBtn')?.addEventListener('click', exportDashboardCsv);
    document.getElementById('whDashRefreshBtn')?.addEventListener('click', load);

    document.addEventListener('wh:refresh', load);
    document.addEventListener('themechange', () => {
        if (state.data) {
            renderMovementChart(state.data.charts?.movements);
            renderCapacityChart(state.data.charts?.capacity);
        }
    });

    load();
});
