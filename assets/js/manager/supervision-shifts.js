/**
 * Shift supervision page
 */
(() => {
    const root = document.getElementById('shiftsRoot');
    if (!root) return;

    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    let lastFetchAt = null;

    const els = {
        errorBanner: document.getElementById('mgrError'),
        lastUpdated: document.getElementById('lastUpdated'),
        countOpen: document.getElementById('shiftsCountOpen'),
        totalSales: document.getElementById('shiftsTotalSales'),
        totalTx: document.getElementById('shiftsTotalTx'),
        totalFloat: document.getElementById('shiftsTotalFloat'),
        tableCount: document.getElementById('shiftsTableCount'),
        summaryCards: document.querySelectorAll('#shiftsSummary .ad-stat-card'),
    };

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function escAttr(s) {
        return String(s ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function showError(msg) {
        if (!els.errorBanner) return;
        els.errorBanner.classList.add('is-visible');
        const text = els.errorBanner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        els.errorBanner?.classList.remove('is-visible');
    }

    function updateLastUpdated() {
        if (!els.lastUpdated || !lastFetchAt) return;
        const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        els.lastUpdated.textContent = `${t('last_updated')} · ${time}`;
    }

    function setSummaryLoading(loading) {
        els.summaryCards.forEach((card) => card.classList.toggle('is-loading', loading));
    }

    function statusLabel(status) {
        if (status === 'open') return t('shift_status_open');
        if (status === 'closed') return t('shift_status_closed');
        return status || '—';
    }

    function statusBadgeClass(status) {
        if (status === 'open') return 'mgr-badge--ok';
        return 'mgr-badge--off';
    }

    function updateSummary(items) {
        const open = items.length;
        const sales = items.reduce((sum, row) => sum + Number(row.total_sales || 0), 0);
        const tx = items.reduce((sum, row) => sum + Number(row.transaction_count || 0), 0);
        const floatTotal = items.reduce((sum, row) => sum + Number(row.opening_float || 0), 0);

        if (els.countOpen) els.countOpen.textContent = String(open);
        if (els.totalSales) els.totalSales.textContent = ManagerAPI.formatCurrency(sales);
        if (els.totalTx) els.totalTx.textContent = String(tx);
        if (els.totalFloat) els.totalFloat.textContent = ManagerAPI.formatCurrency(floatTotal);
        if (els.tableCount) els.tableCount.textContent = String(open);
    }

    function resetSummary() {
        ['countOpen', 'totalSales', 'totalTx', 'totalFloat', 'tableCount'].forEach((key) => {
            if (els[key]) els[key].textContent = '—';
        });
    }

    function renderTable(items) {
        const cols = {
            cashier: t('cashier_label'),
            opened: t('col_opened'),
            float: t('col_opening_float'),
            sales: t('col_sales'),
            tx: t('col_transactions'),
            status: t('col_status'),
        };

        root.innerHTML = `<div class="mgr-table-wrap"><table class="modern-table mgr-shifts-table"><thead><tr>
            <th>${esc(cols.cashier)}</th>
            <th>${esc(cols.opened)}</th>
            <th>${esc(cols.float)}</th>
            <th>${esc(cols.sales)}</th>
            <th>${esc(cols.tx)}</th>
            <th>${esc(cols.status)}</th>
        </tr></thead><tbody>${items.map((s) => {
            const status = s.status || 'open';
            const rowClass = status === 'open' ? 'mgr-shifts-row--open' : '';
            return `<tr class="${rowClass}">
            <td class="mgr-cashier-cell" data-label="${escAttr(cols.cashier)}">
                <strong>${esc(s.cashier_name)}</strong>
                ${s.id ? `<span class="mgr-muted">#${esc(String(s.id))}</span>` : ''}
            </td>
            <td data-label="${escAttr(cols.opened)}">${esc(ManagerAPI.formatRelative(s.opened_at))}<br><span class="mgr-muted">${esc(ManagerAPI.formatDate(s.opened_at))}</span></td>
            <td data-label="${escAttr(cols.float)}">${esc(ManagerAPI.formatCurrency(s.opening_float))}</td>
            <td data-label="${escAttr(cols.sales)}">${esc(ManagerAPI.formatCurrency(s.total_sales))}</td>
            <td data-label="${escAttr(cols.tx)}">${esc(String(s.transaction_count ?? 0))}</td>
            <td data-label="${escAttr(cols.status)}"><span class="mgr-badge ${statusBadgeClass(status)}">${esc(statusLabel(status))}</span></td>
        </tr>`;
        }).join('')}</tbody></table></div>`;
    }

    async function load() {
        hideError();
        setSummaryLoading(true);
        root.innerHTML = `<div class="mgr-list mgr-list--loading">${esc(t('loading'))}</div>`;

        try {
            const res = await ManagerAPI.getShifts();
            if (res.status !== 'success') {
                throw new Error(res.message || t('load_error'));
            }

            const items = res.data || [];
            updateSummary(items);
            lastFetchAt = new Date();
            updateLastUpdated();

            if (!items.length) {
                root.innerHTML = `<p class="mgr-empty">${esc(t('no_open_shifts'))}</p>`;
            } else {
                renderTable(items);
            }
        } catch (e) {
            console.error(e);
            const msg = e.message || t('load_error');
            showError(msg);
            root.innerHTML = `<p class="mgr-empty">${esc(msg)}</p>`;
            resetSummary();
        }

        setSummaryLoading(false);
    }

    document.addEventListener('DOMContentLoaded', load);
    document.addEventListener('mgr:refresh', load);
})();
