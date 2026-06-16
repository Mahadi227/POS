/**
 * Cash reconciliation — manager operations
 */
(() => {
    const root = document.getElementById('cashReconRoot');
    if (!root) return;

    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    let activeFilter = 'open';
    let lastFetchAt = null;

    const els = {
        errorBanner: document.getElementById('mgrError'),
        lastUpdated: document.getElementById('lastUpdated'),
        countOpen: document.getElementById('crCountOpen'),
        totalExpected: document.getElementById('crTotalExpected'),
        totalCounted: document.getElementById('crTotalCounted'),
        totalVariance: document.getElementById('crTotalVariance'),
        tableCount: document.getElementById('crTableCount'),
        summaryCards: document.querySelectorAll('#crSummary .ad-stat-card'),
        filterBar: document.getElementById('crFilterBar'),
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

    function syncFilterBar() {
        els.filterBar?.querySelectorAll('[data-filter]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.filter === activeFilter);
        });
    }

    function money(value) {
        if (value === null || value === undefined || value === '') return '—';
        return ManagerAPI.formatCurrency(value);
    }

    function reconLabel(status) {
        const map = {
            open: t('recon_status_open'),
            balanced: t('recon_status_balanced'),
            short: t('recon_status_short'),
            over: t('recon_status_over'),
        };
        return map[status] || status;
    }

    function reconBadgeClass(status) {
        if (status === 'balanced') return 'mgr-badge--ok';
        if (status === 'open') return 'mgr-badge--idle';
        return 'mgr-badge--off';
    }

    function shiftStatusLabel(status) {
        if (status === 'open') return t('shift_status_open');
        if (status === 'closed') return t('shift_status_closed');
        return status || '—';
    }

    function updateSummary(summary) {
        const s = summary || {};
        if (els.countOpen) els.countOpen.textContent = String(s.open_shifts ?? 0);
        if (els.totalExpected) els.totalExpected.textContent = money(s.total_expected);
        if (els.totalCounted) els.totalCounted.textContent = money(s.total_counted);
        if (els.totalVariance) {
            const variance = s.total_variance ?? 0;
            els.totalVariance.textContent = money(variance);
            els.totalVariance.classList.toggle('is-negative', variance < 0);
            els.totalVariance.classList.toggle('is-positive', variance > 0);
        }
    }

    function resetSummary() {
        ['countOpen', 'tableCount'].forEach((key) => {
            if (els[key]) els[key].textContent = '—';
        });
        ['totalExpected', 'totalCounted', 'totalVariance'].forEach((key) => {
            if (els[key]) els[key].textContent = '—';
        });
    }

    function renderTable(items) {
        const cols = {
            cashier: t('cashier_label'),
            opened: t('col_opened'),
            float: t('col_opening_float'),
            cashSales: t('col_cash_sales'),
            expected: t('col_expected_cash'),
            counted: t('col_counted_cash'),
            variance: t('col_variance'),
            shift: t('col_status'),
            recon: t('col_recon_status'),
        };

        root.innerHTML = `<div class="mgr-table-wrap"><table class="modern-table mgr-cr-table"><thead><tr>
            <th>${esc(cols.cashier)}</th>
            <th>${esc(cols.opened)}</th>
            <th>${esc(cols.float)}</th>
            <th>${esc(cols.cashSales)}</th>
            <th>${esc(cols.expected)}</th>
            <th>${esc(cols.counted)}</th>
            <th>${esc(cols.variance)}</th>
            <th>${esc(cols.shift)}</th>
            <th>${esc(cols.recon)}</th>
        </tr></thead><tbody>${items.map((row) => {
            const recon = row.reconciliation_status || 'open';
            const rowClass = recon === 'short' || recon === 'over' ? 'mgr-cr-row--variance' : '';
            const variance = row.variance;
            const varianceClass = variance < 0 ? 'mgr-cr-variance--short' : variance > 0 ? 'mgr-cr-variance--over' : '';
            return `<tr class="${rowClass}">
            <td class="mgr-cashier-cell" data-label="${escAttr(cols.cashier)}">
                <strong>${esc(row.cashier_name)}</strong>
                ${row.id ? `<span class="mgr-muted">#${esc(String(row.id))}</span>` : ''}
            </td>
            <td data-label="${escAttr(cols.opened)}">${esc(ManagerAPI.formatRelative(row.opened_at))}<br><span class="mgr-muted">${esc(ManagerAPI.formatDate(row.opened_at))}</span></td>
            <td data-label="${escAttr(cols.float)}">${esc(money(row.opening_float))}</td>
            <td data-label="${escAttr(cols.cashSales)}">${esc(money(row.cash_sales))}</td>
            <td data-label="${escAttr(cols.expected)}"><strong>${esc(money(row.expected_cash))}</strong></td>
            <td data-label="${escAttr(cols.counted)}">${esc(money(row.counted_cash))}</td>
            <td data-label="${escAttr(cols.variance)}" class="${varianceClass}">${esc(money(row.variance))}</td>
            <td data-label="${escAttr(cols.shift)}">${esc(shiftStatusLabel(row.status))}</td>
            <td data-label="${escAttr(cols.recon)}"><span class="mgr-badge ${reconBadgeClass(recon)}">${esc(reconLabel(recon))}</span></td>
        </tr>`;
        }).join('')}</tbody></table></div>`;
    }

    async function load() {
        hideError();
        setSummaryLoading(true);
        syncFilterBar();
        root.innerHTML = `<div class="mgr-list mgr-list--loading">${esc(t('loading'))}</div>`;

        try {
            const res = await ManagerAPI.getCashReconciliation(activeFilter);
            if (res.status !== 'success') {
                throw new Error(res.message || t('load_error'));
            }

            const data = res.data || {};
            const items = data.items || [];
            updateSummary(data.summary);
            if (els.tableCount) els.tableCount.textContent = String(items.length);
            lastFetchAt = new Date();
            updateLastUpdated();

            if (!items.length) {
                root.innerHTML = `<p class="mgr-empty">${esc(t('no_cash_recon'))}</p>`;
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

    els.filterBar?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-filter]');
        if (!btn || btn.dataset.filter === activeFilter) return;
        activeFilter = btn.dataset.filter;
        load();
    });

    document.addEventListener('DOMContentLoaded', load);
    document.addEventListener('mgr:refresh', load);
})();
