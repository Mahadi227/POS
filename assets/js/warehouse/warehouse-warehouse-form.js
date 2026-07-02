/**
 * Warehouse portal — create / edit warehouse form
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('whWhFormRoot');
    if (!root) return;

    const { t, esc, showError, hideError } = WarehouseUI;
    const mode = root.dataset.mode || 'create';
    const whId = Number(root.dataset.warehouseId || 0);
    const page = window.WH_PAGE || {};
    const isEdit = mode === 'edit' && whId > 0;
    const isGlobal = !!page.isGlobalView;

    const TYPES = ['central', 'regional', 'store', 'distribution', 'cold_storage', 'temporary'];
    const TYPE_KEYS = {
        central: 'wms_wh_type_central',
        regional: 'wms_wh_type_regional',
        store: 'wms_wh_type_store',
        distribution: 'wms_wh_type_distribution',
        cold_storage: 'wms_wh_type_cold_storage',
        temporary: 'wms_wh_type_temporary',
    };

    let stores = [];
    let managers = [];

    function typeLabel(tp) {
        return t(TYPE_KEYS[tp] || tp) || tp;
    }

    function suggestCode(name) {
        const cleaned = String(name || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, '-')
            .replace(/^-|-$/g, '')
            .slice(0, 12);
        const base = cleaned || 'WH';
        const suffix = Date.now().toString(36).slice(-3).toUpperCase();
        return `${base}-${suffix}`;
    }

    function storeOptions(selectedId) {
        const sel = Number(selectedId || 0);
        const opts = [`<option value="">${esc(t('wms_select_store'))}</option>`];
        stores.forEach((s) => {
            const id = Number(s.id);
            opts.push(`<option value="${id}"${id === sel ? ' selected' : ''}>${esc(s.name || `#${id}`)}</option>`);
        });
        return opts.join('');
    }

    function managerOptions(selectedId) {
        const sel = Number(selectedId || 0);
        const opts = [`<option value="">${esc(t('wh_wh_form_manager_none'))}</option>`];
        managers.forEach((u) => {
            const id = Number(u.id);
            const label = u.name || u.email || `#${id}`;
            opts.push(`<option value="${id}"${id === sel ? ' selected' : ''}>${esc(label)}</option>`);
        });
        return opts.join('');
    }

    function typeOptions(selected) {
        return TYPES.map((tp) => {
            const sel = selected === tp ? ' selected' : '';
            return `<option value="${tp}"${sel}>${esc(typeLabel(tp))}</option>`;
        }).join('');
    }

    function renderForm(data = {}) {
        const storeLocked = !isGlobal && page.storeId;
        const defaultStoreId = data.store_id || page.storeId || '';

        root.innerHTML = `
<form class="wh-wh-form" id="whWhForm" novalidate>
    <section class="wh-wh-form-section">
        <header class="wh-wh-form-section__head">
            <span class="material-icons-round" aria-hidden="true">badge</span>
            <h3>${esc(t('wh_wh_form_section_identity'))}</h3>
        </header>
        <div class="wh-wh-form-grid">
            <label class="wh-wh-field wh-wh-field--code">
                <span class="wh-wh-field__label">${esc(t('wms_wh_code'))}</span>
                <div class="wh-wh-field__row">
                    <input class="wh-input" name="warehouse_code" value="${esc(data.warehouse_code || '')}" ${isEdit ? 'readonly' : 'required'} maxlength="32" autocomplete="off">
                    ${isEdit ? '' : `<button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" id="whWhGenCode">${esc(t('wh_wh_form_generate_code'))}</button>`}
                </div>
                ${isEdit ? '' : `<span class="wh-wh-field__hint">${esc(t('wh_wh_form_code_hint'))}</span>`}
            </label>
            <label class="wh-wh-field">
                <span class="wh-wh-field__label">${esc(t('wms_wh_name'))}</span>
                <input class="wh-input" name="name" value="${esc(data.name || '')}" required maxlength="120">
            </label>
            <label class="wh-wh-field">
                <span class="wh-wh-field__label">${esc(t('wms_wh_type'))}</span>
                <select class="wh-select" name="warehouse_type">${typeOptions(data.warehouse_type || 'central')}</select>
            </label>
            <label class="wh-wh-field">
                <span class="wh-wh-field__label">${esc(t('col_status'))}</span>
                <select class="wh-select" name="status">
                    <option value="active"${data.status === 'active' || !data.status ? ' selected' : ''}>${esc(t('wms_status_active'))}</option>
                    <option value="inactive"${data.status === 'inactive' ? ' selected' : ''}>${esc(t('wms_status_inactive'))}</option>
                </select>
            </label>
        </div>
    </section>

    <section class="wh-wh-form-section">
        <header class="wh-wh-form-section__head">
            <span class="material-icons-round" aria-hidden="true">store</span>
            <h3>${esc(t('wh_wh_form_section_branch'))}</h3>
        </header>
        <div class="wh-wh-form-grid wh-wh-form-grid--narrow">
            <label class="wh-wh-field">
                <span class="wh-wh-field__label">${esc(t('wms_col_store'))}</span>
                <select class="wh-select" name="store_id" ${storeLocked ? 'disabled' : ''}>${storeOptions(defaultStoreId)}</select>
                ${storeLocked ? `<input type="hidden" name="store_id" value="${Number(page.storeId)}">` : ''}
            </label>
            <label class="wh-wh-field">
                <span class="wh-wh-field__label">${esc(t('wms_wh_manager'))}</span>
                <select class="wh-select" name="manager_id">${managerOptions(data.manager_id)}</select>
            </label>
        </div>
    </section>

    <section class="wh-wh-form-section">
        <header class="wh-wh-form-section__head">
            <span class="material-icons-round" aria-hidden="true">location_on</span>
            <h3>${esc(t('wh_wh_form_section_location'))}</h3>
        </header>
        <div class="wh-wh-form-grid">
            <label class="wh-wh-field wh-wh-field--full">
                <span class="wh-wh-field__label">${esc(t('wms_wh_address'))}</span>
                <textarea class="wh-input wh-wh-textarea" name="address" rows="2">${esc(data.address || '')}</textarea>
            </label>
            <label class="wh-wh-field">
                <span class="wh-wh-field__label">${esc(t('wms_wh_city'))}</span>
                <input class="wh-input" name="city" value="${esc(data.city || '')}" maxlength="80">
            </label>
            <label class="wh-wh-field">
                <span class="wh-wh-field__label">${esc(t('wh_wh_form_country'))}</span>
                <input class="wh-input" name="country" value="${esc(data.country || 'Senegal')}" maxlength="80">
            </label>
        </div>
    </section>

    <section class="wh-wh-form-section">
        <header class="wh-wh-form-section__head">
            <span class="material-icons-round" aria-hidden="true">settings</span>
            <h3>${esc(t('wh_wh_form_section_operations'))}</h3>
        </header>
        <div class="wh-wh-form-grid">
            <label class="wh-wh-field">
                <span class="wh-wh-field__label">${esc(t('wms_wh_capacity'))}</span>
                <input class="wh-input" type="number" name="capacity_units" min="0" step="1" value="${esc(data.capacity_units ?? 0)}">
            </label>
            <label class="wh-wh-field">
                <span class="wh-wh-field__label">${esc(t('wh_wh_form_phone'))}</span>
                <input class="wh-input" type="tel" name="phone" value="${esc(data.phone || '')}" maxlength="40">
            </label>
            <label class="wh-wh-field">
                <span class="wh-wh-field__label">${esc(t('wh_wh_form_email'))}</span>
                <input class="wh-input" type="email" name="email" value="${esc(data.email || '')}" maxlength="120">
            </label>
        </div>
    </section>

    <section class="wh-wh-form-section">
        <header class="wh-wh-form-section__head">
            <span class="material-icons-round" aria-hidden="true">notes</span>
            <h3>${esc(t('wh_wh_form_section_notes'))}</h3>
        </header>
        <label class="wh-wh-field wh-wh-field--full">
            <textarea class="wh-input wh-wh-textarea" name="notes" rows="3" placeholder="">${esc(data.notes || '')}</textarea>
        </label>
    </section>

    <footer class="wh-wh-form-actions">
        <a href="warehouses.php" class="wh-btn wh-btn--ghost">${esc(t('cancel'))}</a>
        <button type="submit" class="wh-btn" id="whWhFormSubmit">
            <span class="material-icons-round">save</span>
            ${esc(t('save'))}
        </button>
    </footer>
</form>`;

        const form = root.querySelector('#whWhForm');
        const genBtn = root.querySelector('#whWhGenCode');
        const nameInput = form.querySelector('[name=name]');
        const codeInput = form.querySelector('[name=warehouse_code]');

        genBtn?.addEventListener('click', () => {
            codeInput.value = suggestCode(nameInput.value);
            codeInput.focus();
        });

        form.addEventListener('submit', onSubmit);
    }

    async function onSubmit(e) {
        e.preventDefault();
        hideError();

        const form = e.target;
        const submitBtn = form.querySelector('#whWhFormSubmit');
        const fd = new FormData(form);
        const payload = Object.fromEntries(fd.entries());

        payload.capacity_units = parseInt(payload.capacity_units, 10) || 0;
        if (payload.manager_id === '') delete payload.manager_id;
        else payload.manager_id = parseInt(payload.manager_id, 10) || null;
        if (payload.store_id === '') delete payload.store_id;
        else payload.store_id = parseInt(payload.store_id, 10) || null;

        if (!payload.store_id && page.storeId) {
            payload.store_id = page.storeId;
        }

        const originalHtml = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<span class="material-icons-round">hourglass_top</span> ${esc(t('wh_wh_form_saving'))}`;

        try {
            const res = isEdit
                ? await AdminAPI.updateWmsWarehouse(whId, payload)
                : await AdminAPI.createWmsWarehouse(payload);

            if (res.status === 'success') {
                window.location.href = 'warehouses.php';
                return;
            }
            showError(res.message || t('error'));
        } catch {
            showError(t('connection_error') || t('error'));
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    }

    async function loadStores() {
        const res = await AdminAPI.listStores();
        if (res.status === 'success' && Array.isArray(res.data)) {
            stores = res.data.filter((s) => s.is_active !== 0 && s.is_active !== false);
            return;
        }
        stores = [];
    }

    async function loadManagers() {
        const res = await AdminAPI.getUsers();
        if (res.status === 'success' && Array.isArray(res.data)) {
            managers = res.data.filter((u) => u.is_active !== 0 && u.is_active !== false);
            return;
        }
        managers = [];
    }

    async function init() {
        hideError();
        try {
            await Promise.all([loadStores(), loadManagers()]);

            if (isEdit) {
                const res = await AdminAPI.getWmsWarehouse(whId);
                if (res.status === 'success' && res.data) {
                    renderForm(res.data);
                    return;
                }
                showError(res.message || t('load_error'));
                root.innerHTML = `<p class="wh-wh-form-error">${esc(t('load_error'))}</p>`;
                return;
            }

            renderForm({});
        } catch {
            showError(t('load_error'));
            root.innerHTML = `<p class="wh-wh-form-error">${esc(t('load_error'))}</p>`;
        }
    }

    init();
});
