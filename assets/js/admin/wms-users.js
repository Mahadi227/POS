document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('wmsUsersRoot');
    if (!root) return;

    const { t, esc, hideError, showError, updateLastUpdated } = WmsUI;
    let allUsers = [];
    let stores = [];

    function setStats(users) {
        const total = users.length;
        const active = users.filter((u) => Number(u.is_active) === 1).length;
        const suspended = total - active;
        const set = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(value);
        };
        set('wmsUsersTotal', total);
        set('wmsUsersActive', active);
        set('wmsUsersSuspended', suspended);
        document.querySelectorAll('.cr-kpi-card').forEach((c) => c.classList.remove('is-loading'));
    }

    function roleLabel(role) {
        const map = {
            admin: t('role_admin'),
            manager: t('role_manager'),
            cashier: t('role_cashier'),
            staff: t('role_staff'),
        };
        return map[role] || role || '—';
    }

    function statusLabel(active) {
        return Number(active) === 1 ? t('user_active') : t('user_suspended');
    }

    function applyFilters() {
        const q = (document.getElementById('wmsUsersSearch')?.value || '').trim().toLowerCase();
        const role = document.getElementById('wmsUsersRole')?.value || '';
        const store = document.getElementById('wmsUsersStore')?.value || '';
        const status = document.getElementById('wmsUsersStatus')?.value || '';

        const filtered = allUsers.filter((u) => {
            const name = String(u.name || '').toLowerCase();
            const email = String(u.email || '').toLowerCase();
            if (q && !name.includes(q) && !email.includes(q)) return false;
            if (role && u.role_slug !== role) return false;
            if (store && Number(u.store_id) !== Number(store)) return false;
            if (status === 'active' && Number(u.is_active) !== 1) return false;
            if (status === 'suspended' && Number(u.is_active) === 1) return false;
            return true;
        });

        renderTable(filtered);
    }

    function renderTable(users) {
        if (!users.length) {
            root.innerHTML = `<p class="cr-empty">${esc(t('no_users'))}</p>`;
            return;
        }

        root.innerHTML = `<div class="cr-table-wrap"><table class="modern-table"><thead><tr>
            <th>${esc(t('col_user'))}</th>
            <th>${esc(t('col_role'))}</th>
            <th>${esc(t('col_store'))}</th>
            <th>${esc(t('col_status'))}</th>
            <th>${esc(t('col_last_login'))}</th>
        </tr></thead><tbody>${users.map((u) => {
            const store = stores.find((s) => Number(s.id) === Number(u.store_id));
            const displayStore = store?.name || '—';
            const lastLogin = u.last_login_at ? AdminAPI.formatDate(u.last_login_at) : '—';
            return `<tr>
                <td><strong>${esc(u.name || '—')}</strong><div class="cr-muted">${esc(u.email || '')}</div></td>
                <td>${esc(roleLabel(u.role_slug))}</td>
                <td>${esc(displayStore)}</td>
                <td><span class="cr-badge cr-badge--${Number(u.is_active) === 1 ? 'ok' : 'off'}">${esc(statusLabel(u.is_active))}</span></td>
                <td>${esc(lastLogin)}</td>
            </tr>`;
        }).join('')}</tbody></table></div>`;
    }

    async function loadStores() {
        const res = await AdminAPI.listStores();
        stores = res.status === 'success' ? (res.data || []) : [];
        const sel = document.getElementById('wmsUsersStore');
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = `<option value="">${esc(t('filter_all_stores'))}</option>` +
            stores.map((s) => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
        if (current) sel.value = current;
    }

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        try {
            await loadStores();
            const query = {};
            const role = document.getElementById('wmsUsersRole')?.value;
            const store = document.getElementById('wmsUsersStore')?.value;
            const status = document.getElementById('wmsUsersStatus')?.value;
            if (role) query.role = role;
            if (store) query.store_id = store;
            if (status) query.status = status;

            const res = await AdminAPI.getUsers(query);
            if (res.status !== 'success') throw new Error(res.message || t('load_error'));

            allUsers = (res.data || []).filter((u) => u.role_slug !== 'super_admin');
            setStats(allUsers);
            applyFilters();
            updateLastUpdated();
        } catch (e) {
            const message = e?.message || t('load_error');
            showError(message);
            root.innerHTML = `<p class="cr-empty">${esc(message)}</p>`;
        }
    }

    document.getElementById('wmsUsersRefresh')?.addEventListener('click', load);
    ['wmsUsersSearch', 'wmsUsersRole', 'wmsUsersStore', 'wmsUsersStatus'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', applyFilters);
        document.getElementById(id)?.addEventListener('change', applyFilters);
    });

    document.addEventListener('wms:refresh', load);
    load();
});
