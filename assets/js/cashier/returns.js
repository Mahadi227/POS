/**
 * Returns & refunds — cashier
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.RETURNS_CONFIG || {};
    const i18n = window.RETURNS_I18N || {};
    const locale = cfg.locale || (cfg.lang === 'fr' ? 'fr-FR' : 'en-US');

    if (window.POS_CONFIG && !window.POS_CONFIG.locale) {
        window.POS_CONFIG.locale = locale;
        window.POS_CONFIG.lang = cfg.lang || 'en';
    }

    let currentSale = null;
    let lastSearchAt = null;

    const els = {
        receiptInput: document.getElementById('receiptNumber'),
        searchBtn: document.getElementById('searchBtn'),
        resultArea: document.getElementById('resultArea'),
        refundTotal: null,
        submitBtn: null,
        errorBanner: document.getElementById('returnsError'),
        headerDate: document.getElementById('rtHeaderDate'),
        lastUpdated: document.getElementById('lastUpdated'),
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

    function fmt(n) {
        return CashierAPI.formatCurrency(n);
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
        if (els.lastUpdated && lastSearchAt) {
            const time = lastSearchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
            els.lastUpdated.textContent = `${t('last_updated')} · ${time}`;
        }
    }

    function showError(msg) {
        if (!els.errorBanner) return;
        els.errorBanner.classList.add('is-visible');
        const text = els.errorBanner.querySelector('.rt-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        els.errorBanner?.classList.remove('is-visible');
    }

    function reasonOptions(selectedDisabled) {
        const options = [
            ['customer_request', 'reason_customer'],
            ['defective', 'reason_defective'],
            ['wrong_item', 'reason_wrong_item'],
            ['other', 'reason_other'],
        ];
        return options
            .map(([val, labelKey]) => `<option value="${val}">${escapeHtml(t(labelKey))}</option>`)
            .join('');
    }

    function refundMethodOptions(disabled) {
        const options = [
            ['cash', 'pay_cash'],
            ['mobile_money', 'pay_mobile_money'],
            ['card', 'pay_card'],
        ];
        return options
            .map(([val, labelKey]) => `<option value="${val}">${escapeHtml(t(labelKey))}</option>`)
            .join('');
    }

    function calcRefundTotal() {
        if (!currentSale) return 0;
        let total = 0;
        document.querySelectorAll('.rt-return-item').forEach((row) => {
            const cb = row.querySelector('.rt-item-check');
            const qtyInput = row.querySelector('.rt-item-qty');
            if (cb?.checked && qtyInput) {
                const qty = parseInt(qtyInput.value, 10) || 0;
                const unit = parseFloat(row.dataset.unitPrice) || 0;
                total += qty * unit;
            }
        });
        return total;
    }

    function updateRefundDisplay() {
        const total = calcRefundTotal();
        if (els.refundTotal) els.refundTotal.textContent = fmt(total);
        if (els.submitBtn) {
            els.submitBtn.disabled = total <= 0 || currentSale?.status === 'cancelled';
        }
    }

    function bindItemEvents() {
        document.querySelectorAll('.rt-return-item').forEach((row) => {
            const cb = row.querySelector('.rt-item-check');
            const qtyInput = row.querySelector('.rt-item-qty');
            const damagedCb = row.querySelector('.rt-item-damaged');
            const max = parseInt(row.dataset.maxQty, 10) || 1;

            const sync = () => {
                const on = cb?.checked;
                if (qtyInput) {
                    qtyInput.disabled = !on;
                    if (!on) qtyInput.value = '0';
                    else if (parseInt(qtyInput.value, 10) < 1) qtyInput.value = '1';
                }
                if (damagedCb) {
                    damagedCb.disabled = !on || parseInt(qtyInput?.value || '0', 10) < 1;
                    if (!on) damagedCb.checked = false;
                }
                updateRefundDisplay();
            };

            cb?.addEventListener('change', sync);
            qtyInput?.addEventListener('input', () => {
                let v = parseInt(qtyInput.value, 10) || 0;
                if (v > max) v = max;
                if (v < 0) v = 0;
                qtyInput.value = String(v);
                if (v > 0 && cb) cb.checked = true;
                sync();
            });
            damagedCb?.addEventListener('change', updateRefundDisplay);
        });

        document.getElementById('returnReason')?.addEventListener('change', (e) => {
            if (e.target.value !== 'defective') return;
            document.querySelectorAll('.rt-return-item').forEach((row) => {
                const cb = row.querySelector('.rt-item-check');
                const qtyInput = row.querySelector('.rt-item-qty');
                const damagedCb = row.querySelector('.rt-item-damaged');
                if (cb?.checked && parseInt(qtyInput?.value || '0', 10) > 0 && damagedCb) {
                    damagedCb.checked = true;
                }
            });
        });
    }

    function renderSale(sale) {
        currentSale = sale;
        const items = sale.items || [];
        const receipt = sale.receipt_no || sale.receipt_number || `#${sale.id}`;
        const total = sale.total ?? sale.total_amount ?? 0;
        const isCancelled = sale.status === 'cancelled';

        const itemsHtml = items
            .map(
                (item) => `
            <div class="rt-return-item" data-unit-price="${item.unit_price}" data-max-qty="${item.quantity}" data-product-id="${item.product_id}">
                <input type="checkbox" class="rt-item-check" id="item-${item.product_id}" ${isCancelled ? 'disabled' : ''}>
                <label class="rt-return-item__info" for="item-${item.product_id}">
                    <strong>${escapeHtml(item.product_name)}</strong>
                    <small>${escapeHtml(t('sold_qty', String(item.quantity), fmt(item.unit_price)))}</small>
                </label>
                <div class="rt-qty-wrap">
                    <input type="number" class="rt-item-qty" min="0" max="${item.quantity}" value="0" disabled>
                    <span class="rt-qty-max">/ ${item.quantity}</span>
                </div>
                <label class="rt-damaged-flag">
                    <input type="checkbox" class="rt-item-damaged" disabled>
                    <span>${escapeHtml(t('mark_damaged'))}</span>
                </label>
            </div>`
            )
            .join('');

        els.resultArea.innerHTML = `
            <div class="rt-result is-visible">
                ${isCancelled ? `<div class="rt-cancelled-banner"><span class="material-icons-round">info</span> ${escapeHtml(t('already_cancelled'))}</div>` : ''}
                <div class="rt-sale-head">
                    <div class="rt-sale-head__row">
                        <div>
                            <h3>${escapeHtml(receipt)}</h3>
                            <p>${escapeHtml(CashierAPI.formatDate(sale.created_at || sale.sale_date, { dateStyle: 'full', timeStyle: 'short' }))}</p>
                            <p>${escapeHtml(t('cashier_label', sale.cashier_name || '—'))}</p>
                        </div>
                        <div class="rt-sale-head__total">${escapeHtml(fmt(total))}</div>
                    </div>
                </div>
                <div class="rt-panel">
                    <div class="rt-panel__section">
                        <h4 class="rt-panel__title">
                            <span class="material-icons-round">inventory_2</span>
                            ${escapeHtml(t('items_to_return'))}
                        </h4>
                        ${itemsHtml || `<p class="rt-state">${escapeHtml(t('no_items'))}</p>`}
                        <p class="rt-panel__hint">${escapeHtml(t('select_items_hint'))}</p>
                    </div>
                    <div class="rt-panel__section">
                        <h4 class="rt-panel__title">
                            <span class="material-icons-round">info</span>
                            ${escapeHtml(t('return_details'))}
                        </h4>
                        <div class="rt-fields">
                            <div class="rt-field">
                                <label for="returnReason">${escapeHtml(t('reason'))}</label>
                                <select id="returnReason" ${isCancelled ? 'disabled' : ''}>
                                    ${reasonOptions(isCancelled)}
                                </select>
                            </div>
                            <div class="rt-field">
                                <label for="refundMethod">${escapeHtml(t('refund_method'))}</label>
                                <select id="refundMethod" ${isCancelled ? 'disabled' : ''}>
                                    ${refundMethodOptions(isCancelled)}
                                </select>
                            </div>
                            <div class="rt-field rt-field--full">
                                <label for="returnNotes">${escapeHtml(t('notes'))}</label>
                                <textarea id="returnNotes" placeholder="${escapeHtml(t('notes_placeholder'))}" ${isCancelled ? 'disabled' : ''}></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="rt-panel__section">
                        <div class="rt-refund-box">
                            <span class="rt-refund-box__label">${escapeHtml(t('refund_estimated'))}</span>
                            <span class="rt-refund-box__amount" id="refundTotalDisplay">${fmt(0)}</span>
                        </div>
                        <div class="rt-actions">
                            <button type="button" class="rt-btn rt-btn--danger" id="submitReturnBtn" ${isCancelled ? 'disabled' : ''}>
                                <span class="material-icons-round">assignment_return</span>
                                ${escapeHtml(t('submit_return'))}
                            </button>
                            <a href="view_sale.php?id=${sale.id}" class="rt-btn rt-btn--outline">
                                <span class="material-icons-round">visibility</span>
                                ${escapeHtml(t('view_ticket'))}
                            </a>
                            <button type="button" class="rt-btn rt-btn--outline" id="resetSearchBtn">
                                <span class="material-icons-round">search</span>
                                ${escapeHtml(t('another_ticket'))}
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

        els.resultArea.classList.add('is-visible');
        els.refundTotal = document.getElementById('refundTotalDisplay');
        els.submitBtn = document.getElementById('submitReturnBtn');

        bindItemEvents();
        updateRefundDisplay();

        document.getElementById('submitReturnBtn')?.addEventListener('click', submitReturn);
        document.getElementById('resetSearchBtn')?.addEventListener('click', resetSearch);
    }

    function renderState(html) {
        els.resultArea.innerHTML = html;
        els.resultArea.classList.add('is-visible');
        currentSale = null;
    }

    function resetSearch() {
        hideError();
        if (els.receiptInput) els.receiptInput.value = '';
        els.resultArea.classList.remove('is-visible');
        els.resultArea.innerHTML = '';
        currentSale = null;
        lastSearchAt = null;
        if (els.lastUpdated) els.lastUpdated.textContent = '';
        els.receiptInput?.focus();
    }

    async function searchSale() {
        const num = els.receiptInput?.value.trim();
        if (!num) return;

        hideError();
        els.searchBtn.disabled = true;
        renderState(`
            <div class="rt-state">
                <span class="material-icons-round">hourglass_empty</span>
                <p>${escapeHtml(t('searching'))}</p>
            </div>`);

        try {
            let sale = null;
            const byReceipt = await CashierAPI.findByReceipt(num);

            if (byReceipt.status === 'success' && byReceipt.data?.id) {
                const detail = await CashierAPI.getSale(byReceipt.data.id);
                if (detail.status === 'success') sale = detail.data;
            } else if (/^\d+$/.test(num)) {
                const detail = await CashierAPI.getSale(num);
                if (detail.status === 'success') sale = detail.data;
            }

            if (sale) {
                hideError();
                lastSearchAt = new Date();
                updateHeaderDate();
                renderSale(sale);
            } else {
                const result = byReceipt;
                const msg = result.message || t('ticket_not_found');
                showError(msg);
                renderState(`
                    <div class="rt-state rt-state--error">
                        <span class="material-icons-round">search_off</span>
                        <p>${escapeHtml(msg)}</p>
                    </div>`);
            }
        } catch (err) {
            console.error(err);
            showError(t('connection_error'));
            renderState(`
                <div class="rt-state rt-state--error">
                    <span class="material-icons-round">error_outline</span>
                    <p>${escapeHtml(t('connection_error'))}</p>
                </div>`);
        }

        els.searchBtn.disabled = false;
    }

    function broadcastDamagedInventory() {
        try {
            localStorage.setItem('pos-inventory-damaged', String(Date.now()));
        } catch (err) {
            /* ignore quota / private mode */
        }
        window.dispatchEvent(new CustomEvent('inventory-damaged'));
    }

    async function submitReturn() {
        if (!currentSale || currentSale.status === 'cancelled') return;

        const returnItems = [];
        document.querySelectorAll('.rt-return-item').forEach((row) => {
            const cb = row.querySelector('.rt-item-check');
            const qtyInput = row.querySelector('.rt-item-qty');
            const damagedCb = row.querySelector('.rt-item-damaged');
            const productId = parseInt(row.dataset.productId, 10);
            if (cb?.checked && qtyInput) {
                const qty = parseInt(qtyInput.value, 10) || 0;
                if (qty > 0 && productId) {
                    returnItems.push({
                        product_id: productId,
                        quantity: qty,
                        condition: damagedCb?.checked ? 'damaged' : 'restock',
                    });
                }
            }
        });

        if (!returnItems.length) {
            alert(t('select_at_least_one'));
            return;
        }

        const refundAmt = fmt(calcRefundTotal());
        if (!confirm(t('confirm_return', String(returnItems.length), refundAmt))) {
            return;
        }

        els.submitBtn.disabled = true;

        try {
            const result = await CashierAPI.processReturn({
                sale_id: currentSale.id,
                items: returnItems,
                reason: document.getElementById('returnReason')?.value || 'customer_request',
                refund_method: document.getElementById('refundMethod')?.value || 'cash',
                notes: document.getElementById('returnNotes')?.value?.trim() || '',
            });

            if (result.status === 'success') {
                if ((result.damaged_units || 0) > 0) {
                    broadcastDamagedInventory();
                }
                renderState(`
                    <div class="rt-state rt-state--success">
                        <span class="material-icons-round">check_circle</span>
                        <h3 class="rt-success-title">${escapeHtml(result.message)}</h3>
                        <p>${escapeHtml(t('estimated_refund', fmt(result.refund_total)))}</p>
                        <div class="rt-actions rt-actions--center">
                            <button type="button" class="rt-btn rt-btn--outline" id="newReturnBtn">
                                <span class="material-icons-round">add</span>
                                ${escapeHtml(t('success_new_return'))}
                            </button>
                            <a href="sales_history.php" class="rt-btn rt-btn--outline">${escapeHtml(t('history'))}</a>
                        </div>
                    </div>`);
                document.getElementById('newReturnBtn')?.addEventListener('click', resetSearch);
            } else {
                alert(result.message || t('error_return'));
                els.submitBtn.disabled = false;
            }
        } catch (err) {
            console.error(err);
            alert(t('system_error'));
            els.submitBtn.disabled = false;
        }
    }

    els.searchBtn?.addEventListener('click', searchSale);
    els.receiptInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchSale();
        }
    });

    updateHeaderDate();
});
