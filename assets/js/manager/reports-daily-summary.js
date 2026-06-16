/**
 * Daily summary report — manager reports
 */
(() => {
    const reportDateEl = document.getElementById('dsReportDate');
    if (!reportDateEl) return;

    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    let lastFetchAt = null;
    let selectedDate = todayIso();

    const els = {
        errorBanner: document.getElementById('mgrError'),
        lastUpdated: document.getElementById('lastUpdated'),
        dateInput: document.getElementById('dsDateInput'),
        dateApply: document.getElementById('dsDateApply'),
        dateQuick: document.getElementById('dsDateQuick'),
        reportDateLabel: document.getElementById('dsReportDate'),
        revenue: document.getElementById('dsRevenue'),
        revenueTrend: document.getElementById('dsRevenueTrend'),
        txCount: document.getElementById('dsTxCount'),
        txTrend: document.getElementById('dsTxTrend'),
        avgTicket: document.getElementById('dsAvgTicket'),
        returnsAmount: document.getElementById('dsReturnsAmount'),
        returnsCount: document.getElementById('dsReturnsCount'),
        shiftsClosed: document.getElementById('dsShiftsClosed'),
        pendingApprovals: document.getElementById('dsPendingApprovals'),
        stockAlerts: document.getElementById('dsStockAlerts'),
        cashVariance: document.getElementById('dsCashVariance'),
        paymentBars: document.getElementById('dsPaymentBars'),
        hourlyBars: document.getElementById('dsHourlyBars'),
        cashiersRoot: document.getElementById('dsCashiersRoot'),
        shiftsRoot: document.getElementById('dsShiftsRoot'),
        cashiersCount: document.getElementById('dsCashiersCount'),
        shiftsCount: document.getElementById('dsShiftsCount'),
        summaryCards: document.querySelectorAll('#dsSummary .ad-stat-card, #dsSecondary .ad-stat-card'),
    };

    const PAY_ICONS = {
        cash: 'payments',
        mobile_money: 'smartphone',
        card: 'credit_card',
        split: 'account_balance_wallet',
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

    function todayIso() {
        return new Date().toISOString().slice(0, 10);
    }

    function yesterdayIso() {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        return d.toISOString().slice(0, 10);
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

    function money(value) {
        if (value === null || value === undefined || value === '') return '—';
        return ManagerAPI.formatCurrency(value);
    }

    function formatReportDate(dateStr) {
        if (!dateStr) return '—';
        return new Date(`${dateStr}T12:00:00`).toLocaleDateString(locale, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }

    function formatHour(hour) {
        const h = Number(hour);
        return `${String(h).padStart(2, '0')}:00`;
    }

    function paymentLabel(method) {
        const map = {
            cash: t('pay_cash'),
            card: t('pay_card'),
            mobile_money: t('pay_mobile'),
            split: t('pay_split'),
        };
        return map[method] || method || '—';
    }

    function renderTrend(el, pct) {
        if (!el) return;
        if (pct === null || pct === undefined) {
            el.hidden = true;
            return;
        }
        const dir = pct >= 0 ? 'up' : 'down';
        const icon = dir === 'up' ? 'trending_up' : 'trending_down';
        const sign = pct >= 0 ? '+' : '';
        el.hidden = false;
        el.className = `mgr-ds-trend mgr-ds-trend--${dir}`;
        el.innerHTML = `<span class="material-icons-round">${icon}</span>${sign}${pct}% <small>${esc(t('vs_previous_day'))}</small>`;
    }

    function renderPaymentBars(payments) {
        if (!els.paymentBars) return;
        if (!payments?.length) {
            els.paymentBars.innerHTML = `<p class="mgr-empty">${esc(t('no_payments_day'))}</p>`;
            return;
        }
        const maxAmount = Math.max(...payments.map((p) => p.amount), 1);
        els.paymentBars.innerHTML = payments.map((p) => {
            const pct = Math.round((p.amount / maxAmount) * 100);
            const icon = PAY_ICONS[p.method] || 'account_balance_wallet';
            return `
                <div class="mgr-ds-pay-row">
                    <span class="mgr-ds-pay-row__label">
                        <span class="material-icons-round">${icon}</span>
                        ${esc(paymentLabel(p.method))}
                        <small>(${p.count})</small>
                    </span>
                    <div class="mgr-ds-bar"><div class="mgr-ds-bar__fill" style="width:${pct}%"></div></div>
                    <span class="mgr-ds-pay-row__amount">${esc(money(p.amount))}</span>
                </div>`;
        }).join('');
    }

    function renderHourlyBars(hourly) {
        if (!els.hourlyBars) return;
        const active = (hourly || []).filter((h) => h.count > 0);
        if (!active.length) {
            els.hourlyBars.innerHTML = `<p class="mgr-empty">${esc(t('no_hourly_sales'))}</p>`;
            return;
        }
        const maxRev = Math.max(...active.map((h) => h.revenue), 1);
        els.hourlyBars.innerHTML = active.map((h) => {
            const pct = Math.round((h.revenue / maxRev) * 100);
            return `
                <div class="mgr-ds-hour-row" title="${esc(formatHour(h.hour))} · ${h.count} ${esc(t('ds_tx_short'))}">
                    <span class="mgr-ds-hour-row__label">${esc(formatHour(h.hour))}</span>
                    <div class="mgr-ds-bar mgr-ds-bar--hour"><div class="mgr-ds-bar__fill" style="width:${pct}%"></div></div>
                    <span class="mgr-ds-hour-row__amount">${esc(money(h.revenue))}</span>
                </div>`;
        }).join('');
    }

    function renderCashiers(cashiers) {
        if (!els.cashiersRoot) return;
        if (!cashiers?.length) {
            els.cashiersRoot.innerHTML = `<p class="mgr-empty">${esc(t('no_team_data'))}</p>`;
            if (els.cashiersCount) els.cashiersCount.textContent = '0';
            return;
        }
        if (els.cashiersCount) els.cashiersCount.textContent = String(cashiers.length);
        els.cashiersRoot.innerHTML = `
            <div class="mgr-table-wrap">
            <table class="modern-table mgr-ds-table">
                <thead>
                    <tr>
                        <th>${esc(t('col_rank'))}</th>
                        <th>${esc(t('cashier_label'))}</th>
                        <th>${esc(t('col_transactions'))}</th>
                        <th>${esc(t('col_revenue'))}</th>
                        <th>${esc(t('col_avg_ticket'))}</th>
                        <th>${esc(t('col_returns'))}</th>
                    </tr>
                </thead>
                <tbody>
                    ${cashiers.map((c, i) => `
                        <tr>
                            <td>${i + 1}</td>
                            <td><strong>${esc(c.cashier_name)}</strong></td>
                            <td>${esc(String(c.transactions ?? 0))}</td>
                            <td>${esc(money(c.revenue))}</td>
                            <td>${esc(money(c.avg_ticket))}</td>
                            <td>${esc(String(c.returns_count ?? 0))}</td>
                        </tr>`).join('')}
                </tbody>
            </table>
            </div>`;
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

    function renderShifts(shifts) {
        if (!els.shiftsRoot) return;
        const items = shifts?.items || [];
        if (els.shiftsCount) els.shiftsCount.textContent = String(items.length);
        if (!items.length) {
            els.shiftsRoot.innerHTML = `<p class="mgr-empty">${esc(t('no_daily_shifts'))}</p>`;
            return;
        }
        els.shiftsRoot.innerHTML = `
            <div class="mgr-table-wrap">
            <table class="modern-table mgr-ds-table">
                <thead>
                    <tr>
                        <th>${esc(t('cashier_label'))}</th>
                        <th>${esc(t('col_status'))}</th>
                        <th>${esc(t('col_opened'))}</th>
                        <th>${esc(t('col_sales'))}</th>
                        <th>${esc(t('col_variance'))}</th>
                        <th>${esc(t('col_recon_status'))}</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.map((s) => `
                        <tr>
                            <td><strong>${esc(s.cashier_name)}</strong></td>
                            <td>${esc(s.status === 'open' ? t('shift_status_open') : t('shift_status_closed'))}</td>
                            <td>${esc(ManagerAPI.formatDate(s.opened_at))}</td>
                            <td>${esc(money(s.total_sales))}</td>
                            <td>${s.variance !== null && s.variance !== undefined ? esc(money(s.variance)) : '—'}</td>
                            <td><span class="mgr-badge ${reconBadgeClass(s.reconciliation_status)}">${esc(reconLabel(s.reconciliation_status))}</span></td>
                        </tr>`).join('')}
                </tbody>
            </table>
            </div>`;
    }

    function syncDateQuick() {
        els.dateQuick?.querySelectorAll('[data-date]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.date === selectedDate);
        });
    }

    function applyDate(dateStr) {
        selectedDate = dateStr || todayIso();
        if (els.dateInput) els.dateInput.value = selectedDate;
        syncDateQuick();
    }

    async function loadReport(silent = false) {
        if (!silent) {
            setSummaryLoading(true);
            hideError();
        }

        try {
            const res = await ManagerAPI.getDailySummary(selectedDate);
            if (res.status !== 'success' || !res.data) {
                throw new Error(res.message || t('load_error'));
            }

            const d = res.data;
            const sales = d.sales || {};
            const vs = sales.vs_previous || {};

            if (els.reportDateLabel) {
                els.reportDateLabel.textContent = formatReportDate(d.date);
            }
            if (els.revenue) els.revenue.textContent = money(sales.revenue);
            if (els.txCount) els.txCount.textContent = String(sales.count ?? 0);
            if (els.avgTicket) els.avgTicket.textContent = money(sales.avg_ticket);
            if (els.returnsAmount) els.returnsAmount.textContent = money(d.returns?.amount);
            if (els.returnsCount) els.returnsCount.textContent = t('ds_returns_count', String(d.returns?.count ?? 0));

            renderTrend(els.revenueTrend, vs.revenue_pct);
            renderTrend(els.txTrend, vs.count_pct);

            if (els.shiftsClosed) els.shiftsClosed.textContent = String(d.shifts?.closed ?? 0);
            if (els.pendingApprovals) els.pendingApprovals.textContent = String(d.approvals?.pending_now ?? 0);
            if (els.stockAlerts) els.stockAlerts.textContent = String(d.inventory?.total_alerts ?? 0);
            if (els.cashVariance) els.cashVariance.textContent = money(d.shifts?.total_variance);

            renderPaymentBars(d.payments);
            renderHourlyBars(d.hourly);
            renderCashiers(d.top_cashiers);
            renderShifts(d.shifts);

            lastFetchAt = new Date();
            updateLastUpdated();
        } catch (e) {
            console.error(e);
            const msg = e.message || t('load_error');
            if (!silent) showError(msg);
        }

        if (!silent) setSummaryLoading(false);
    }

    function initDateControls() {
        const today = todayIso();
        if (els.dateInput) {
            els.dateInput.max = today;
            els.dateInput.value = selectedDate;
        }

        els.dateQuick?.querySelectorAll('[data-date]').forEach((btn) => {
            btn.addEventListener('click', () => {
                applyDate(btn.dataset.date);
                loadReport();
            });
        });

        els.dateApply?.addEventListener('click', () => {
            applyDate(els.dateInput?.value || today);
            loadReport();
        });

        els.dateInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                applyDate(els.dateInput.value || today);
                loadReport();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initDateControls();
        loadReport();
    });

    document.addEventListener('mgr:refresh', () => loadReport(true));
})();
