document.addEventListener('DOMContentLoaded', () => {
    const { t, esc, updateLastUpdated, toast, bindModalClose } = EcommerceUI;
    const modal = document.getElementById('ecomBrandModal');
    const form = document.getElementById('ecomBrandForm');
    let brandsCache = [];

    async function load() {
        const data = await AdminAPI.getEcommerceBrands();
        const grid = document.getElementById('ecomBrandsGrid');
        if (!grid) return;
        brandsCache = data.items || [];
        grid.innerHTML = brandsCache.length
            ? brandsCache.map((b) => `<article class="ecom-brand-card">
                <div class="ecom-brand-card__logo">${b.logo_url ? `<img src="${esc(b.logo_url)}" alt="">` : '<span class="material-icons-round">sell</span>'}</div>
                <h3>${esc(b.name)}</h3>
                <p><code>${esc(b.slug)}</code></p>
                <div class="ecom-brand-card__actions">
                    <button type="button" class="ecom-btn ecom-btn--ghost" data-edit-id="${b.id}">Edit</button>
                    <button type="button" class="ecom-btn ecom-btn--ghost ecom-btn--danger" data-delete="${b.id}">${esc(t('delete'))}</button>
                </div>
            </article>`).join('')
            : `<p>${esc(t('ecom_no_brands'))}</p>`;

        grid.querySelectorAll('[data-edit-id]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const b = brandsCache.find((x) => String(x.id) === String(btn.dataset.editId));
                openModal(b);
            });
        });
        grid.querySelectorAll('[data-delete]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (!confirm(t('delete_confirm'))) return;
                await AdminAPI.deleteEcommerceBrand(btn.dataset.delete);
                toast(t('ecom_saved'));
                load();
            });
        });
        updateLastUpdated();
    }

    function openModal(brand = null) {
        document.getElementById('ecomBrandId').value = brand?.id || '';
        document.getElementById('ecomBrandName').value = brand?.name || '';
        document.getElementById('ecomBrandSlug').value = brand?.slug || '';
        document.getElementById('ecomBrandLogo').value = brand?.logo_url || '';
        document.getElementById('ecomBrandModalTitle').textContent = brand ? 'Edit' : t('ecom_add_brand');
        modal?.showModal();
    }

    document.getElementById('ecomAddBrandBtn')?.addEventListener('click', () => openModal());
    bindModalClose(modal);
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const payload = Object.fromEntries(fd.entries());
        const id = payload.id ? Number(payload.id) : null;
        delete payload.id;
        await AdminAPI.saveEcommerceBrand(payload, id);
        modal?.close();
        toast(t('ecom_saved'));
        load();
    });

    load();
    document.addEventListener('ecom:refresh', load);
});
