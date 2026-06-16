/**
 * Cash register create / edit form
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crCreateForm');
    if (!root) return;

    const { t, esc, money, showError, hideError } = CashRegistersUI;
    const mode = root.dataset.mode || 'create';
    const registerId = Number(root.dataset.registerId || 0);
    const storeId = window.ADMIN_PAGE?.storeId;

    async function loadCashiers() {
        try {
            const res = await AdminAPI.getUsers({ role: 'cashier', store_id: storeId });
            return (res.data?.users || res.data || []).filter(Boolean);
        } catch {
            return [];
        }
    }

    function renderForm(data = {}, cashiers = []) {
        const isEdit = mode === 'edit';
        root.innerHTML = `
            <form class="cr-form" id="crRegisterForm">
                <div class="cr-form-grid">
                    <label>${esc(t('cr_register_code'))}
                        <input type="text" name="register_code" value="${esc(data.register_code || '')}" ${isEdit ? 'readonly' : 'required'}>
                    </label>
                    <label>${esc(t('cr_register_name'))}
                        <input type="text" name="name" value="${esc(data.name || '')}" required>
                    </label>
                    <label>${esc(t('cr_assigned_cashier'))}
                        <select name="assigned_user_id">
                            <option value="">—</option>
                            ${cashiers.map((u) => `<option value="${u.id}" ${String(data.assigned_user_id) === String(u.id) ? 'selected' : ''}>${esc(u.name)}</option>`).join('')}
                        </select>
                    </label>
                    <label>${esc(t('cr_opening_balance'))}
                        <input type="number" name="opening_balance" min="0" step="0.01" value="${esc(data.opening_balance ?? 0)}">
                    </label>
                    <label>${esc(t('col_status'))}
                        <select name="status">
                            <option value="active" ${data.status === 'active' ? 'selected' : ''}>${esc(t('cr_status_active'))}</option>
                            <option value="inactive" ${data.status === 'inactive' ? 'selected' : ''}>${esc(t('cr_status_inactive'))}</option>
                            <option value="maintenance" ${data.status === 'maintenance' ? 'selected' : ''}>${esc(t('cr_status_maintenance'))}</option>
                        </select>
                    </label>
                </div>
                <div class="cr-form-actions">
                    <a href="registers.php" class="cr-btn cr-btn--ghost">${esc(t('cancel'))}</a>
                    <button type="submit" class="cr-btn">${esc(t('save'))}</button>
                </div>
            </form>`;

        root.querySelector('#crRegisterForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideError();
            const fd = new FormData(e.target);
            const payload = {
                store_id: storeId,
                register_code: fd.get('register_code'),
                name: fd.get('name'),
                assigned_user_id: fd.get('assigned_user_id') || null,
                opening_balance: parseFloat(fd.get('opening_balance')) || 0,
                status: fd.get('status'),
            };
            const res = isEdit
                ? await AdminAPI.updateCashRegister(registerId, payload)
                : await AdminAPI.createCashRegister(payload);
            if (res.status === 'success') {
                window.location.href = 'register_details.php?id=' + (isEdit ? registerId : res.data?.id);
            } else {
                showError(res.message || t('error'));
            }
        });
    }

    async function init() {
        const cashiers = await loadCashiers();
        if (mode === 'edit' && registerId) {
            const res = await AdminAPI.getCashRegister(registerId);
            if (res.status !== 'success' || !res.data) {
                showError(res.message || t('load_error'));
                return;
            }
            renderForm(res.data, cashiers);
            return;
        }
        renderForm({}, cashiers);
    }

    init();
});
