document.addEventListener('DOMContentLoaded', () => {
    const { t, esc, money, formatDate, updateLastUpdated, bindModalClose } = EcommerceUI;
    const modal = document.getElementById('ecomOrderModal');
    let currentOrderId = null;

    function statusBadge(order) {
        const status = order.status || 'completed';
        let labelKey = `ecom_status_${status}`;
        if (status === 'pending' && order.payment_provider === 'paystack') {
            labelKey = 'ecom_status_pending_payment';
        }
        const cls = status === 'pending' ? 'ecom-badge ecom-badge--pending' : `ecom-badge ecom-badge--${status}`;
        const label = t(labelKey, status);
        return `<span class="${cls}">${esc(label)}</span>`;
    }

    function paymentLabel(order) {
        const method = order.checkout_method || order.payment_method || '';
        if (method === 'cash_on_delivery' || order.payment_provider === 'cod') {
            return t('ecom_pay_cod');
        }
        if (method === 'mobile_money') {
            return t('ecom_pay_mobile');
        }
        return t('ecom_pay_card');
    }

    function canAccept(order) {
        return (order.status === 'pending')
            && (order.payment_provider === 'cod' || order.checkout_method === 'cash_on_delivery');
    }

    async function load() {
        const data = await AdminAPI.getEcommerceOrders({ limit: 50 });
        const tbody = document.querySelector('#ecomOrdersTable tbody');
        if (!tbody) return;
        if (data.status !== 'ok') {
            tbody.innerHTML = `<tr><td colspan="7">${esc(t('load_error'))}</td></tr>`;
            return;
        }
        const items = data.items || [];
        tbody.innerHTML = items.length
            ? items.map((o) => `<tr>
                <td>${esc(o.receipt_no)}</td>
                <td>${esc(o.customer_name || o.customer_email || '—')}</td>
                <td>${formatDate(o.created_at)}</td>
                <td>${money(o.total)}</td>
                <td>${esc(paymentLabel(o))}</td>
                <td>${statusBadge(o)}</td>
                <td><button type="button" class="ecom-btn ecom-btn--ghost ecom-view-order" data-id="${o.id}">${esc(t('ecom_view_order'))}</button></td>
            </tr>`).join('')
            : `<tr><td colspan="7">${esc(t('ecom_no_orders'))}</td></tr>`;

        tbody.querySelectorAll('.ecom-view-order').forEach((btn) => {
            btn.addEventListener('click', () => openOrder(btn.dataset.id));
        });
        updateLastUpdated();
    }

    async function openOrder(id) {
        currentOrderId = id;
        const data = await AdminAPI.getEcommerceOrder(id);
        if (data.status !== 'ok' || !data.order) return;
        const o = data.order;
        document.getElementById('ecomOrderModalTitle').textContent = o.receipt_no;

        const acceptBtn = canAccept(o)
            ? `<button type="button" class="ecom-btn ecom-btn--primary" id="ecomAcceptOrderBtn">
                <span class="material-icons-round">check_circle</span>${esc(t('ecom_accept_order'))}
               </button>`
            : '';

        document.getElementById('ecomOrderModalBody').innerHTML = `
            <div class="ecom-order-modal-meta">
                <p><strong>${esc(t('col_date'))}:</strong> ${formatDate(o.created_at)}</p>
                <p><strong>${esc(t('col_amount'))}:</strong> ${money(o.total)}</p>
                <p><strong>${esc(t('ecom_payment_method'))}:</strong> ${esc(paymentLabel(o))}</p>
                <p><strong>${esc(t('col_status'))}:</strong> ${statusBadge(o)}</p>
            </div>
            ${o.status === 'pending' && canAccept(o) ? `<p class="ecom-order-modal-hint">${esc(t('ecom_cod_pending_hint'))}</p>` : ''}
            ${o.status === 'pending' && o.payment_provider === 'paystack' ? `<p class="ecom-order-modal-hint">${esc(t('ecom_paystack_pending_hint'))}</p>` : ''}
            <table class="ecom-table ecom-table--compact">
                <thead><tr><th>${esc(t('ecom_product'))}</th><th>${esc(t('ecom_qty'))}</th><th>${esc(t('col_amount'))}</th></tr></thead>
                <tbody>${(o.items || []).map((i) => `<tr>
                    <td>${esc(i.product_name || i.product_id)}</td>
                    <td>${esc(i.quantity)}</td>
                    <td>${money(i.subtotal)}</td>
                </tr>`).join('')}</tbody>
            </table>
            <div class="ecom-order-modal-actions">${acceptBtn}</div>`;

        document.getElementById('ecomAcceptOrderBtn')?.addEventListener('click', () => acceptOrder(id));
        modal?.showModal();
    }

    async function acceptOrder(id) {
        const btn = document.getElementById('ecomAcceptOrderBtn');
        if (btn) btn.disabled = true;
        try {
            const res = await AdminAPI.acceptEcommerceOrder(id);
            if (res.status === 'ok') {
                modal?.close();
                await load();
            } else {
                window.alert(res.message || t('load_error'));
            }
        } catch (e) {
            window.alert(t('load_error'));
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    bindModalClose(modal);
    document.getElementById('ecomOrderModalClose')?.addEventListener('click', () => modal?.close());
    load();
    document.addEventListener('ecom:refresh', load);
});
