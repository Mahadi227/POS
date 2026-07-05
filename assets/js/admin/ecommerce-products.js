document.addEventListener('DOMContentLoaded', () => {
    const { t, esc, money, updateLastUpdated, toast } = EcommerceUI;
    let filterOnline = '';
    let searchQ = '';
    let timer;

    async function load() {
        const data = await AdminAPI.getEcommerceProducts({ online: filterOnline, q: searchQ, limit: 100 });
        const tbody = document.querySelector('#ecomProductsTable tbody');
        if (!tbody) return;
        if (data.status !== 'ok') {
            tbody.innerHTML = `<tr><td colspan="6">${esc(t('load_error'))}</td></tr>`;
            return;
        }
        const items = data.items || [];
        tbody.innerHTML = items.length
            ? items.map((p) => `<tr data-id="${p.id}">
                <td><strong>${esc(p.name)}</strong></td>
                <td>${esc(p.sku || '—')}</td>
                <td>${money(p.price)}</td>
                <td>${esc(p.stock_quantity ?? '—')}</td>
                <td>
                    <label class="ecom-switch">
                        <input type="checkbox" class="ecom-online-toggle" data-id="${p.id}" ${Number(p.is_online) ? 'checked' : ''}>
                        <span></span>
                    </label>
                </td>
                <td><code>${esc(p.slug || '—')}</code></td>
            </tr>`).join('')
            : `<tr><td colspan="6">${esc(t('ecom_no_products'))}</td></tr>`;

        tbody.querySelectorAll('.ecom-online-toggle').forEach((input) => {
            input.addEventListener('change', async () => {
                const id = input.dataset.id;
                const res = await AdminAPI.toggleEcommerceProductOnline(id, input.checked);
                if (res.status === 'ok') toast(t('ecom_saved'));
                else input.checked = !input.checked;
            });
        });
        updateLastUpdated();
    }

    document.querySelectorAll('.ecom-chip[data-online]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.ecom-chip').forEach((b) => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            filterOnline = btn.dataset.online ?? '';
            load();
        });
    });

    document.getElementById('ecomProductSearch')?.addEventListener('input', (e) => {
        clearTimeout(timer);
        timer = setTimeout(() => { searchQ = e.target.value.trim(); load(); }, 300);
    });

    load();
    document.addEventListener('ecom:refresh', load);
});
