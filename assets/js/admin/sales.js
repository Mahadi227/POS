/**
 * Ventes admin — liste dynamique, stats, détails, impression
 */
(() => {
    const PAGE_SIZE = 20;
    const PAY_CLASS = {
        cash: 'as-pay-badge--cash',
        card: 'as-pay-badge--card',
        mobile_money: 'as-pay-badge--mobile_money',
    };

    const $ = (id) => document.getElementById(id);
    let allSales = [];
    let period = 'today';
    let paymentFilter = '';
    let searchQuery = '';
    let startDate = '';
    let endDate = '';
    let currentPage = 1;
    let currentSaleId = null;
    let searchDebounce = null;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function showError(msg) {
        const banner = $('salesError');
        if (!banner) return;
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        banner.classList.add('is-visible');
    }

    function hideError() {
        $('salesError')?.classList.remove('is-visible');
    }

    function toast(msg, type = 'success') {
        const el = $('asToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `as-toast show ${type === 'error' ? 'error' : ''}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3000);
    }

    function saleReceipt(s) {
        return s.receipt_no || s.receipt_number || `#${s.id}`;
    }

    function saleTotal(s) {
        return parseFloat(s.total ?? s.total_amount ?? 0);
    }

    function saleDate(s) {
        return s.created_at || s.sale_date;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.as-stat').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function formatSalesDateLabel() {
        const formatDateOnly = (dateString) => {
            if (!dateString) return '—';
            return new Date(dateString).toLocaleDateString('fr-FR', { dateStyle: 'long' });
        };

        if (startDate || endDate) {
            if (startDate && endDate) {
                return `${formatDateOnly(startDate)} – ${formatDateOnly(endDate)}`;
            }
            if (startDate) {
                return `À partir du ${formatDateOnly(startDate)}`;
            }
            return `Jusqu'au ${formatDateOnly(endDate)}`;
        }

        switch (period) {
            case 'today': return 'Aujourd\'hui';
            case 'week': return '7 derniers jours';
            case 'month': return '30 derniers jours';
            default: return 'Toutes les ventes';
        }
    }

    function updateDateHeader() {
        const header = document.getElementById('sales-date');
        if (!header) return;
        header.textContent = formatSalesDateLabel();
    }

    function updateStatsUI(data) {
        setStatsLoading(false);
        $('stat-today-count').textContent = String(data.today_count ?? 0);
        $('stat-today-revenue').textContent = AdminAPI.formatCurrency(data.today_revenue);
        $('stat-today-avg').textContent = AdminAPI.formatCurrency(data.today_avg);
        $('stat-week-count').textContent = String(data.week_count ?? 0);
        $('stat-week-revenue').textContent = AdminAPI.formatCurrency(data.week_revenue);
        $('stat-month-count').textContent = String(data.month_count ?? 0);
        $('stat-month-revenue').textContent = AdminAPI.formatCurrency(data.month_revenue);
    }

    function getFilteredSales() {
        let list = [...allSales];
        const q = searchQuery.trim().toLowerCase();

        if (q) {
            list = list.filter((s) => {
                const receipt = saleReceipt(s).toLowerCase();
                const customer = (s.customer_name || '').toLowerCase();
                const cashier = (s.cashier_name || '').toLowerCase();
                return receipt.includes(q) || customer.includes(q) || cashier.includes(q) || String(s.id).includes(q);
            });
        }

        list.sort((a, b) => new Date(saleDate(b)) - new Date(saleDate(a)));
        return list;
    }

    function payBadge(method) {
        const cls = PAY_CLASS[method] || 'as-pay-badge--default';
        const label = AdminAPI.paymentLabel(method);
        return `<span class="as-pay-badge ${cls}">${escapeHtml(label)}</span>`;
    }

    function statusBadge(status) {
        const cls = AdminAPI.statusClass(status);
        return `<span class="status-badge ${cls}">${escapeHtml(AdminAPI.statusLabel(status))}</span>`;
    }

    function renderSales() {
        const filtered = getFilteredSales();
        const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * PAGE_SIZE;
        const pageItems = filtered.slice(start, start + PAGE_SIZE);
        const filteredRevenue = filtered.reduce((sum, s) => sum + saleTotal(s), 0);

        $('tableSummary').textContent = filtered.length === 0
            ? 'Aucune vente'
            : `${filtered.length} vente(s) — ${AdminAPI.formatCurrency(filteredRevenue)} — page ${currentPage}/${totalPages}`;
        $('pageInfo').textContent = `${currentPage} / ${totalPages}`;
        $('pagePrev').disabled = currentPage <= 1;
        $('pageNext').disabled = currentPage >= totalPages;

        const tbody = $('salesTableBody');
        tbody.innerHTML = '';

        if (!pageItems.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="ad-empty-row">Aucune vente trouvée</td></tr>';
            return;
        }

        pageItems.forEach((sale) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <a href="#" class="receipt-link" data-view-id="${sale.id}">${escapeHtml(saleReceipt(sale))}</a>
                </td>
                <td style="color:var(--text-secondary);white-space:nowrap;">${AdminAPI.formatDate(saleDate(sale))}</td>
                <td>${escapeHtml(sale.customer_name || '—')}</td>
                <td>${escapeHtml(sale.cashier_name || 'Système')}</td>
                <td style="font-weight:700;">${AdminAPI.formatCurrency(saleTotal(sale))}</td>
                <td>${payBadge(sale.payment_method)}</td>
                <td>${statusBadge(sale.status)}</td>
                <td>
                    <div class="as-row-actions">
                        <button type="button" class="icon-btn view-btn" data-id="${sale.id}" title="Détails">
                            <span class="material-icons-round" style="font-size:18px;">visibility</span>
                        </button>
                        <button type="button" class="icon-btn print-btn" data-id="${sale.id}" title="Imprimer">
                            <span class="material-icons-round" style="font-size:18px;color:var(--primary);">print</span>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('[data-view-id]').forEach((a) => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                viewSaleDetails(a.getAttribute('data-view-id'));
            });
        });
        tbody.querySelectorAll('.view-btn').forEach((btn) => {
            btn.addEventListener('click', () => viewSaleDetails(btn.getAttribute('data-id')));
        });
        tbody.querySelectorAll('.print-btn').forEach((btn) => {
            btn.addEventListener('click', () => printReceipt(parseInt(btn.getAttribute('data-id'), 10)));
        });
    }

    function printReceipt(saleId) {
        const sale = allSales.find((s) => s.id === saleId);
        const url = new URL('../../receipts/templates/thermal-80mm.php', window.location.href);
        if (saleId) url.searchParams.set('id', String(saleId));
        else if (sale) {
            const r = sale.receipt_no || sale.receipt_number;
            if (r) url.searchParams.set('receipt_no', r);
        }
        const win = window.open(url.toString(), 'ReceiptPrint', 'width=420,height=720,scrollbars=yes');
        if (!win) toast('Autorisez les fenêtres pop-up pour imprimer', 'error');
    }

    async function loadStats() {
        setStatsLoading(true);
        try {
            const result = await AdminAPI.getSalesStats();
            if (result.status === 'success') {
                updateStatsUI(result.data);
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadSales() {
        const tbody = $('salesTableBody');
        tbody.innerHTML = '<tr><td colspan="8" class="ad-empty-row">Chargement…</td></tr>';

        const query = { period, limit: 200 };
        if (paymentFilter) query.payment = paymentFilter;
        if (startDate) query.start_date = startDate;
        if (endDate) query.end_date = endDate;
        if (startDate || endDate) query.period = 'all';

        try {
            const result = await AdminAPI.getSales(query);
            hideError();
            if (result.status === 'success') {
                allSales = result.data || [];
                currentPage = 1;
                renderSales();
            } else {
                showError(result.message || 'Erreur de chargement');
                tbody.innerHTML = `<tr><td colspan="8" class="ad-empty-row">${escapeHtml(result.message || 'Erreur')}</td></tr>`;
            }
        } catch (e) {
            console.error(e);
            showError('Connexion impossible');
            tbody.innerHTML = '<tr><td colspan="8" class="ad-empty-row">Erreur réseau</td></tr>';
        }
    }

    async function refreshAll() {
        const btn = $('refreshSales');
        btn?.classList.add('spinning');
        await Promise.all([loadStats(), loadSales()]);
        btn?.classList.remove('spinning');
    }

    function openModal() {
        $('saleDetailsModal')?.classList.add('active');
    }

    function closeModal() {
        $('saleDetailsModal')?.classList.remove('active');
        currentSaleId = null;
    }

    async function viewSaleDetails(id) {
        currentSaleId = parseInt(id, 10);
        const content = $('saleDetailsContent');
        content.innerHTML = '<p class="ad-empty-row">Chargement…</p>';
        openModal();

        try {
            const result = await AdminAPI.getSale(id);
            if (result.status !== 'success') {
                content.innerHTML = `<p style="color:var(--danger);">${escapeHtml(result.message || 'Erreur')}</p>`;
                return;
            }

            const info = result.data;
            const items = info.items || [];

            let rows = '';
            items.forEach((item) => {
                rows += `
                    <tr>
                        <td>${escapeHtml(item.product_name)}<br><small style="color:var(--text-muted);">${escapeHtml(item.sku)}</small></td>
                        <td>${item.quantity}</td>
                        <td>${AdminAPI.formatCurrency(item.unit_price)}</td>
                        <td>${AdminAPI.formatCurrency(item.subtotal)}</td>
                    </tr>`;
            });

            content.innerHTML = `
                <div class="as-detail-meta">
                    <div><strong>Reçu</strong>${escapeHtml(saleReceipt(info))}</div>
                    <div><strong>Date</strong>${AdminAPI.formatDate(saleDate(info))}</div>
                    <div><strong>Caissier</strong>${escapeHtml(info.cashier_name || '—')}</div>
                    <div><strong>Client</strong>${escapeHtml(info.customer_name || '—')}</div>
                    <div><strong>Paiement</strong>${payBadge(info.payment_method)}</div>
                    <div><strong>Statut</strong>${statusBadge(info.status)}</div>
                    ${info.store_name ? `<div><strong>Magasin</strong>${escapeHtml(info.store_name)}</div>` : ''}
                </div>
                <table class="as-receipt-items">
                    <thead>
                        <tr><th>Produit</th><th>Qté</th><th>Prix unit.</th><th>Sous-total</th></tr>
                    </thead>
                    <tbody>${rows || '<tr><td colspan="4">Aucun article</td></tr>'}</tbody>
                </table>
                <div class="as-receipt-summary">
                    <div><span style="color:var(--text-secondary);">Sous-total:</span> <strong>${AdminAPI.formatCurrency(info.subtotal ?? 0)}</strong></div>
                    <div><span style="color:var(--text-secondary);">Taxe:</span> <strong>${AdminAPI.formatCurrency(info.tax_amount ?? info.tax ?? 0)}</strong></div>
                    <div><span style="color:var(--text-secondary);">Remise:</span> <strong>${AdminAPI.formatCurrency(info.discount_amount ?? info.discount ?? 0)}</strong></div>
                    <div class="total-line">Total: ${AdminAPI.formatCurrency(info.total_amount ?? info.total ?? 0)}</div>
                </div>`;

            $('modalTitle').textContent = `Vente ${saleReceipt(info)}`;
        } catch (e) {
            console.error(e);
            content.innerHTML = '<p style="color:var(--danger);">Erreur de connexion</p>';
        }
    }

    function setActivePeriodChip(periodValue) {
        document.querySelectorAll('.as-chip').forEach((c) => {
            c.classList.toggle('active', c.dataset.period === periodValue);
        });
    }

    function initEvents() {
        document.querySelectorAll('.as-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('.as-chip').forEach((c) => c.classList.remove('active'));
                chip.classList.add('active');
                period = chip.dataset.period || 'all';
                startDate = '';
                endDate = '';
                if ($('salesStartDate')) $('salesStartDate').value = '';
                if ($('salesEndDate')) $('salesEndDate').value = '';
                updateDateHeader();
                loadSales();
            });
        });

        $('paymentFilter')?.addEventListener('change', (e) => {
            paymentFilter = e.target.value;
            loadSales();
        });

        $('applyDateFilter')?.addEventListener('click', () => {
            startDate = $('salesStartDate')?.value || '';
            endDate = $('salesEndDate')?.value || '';
            period = 'all';
            setActivePeriodChip('all');
            updateDateHeader();
            loadSales();
        });

        $('clearDateFilter')?.addEventListener('click', () => {
            startDate = '';
            endDate = '';
            period = 'all';
            setActivePeriodChip('all');
            if ($('salesStartDate')) $('salesStartDate').value = '';
            if ($('salesEndDate')) $('salesEndDate').value = '';
            updateDateHeader();
            loadSales();
        });

        $('searchInput')?.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            $('searchClear')?.classList.toggle('visible', !!searchQuery.trim());
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                currentPage = 1;
                renderSales();
            }, 280);
        });

        $('searchInput')?.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            $('searchClear')?.classList.toggle('visible', !!searchQuery.trim());
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                currentPage = 1;
                renderSales();
            }, 280);
        });

        $('searchClear')?.addEventListener('click', () => {
            $('searchInput').value = '';
            searchQuery = '';
            $('searchClear')?.classList.remove('visible');
            currentPage = 1;
            renderSales();
        });

        $('pagePrev')?.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderSales();
            }
        });
        $('pageNext')?.addEventListener('click', () => {
            currentPage++;
            renderSales();
        });

        $('refreshSales')?.addEventListener('click', () => refreshAll());

        $('closeModalBtn')?.addEventListener('click', closeModal);
        $('printReceiptBtn')?.addEventListener('click', () => {
            if (currentSaleId) printReceipt(currentSaleId);
        });

        $('saleDetailsModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'saleDetailsModal') closeModal();
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initEvents();
        updateDateHeader();
        refreshAll();
    });
})();
