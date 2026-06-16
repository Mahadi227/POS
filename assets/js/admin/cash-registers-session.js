/**
 * Open / close cash register session workflows
 */
document.addEventListener('DOMContentLoaded', () => {
    const openRoot = document.getElementById('crOpenSessionRoot');
    const closeRoot = document.getElementById('crCloseSessionRoot');
    const { t, esc, money, showError, hideError } = CashRegistersUI;

    async function renderOpen() {
        if (!openRoot) return;
        hideError();
        try {
            const res = await AdminAPI.getCashRegisters('active');
            const items = Array.isArray(res.data) ? res.data : (res.data?.items || []);
            const available = items.filter((r) => r.session_status !== 'open');
            if (!available.length) {
                openRoot.innerHTML = `<p class="cr-empty">${esc(t('cr_no_registers'))}</p>`;
                return;
            }
            openRoot.innerHTML = `
                <form class="cr-form" id="crOpenForm">
                    <label>${esc(t('cr_register_name'))}
                        <select name="register_id" required>
                            ${available.map((r) => `<option value="${r.id}">${esc(r.name)} (${esc(r.register_code)})</option>`).join('')}
                        </select>
                    </label>
                    <label>${esc(t('cr_opening_balance'))}
                        <input type="number" name="opening_balance" min="0" step="0.01" value="0" required>
                    </label>
                    <label>Shift
                        <select name="shift_type">
                            <option value="morning">${esc(t('cr_shift_morning'))}</option>
                            <option value="afternoon">${esc(t('cr_shift_afternoon'))}</option>
                            <option value="evening">${esc(t('cr_shift_evening'))}</option>
                            <option value="night">${esc(t('cr_shift_night'))}</option>
                        </select>
                    </label>
                    <div class="cr-form-actions">
                        <a href="registers.php" class="cr-btn cr-btn--ghost">${esc(t('cancel'))}</a>
                        <button type="submit" class="cr-btn">${esc(t('cr_open_register'))}</button>
                    </div>
                </form>`;
            openRoot.querySelector('#crOpenForm')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const fd = new FormData(e.target);
                const result = await AdminAPI.openCashRegisterSession(Number(fd.get('register_id')), {
                    opening_balance: parseFloat(fd.get('opening_balance')) || 0,
                    shift_type: fd.get('shift_type'),
                });
                if (result.status === 'success') window.location.href = 'registers.php';
                else showError(result.message || t('error'));
            });
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    async function renderClose() {
        if (!closeRoot) return;
        hideError();
        try {
            const res = await AdminAPI.getCashRegisters();
            const items = Array.isArray(res.data) ? res.data : (res.data?.items || []);
            const open = items.filter((r) => r.session_status === 'open' && r.open_session_id);
            if (!open.length) {
                closeRoot.innerHTML = `<p class="cr-empty">${esc(t('cr_no_open_sessions'))}</p>`;
                return;
            }
            closeRoot.innerHTML = `
                <form class="cr-form" id="crCloseForm">
                    <label>${esc(t('cr_register_name'))}
                        <select name="session_id" id="crCloseRegisterSelect" required>
                            ${open.map((r) => `<option value="${r.open_session_id}" data-balance="${r.current_balance || 0}">${esc(r.name)}</option>`).join('')}
                        </select>
                    </label>
                    <label>${esc(t('cr_counted_cash'))}
                        <input type="number" name="counted_cash" id="crCountedCash" min="0" step="0.01" required>
                    </label>
                    <div class="cr-form-actions">
                        <a href="registers.php" class="cr-btn cr-btn--ghost">${esc(t('cancel'))}</a>
                        <button type="submit" class="cr-btn cr-btn--warn">${esc(t('cr_close_register'))}</button>
                    </div>
                </form>`;
            const sel = closeRoot.querySelector('#crCloseRegisterSelect');
            const counted = closeRoot.querySelector('#crCountedCash');
            if (sel && counted) {
                counted.value = sel.selectedOptions[0]?.dataset.balance || '0';
                sel.addEventListener('change', () => {
                    counted.value = sel.selectedOptions[0]?.dataset.balance || '0';
                });
            }
            closeRoot.querySelector('#crCloseForm')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const fd = new FormData(e.target);
                const result = await AdminAPI.closeCashSession(Number(fd.get('session_id')), {
                    counted_cash: parseFloat(fd.get('counted_cash')) || 0,
                });
                if (result.status === 'success') window.location.href = 'reconciliation.php';
                else showError(result.message || t('error'));
            });
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    renderOpen();
    renderClose();
});
