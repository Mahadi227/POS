document.addEventListener('DOMContentLoaded', () => {

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = WmsUI;

    let movementChart;

    let capacityChart;



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



    function actionLabel(action) {

        if (!action) return '—';

        return t(ACTION_KEYS[action] || action) || action.replace(/_/g, ' ');

    }



    async function load() {

        hideError();

        try {

            const res = await AdminAPI.getWmsDashboard();

            if (res.status !== 'success') throw new Error(res.message);

            const d = res.data || {};

            setMigrationHint(d.module_ready !== false);

            const s = d.summary || {};

            const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };

            set('wmsTotalWh', s.total ?? '—');

            set('wmsActiveWh', s.active ?? '—');

            set('wmsInvValue', money(s.total_value));

            set('wmsProducts', s.total_products ?? '—');

            set('wmsIncoming', s.incoming_shipments ?? '—');

            set('wmsOutgoing', s.outgoing_shipments ?? '—');

            set('wmsPendingTransfers', s.pending_transfers ?? '—');

            set('wmsLowStock', s.low_stock_alerts ?? '—');

            set('wmsDamaged', s.damaged_products ?? '—');

            set('wmsExpired', s.expired_products ?? '—');



            renderStatus(d.warehouse_status || []);

            renderActivity(d.recent_activities || []);

            renderCharts(d.collection_chart, d.capacity_chart);

            document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));

            updateLastUpdated();

        } catch (e) {

            showError(e.message || t('load_error'));

        }

    }



    function renderStatus(items) {

        const root = document.getElementById('wmsStatusList');

        if (!root) return;

        if (!items.length) { root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`; return; }

        root.innerHTML = items.map((w) => `

            <div class="cr-status-item">

                <div><strong>${esc(w.name)}</strong><span class="cr-muted">${esc(w.code)} · ${esc(w.type)}</span></div>

                <span>${esc(money(w.stock_value))}</span>

                <span class="cr-badge cr-badge--${w.capacity_usage > 90 ? 'off' : 'ok'}">${w.capacity_usage}%</span>

            </div>`).join('');

    }



    function renderActivity(items) {

        const root = document.getElementById('wmsActivityList');

        if (!root) return;

        if (!items.length) { root.innerHTML = `<p class="cr-empty">${esc(t('wms_no_data'))}</p>`; return; }

        root.innerHTML = items.map((a) => `

            <div class="cr-activity-item"><span class="material-icons-round">history</span>

            <div><strong>${esc(actionLabel(a.action))}</strong><span class="cr-muted">${esc(a.user_name || '')} · ${esc(AdminAPI.formatDate(a.created_at))}</span></div></div>`).join('');

    }



    function renderCharts(movement, capacity) {

        movementChart?.destroy();

        capacityChart?.destroy();

        const mc = document.getElementById('wmsMovementChart');

        const cc = document.getElementById('wmsCapacityChart');

        if (mc && movement) {

            movementChart = new Chart(mc, {

                type: 'line',

                data: {

                    labels: movement.labels || [],

                    datasets: [

                        { label: t('wms_chart_incoming'), data: movement.incoming || [], borderColor: '#0d9488', tension: 0.3 },

                        { label: t('wms_chart_outgoing'), data: movement.outgoing || [], borderColor: '#dc2626', tension: 0.3 },

                    ],

                },

                options: { responsive: true, plugins: { legend: { position: 'bottom' } } },

            });

        }

        if (cc && capacity) {

            capacityChart = new Chart(cc, {

                type: 'bar',

                data: {

                    labels: capacity.labels || [],

                    datasets: [{ label: t('wms_chart_capacity_pct'), data: capacity.usage || [], backgroundColor: '#2563eb' }],

                },

                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { max: 100 } } },

            });

        }

    }



    load();

    document.addEventListener('wms:refresh', load);

    document.addEventListener('store-switched', load);

});

