(function () {
    'use strict';

    const { apiGet, t, setLastUpdated } = window.PlatformAPI || {};

    async function loadDashboard() {
        try {
            const res = await apiGet('dashboard');
            if (res.status !== 'success' || !res.data) {
                return;
            }
            const d = res.data;
            const tenants = d.tenants || {};
            document.getElementById('platKpiTenants').textContent = tenants.total ?? '0';
            document.getElementById('platKpiActive').textContent = tenants.active ?? '0';
            document.getElementById('platKpiStores').textContent = d.stores_total ?? '0';
            document.getElementById('platKpiUsers').textContent = d.users_total ?? '0';
            document.getElementById('platStatTrial').textContent = tenants.trial ?? '0';
            document.getElementById('platStatActive').textContent = tenants.active ?? '0';
            document.getElementById('platStatSuspended').textContent = tenants.suspended ?? '0';
            document.getElementById('platStatCancelled').textContent = tenants.cancelled ?? '0';
            document.getElementById('platSchemaVersion').textContent = d.schema_version ?? '—';
            setLastUpdated();
        } catch (e) {
            console.error(e);
        }
    }

    document.addEventListener('DOMContentLoaded', loadDashboard);
    document.addEventListener('plat:refresh', loadDashboard);
})();
