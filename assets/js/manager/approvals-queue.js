/**
 * Approval queue — list, approve, reject
 */
(() => {
    const mount = document.querySelector('.mgr-workspace[data-approval-filter]')
        || document.getElementById('approvalsQueueRoot')
        || document.getElementById('approvalsReturnsRoot')
        || document.getElementById('approvalsDiscountsRoot')
        || document.getElementById('approvalsVoidsRoot');

    if (!mount) return;

    const filter = mount.dataset.approvalFilter || null;

    async function load() {
        mount.innerHTML = '<div class="mgr-list mgr-list--loading">Chargement…</div>';
        const res = await ManagerAPI.getApprovals(filter);
        if (res.status !== 'success') {
            mount.innerHTML = `<p class="mgr-empty">${res.message || 'Erreur'}</p>`;
            return;
        }
        const items = res.data || [];
        if (!items.length) {
            mount.innerHTML = '<p class="mgr-empty">Aucune demande en attente</p>';
            return;
        }
        mount.innerHTML = items.map((a) => `
            <article class="mgr-approval-card" data-id="${a.id}">
                <header>
                    <span class="mgr-badge">${esc(a.type)}</span>
                    <time>${ManagerAPI.formatDate(a.created_at)}</time>
                </header>
                <p><strong>${esc(a.requester_name)}</strong> — ${ManagerAPI.formatCurrency(a.amount)}</p>
                <p class="mgr-muted">${esc(a.reason || 'Sans motif')}</p>
                <footer>
                    <button type="button" class="inv-btn inv-btn-outline mgr-reject">Rejeter</button>
                    <button type="button" class="inv-btn inv-btn-primary mgr-approve">Approuver</button>
                </footer>
            </article>`).join('');

        mount.querySelectorAll('.mgr-approve').forEach((btn) => {
            btn.addEventListener('click', () => act(btn, 'approve'));
        });
        mount.querySelectorAll('.mgr-reject').forEach((btn) => {
            btn.addEventListener('click', () => act(btn, 'reject'));
        });
    }

    async function act(btn, kind) {
        const card = btn.closest('.mgr-approval-card');
        const id = card?.dataset.id;
        if (!id) return;
        const note = prompt(kind === 'approve' ? 'Note (optionnel)' : 'Motif du rejet') || '';
        btn.disabled = true;
        const res = kind === 'approve'
            ? await ManagerAPI.approve(id, note)
            : await ManagerAPI.reject(id, note);
        if (res.status === 'success') await load();
        else alert(res.message || 'Erreur');
        btn.disabled = false;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    document.addEventListener('DOMContentLoaded', load);
    document.addEventListener('mgr:refresh', load);
})();
