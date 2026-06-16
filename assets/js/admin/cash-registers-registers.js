/**
 * Cash registers — list & CRUD
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crRegistersRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = CashRegistersUI;
    const canManage = window.ADMIN_PAGE?.canManage;

    async function load() {
        hideError();
        root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
        try {
            const res = await AdminAPI.getCashRegisters();
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.data?.module_ready ?? true);
            const items = Array.isArray(res.data) ? res.data : (res.data?.items || res.data || []);
            if (!items.length) {
                root.innerHTML = `<p class="cr-empty">${esc(t('cr_no_registers'))}</p>`;
                return;
            }
            root.innerHTML = `
                <div class="cr-table-wrap">
                    <table class="modern-table">
                        <thead><tr>
                            <th>${esc(t('cr_register_code'))}</th>
                            <th>${esc(t('cr_register_name'))}</th>
                            <th>${esc(t('cr_branch'))}</th>
                            <th>${esc(t('cr_assigned_cashier'))}</th>
                            <th>${esc(t('col_status'))}</th>
                            <th>${esc(t('cr_opening_balance'))}</th>
                            <th>${esc(t('cr_stat_cash_balance'))}</th>
                            <th></th>
                        </tr></thead>
                        <tbody>
                            ${items.map((r) => `
                                <tr>
                                    <td><strong>${esc(r.register_code)}</strong></td>
                                    <td>${esc(r.name)}</td>
                                    <td>${esc(r.store_name)}</td>
                                    <td>${esc(r.assigned_cashier || '—')}</td>
                                    <td><span class="cr-badge cr-badge--${r.session_status === 'open' ? 'ok' : 'idle'}">${esc(r.session_status === 'open' ? t('cr_session_open') : t('cr_session_closed'))}</span></td>
                                    <td>${esc(money(r.opening_balance))}</td>
                                    <td>${esc(money(r.current_balance))}</td>
                                    <td class="cr-actions">
                                        <a href="register_details.php?id=${r.id}" class="cr-btn cr-btn--ghost">${esc(t('view_all'))}</a>
                                        ${canManage ? `<button type="button" class="cr-btn" data-open="${r.id}" ${r.session_status === 'open' ? 'disabled' : ''}>${esc(t('cr_open_register'))}</button>` : ''}
                                        ${canManage && r.session_status === 'open' ? `<button type="button" class="cr-btn cr-btn--warn" data-close-session="${r.id}">${esc(t('cr_close_register'))}</button>` : ''}
                                    </td>
                                </tr>`).join('')}
                        </tbody>
                    </table>
                </div>`;

            root.querySelectorAll('[data-open]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const val = prompt(t('cr_opening_balance'), '0');
                    if (val === null) return;
                    const res = await AdminAPI.openCashRegisterSession(Number(btn.dataset.open), {
                        opening_balance: parseFloat(val) || 0,
                        shift_type: 'morning',
                    });
                    if (res.status === 'success') load();
                    else alert(res.message || t('error'));
                });
            });

            root.querySelectorAll('[data-close-session]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const registerId = Number(btn.dataset.closeSession);
                    const row = items.find((r) => r.id === registerId);
                    const sessionId = row?.open_session_id;
                    if (!sessionId) {
                        alert(t('error'));
                        return;
                    }
                    const hint = row ? `${t('cr_stat_expected')}: ${money((row.opening_balance || 0) + (row.cash_sales || 0))}` : '';
                    const val = prompt(`${t('cr_counted_cash')}\n${hint}`, '0');
                    if (val === null) return;
                    const res = await AdminAPI.closeCashSession(sessionId, {
                        counted_cash: parseFloat(val) || 0,
                    });
                    if (res.status === 'success') load();
                    else alert(res.message || t('error'));
                });
            });
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            root.innerHTML = `<p class="cr-empty">${esc(e.message)}</p>`;
        }
    }

    load();
    document.addEventListener('cr:refresh', load);
});
