document.addEventListener('DOMContentLoaded', () => {

    const root = document.getElementById('wmsWhForm');

    if (!root) return;

    const { t, esc, showError } = WmsUI;

    const mode = root.dataset.mode;

    const whId = Number(root.dataset.warehouseId || 0);



    const types = ['central', 'regional', 'store', 'distribution', 'cold_storage', 'temporary'];

    const TYPE_KEYS = {

        central: 'wms_wh_type_central',

        regional: 'wms_wh_type_regional',

        store: 'wms_wh_type_store',

        distribution: 'wms_wh_type_distribution',

        cold_storage: 'wms_wh_type_cold_storage',

        temporary: 'wms_wh_type_temporary',

    };



    function typeLabel(tp) {

        return t(TYPE_KEYS[tp] || tp) || tp;

    }



    function renderForm(data = {}) {

        root.innerHTML = `<form class="cr-form" id="whForm">

            <div class="cr-form-grid">

                <label>${esc(t('wms_wh_code'))}<input name="warehouse_code" value="${esc(data.warehouse_code || '')}" ${mode === 'edit' ? 'readonly' : 'required'}></label>

                <label>${esc(t('wms_wh_name'))}<input name="name" value="${esc(data.name || '')}" required></label>

                <label>${esc(t('wms_wh_type'))}<select name="warehouse_type">${types.map((tp) => `<option value="${tp}" ${data.warehouse_type === tp ? 'selected' : ''}>${esc(typeLabel(tp))}</option>`).join('')}</select></label>

                <label>${esc(t('wms_wh_city'))}<input name="city" value="${esc(data.city || '')}"></label>

                <label>${esc(t('wms_wh_capacity'))}<input type="number" name="capacity_units" min="0" value="${esc(data.capacity_units ?? 0)}"></label>

                <label>${esc(t('col_status'))}<select name="status">

                    <option value="active">${esc(t('wms_status_active'))}</option>

                    <option value="inactive">${esc(t('wms_status_inactive'))}</option>

                </select></label>

            </div>

            <label>${esc(t('wms_wh_address'))}<textarea name="address">${esc(data.address || '')}</textarea></label>

            <div class="cr-form-actions"><a href="warehouses.php" class="cr-btn cr-btn--ghost">${esc(t('cancel'))}</a><button type="submit" class="cr-btn">${esc(t('save'))}</button></div>

        </form>`;

        if (data.status) root.querySelector('[name=status]').value = data.status;

        root.querySelector('#whForm').addEventListener('submit', async (e) => {

            e.preventDefault();

            const fd = new FormData(e.target);

            const payload = Object.fromEntries(fd.entries());

            payload.store_id = window.ADMIN_PAGE?.storeId;

            payload.capacity_units = parseInt(payload.capacity_units, 10) || 0;

            const res = mode === 'edit'

                ? await AdminAPI.updateWmsWarehouse(whId, payload)

                : await AdminAPI.createWmsWarehouse(payload);

            if (res.status === 'success') window.location.href = 'warehouses.php';

            else showError(res.message || t('error'));

        });

    }



    async function init() {

        if (mode === 'edit' && whId) {

            const res = await AdminAPI.getWmsWarehouse(whId);

            if (res.status === 'success' && res.data) renderForm(res.data);

            else showError(res.message || t('load_error'));

            return;

        }

        renderForm();

    }

    init();

});

