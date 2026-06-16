/**
 * Sélecteur de succursale — header admin (multi-store).
 */
(() => {
    const WRAP_ID = 'adminStoreSwitcher';

    async function init() {
        if (document.getElementById(WRAP_ID)) return;

        const slot = document.getElementById('headerStoreSlot');
        const headerRight = document.querySelector('.top-header .header-right');
        const mount = slot || headerRight;
        if (!mount) return;

        const wrap = document.createElement('div');
        wrap.id = WRAP_ID;
        wrap.className = 'ms-store-switcher';
        wrap.innerHTML = `
            <span class="material-icons-round">store</span>
            <select id="storeSwitcherSelect" aria-label="Succursale active" title="Changer de succursale">
                <option value="">Chargement…</option>
            </select>`;
        if (slot) {
            slot.appendChild(wrap);
        } else {
            mount.insertBefore(wrap, mount.firstChild);
        }

        const select = document.getElementById('storeSwitcherSelect');
        const [ctxRes, listRes] = await Promise.all([
            AdminAPI.getStoreContext(),
            AdminAPI.listStores(),
        ]);

        if (ctxRes.status !== 'success' || listRes.status !== 'success') return;

        const ctx = ctxRes.data;
        const stores = listRes.data || [];
        select.innerHTML = '';

        if (ctx.is_super_admin) {
            const optAll = document.createElement('option');
            optAll.value = '';
            optAll.textContent = 'Toutes les succursales';
            if (ctx.is_global_view) optAll.selected = true;
            select.appendChild(optAll);
        }

        stores.forEach((s) => {
            const o = document.createElement('option');
            o.value = String(s.id);
            o.textContent = s.code ? `${s.name} (${s.code})` : s.name;
            if (ctx.active_store_id === s.id) o.selected = true;
            select.appendChild(o);
        });

        select.addEventListener('change', async () => {
            const val = select.value;
            const payload = { store_id: val === '' ? null : parseInt(val, 10) };
            select.disabled = true;
            const res = await AdminAPI.switchStore(payload);
            select.disabled = false;
            if (res.status === 'success') {
                window.location.reload();
            } else {
                alert(res.message || 'Erreur changement succursale');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
