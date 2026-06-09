/**
 * Historique des ventes — caissier
 */
document.addEventListener('DOMContentLoaded', () => {
    const PAY_CLASS = {
        cash: 'sh-pay-badge--cash',
        mobile_money: 'sh-pay-badge--mobile_money',
        card: 'sh-pay-badge--card',
    };

    let allSales = [];
    let period = 'today';
    let searchQuery = '';

    const els = {
        tbody: document.getElementById('salesTableBody'),
        cards: document.getElementById('salesCards'),
        searchInput: document.getElementById('salesSearch'),
        searchClear: document.getElementById('salesSearchClear'),
        refreshBtn: document.getElementById('salesRefreshBtn'),
        countLabel: document.getElementById('salesCountLabel'),
        summaryCount: document.getElementById('summaryCount'),
        summaryRevenue: document.getElementById('summaryRevenue'),
        summaryFiltered: document.getElementById('summaryFiltered'),
    };

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function saleDate(sale) {
        return sale.created_at || sale.sale_date;
    }

    function saleTotal(sale) {
        return sale.total ?? sale.total_amount ?? 0;
    }

    function saleReceipt(sale) {
        return sale.receipt_no || sale.receipt_number || `#${sale.id}`;
    }

    function payBadgeClass(method) {
        return PAY_CLASS[method] || 'sh-pay-badge--default';
    }

    function getFilteredSales() {
        let list = [...allSales];

        if (searchQuery.trim()) {
            const q = searchQuery.trim().toLowerCase();
            list = list.filter((s) => {
                const receipt = saleReceipt(s).toLowerCase();
                const customer = (s.customer_name || '').toLowerCase();
                return receipt.includes(q) || customer.includes(q) || String(s.id).includes(q);
            });
        }

        list.sort((a, b) => new Date(saleDate(b)) - new Date(saleDate(a)));
        return list;
    }

    function updateSummary(filtered) {
        const revenue = filtered.reduce((sum, s) => sum + parseFloat(saleTotal(s)), 0);
        if (els.summaryCount) els.summaryCount.textContent = String(filtered.length);
        if (els.summaryRevenue) els.summaryRevenue.textContent = CashierAPI.formatCurrency(revenue);
        if (els.summaryFiltered) {
            els.summaryFiltered.textContent =
                searchQuery.trim() && filtered.length !== allSales.length
                    ? `${filtered.length} sur ${allSales.length}`
                    : `${allSales.length} ticket(s)`;
        }
        if (els.countLabel) {
            els.countLabel.textContent = `${filtered.length} résultat(s)`;
        }
    }

    function printReceipt(sale) {
        const url = new URL('../../receipts/templates/thermal-80mm.php', window.location.href);
        if (sale.id) url.searchParams.set('id', String(sale.id));
        else if (sale.receipt_no || sale.receipt_number) {
            url.searchParams.set('receipt_no', sale.receipt_no || sale.receipt_number);
        }
        const win = window.open(url.toString(), 'ReceiptPrint', 'width=420,height=720,scrollbars=yes');
        if (!win) alert('Autorisez les fenêtres pop-up pour imprimer le reçu.');
    }

    function bindPrintButtons(root) {
        root.querySelectorAll('[data-print-id]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = parseInt(btn.dataset.printId, 10);
                const sale = allSales.find((s) => s.id === id);
                if (sale) printReceipt(sale);
            });
        });
    }

    function renderState(type, title, message, icon = 'inbox') {
        const html = `
            <div class="sh-state ${type === 'error' ? 'sh-state--error' : ''}">
                <span class="material-icons-round">${icon}</span>
                <h3>${escapeHtml(title)}</h3>
                <p>${escapeHtml(message)}</p>
            </div>`;

        if (els.tbody) {
            els.tbody.innerHTML = `<tr><td colspan="6">${html}</td></tr>`;
        }
        if (els.cards) els.cards.innerHTML = html;
    }

    function renderSales() {
        const filtered = getFilteredSales();
        updateSummary(filtered);

        if (!filtered.length) {
            const msg = searchQuery.trim()
                ? 'Aucun ticket ne correspond à votre recherche.'
                : period === 'today'
                  ? 'Aucune vente enregistrée aujourd\'hui.'
                  : 'Aucune vente trouvée.';
            renderState('', 'Aucune vente', msg, searchQuery.trim() ? 'search_off' : 'receipt_long');
            return;
        }

        if (els.tbody) {
            els.tbody.innerHTML = filtered
                .map((sale) => {
                    const receipt = escapeHtml(saleReceipt(sale));
                    const date = escapeHtml(CashierAPI.formatDate(saleDate(sale)));
                    const total = escapeHtml(CashierAPI.formatCurrency(saleTotal(sale)));
                    const pay = escapeHtml(CashierAPI.paymentLabel(sale.payment_method));
                    const payClass = payBadgeClass(sale.payment_method);
                    const customer = sale.customer_name
                        ? `<small>${escapeHtml(sale.customer_name)}</small>`
                        : '';
                    const viewUrl = `view_sale.php?id=${sale.id}`;

                    return `
                        <tr>
                            <td>
                                <span class="sh-receipt">${receipt}${customer}</span>
                            </td>
                            <td><span class="sh-date">${date}</span></td>
                            <td><span class="sh-total">${total}</span></td>
                            <td><span class="sh-pay-badge ${payClass}">${pay}</span></td>
                            <td>${sale.customer_name ? escapeHtml(sale.customer_name) : '<span style="color:var(--text-muted)">—</span>'}</td>
                            <td>
                                <div class="sh-actions">
                                    <a href="${viewUrl}" class="sh-action-btn" title="Voir le détail">
                                        <span class="material-icons-round">visibility</span>
                                    </a>
                                    <button type="button" class="sh-action-btn sh-action-btn--print" data-print-id="${sale.id}" title="Réimprimer">
                                        <span class="material-icons-round">print</span>
                                    </button>
                                </div>
                            </td>
                        </tr>`;
                })
                .join('');
            bindPrintButtons(els.tbody);
        }

        if (els.cards) {
            els.cards.innerHTML = filtered
                .map((sale) => {
                    const receipt = escapeHtml(saleReceipt(sale));
                    const date = escapeHtml(CashierAPI.formatDate(saleDate(sale)));
                    const total = escapeHtml(CashierAPI.formatCurrency(saleTotal(sale)));
                    const pay = escapeHtml(CashierAPI.paymentLabel(sale.payment_method));
                    const payClass = payBadgeClass(sale.payment_method);
                    const viewUrl = `view_sale.php?id=${sale.id}`;

                    return `
                        <article class="sh-card">
                            <div class="sh-card__top">
                                <span class="sh-card__receipt">${receipt}</span>
                                <span class="sh-card__total">${total}</span>
                            </div>
                            <div class="sh-card__meta">
                                <span class="sh-date">${date}</span>
                                <span class="sh-pay-badge ${payClass}">${pay}</span>
                                ${sale.customer_name ? `<span>${escapeHtml(sale.customer_name)}</span>` : ''}
                            </div>
                            <div class="sh-card__actions">
                                <a href="${viewUrl}" class="sh-action-btn">
                                    <span class="material-icons-round">visibility</span>
                                    <span class="label">Détail</span>
                                </a>
                                <button type="button" class="sh-action-btn sh-action-btn--print" data-print-id="${sale.id}">
                                    <span class="material-icons-round">print</span>
                                    <span class="label">Imprimer</span>
                                </button>
                            </div>
                        </article>`;
                })
                .join('');
            bindPrintButtons(els.cards);
        }
    }

    async function loadSales() {
        renderState('', 'Chargement…', 'Récupération des ventes en cours.', 'hourglass_empty');
        els.refreshBtn?.classList.add('spinning');

        try {
            const result = await CashierAPI.getSales({
                today: period === 'today',
                limit: 200,
            });

            if (result.status !== 'success') {
                throw new Error(result.message || 'Erreur API');
            }

            allSales = result.data || [];
            renderSales();
        } catch (err) {
            console.error('Sales history:', err);
            renderState('error', 'Erreur', err.message || 'Impossible de charger l\'historique.', 'error_outline');
        }

        els.refreshBtn?.classList.remove('spinning');
    }

    /* Filters */
    document.querySelectorAll('.sh-filter-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.sh-filter-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            period = btn.dataset.period || 'today';
            loadSales();
        });
    });

    els.searchInput?.addEventListener('input', (e) => {
        searchQuery = e.target.value;
        els.searchClear?.classList.toggle('visible', searchQuery.length > 0);
        renderSales();
    });

    els.searchClear?.addEventListener('click', () => {
        if (els.searchInput) els.searchInput.value = '';
        searchQuery = '';
        els.searchClear?.classList.remove('visible');
        renderSales();
        els.searchInput?.focus();
    });

    els.refreshBtn?.addEventListener('click', loadSales);

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

    loadSales();
});
