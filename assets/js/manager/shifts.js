/**
 * Shift management page
 */
(() => {
    const root = document.getElementById('shiftsRoot');
    if (!root) return;

    async function load() {
        root.innerHTML = '<div class="mgr-list mgr-list--loading">Chargement…</div>';
        const res = await ManagerAPI.getShifts();
        if (res.status !== 'success') {
            root.innerHTML = `<p class="mgr-empty">${res.message || 'Erreur'}</p>`;
            return;
        }
        const items = res.data || [];
        if (!items.length) {
            root.innerHTML = '<p class="mgr-empty">Aucun shift ouvert — exécutez la migration 005 pour activer les shifts.</p>';
            return;
        }
        root.innerHTML = `<div class="mgr-table-wrap"><table class="modern-table"><thead><tr>
            <th>Caissier</th><th>Ouverture</th><th>Fond caisse</th><th>Ventes</th><th>Statut</th>
        </tr></thead><tbody>${items.map((s) => `<tr>
            <td>${esc(s.cashier_name)}</td>
            <td>${ManagerAPI.formatDate(s.opened_at)}</td>
            <td>${ManagerAPI.formatCurrency(s.opening_float)}</td>
            <td>${ManagerAPI.formatCurrency(s.total_sales)}</td>
            <td><span class="mgr-badge mgr-badge--ok">${esc(s.status)}</span></td>
        </tr>`).join('')}</tbody></table></div>`;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', load);
    document.addEventListener('mgr:refresh', load);
})();
