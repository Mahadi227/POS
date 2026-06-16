/**
 * Cash register detail page
 */
document.addEventListener('DOMContentLoaded', async () => {
    const root = document.getElementById('crDetailRoot');
    if (!root) return;

    const id = Number(root.dataset.registerId || 0);
    const { t, esc, money, showError } = CashRegistersUI;
    const canManage = window.ADMIN_PAGE?.canManage;

    if (!id) {
        root.innerHTML = `<p class="cr-empty">${esc(t('load_error'))}</p>`;
        return;
    }

    try {
        const res = await AdminAPI.getCashRegister(id);
        if (res.status !== 'success' || !res.data) {
            root.innerHTML = `<p class="cr-empty">${esc(res.message || t('load_error'))}</p>`;
            return;
        }
        const r = res.data;
        const sessions = r.sessions || [];
        const movements = r.movements || [];

        root.innerHTML = `
            <div class="cr-detail-header">
                <div>
                    <h2>${esc(r.name)}</h2>
                    <p class="cr-muted">${esc(r.register_code)} · ${esc(r.store_name)}</p>
                </div>
                <div class="cr-actions">
                    <a href="registers.php" class="cr-btn cr-btn--ghost">← ${esc(t('cr_nav_registers'))}</a>
                    ${canManage ? `<a href="edit_register.php?id=${r.id}" class="cr-btn">${esc(t('cr_edit_register'))}</a>` : ''}
                    ${canManage && r.session_status !== 'open' ? `<a href="open_register.php" class="cr-btn">${esc(t('cr_open_register'))}</a>` : ''}
                    ${canManage && r.session_status === 'open' ? `<a href="close_register.php" class="cr-btn cr-btn--warn">${esc(t('cr_close_register'))}</a>` : ''}
                </div>
            </div>
            <div class="cr-kpi-grid cr-kpi-grid--compact">
                <div class="cr-kpi"><span>${esc(t('col_status'))}</span><strong>${esc(r.status)}</strong></div>
                <div class="cr-kpi"><span>${esc(t('cr_assigned_cashier'))}</span><strong>${esc(r.assigned_cashier || '—')}</strong></div>
                <div class="cr-kpi"><span>${esc(t('cr_stat_cash_balance'))}</span><strong>${esc(money(r.current_balance))}</strong></div>
                <div class="cr-kpi"><span>Session</span><strong>${esc(r.session_status === 'open' ? t('cr_session_open') : t('cr_session_closed'))}</strong></div>
            </div>
            <h3>${esc(t('cr_shifts_title'))}</h3>
            <div class="cr-table-wrap">${sessions.length ? `
                <table class="modern-table"><thead><tr>
                    <th>${esc(t('col_date'))}</th><th>${esc(t('cr_col_cashier'))}</th><th>Shift</th>
                    <th>${esc(t('cr_opening_balance'))}</th><th>${esc(t('cr_col_expected'))}</th><th>${esc(t('cr_col_difference'))}</th><th>${esc(t('col_status'))}</th>
                </tr></thead><tbody>
                ${sessions.map((s) => `<tr>
                    <td>${esc(AdminAPI.formatDate(s.opened_at))}</td>
                    <td>${esc(s.cashier_name)}</td>
                    <td>${esc(s.shift_type)}</td>
                    <td>${esc(money(s.opening_balance))}</td>
                    <td>${esc(money(s.expected_cash))}</td>
                    <td>${esc(money(s.variance))}</td>
                    <td>${esc(s.status)}</td>
                </tr>`).join('')}
                </tbody></table>` : `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`}
            </div>
            <h3>${esc(t('cr_movements_title'))}</h3>
            <div class="cr-table-wrap">${movements.length ? `
                <table class="modern-table"><thead><tr>
                    <th>${esc(t('col_date'))}</th><th>${esc(t('cr_col_action'))}</th><th>${esc(t('cr_amount'))}</th>
                </tr></thead><tbody>
                ${movements.map((m) => `<tr>
                    <td>${esc(AdminAPI.formatDate(m.created_at))}</td>
                    <td>${esc(m.movement_type)}</td>
                    <td>${esc(money(m.amount))}</td>
                </tr>`).join('')}
                </tbody></table>` : `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`}
            </div>`;
    } catch (e) {
        showError(e.message || t('load_error'));
        root.innerHTML = `<p class="cr-empty">${esc(e.message)}</p>`;
    }
});
