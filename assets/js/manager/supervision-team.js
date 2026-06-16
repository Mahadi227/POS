/**
 * Team performance supervision page
 */
(() => {
    const root = document.getElementById('teamPerformanceRoot');
    if (!root) return;

    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    let lastFetchAt = null;
    let filterState = { period: 'today', from: null, to: null };

    const els = {
        errorBanner: document.getElementById('mgrError'),
        lastUpdated: document.getElementById('lastUpdated'),
        periodFilter: document.getElementById('teamPeriodFilter'),
        dateRange: document.getElementById('teamDateRange'),
        dateFrom: document.getElementById('teamDateFrom'),
        dateTo: document.getElementById('teamDateTo'),
        dateApply: document.getElementById('teamDateApply'),
        countActive: document.getElementById('teamCountActive'),
        totalRevenue: document.getElementById('teamTotalRevenue'),
        avgTicket: document.getElementById('teamAvgTicket'),
        totalReturns: document.getElementById('teamTotalReturns'),
        tableCount: document.getElementById('teamTableCount'),
        summaryCards: document.querySelectorAll('#teamSummary .ad-stat-card'),
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

    function todayIso() {
        return new Date().toISOString().slice(0, 10);
    }

    function initDateInputs() {
        const today = todayIso();
        if (els.dateFrom && !els.dateFrom.value) els.dateFrom.value = today;
        if (els.dateTo && !els.dateTo.value) els.dateTo.value = today;
        if (els.dateFrom) els.dateFrom.max = today;
        if (els.dateTo) els.dateTo.max = today;
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
        if (els.dateRange) {
            const showCustom = period === 'custom';
            els.dateRange.hidden = !showCustom;
            els.dateRange.classList.toggle('is-visible', showCustom);
        }
    }

    function buildQuery() {
        const query = { period: filterState.period };
        if (filterState.period === 'custom') {
            query.from = filterState.from;
            query.to = filterState.to;
        }
        return query;
    }

    function updateSummary(summary) {
        const s = summary || {};
        if (els.countActive) els.countActive.textContent = String(s.active_cashiers ?? 0);
        if (els.totalRevenue) els.totalRevenue.textContent = ManagerAPI.formatCurrency(s.total_revenue);
        if (els.avgTicket) els.avgTicket.textContent = ManagerAPI.formatCurrency(s.avg_ticket);
        if (els.totalReturns) els.totalReturns.textContent = String(s.total_returns ?? 0);
        if (els.tableCount) els.tableCount.textContent = String(s.active_cashiers ?? 0);
    }

    function resetSummary() {
        ['countActive', 'totalRevenue', 'avgTicket', 'totalReturns', 'tableCount'].forEach((key) => {
            if (els[key]) els[key].textContent = '—';
        });
    }

    function renderTable(cashiers) {
        const cols = {
            rank: t('col_rank'),
            cashier: t('cashier_label'),
            revenue: t('col_revenue'),
            tx: t('col_transactions'),
            avg: t('col_avg_ticket'),
            returns: t('col_returns'),
            lastSale: t('col_last_sale'),
        };

        root.innerHTML = `<div class="mgr-table-wrap"><table class="modern-table mgr-team-table"><thead><tr>
            <th>${esc(cols.rank)}</th>
            <th>${esc(cols.cashier)}</th>
            <th>${esc(cols.revenue)}</th>
            <th>${esc(cols.tx)}</th>
            <th>${esc(cols.avg)}</th>
            <th>${esc(cols.returns)}</th>
            <th>${esc(cols.lastSale)}</th>
        </tr></thead><tbody>${cashiers.map((row, index) => {
            const rank = index + 1;
            const rowClass = rank === 1 ? 'mgr-team-row--top' : '';
            const returnsLabel = (row.returns_count ?? 0) > 0
                ? `${row.returns_count} · ${ManagerAPI.formatCurrency(row.returns_amount)}`
                : '—';
            return `<tr class="${rowClass}">
            <td data-label="${escAttr(cols.rank)}"><span class="mgr-rank">${esc(String(rank))}</span></td>
            <td class="mgr-cashier-cell" data-label="${escAttr(cols.cashier)}">
                <strong>${esc(row.cashier_name)}</strong>
            </td>
            <td data-label="${escAttr(cols.revenue)}"><strong>${esc(ManagerAPI.formatCurrency(row.revenue))}</strong></td>
            <td data-label="${escAttr(cols.tx)}">${esc(String(row.transactions ?? 0))}</td>
            <td data-label="${escAttr(cols.avg)}">${esc(ManagerAPI.formatCurrency(row.avg_ticket))}</td>
            <td data-label="${escAttr(cols.returns)}">${esc(returnsLabel)}</td>
            <td data-label="${escAttr(cols.lastSale)}">${esc(ManagerAPI.formatRelative(row.last_sale_at))}<br><span class="mgr-muted">${esc(ManagerAPI.formatDate(row.last_sale_at))}</span></td>
        </tr>`;
        }).join('')}</tbody></table></div>`;
    }

    function validateCustomDates() {
        const from = els.dateFrom?.value || '';
        const to = els.dateTo?.value || '';
        if (!from || !to) {
            showError(t('date_range_error'));
            return null;
        }
        if (from > to) {
            showError(t('date_range_error'));
            return null;
        }
        return { from, to };
    }

    async function load(next = {}) {
        filterState = { ...filterState, ...next };
        setPeriodActive(filterState.period);
        hideError();
        setSummaryLoading(true);
        root.innerHTML = `<div class="mgr-list mgr-list--loading">${esc(t('loading'))}</div>`;

        try {
            const res = await ManagerAPI.getTeamPerformance(buildQuery());
            if (res.status !== 'success') {
                throw new Error(res.message || t('load_error'));
            }

            const data = res.data || {};
            const cashiers = data.cashiers || [];
            if (data.period) filterState.period = data.period;
            if (data.from) filterState.from = data.from;
            if (data.to) filterState.to = data.to;
            if (filterState.period === 'custom' && els.dateFrom && data.from) {
                els.dateFrom.value = data.from;
                els.dateTo.value = data.to || data.from;
            }

            updateSummary(data.summary);
            lastFetchAt = new Date();
            updateLastUpdated();

            if (!cashiers.length) {
                root.innerHTML = `<p class="mgr-empty">${esc(t('no_team_data'))}</p>`;
            } else {
                renderTable(cashiers);
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
        if (!btn?.dataset.period) return;
        const period = btn.dataset.period;
        if (period === filterState.period && period !== 'custom') return;

        if (period === 'custom') {
            initDateInputs();
            filterState.period = 'custom';
            setPeriodActive('custom');
            return;
        }

        load({ period, from: null, to: null });
    });

    els.dateApply?.addEventListener('click', () => {
        const range = validateCustomDates();
        if (!range) return;
        load({ period: 'custom', from: range.from, to: range.to });
    });

    els.dateFrom?.addEventListener('change', () => {
        if (els.dateTo && els.dateFrom.value > els.dateTo.value) {
            els.dateTo.value = els.dateFrom.value;
        }
    });

    els.dateTo?.addEventListener('change', () => {
        if (els.dateFrom && els.dateTo.value < els.dateFrom.value) {
            els.dateFrom.value = els.dateTo.value;
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        initDateInputs();
        load({ period: 'today' });
    });
    document.addEventListener('mgr:refresh', () => load());
})();
