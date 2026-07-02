/**
 * Warehouse portal — global search
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('whGlobalSearchForm');
    const input = document.getElementById('whGlobalSearch');
    const dropdown = document.getElementById('whSearchResults');
    if (!input || !dropdown) return;

    const { t, esc } = WarehouseUI;
    let timer;

    function hide() {
        dropdown.hidden = true;
        dropdown.innerHTML = '';
    }

    function render(data) {
        const sections = [];
        if (data.products?.length) {
            sections.push(`<section><h4>${esc(t('wh_nav_products'))}</h4><ul>${data.products.map((p) =>
                `<li><a href="inventory/products.php?q=${encodeURIComponent(p.sku || p.name)}">${esc(p.name)} <small>${esc(p.sku || '')}</small></a></li>`).join('')}</ul></section>`);
        }
        if (data.batches?.length) {
            sections.push(`<section><h4>${esc(t('wh_nav_batch'))}</h4><ul>${data.batches.map((b) =>
                `<li><a href="batch/batch_tracking.php">${esc(b.batch_number)} · ${esc(b.product_name || '')}</a></li>`).join('')}</ul></section>`);
        }
        if (data.transfers?.length) {
            sections.push(`<section><h4>${esc(t('wh_section_transfers'))}</h4><ul>${data.transfers.map((tr) =>
                `<li><a href="transfers/transfer_history.php">${esc(tr.transfer_number)}</a></li>`).join('')}</ul></section>`);
        }
        if (data.purchase_orders?.length) {
            sections.push(`<section><h4>${esc(t('wh_nav_purchase_orders'))}</h4><ul>${data.purchase_orders.map((po) =>
                `<li><a href="receiving/purchase_orders.php">${esc(po.po_number)}</a></li>`).join('')}</ul></section>`);
        }
        if (!sections.length) {
            dropdown.innerHTML = `<p class="wh-muted">${esc(t('wh_no_results'))}</p>`;
        } else {
            dropdown.innerHTML = sections.join('');
        }
        dropdown.hidden = false;
    }

    async function search(q) {
        if (q.length < 2) { hide(); return; }
        try {
            const res = await AdminAPI.warehousePortalSearch(q);
            if (res.status === 'success') render(res.data || {});
        } catch (_) { hide(); }
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => search(input.value.trim()), 300);
    });
    form?.addEventListener('submit', (e) => { e.preventDefault(); search(input.value.trim()); });
    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && e.target !== input) hide();
    });
});
