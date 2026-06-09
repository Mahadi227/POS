/**
 * Détail d'une vente — caissier
 */
document.addEventListener('DOMContentLoaded', () => {
    const saleId = window.VS_SALE_ID;
    const root = document.getElementById('saleDetailRoot');

    const PAY_CLASS = {
        cash: 'vs-pay-badge--cash',
        mobile_money: 'vs-pay-badge--mobile_money',
        card: 'vs-pay-badge--card',
    };

    const STATUS_LABELS = {
        completed: 'Terminée',
        pending: 'En attente',
        cancelled: 'Annulée',
    };

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function fmt(n) {
        return CashierAPI.formatCurrency(n);
    }

    function paymentLabel(method, provider) {
        let label = CashierAPI.paymentLabel(method);
        if (method === 'mobile_money' && provider) {
            const map = { orange_money: 'Orange', mtn_momo: 'MTN', wave: 'Wave', moov: 'Moov' };
            label += ' — ' + (map[provider] || provider);
        }
        return label;
    }

    function printReceipt(sale) {
        const url = new URL('../../receipts/templates/thermal-80mm.php', window.location.href);
        url.searchParams.set('id', String(sale.id));
        const win = window.open(url.toString(), 'ReceiptPrint', 'width=420,height=720,scrollbars=yes');
        if (!win) alert('Autorisez les fenêtres pop-up pour imprimer le reçu.');
    }

    function renderError(title, message) {
        if (!root) return;
        root.innerHTML = `
            <div class="vs-state vs-state--error">
                <span class="material-icons-round">error_outline</span>
                <h3>${escapeHtml(title)}</h3>
                <p>${escapeHtml(message)}</p>
                <div class="vs-actions" style="justify-content:center;margin-top:20px;">
                    <a href="sales_history.php" class="vs-btn vs-btn--outline">
                        <span class="material-icons-round">arrow_back</span>
                        Retour à l'historique
                    </a>
                </div>
            </div>`;
    }

    function renderSale(sale) {
        const items = sale.items || [];
        const receipt = sale.receipt_no || sale.receipt_number || `#${sale.id}`;
        const date = sale.created_at || sale.sale_date;
        const total = sale.total ?? sale.total_amount ?? 0;
        const tax = sale.tax ?? sale.tax_amount ?? 0;
        const discount = sale.discount ?? sale.discount_amount ?? 0;
        const subtotal = sale.subtotal ?? items.reduce((s, i) => s + (i.subtotal || 0), 0);
        const status = sale.status || 'completed';
        const statusLabel = STATUS_LABELS[status] || status;
        const payMethod = sale.payment_method || 'cash';
        const payClass = PAY_CLASS[payMethod] || 'vs-pay-badge--default';

        document.getElementById('pageReceiptLabel')?.replaceChildren(
            document.createTextNode(receipt)
        );

        const itemsTableRows = items
            .map(
                (item) => `
            <tr>
                <td>
                    <div class="vs-item-name">${escapeHtml(item.product_name)}</div>
                    ${item.sku ? `<div class="vs-item-sku">SKU: ${escapeHtml(item.sku)}</div>` : ''}
                </td>
                <td><span class="vs-qty">${item.quantity}</span></td>
                <td class="vs-money">${escapeHtml(fmt(item.unit_price))}</td>
                <td class="vs-money vs-money--strong">${escapeHtml(fmt(item.subtotal))}</td>
            </tr>`
            )
            .join('');

        const itemsCards = items
            .map(
                (item) => `
            <div class="vs-item-card">
                <div class="vs-item-card__row">
                    <div>
                        <div class="vs-item-name">${escapeHtml(item.product_name)}</div>
                        ${item.sku ? `<div class="vs-item-sku">${escapeHtml(item.sku)}</div>` : ''}
                    </div>
                    <span class="vs-money vs-money--strong">${escapeHtml(fmt(item.subtotal))}</span>
                </div>
                <div class="vs-item-card__line">
                    <span>${item.quantity} × ${escapeHtml(fmt(item.unit_price))}</span>
                </div>
            </div>`
            )
            .join('');

        root.innerHTML = `
            <section class="vs-hero">
                <div class="vs-hero__top">
                    <div>
                        <h2 class="vs-hero__receipt">${escapeHtml(receipt)}</h2>
                        <p class="vs-hero__date">${escapeHtml(CashierAPI.formatDate(date, { dateStyle: 'full', timeStyle: 'short' }))}</p>
                    </div>
                    <div class="vs-hero__badges">
                        <span class="vs-badge vs-badge--${escapeHtml(status)}">${escapeHtml(statusLabel)}</span>
                        <span class="vs-pay-badge ${payClass}">${escapeHtml(paymentLabel(payMethod, sale.payment_provider))}</span>
                    </div>
                </div>
                <div>
                    <div class="vs-hero__total-label">Total payé</div>
                    <div class="vs-hero__total">${escapeHtml(fmt(total))}</div>
                </div>
            </section>

            <div class="vs-meta">
                <div class="vs-meta-card">
                    <span class="vs-meta-card__icon material-icons-round">person</span>
                    <div>
                        <div class="vs-meta-card__label">Caissier</div>
                        <div class="vs-meta-card__value">${escapeHtml(sale.cashier_name || '—')}</div>
                    </div>
                </div>
                <div class="vs-meta-card">
                    <span class="vs-meta-card__icon material-icons-round">groups</span>
                    <div>
                        <div class="vs-meta-card__label">Client</div>
                        <div class="vs-meta-card__value">${escapeHtml(sale.customer_name || 'Client passage')}</div>
                    </div>
                </div>
                <div class="vs-meta-card">
                    <span class="vs-meta-card__icon material-icons-round">storefront</span>
                    <div>
                        <div class="vs-meta-card__label">Magasin</div>
                        <div class="vs-meta-card__value">${escapeHtml(sale.store_name || '—')}</div>
                    </div>
                </div>
            </div>

            ${
                sale.payment_ref
                    ? `<div class="vs-panel" style="margin-bottom:20px;">
                <div class="vs-panel__head"><span class="material-icons-round">tag</span> Réf. paiement</div>
                <div style="padding:14px 20px;font-weight:600;">${escapeHtml(sale.payment_ref)}</div>
               </div>`
                    : ''
            }

            <section class="vs-panel">
                <div class="vs-panel__head">
                    <span class="material-icons-round">inventory_2</span>
                    Articles (${items.length})
                </div>
                <div class="vs-items-wrap">
                    <table class="vs-items">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Qté</th>
                                <th style="text-align:right">P.U.</th>
                                <th style="text-align:right">Total</th>
                            </tr>
                        </thead>
                        <tbody>${itemsTableRows || '<tr><td colspan="4" style="text-align:center;padding:24px;">Aucun article</td></tr>'}</tbody>
                    </table>
                </div>
                <div class="vs-item-cards">${itemsCards || '<p style="text-align:center;color:var(--text-muted);">Aucun article</p>'}</div>
            </section>

            <section class="vs-panel">
                <div class="vs-panel__head">
                    <span class="material-icons-round">calculate</span>
                    Récapitulatif
                </div>
                <div class="vs-totals">
                    <div class="vs-total-row">
                        <span>Sous-total</span>
                        <span>${escapeHtml(fmt(subtotal))}</span>
                    </div>
                    ${
                        discount > 0
                            ? `<div class="vs-total-row vs-total-row--discount">
                        <span>Remise</span>
                        <span>- ${escapeHtml(fmt(discount))}</span>
                    </div>`
                            : ''
                    }
                    <div class="vs-total-row">
                        <span>TVA</span>
                        <span>${escapeHtml(fmt(tax))}</span>
                    </div>
                    <div class="vs-total-row vs-total-row--grand">
                        <span>TOTAL</span>
                        <span>${escapeHtml(fmt(total))}</span>
                    </div>
                </div>
            </section>

            <div class="vs-actions">
                <button type="button" class="vs-btn vs-btn--primary" id="printSaleBtn">
                    <span class="material-icons-round">print</span>
                    Imprimer le reçu
                </button>
                <a href="sales_history.php" class="vs-btn vs-btn--outline">
                    <span class="material-icons-round">history</span>
                    Historique des ventes
                </a>
            </div>`;

        document.getElementById('printSaleBtn')?.addEventListener('click', () => printReceipt(sale));
    }

    async function loadSale() {
        if (!saleId || !root) return;

        try {
            const result = await CashierAPI.getSale(saleId);

            if (result.status === 'success' && result.data) {
                renderSale(result.data);
            } else {
                renderError('Vente introuvable', result.message || 'Ce ticket n\'existe pas ou accès refusé.');
            }
        } catch (err) {
            console.error('View sale:', err);
            renderError('Erreur de connexion', 'Impossible de charger les détails de la vente.');
        }
    }

    /* Mobile sidebar */
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

    loadSale();
});
