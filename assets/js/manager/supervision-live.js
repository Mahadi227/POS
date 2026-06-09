/**
 * Live registers supervision page
 */
(() => {
    const root = document.getElementById('liveRegistersRoot');
    if (!root) return;

    async function load() {
        root.innerHTML = '<div class="mgr-list mgr-list--loading">Chargement…</div>';
        const res = await ManagerAPI.getLiveRegisters();
        if (res.status !== 'success') {
            root.innerHTML = `<p class="mgr-empty">${res.message || 'Erreur'}</p>`;
            return;
        }
        const items = res.data || [];
        if (!items.length) {
            root.innerHTML = '<p class="mgr-empty">Aucun terminal actif</p>';
            return;
        }
        root.innerHTML = `<div class="mgr-table-wrap"><table class="modern-table"><thead><tr>
            <th>Caissier</th><th>Source</th><th>Statut</th><th>Dernière activité</th>
        </tr></thead><tbody>${items.map((r) => `<tr>
            <td><strong>${esc(r.cashier_name)}</strong></td>
            <td>${esc(r.source || '—')}</td>
            <td><span class="mgr-badge ${r.online ? 'mgr-badge--ok' : 'mgr-badge--off'}">${r.online ? 'En ligne' : 'Hors ligne'}</span></td>
            <td>${ManagerAPI.formatDate(r.last_seen || r.opened_at)}</td>
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
