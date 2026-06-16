/**
 * Sales review — manager operations
 */
(() => {
    const root = document.getElementById('salesReviewRoot');
    if (!root) return;

    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    const mgrPrefix = window.MANAGER_CONFIG?.pagePrefix || '../';
    let activeFilter = 'all';
    let filterState = { period: 'today', from: null, to: null };
    let lastFetchAt = null;

    const els = {
        errorBanner: document.getElementById('mgrError'),
        lastUpdated: document.getElementById('lastUpdated'),
        periodFilter: document.getElementById('srPeriodFilter'),
        countTotal: document.getElementById('srCountTotal'),
        countCancelled: document.getElementById('srCountCancelled'),
        countDiscount: document.getElementById('srCountDiscount'),
        countHigh: document.getElementById('srCountHigh'),
        tableCount: document.getElementById('srTableCount'),
        thresholdHint: document.getElementById('srThresholdHint'),
        summaryCards: document.querySelectorAll('#srSummary .ad-stat-card'),
        flagBar: document.getElementById('srFlagBar'),
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

    function setPeriodActive(period) {
        els.periodFilter?.querySelectorAll('.mgr-period-btn').forEach((btn) => {
            const active = btn.dataset.period === period;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function syncFlagBar() {
        els.flagBar?.querySelectorAll('[data-filter]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.filter === activeFilter);
        });
    }

    function buildQuery() {
        const query = { period: filterState.period, filter: activeFilter };
        if (filterState.period === 'custom') {
            query.from = filterState.from;
            query.to = filterState.to;
        }
        return query;
    }

    function flagLabel(type) {
        const map = {
            cancelled: t('flag_cancelled'),
            pending: t('flag_pending'),
            high_discount: t('flag_high_discount'),
            high_amount: t('flag_high_amount'),
        };
        return map[type] || type;
    }

    function flagBadgeClass(type) {
        if (type === 'cancelled') return 'mgr-badge--off';
        if (type === 'pending') return 'mgr-badge--idle';
        if (type === 'high_discount') return 'mgr-badge--idle';
        return 'mgr-badge--off';
    }

    function statusLabel(status) {
        if (status === 'completed') return t('sale_status_completed');
        if (status === 'cancelled') return t('sale_status_cancelled');
        if (status === 'pending') return t('sale_status_pending');
        return status || '—';
    }

    function updateSummary(summary, meta) {
        const s = summary || {};
        if (els.countTotal) els.countTotal.textContent = String(s.total ?? 0);
        if (els.countCancelled) els.countCancelled.textContent = String(s.cancelled ?? 0);
        if (els.countDiscount) els.countDiscount.textContent = String(s.high_discount ?? 0);
        if (els.countHigh) els.countHigh.textContent = String(s.high_amount ?? 0);
        if (els.thresholdHint && meta?.high_threshold != null) {
            els.thresholdHint.textContent = t(
                'sales_review_threshold_hint',
                ManagerAPI.formatCurrency(meta.high_threshold),
                ManagerAPI.formatCurrency(meta.avg_ticket ?? 0)
            );
        }
    }

    function resetSummary() {
        ['countTotal', 'countCancelled', 'countDiscount', 'countHigh', 'tableCount'].forEach((key) => {
            if (els[key]) els[key].textContent = '—';
        });
    }

    function saleViewUrl(id) {
        return `${mgrPrefix}../cashier/view_sale.php?id=${encodeURIComponent(String(id))}`;
    }

    function renderTable(items) {
        const cols = {
            receipt: t('col_receipt'),
            cashier: t('cashier_label'),
            date: t('col_date'),
            total: t('col_total'),
            discount: t('col_discount'),
            status: t('col_status'),
            flag: t('col_flag'),
            actions: t('col_actions'),
        };

        root.innerHTML = `<div class="mgr-table-wrap"><table class="modern-table mgr-sr-table"><thead><tr>
            <th>${esc(cols.receipt)}</th>
            <th>${esc(cols.cashier)}</th>
            <th>${esc(cols.date)}</th>
            <th>${esc(cols.total)}</th>
            <th>${esc(cols.discount)}</th>
            <th>${esc(cols.status)}</th>
            <th>${esc(cols.flag)}</th>
            <th>${esc(cols.actions)}</th>
        </tr></thead><tbody>${items.map((row) => {
            const flag = row.flag_type || 'high_amount';
            const rowClass = ['cancelled', 'high_amount'].includes(flag) ? 'mgr-sr-row--critical' : '';
            return `<tr class="${rowClass}">
            <td data-label="${escAttr(cols.receipt)}"><strong>${esc(row.receipt_no || '—')}</strong></td>
            <td data-label="${escAttr(cols.cashier)}">${esc(row.cashier_name || '—')}</td>
            <td data-label="${escAttr(cols.date)}">${esc(ManagerAPI.formatDate(row.created_at))}</td>
            <td data-label="${escAttr(cols.total)}">${esc(ManagerAPI.formatCurrency(row.total))}</td>
            <td data-label="${escAttr(cols.discount)}">${esc(ManagerAPI.formatCurrency(row.discount))}</td>
            <td data-label="${escAttr(cols.status)}">${esc(statusLabel(row.status))}</td>
            <td data-label="${escAttr(cols.flag)}"><span class="mgr-badge ${flagBadgeClass(flag)}">${esc(flagLabel(flag))}</span></td>
            <td data-label="${escAttr(cols.actions)}">
                <a class="mgr-sr-view-link" href="${escAttr(saleViewUrl(row.id))}" target="_blank" rel="noopener">
                    ${esc(t('view_sale_btn'))}
                </a>
            </td>
        </tr>`;
        }).join('')}</tbody></table></div>`;
    }

    async function load() {
        hideError();
        setSummaryLoading(true);
        syncFlagBar();
        root.innerHTML = `<div class="mgr-list mgr-list--loading">${esc(t('loading'))}</div>`;

        try {
            const res = await ManagerAPI.getSalesReview(buildQuery());
            if (res.status !== 'success') {
                throw new Error(res.message || t('load_error'));
            }

            const data = res.data || {};
            const items = data.items || [];
            updateSummary(data.summary, data);
            if (els.tableCount) els.tableCount.textContent = String(items.length);
            lastFetchAt = new Date();
            updateLastUpdated();

            if (!items.length) {
                root.innerHTML = `<p class="mgr-empty">${esc(t('no_sales_review'))}</p>`;
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

    els.periodFilter?.addEventListener('click', (e) => {
        const btn = e.target.closest('.mgr-period-btn');
        if (!btn || btn.dataset.period === filterState.period) return;
        filterState.period = btn.dataset.period;
        setPeriodActive(filterState.period);
        load();
    });

    els.flagBar?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-filter]');
        if (!btn || btn.dataset.filter === activeFilter) return;
        activeFilter = btn.dataset.filter;
        load();
    });

    document.addEventListener('DOMContentLoaded', () => {
        setPeriodActive(filterState.period);
        load();
    });
    document.addEventListener('mgr:refresh', load);
})();
