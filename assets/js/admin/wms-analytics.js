document.addEventListener('DOMContentLoaded', () => {
    const periodEl = document.getElementById('wmsPeriod');
    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = WmsUI;
    let charts = [];

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

    function typeLabel(type) {
        return t(TYPE_KEYS[type] || type) || type || '—';
    }

    function setKpis(summary = {}) {
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('wmsAnMovTotal', summary.movements ?? '—');
        set('wmsAnMovIn', summary.stock_in ?? '—');
        set('wmsAnMovOut', summary.stock_out ?? '—');
        set('wmsAnMovValue', money(summary.movement_value));
        set('wmsAnInvValue', money(summary.total_value));
        set('wmsAnExpiring', summary.expiring_soon ?? '—');
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function destroyCharts() {
        charts.forEach((c) => c.destroy());
        charts = [];
    }

    function mkChart(id, type, labels, datasets, opts = {}) {
        const el = document.getElementById(id);
        if (!el) return;
        charts.push(new Chart(el, {
            type,
            data: { labels: labels || [], datasets },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } }, ...opts },
        }));
    }

    async function load() {
        hideError();
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.add('is-loading'));
        try {
            const res = await AdminAPI.getWmsAnalytics(periodEl?.value || 'month');
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));
            const d = res.data || {};
            setMigrationHint(d.module_ready !== false);
            setKpis(d.summary || {});
            destroyCharts();

            const trendLabels = (d.inventory_trends?.labels || []).map(typeLabel);
            mkChart('wmsTrendChart', 'bar', trendLabels, [{
                data: d.inventory_trends?.values || [],
                backgroundColor: '#0d9488',
            }], { plugins: { legend: { display: false } } });

            mkChart('wmsCompareChart', 'bar', d.warehouse_comparison?.labels || [], [{
                data: d.warehouse_comparison?.values || [],
                backgroundColor: '#2563eb',
            }], { plugins: { legend: { display: false } } });

            mkChart('wmsTopChart', 'doughnut', d.top_moving?.labels || [], [{
                data: d.top_moving?.values || [],
                backgroundColor: ['#2563eb', '#0d9488', '#d97706', '#7c3aed', '#dc2626', '#059669', '#ea580c', '#4f46e5'],
            }]);

            mkChart('wmsExpiryChart', 'line', d.expiry_trends?.labels || [], [{
                data: d.expiry_trends?.values || [],
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220,38,38,0.1)',
                fill: true,
                tension: 0.3,
            }], { plugins: { legend: { display: false } } });

            updateLastUpdated();
        } catch (e) {
            destroyCharts();
            showError(e.message || t('load_error'));
        }
    }

    periodEl?.addEventListener('change', load);
    document.getElementById('wmsAnalyticsRefresh')?.addEventListener('click', load);
    document.addEventListener('wms:refresh', load);
    document.addEventListener('store-switched', load);
    load();
});
