document.addEventListener('DOMContentLoaded', () => {
    const { t, esc, formatDate, updateLastUpdated, toast, bindModalClose } = EcommerceUI;
    const modal = document.getElementById('ecomCustomerModal');
    const form = document.getElementById('ecomCustomerForm');
    const canManage = !!document.getElementById('ecomAddCustomerBtn');
    let customersCache = [];

    function isPlaceholderEmail(email) {
        return typeof email === 'string' && /@t\d+\.checkout\.local$/i.test(email);
    }

    function displayEmail(email) {
        return isPlaceholderEmail(email) ? '—' : esc(email || '—');
    }

    function formEmailValue(email) {
        return isPlaceholderEmail(email) ? '' : (email || '');
    }

    async function load() {
        const data = await AdminAPI.getEcommerceCustomers();
        const tbody = document.querySelector('#ecomCustomersTable tbody');
        if (!tbody) return;
        customersCache = data.items || [];
        const colSpan = canManage ? 6 : 5;

        tbody.innerHTML = customersCache.length
            ? customersCache.map((c) => {
                const actions = canManage
                    ? `<td class="ecom-table__actions">
                        <button type="button" class="ecom-btn ecom-btn--ghost ecom-btn--sm" data-view-id="${c.id}">${esc(t('edit'))}</button>
                        <button type="button" class="ecom-btn ecom-btn--ghost ecom-btn--sm ecom-btn--danger" data-delete-id="${c.id}">${esc(t('delete'))}</button>
                       </td>`
                    : '';
                return `<tr>
                <td>${esc(c.name)}</td>
                <td>${displayEmail(c.email)}</td>
                <td>${esc(c.phone || '—')}</td>
                <td>${formatDate(c.created_at)}</td>
                <td>${formatDate(c.last_login)}</td>
                ${actions}
            </tr>`;
            }).join('')
            : `<tr><td colspan="${colSpan}">${esc(t('ecom_no_customers'))}</td></tr>`;

        if (canManage) {
            tbody.querySelectorAll('[data-view-id]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const customer = customersCache.find((x) => String(x.id) === String(btn.dataset.viewId));
                    openModal(customer);
                });
            });
            tbody.querySelectorAll('[data-delete-id]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    if (!confirm(t('delete_confirm'))) return;
                    const res = await AdminAPI.deleteEcommerceCustomer(btn.dataset.deleteId);
                    if (res.status === 'ok') {
                        toast(t('ecom_saved'));
                        load();
                    } else {
                        window.alert(res.message || t('load_error'));
                    }
                });
            });
        }

        updateLastUpdated();
    }

    function openModal(customer = null) {
        if (!modal) return;
        document.getElementById('ecomCustomerId').value = customer?.id || '';
        document.getElementById('ecomCustomerName').value = customer?.name || '';
        document.getElementById('ecomCustomerPhone').value = customer?.phone || '';
        document.getElementById('ecomCustomerEmail').value = formEmailValue(customer?.email);
        document.getElementById('ecomCustomerPassword').value = '';
        document.getElementById('ecomCustomerModalTitle').textContent = customer
            ? t('ecom_edit_customer')
            : t('ecom_add_customer');
        modal.showModal();
    }

    document.getElementById('ecomAddCustomerBtn')?.addEventListener('click', () => openModal());
    bindModalClose(modal);

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const payload = {
            name: fd.get('name'),
            phone: fd.get('phone'),
            email: fd.get('email'),
        };
        const password = String(fd.get('password') || '').trim();
        if (password) payload.password = password;

        const id = fd.get('id') ? Number(fd.get('id')) : null;
        const res = await AdminAPI.saveEcommerceCustomer(payload, id);
        if (res.status === 'ok') {
            modal?.close();
            toast(t('ecom_saved'));
            load();
        } else {
            window.alert(res.message || t('load_error'));
        }
    });

    load();
    document.addEventListener('ecom:refresh', load);
});
