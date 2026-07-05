(function () {
    'use strict';

    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    const STATUS_KEYS = ['trial', 'active', 'suspended', 'cancelled'];

    function setKpiLoading(loading) {
        document.querySelectorAll('#platKpiGrid .plat-kpi-card').forEach((card) => {
            card.classList.toggle('is-loading', loading);
        });
    }

    function showError(msg) {
        const banner = document.getElementById('platDashError');
        const text = document.getElementById('platDashErrorText');
        if (!banner || !text) return;
        text.textContent = msg || t('plat_dash_load_error');
        banner.hidden = false;
    }

    function hideError() {
        const banner = document.getElementById('platDashError');
        if (banner) banner.hidden = true;
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value ?? '0';
    }

    function setBar(id, pct) {
        const el = document.getElementById(id);
        if (el) el.style.width = `${Math.max(0, Math.min(100, pct))}%`;
    }

    function renderStatusBreakdown(tenants) {
        const total = Math.max(1, Number(tenants.total) || 0);
        const map = {
            trial: 'platStatTrial',
            active: 'platStatActive',
            suspended: 'platStatSuspended',
            cancelled: 'platStatCancelled',
        };
        const bars = {
            trial: 'platBarTrial',
            active: 'platBarActive',
            suspended: 'platBarSuspended',
            cancelled: 'platBarCancelled',
        };

        STATUS_KEYS.forEach((status) => {
            const count = Number(tenants[status]) || 0;
            setText(map[status], String(count));
            setBar(bars[status], (count / total) * 100);
        });
    }

    async function loadDashboard() {
        hideError();
        setKpiLoading(true);

        try {
            const res = await apiGet('dashboard');
            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || t('plat_dash_load_error'));
            }

            const d = res.data;
            const tenants = d.tenants || {};

            setText('platKpiTenants', tenants.total ?? '0');
            setText('platKpiActive', tenants.active ?? '0');
            setText('platKpiStores', d.stores_total ?? '0');
            setText('platKpiUsers', d.users_total ?? '0');
            renderStatusBreakdown(tenants);
            setText('platSchemaVersion', d.schema_version ?? '—');
            setLastUpdated();
        } catch (e) {
            console.error(e);
            showError(e.message || t('load_error'));
        } finally {
            setKpiLoading(false);
        }
    }

    document.addEventListener('DOMContentLoaded', loadDashboard);
    document.addEventListener('plat:refresh', loadDashboard);
})();
