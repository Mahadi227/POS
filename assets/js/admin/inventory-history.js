/**
 * Admin inventory history and analytics scaffold.
 */
const InventoryHistoryUI = (() => {
    const config = window.INVENTORY_CONFIG || window.ADMIN_PAGE || {};
    const currency = config.currency || 'FCFA';

    const movementLabels = {
        purchase: 'Achat',
        sale: 'Vente',
        return: 'Retour',
        transfer_in: 'Transfert entrant',
        transfer_out: 'Transfert sortant',
        adjustment: 'Ajustement',
        damaged: 'Endommagé',
        expired: 'Périmé',
        manual_edit: 'Édition manuelle',
    };

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function formatCurrency(amount) {
        return `${Number(amount || 0).toLocaleString('fr-FR')} ${currency}`;
    }

    function formatDate(value) {
        if (!value) return '—';
        return new Date(value).toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function buildQuery() {
        const search = document.getElementById('historySearch')?.value.trim();
        const movementType = document.getElementById('historyMovementType')?.value;
        const storeId = document.getElementById('historyStore')?.value;
        const dateFrom = document.getElementById('historyDateFrom')?.value;
        const dateTo = document.getElementById('historyDateTo')?.value;
        const params = new URLSearchParams();

        if (search) params.set('q', search);
        if (movementType) params.set('type', movementType);
        if (storeId) params.set('store_id', storeId);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);

        return params.toString();
    }

    async function fetchLedger() {
        const query = buildQuery();
        const url = `../../api/v1/index.php?request=inventory/ledger${query ? `&${query}` : ''}`;

        try {
            const res = await fetch(url);
            const data = await res.json();
            return Array.isArray(data.data) ? data.data : [];
        } catch (error) {
            console.error(error);
            return [];
        }
    }

    function renderNoData() {
        const body = document.getElementById('inventoryHistoryBody');
        if (body) {
            body.innerHTML = '<tr><td colspan="16" class="ad-empty-row">Aucune entrée d historique disponible.</td></tr>';
        }
        document.getElementById('historyTotalEntries').textContent = '0 entrées';
        document.getElementById('historyTraceCount').textContent = '0 traçabilité';
    }

    function openTraceabilityModal(record) {
        const modalOverlay = document.getElementById('traceabilityModalOverlay');
        const modalContent = document.getElementById('traceabilityModalContent');
        if (!modalOverlay || !modalContent) return;

        modalContent.innerHTML = `
            <div><strong>Date :</strong> ${escapeHtml(formatDate(record.movement_date))}</div>
            <div><strong>Type :</strong> ${escapeHtml(movementLabels[record.movement_type] || record.movement_type)}</div>
            <div><strong>Produit :</strong> ${escapeHtml(record.product_name)}</div>
            <div><strong>SKU / Code-barre :</strong> ${escapeHtml(record.sku || record.barcode || '—')}</div>
            <div><strong>Magasin :</strong> ${escapeHtml(record.store_name || '—')}</div>
            <div><strong>Utilisateur :</strong> ${escapeHtml(record.user_name || '—')}</div>
            <div><strong>Quantité entrée :</strong> ${escapeHtml(record.stock_in)}</div>
            <div><strong>Quantité sortie :</strong> ${escapeHtml(record.stock_out)}</div>
            <div><strong>Stock après mouvement :</strong> ${escapeHtml(record.current_stock)}</div>
            <div><strong>Prix d'achat :</strong> ${formatCurrency(record.cost_price)}</div>
            <div><strong>Prix de vente :</strong> ${formatCurrency(record.sale_price)}</div>
            <div><strong>Valeur ouverture :</strong> ${formatCurrency(record.opening_value)}</div>
            <div><strong>Valeur sortie :</strong> ${formatCurrency(record.stock_out_value)}</div>
            <div><strong>Valeur actuelle :</strong> ${formatCurrency(record.current_stock_value)}</div>
            <div><strong>Profit estimé :</strong> ${formatCurrency(record.estimated_profit)}</div>
            <div><strong>Référence de traçabilité :</strong> ${escapeHtml(record.trace_id || '—')}</div>
            <div><strong>Notes :</strong> ${escapeHtml(record.notes || 'Aucune note')}</div>
        `;
        modalOverlay.style.display = 'flex';
    }

    function closeTraceabilityModal() {
        const modalOverlay = document.getElementById('traceabilityModalOverlay');
        if (modalOverlay) modalOverlay.style.display = 'none';
    }

    async function populateStores() {
        const select = document.getElementById('historyStore');
        if (!select || typeof AdminAPI?.listStores !== 'function') return;

        try {
            const response = await AdminAPI.listStores();
            const stores = Array.isArray(response.data) ? response.data : [];
            stores.forEach((store) => {
                const option = document.createElement('option');
                option.value = store.id;
                option.textContent = store.name || `Magasin ${store.id}`;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Impossible de charger les magasins pour les filtres', error);
        }
    }

    function getTraceCount(rows) {
        return rows.filter((row) => row.trace_id || row.notes).length;
    }

    function renderRows(rows) {
        const body = document.getElementById('inventoryHistoryBody');
        if (!body) return;

        if (!rows.length) {
            renderNoData();
            return;
        }

        body.innerHTML = rows.map((row, index) => {
            const traceAction = row.trace_id || row.notes ? `<button type="button" class="inv-btn inv-btn-secondary" data-row-index="${index}">Voir</button>` : '—';
            return `
                <tr>
                    <td>${escapeHtml(formatDate(row.movement_date))}</td>
                    <td>${escapeHtml(movementLabels[row.movement_type] || row.movement_type)}</td>
                    <td>${escapeHtml(row.product_name)}</td>
                    <td>${escapeHtml(row.sku || row.barcode || '—')}</td>
                    <td>${escapeHtml(row.opening_stock)}</td>
                    <td>${escapeHtml(row.stock_in)}</td>
                    <td>${escapeHtml(row.stock_out)}</td>
                    <td>${escapeHtml(row.current_stock)}</td>
                    <td>${escapeHtml(formatCurrency(row.opening_value))}</td>
                    <td>${escapeHtml(formatCurrency(row.stock_out_value))}</td>
                    <td>${escapeHtml(formatCurrency(row.current_stock_value))}</td>
                    <td>${escapeHtml(formatCurrency(row.estimated_profit))}</td>
                    <td>${escapeHtml(row.user_name || '—')}</td>
                    <td>${escapeHtml(row.store_name || '—')}</td>
                    <td>${escapeHtml(row.notes || '—')}</td>
                    <td>${traceAction}</td>
                </tr>`;
        }).join('');

        document.getElementById('historyTotalEntries').textContent = `${rows.length} entrée${rows.length > 1 ? 's' : ''}`;
        document.getElementById('historyTraceCount').textContent = `${getTraceCount(rows)} traçabilité`;

        body.querySelectorAll('[data-row-index]').forEach((button) => {
            button.addEventListener('click', (event) => {
                const rowIndex = Number(event.currentTarget.getAttribute('data-row-index'));
                if (!Number.isNaN(rowIndex) && currentRows[rowIndex]) {
                    openTraceabilityModal(currentRows[rowIndex]);
                }
            });
        });
    }

    let currentRows = [];
    let debounceTimer = null;

    async function renderHistoryTable() {
        const rows = await fetchLedger();
        currentRows = rows;
        renderRows(rows);
    }

    function resetFilters() {
        document.getElementById('historySearch').value = '';
        document.getElementById('historyMovementType').value = '';
        document.getElementById('historyStore').value = '';
        document.getElementById('historyDateFrom').value = '';
        document.getElementById('historyDateTo').value = '';
        renderHistoryTable();
    }

    function attachEvents() {
        document.getElementById('refreshHistory')?.addEventListener('click', renderHistoryTable);
        document.getElementById('clearHistoryFilters')?.addEventListener('click', resetFilters);
        document.getElementById('closeTraceabilityModalBtn')?.addEventListener('click', closeTraceabilityModal);
        document.getElementById('traceabilityModalOverlay')?.addEventListener('click', (event) => {
            if (event.target === event.currentTarget) closeTraceabilityModal();
        });

        const searchInput = document.getElementById('historySearch');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(renderHistoryTable, 350);
            });
        }

        ['historyMovementType', 'historyStore', 'historyDateFrom', 'historyDateTo'].forEach((id) => {
            document.getElementById(id)?.addEventListener('change', renderHistoryTable);
        });
    }

    async function init() {
        await populateStores();
        attachEvents();
        renderHistoryTable();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
