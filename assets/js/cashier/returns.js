/**
 * Retours & remboursements — caissier
 */
document.addEventListener('DOMContentLoaded', () => {
    let currentSale = null;

    const els = {
        receiptInput: document.getElementById('receiptNumber'),
        searchBtn: document.getElementById('searchBtn'),
        resultArea: document.getElementById('resultArea'),
        refundTotal: document.getElementById('refundTotalDisplay'),
        submitBtn: document.getElementById('submitReturnBtn'),
    };

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function fmt(n) {
        return CashierAPI.formatCurrency(n);
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
            const max = parseInt(row.dataset.maxQty, 10) || 1;

            const sync = () => {
                const on = cb?.checked;
                if (qtyInput) {
                    qtyInput.disabled = !on;
                    if (!on) qtyInput.value = '0';
                    else if (parseInt(qtyInput.value, 10) < 1) qtyInput.value = '1';
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
                updateRefundDisplay();
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
                    <small>Vendu : ${item.quantity} × ${escapeHtml(fmt(item.unit_price))}</small>
                </label>
                <div class="rt-qty-wrap">
                    <input type="number" class="rt-item-qty" min="0" max="${item.quantity}" value="0" disabled>
                    <span class="rt-qty-max">/ ${item.quantity}</span>
                </div>
            </div>`
            )
            .join('');

        els.resultArea.innerHTML = `
            <div class="rt-result is-visible">
                ${isCancelled ? `<div class="rt-cancelled-banner"><span class="material-icons-round">info</span> Ce ticket est déjà annulé.</div>` : ''}
                <div class="rt-sale-head">
                    <div class="rt-sale-head__row">
                        <div>
                            <h3>${escapeHtml(receipt)}</h3>
                            <p>${escapeHtml(CashierAPI.formatDate(sale.created_at || sale.sale_date, { dateStyle: 'full', timeStyle: 'short' }))}</p>
                            <p>Caissier : ${escapeHtml(sale.cashier_name || '—')}</p>
                        </div>
                        <div class="rt-sale-head__total">${escapeHtml(fmt(total))}</div>
                    </div>
                </div>
                <div class="rt-panel">
                    <div class="rt-panel__section">
                        <h4 class="rt-panel__title">
                            <span class="material-icons-round">inventory_2</span>
                            Articles à retourner
                        </h4>
                        ${itemsHtml || '<p class="rt-state">Aucun article sur ce ticket.</p>'}
                        <p style="margin-top:12px;font-size:0.8rem;color:var(--text-muted);">
                            Cochez les articles et indiquez les quantités. Le stock sera réapprovisionné automatiquement.
                        </p>
                    </div>
                    <div class="rt-panel__section">
                        <h4 class="rt-panel__title">
                            <span class="material-icons-round">info</span>
                            Détails du retour
                        </h4>
                        <div class="rt-fields">
                            <div class="rt-field">
                                <label for="returnReason">Motif</label>
                                <select id="returnReason" ${isCancelled ? 'disabled' : ''}>
                                    <option value="customer_request">Demande client</option>
                                    <option value="defective">Article défectueux</option>
                                    <option value="wrong_item">Erreur de vente</option>
                                    <option value="other">Autre</option>
                                </select>
                            </div>
                            <div class="rt-field">
                                <label for="refundMethod">Mode de remboursement</label>
                                <select id="refundMethod" ${isCancelled ? 'disabled' : ''}>
                                    <option value="cash">Espèces</option>
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="card">Carte</option>
                                </select>
                            </div>
                            <div class="rt-field rt-field--full">
                                <label for="returnNotes">Notes (optionnel)</label>
                                <textarea id="returnNotes" placeholder="Commentaire interne…" ${isCancelled ? 'disabled' : ''}></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="rt-panel__section">
                        <div class="rt-refund-box">
                            <span class="rt-refund-box__label">Montant à rembourser (estimé)</span>
                            <span class="rt-refund-box__amount" id="refundTotalDisplay">${fmt(0)}</span>
                        </div>
                        <div class="rt-actions">
                            <button type="button" class="rt-btn rt-btn--danger" id="submitReturnBtn" ${isCancelled ? 'disabled' : ''}>
                                <span class="material-icons-round">assignment_return</span>
                                Valider le retour
                            </button>
                            <a href="view_sale.php?id=${sale.id}" class="rt-btn rt-btn--outline">
                                <span class="material-icons-round">visibility</span>
                                Voir le ticket
                            </a>
                            <button type="button" class="rt-btn rt-btn--outline" id="resetSearchBtn">
                                <span class="material-icons-round">search</span>
                                Autre ticket
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
        if (els.receiptInput) els.receiptInput.value = '';
        els.resultArea.classList.remove('is-visible');
        els.resultArea.innerHTML = '';
        currentSale = null;
        els.receiptInput?.focus();
    }

    async function searchSale() {
        const num = els.receiptInput?.value.trim();
        if (!num) return;

        els.searchBtn.disabled = true;
        renderState(`
            <div class="rt-state">
                <span class="material-icons-round">hourglass_empty</span>
                <p>Recherche du ticket…</p>
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
                renderSale(sale);
            } else {
                const result = byReceipt;
                renderState(`
                    <div class="rt-state rt-state--error">
                        <span class="material-icons-round">search_off</span>
                        <p>${escapeHtml(result.message || 'Ticket introuvable.')}</p>
                    </div>`);
            }
        } catch (err) {
            console.error(err);
            renderState(`
                <div class="rt-state rt-state--error">
                    <span class="material-icons-round">error_outline</span>
                    <p>Erreur de connexion au serveur.</p>
                </div>`);
        }

        els.searchBtn.disabled = false;
    }

    async function submitReturn() {
        if (!currentSale || currentSale.status === 'cancelled') return;

        const returnItems = [];
        document.querySelectorAll('.rt-return-item').forEach((row) => {
            const cb = row.querySelector('.rt-item-check');
            const qtyInput = row.querySelector('.rt-item-qty');
            const productId = parseInt(row.dataset.productId, 10);
            if (cb?.checked && qtyInput) {
                const qty = parseInt(qtyInput.value, 10) || 0;
                if (qty > 0 && productId) {
                    returnItems.push({ product_id: productId, quantity: qty });
                }
            }
        });

        if (!returnItems.length) {
            alert('Sélectionnez au moins un article à retourner.');
            return;
        }

        if (!confirm(`Confirmer le retour de ${returnItems.length} ligne(s) pour un remboursement estimé de ${fmt(calcRefundTotal())} ?`)) {
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
                renderState(`
                    <div class="rt-state rt-state--success">
                        <span class="material-icons-round">check_circle</span>
                        <h3 style="margin:0 0 8px;font-family:Outfit,sans-serif;">${escapeHtml(result.message)}</h3>
                        <p>Remboursement estimé : <strong>${escapeHtml(fmt(result.refund_total))}</strong></p>
                        <div class="rt-actions" style="justify-content:center;margin-top:20px;">
                            <button type="button" class="rt-btn rt-btn--outline" onclick="location.reload()">
                                <span class="material-icons-round">add</span>
                                Nouveau retour
                            </button>
                            <a href="sales_history.php" class="rt-btn rt-btn--outline">Historique</a>
                        </div>
                    </div>`);
            } else {
                alert(result.message || 'Erreur lors du retour');
                els.submitBtn.disabled = false;
            }
        } catch (err) {
            console.error(err);
            alert('Erreur système');
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

    const menuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    menuBtn?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
        overlay?.classList.toggle('active');
    });
    overlay?.addEventListener('click', () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('active');
    });
});
