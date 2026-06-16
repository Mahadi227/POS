/**
 * Sale detail — cashier
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.VS_CONFIG || {};
    const i18n = window.VS_I18N || {};
    const locale = cfg.locale || (cfg.lang === 'fr' ? 'fr-FR' : 'en-US');
    const saleId = cfg.saleId || window.VS_SALE_ID;

    if (window.POS_CONFIG && !window.POS_CONFIG.locale) {
        window.POS_CONFIG.locale = locale;
        window.POS_CONFIG.lang = cfg.lang || 'en';
    }

    const root = document.getElementById('saleDetailRoot');
    let lastLoadAt = null;

    const els = {
        errorBanner: document.getElementById('viewSaleError'),
        headerDate: document.getElementById('vsHeaderDate'),
        lastUpdated: document.getElementById('lastUpdated'),
        receiptLabel: document.getElementById('pageReceiptLabel'),
    };

    const PAY_CLASS = {
        cash: 'vs-pay-badge--cash',
        mobile_money: 'vs-pay-badge--mobile_money',
        card: 'vs-pay-badge--card',
    };

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function escapeAttr(str) {
        return String(str ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function fmt(n) {
        return CashierAPI.formatCurrency(n);
    }

    function columnLabels() {
        return {
            product: t('col_product'),
            qty: t('col_qty'),
            unitPrice: t('col_unit_price'),
            lineTotal: t('col_line_total'),
        };
    }

    function statusLabel(status) {
        const map = {
            completed: t('status_completed'),
            pending: t('status_pending'),
            cancelled: t('status_cancelled'),
        };
        return map[status] || status;
    }

    function paymentLabel(method, provider) {
        const map = {
            cash: t('pay_cash'),
            card: t('pay_card'),
            mobile_money: t('pay_mobile_money'),
            split: t('pay_split'),
        };
        let label = map[method] || CashierAPI.paymentLabel(method);
        if (method === 'mobile_money' && provider) {
            const providers = { orange_money: 'Orange', mtn_momo: 'MTN', wave: 'Wave', moov: 'Moov' };
            label += ' — ' + (providers[provider] || provider);
        }
        return label;
    }

    function updateHeaderDate() {
        const now = new Date();
        const dateStr = now.toLocaleDateString(locale, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
        if (els.headerDate) els.headerDate.textContent = dateStr;
        if (els.lastUpdated && lastLoadAt) {
            const time = lastLoadAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
            els.lastUpdated.textContent = `${t('last_updated')} · ${time}`;
        }
    }

    function showError(msg) {
        if (!els.errorBanner) return;
        els.errorBanner.classList.add('is-visible');
        const text = els.errorBanner.querySelector('.vs-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        els.errorBanner?.classList.remove('is-visible');
    }

    function printReceipt(sale) {
        const url = new URL('../../receipts/templates/thermal-80mm.php', window.location.href);
        url.searchParams.set('id', String(sale.id));
        const win = window.open(url.toString(), 'ReceiptPrint', 'width=420,height=720,scrollbars=yes');
        if (!win) alert(t('popup_blocked'));
    }

    function renderError(title, message) {
        showError(message);
        if (!root) return;
        root.innerHTML = `
            <div class="vs-state vs-state--error">
                <span class="material-icons-round">error_outline</span>
                <h3>${escapeHtml(title)}</h3>
                <p>${escapeHtml(message)}</p>
                <div class="vs-actions" style="justify-content:center;margin-top:20px;">
                    <a href="sales_history.php" class="vs-btn vs-btn--outline">
                        <span class="material-icons-round">arrow_back</span>
                        ${escapeHtml(t('back_history'))}
                    </a>
                </div>
            </div>`;
    }

    function renderSale(sale) {
        hideError();
        const items = sale.items || [];
        const receipt = sale.receipt_no || sale.receipt_number || `#${sale.id}`;
        const date = sale.created_at || sale.sale_date;
        const total = sale.total ?? sale.total_amount ?? 0;
        const tax = sale.tax ?? sale.tax_amount ?? 0;
        const discount = sale.discount ?? sale.discount_amount ?? 0;
        const subtotal = sale.subtotal ?? items.reduce((s, i) => s + (i.subtotal || 0), 0);
        const status = sale.status || 'completed';
        const payMethod = sale.payment_method || 'cash';
        const payClass = PAY_CLASS[payMethod] || 'vs-pay-badge--default';
        const cols = columnLabels();

        if (els.receiptLabel) els.receiptLabel.textContent = receipt;

        const itemsTableRows = items.length
            ? items
                  .map(
                      (item) => `
            <tr>
                <td data-label="${escapeAttr(cols.product)}">
                    <div class="vs-item-name">${escapeHtml(item.product_name)}</div>
                    ${item.sku ? `<div class="vs-item-sku">${escapeHtml(t('sku_label', item.sku))}</div>` : ''}
                </td>
                <td data-label="${escapeAttr(cols.qty)}"><span class="vs-qty">${item.quantity}</span></td>
                <td class="vs-money" data-label="${escapeAttr(cols.unitPrice)}">${escapeHtml(fmt(item.unit_price))}</td>
                <td class="vs-money vs-money--strong" data-label="${escapeAttr(cols.lineTotal)}">${escapeHtml(fmt(item.subtotal))}</td>
            </tr>`
                  )
                  .join('')
            : `<tr><td colspan="4" class="vs-empty">${escapeHtml(t('no_items'))}</td></tr>`;

        root.innerHTML = `
            <section class="vs-hero">
                <div class="vs-hero__top">
                    <div>
                        <h2 class="vs-hero__receipt">${escapeHtml(receipt)}</h2>
                        <p class="vs-hero__date">${escapeHtml(CashierAPI.formatDate(date, { dateStyle: 'full', timeStyle: 'short' }))}</p>
                    </div>
                    <div class="vs-hero__badges">
                        <span class="vs-badge vs-badge--${escapeHtml(status)}">${escapeHtml(statusLabel(status))}</span>
                        <span class="vs-pay-badge ${payClass}">${escapeHtml(paymentLabel(payMethod, sale.payment_provider))}</span>
                    </div>
                </div>
                <div>
                    <div class="vs-hero__total-label">${escapeHtml(t('total_paid'))}</div>
                    <div class="vs-hero__total">${escapeHtml(fmt(total))}</div>
                </div>
            </section>

            <div class="vs-meta">
                <div class="vs-meta-card">
                    <span class="vs-meta-card__icon material-icons-round">person</span>
                    <div>
                        <div class="vs-meta-card__label">${escapeHtml(t('cashier_label'))}</div>
                        <div class="vs-meta-card__value">${escapeHtml(sale.cashier_name || '—')}</div>
                    </div>
                </div>
                <div class="vs-meta-card">
                    <span class="vs-meta-card__icon material-icons-round">groups</span>
                    <div>
                        <div class="vs-meta-card__label">${escapeHtml(t('customer'))}</div>
                        <div class="vs-meta-card__value">${escapeHtml(sale.customer_name || t('walk_in'))}</div>
                    </div>
                </div>
                <div class="vs-meta-card">
                    <span class="vs-meta-card__icon material-icons-round">storefront</span>
                    <div>
                        <div class="vs-meta-card__label">${escapeHtml(t('store_label'))}</div>
                        <div class="vs-meta-card__value">${escapeHtml(sale.store_name || '—')}</div>
                    </div>
                </div>
            </div>

            ${
                sale.payment_ref
                    ? `<div class="vs-panel">
                <div class="vs-panel__head"><span class="material-icons-round">tag</span> ${escapeHtml(t('payment_ref'))}</div>
                <div class="vs-panel__body">${escapeHtml(sale.payment_ref)}</div>
               </div>`
                    : ''
            }

            <section class="vs-panel">
                <div class="vs-panel__head">
                    <span class="material-icons-round">inventory_2</span>
                    ${escapeHtml(t('items_section', String(items.length)))}
                </div>
                <div class="vs-items-wrap">
                    <table class="vs-items vs-sale-items">
                        <thead>
                            <tr>
                                <th>${escapeHtml(cols.product)}</th>
                                <th>${escapeHtml(cols.qty)}</th>
                                <th style="text-align:right">${escapeHtml(cols.unitPrice)}</th>
                                <th style="text-align:right">${escapeHtml(cols.lineTotal)}</th>
                            </tr>
                        </thead>
                        <tbody>${itemsTableRows}</tbody>
                    </table>
                </div>
            </section>

            <section class="vs-panel">
                <div class="vs-panel__head">
                    <span class="material-icons-round">calculate</span>
                    ${escapeHtml(t('summary_section'))}
                </div>
                <div class="vs-totals">
                    <div class="vs-total-row">
                        <span>${escapeHtml(t('subtotal'))}</span>
                        <span>${escapeHtml(fmt(subtotal))}</span>
                    </div>
                    ${
                        discount > 0
                            ? `<div class="vs-total-row vs-total-row--discount">
                        <span>${escapeHtml(t('discount'))}</span>
                        <span>- ${escapeHtml(fmt(discount))}</span>
                    </div>`
                            : ''
                    }
                    <div class="vs-total-row">
                        <span>${escapeHtml(t('tax'))}</span>
                        <span>${escapeHtml(fmt(tax))}</span>
                    </div>
                    <div class="vs-total-row vs-total-row--grand">
                        <span>${escapeHtml(t('grand_total'))}</span>
                        <span>${escapeHtml(fmt(total))}</span>
                    </div>
                </div>
            </section>

            <div class="vs-actions">
                <button type="button" class="vs-btn vs-btn--primary" id="printSaleBtn">
                    <span class="material-icons-round">print</span>
                    ${escapeHtml(t('print_receipt'))}
                </button>
                <a href="sales_history.php" class="vs-btn vs-btn--outline">
                    <span class="material-icons-round">history</span>
                    ${escapeHtml(t('sales_history_link'))}
                </a>
            </div>`;

        document.getElementById('printSaleBtn')?.addEventListener('click', () => printReceipt(sale));
    }

    async function loadSale() {
        if (!saleId || !root) return;

        hideError();
        try {
            const result = await CashierAPI.getSale(saleId);

            if (result.status === 'success' && result.data) {
                lastLoadAt = new Date();
                updateHeaderDate();
                renderSale(result.data);
            } else {
                renderError(t('sale_not_found'), result.message || t('sale_not_found_msg'));
            }
        } catch (err) {
            console.error('View sale:', err);
            renderError(t('view_load_error'), t('view_load_error_msg'));
        }
    }

    updateHeaderDate();
    loadSale();
});
