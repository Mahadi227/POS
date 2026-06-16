/**
 * Cashier dashboard — dynamic stats, i18n, auto-refresh.
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.DASHBOARD_CONFIG || {};
    const i18n = window.DASHBOARD_I18N || {};
    const locale = cfg.locale || (cfg.lang === 'fr' ? 'fr-FR' : 'en-US');

    const PAY_ICONS = {
        cash: 'payments',
        mobile_money: 'smartphone',
        card: 'credit_card',
        split: 'account_balance_wallet',
    };

    const els = {
        storeName: document.getElementById('dashStoreName'),
        heroGreeting: document.getElementById('heroGreeting'),
        heroSub: document.getElementById('heroSub'),
        heroDate: document.getElementById('dashHeroDate'),
        roleBadge: document.getElementById('dashRoleBadge'),
        liveClock: document.getElementById('dashLiveClock'),
        liveDate: document.getElementById('dashLiveDate'),
        todaySalesCount: document.getElementById('todaySalesCount'),
        todayRevenue: document.getElementById('todayRevenue'),
        avgTicket: document.getElementById('avgTicket'),
        lastSaleHint: document.getElementById('lastSaleHint'),
        salesTrend: document.getElementById('salesTrend'),
        revenueTrend: document.getElementById('revenueTrend'),
        sessionHint: document.getElementById('sessionHint'),
        recentSalesList: document.getElementById('recentSalesList'),
        paymentBars: document.getElementById('paymentBars'),
        refreshBtn: document.getElementById('dashRefreshBtn'),
        lastUpdated: document.getElementById('lastUpdated'),
        headerDate: document.getElementById('dashHeaderDate'),
        headerUserName: document.getElementById('headerUserName'),
        headerUserRole: document.getElementById('headerUserRole'),
        errorBanner: document.getElementById('dashboardError'),
        shiftPanel: document.getElementById('shiftPanel'),
        sessionStatus: document.getElementById('sessionStatus'),
    };

    let lastFetchAt = null;
    let refreshTimer = null;

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function formatRole(role) {
        return t('connected_as_role', role || cfg.userRole || '—');
    }

    function setStatValue(el, text, loading = false) {
        if (!el) return;
        if (!loading && el.textContent !== text) {
            el.classList.add('cd-stat__value--pulse');
            setTimeout(() => el.classList.remove('cd-stat__value--pulse'), 400);
        }
        el.textContent = text;
        el.classList.toggle('is-loading', loading);
    }

    function updateClockAndDates() {
        const now = new Date();
        if (els.liveClock) {
            els.liveClock.textContent = now.toLocaleTimeString(locale, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        }
        const dateOpts = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
        const dateStr = now.toLocaleDateString(locale, dateOpts);
        if (els.liveDate) els.liveDate.textContent = dateStr;
        if (els.heroDate) els.heroDate.textContent = dateStr;
        if (els.headerDate) els.headerDate.textContent = dateStr;
    }

    function greetingForHour(h) {
        if (h < 12) return t('greeting_morning');
        if (h < 18) return t('greeting_afternoon');
        return t('greeting_evening');
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function paymentLabel(method) {
        const map = {
            cash: t('pay_cash'),
            card: t('pay_card'),
            mobile_money: t('pay_mobile_money'),
            split: t('pay_split'),
        };
        return map[method] || method || '—';
    }

    function calcTrend(current, previous) {
        if (previous === 0 && current === 0) return null;
        if (previous === 0) return { pct: 100, dir: 'up' };
        const pct = Math.round(((current - previous) / previous) * 100);
        return { pct: Math.abs(pct), dir: pct >= 0 ? 'up' : 'down' };
    }

    function renderTrend(el, current, previous) {
        if (!el) return;
        const trend = calcTrend(current, previous);
        if (trend === null) {
            el.hidden = true;
            return;
        }
        const icon = trend.dir === 'up' ? 'trending_up' : 'trending_down';
        const sign = trend.dir === 'up' ? '+' : '-';
        el.hidden = false;
        el.className = `cd-stat__trend cd-stat__trend--${trend.dir}`;
        el.innerHTML = `<span class="material-icons-round">${icon}</span>${sign}${trend.pct}% <small>${escapeHtml(t('vs_yesterday'))}</small>`;
    }

    function updateLastUpdatedLabel() {
        if (!els.lastUpdated || !lastFetchAt) return;
        const time = new Date(lastFetchAt).toLocaleTimeString(locale, {
            hour: '2-digit',
            minute: '2-digit',
        });
        els.lastUpdated.textContent = `${t('last_updated')} · ${time}`;
    }

    function showError(msg) {
        if (!els.errorBanner) return;
        els.errorBanner.classList.add('is-visible');
        const text = els.errorBanner.querySelector('.cd-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        els.errorBanner?.classList.remove('is-visible');
    }

    function updateSessionStat() {
        if (!els.sessionStatus || typeof CashierShift === 'undefined') return;

        if (!CashierShift.isModuleReady()) {
            els.sessionStatus.textContent = t('active');
            els.sessionStatus.className = 'cd-stat__value cd-stat__value--session';
            return;
        }

        if (CashierShift.isOpen()) {
            const shift = CashierShift.getShift();
            els.sessionStatus.textContent = t('shift_status_open');
            els.sessionStatus.className = 'cd-stat__value cd-stat__value--session cd-stat__value--open';
            if (els.sessionHint && shift?.opened_at) {
                els.sessionHint.textContent = t('shift_opened_at', CashierAPI.formatDate(shift.opened_at, {
                    hour: '2-digit',
                    minute: '2-digit',
                }));
            }
        } else {
            els.sessionStatus.textContent = t('shift_closed_title');
            els.sessionStatus.className = 'cd-stat__value cd-stat__value--session cd-stat__value--closed';
            if (els.sessionHint) {
                els.sessionHint.textContent = t('shift_closed_desc');
            }
        }
    }

    async function loadShiftPanel() {
        if (typeof CashierShift === 'undefined' || !els.shiftPanel) return;
        await CashierShift.load();
        CashierShift.renderPanel(els.shiftPanel);
        updateSessionStat();
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.cd-stat').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function renderRecentSales(sales) {
        if (!els.recentSalesList) return;

        if (!sales?.length) {
            els.recentSalesList.innerHTML = `
                <li class="cd-empty">
                    <span class="material-icons-round">receipt_long</span>
                    <p>${escapeHtml(t('no_sales_today'))}</p>
                    <small>${escapeHtml(t('open_pos_hint'))}</small>
                </li>`;
            return;
        }

        els.recentSalesList.innerHTML = sales
            .map((sale) => {
                const receipt = escapeHtml(sale.receipt_no || sale.receipt_number || `#${sale.id}`);
                const time = CashierAPI.formatDate(sale.created_at || sale.sale_date, {
                    hour: '2-digit',
                    minute: '2-digit',
                });
                const total = CashierAPI.formatCurrency(sale.total ?? sale.total_amount);
                const pay = paymentLabel(sale.payment_method);
                const viewUrl = `view_sale.php?id=${sale.id}`;

                return `
                    <li>
                        <a href="${viewUrl}" class="cd-sale-item">
                            <div>
                                <div class="cd-sale-item__receipt">${receipt}</div>
                                <div class="cd-sale-item__time">${escapeHtml(time)}</div>
                            </div>
                            <span class="cd-sale-item__pay">${escapeHtml(pay)}</span>
                            <span class="cd-sale-item__total">${escapeHtml(total)}</span>
                        </a>
                    </li>`;
            })
            .join('');
    }

    function renderPaymentSummary(summary) {
        if (!els.paymentBars) return;

        if (!summary?.length) {
            els.paymentBars.innerHTML = `
                <div class="cd-empty">
                    <span class="material-icons-round">pie_chart</span>
                    <p>${escapeHtml(t('no_payments'))}</p>
                </div>`;
            return;
        }

        const maxAmount = Math.max(...summary.map((p) => p.amount), 1);

        els.paymentBars.innerHTML = summary
            .map((p) => {
                const pct = Math.round((p.amount / maxAmount) * 100);
                const label = paymentLabel(p.method);
                const icon = PAY_ICONS[p.method] || 'account_balance_wallet';
                return `
                    <div class="cd-pay-row">
                        <span class="cd-pay-row__label">
                            <span class="material-icons-round">${icon}</span>
                            ${escapeHtml(label)}
                            <small>(${p.count})</small>
                        </span>
                        <div class="cd-pay-bar">
                            <div class="cd-pay-bar__fill" style="width:${pct}%"></div>
                        </div>
                        <span class="cd-pay-row__amount">${escapeHtml(CashierAPI.formatCurrency(p.amount))}</span>
                    </div>`;
            })
            .join('');
    }

    async function loadDashboard(silent = false) {
        if (!silent) {
            setStatValue(els.todaySalesCount, t('loading'), true);
            setStatValue(els.todayRevenue, t('loading'), true);
            setStatValue(els.avgTicket, t('loading'), true);
            if (els.lastSaleHint) els.lastSaleHint.textContent = '…';
        }

        els.refreshBtn?.classList.add('spinning');
        if (!silent) {
            setStatsLoading(true);
            hideError();
        }

        try {
            const result = await CashierAPI.getDashboardStats();

            if (result.status !== 'success' || !result.data) {
                throw new Error(result.message || t('error_short'));
            }

            const d = result.data;
            const name = d.cashier_name || cfg.userName || '—';
            const role = d.role || cfg.userRole || '—';

            if (els.heroGreeting) {
                els.heroGreeting.textContent = `${greetingForHour(new Date().getHours())}, ${name} !`;
            }
            if (els.heroSub) {
                els.heroSub.textContent = t('today_summary');
            }
            if (els.storeName && d.store_name) {
                els.storeName.textContent = d.store_name;
            }
            if (els.headerUserName && name) {
                els.headerUserName.textContent = name;
            }
            if (els.headerUserRole && role) {
                els.headerUserRole.textContent = role;
            }
            if (els.roleBadge) {
                els.roleBadge.textContent = formatRole(role);
            }
            if (els.sessionHint) {
                els.sessionHint.textContent = formatRole(role);
            }

            setStatValue(els.todaySalesCount, String(d.sales_count ?? 0));
            setStatValue(els.todayRevenue, CashierAPI.formatCurrency(d.revenue));
            setStatValue(els.avgTicket, CashierAPI.formatCurrency(d.avg_ticket ?? 0));

            renderTrend(els.salesTrend, d.sales_count ?? 0, d.sales_count_yesterday ?? 0);
            renderTrend(els.revenueTrend, d.revenue ?? 0, d.revenue_yesterday ?? 0);

            const recent = d.recent_sales || [];
            if (els.lastSaleHint) {
                if (recent.length) {
                    const last = recent[0];
                    const time = CashierAPI.formatDate(last.created_at || last.sale_date, {
                        hour: '2-digit',
                        minute: '2-digit',
                    });
                    els.lastSaleHint.textContent = t('last_sale', time);
                } else {
                    els.lastSaleHint.textContent = t('no_sales_yet');
                }
            }

            renderRecentSales(recent);
            renderPaymentSummary(d.payment_summary || []);

            lastFetchAt = Date.now();
            updateLastUpdatedLabel();
            hideError();
            await loadShiftPanel();
        } catch (err) {
            console.error('Dashboard:', err);
            showError(err.message || t('error_short'));
            setStatValue(els.todaySalesCount, '—');
            setStatValue(els.todayRevenue, t('error_short'));
            setStatValue(els.avgTicket, '—');
            if (els.recentSalesList) {
                els.recentSalesList.innerHTML = `<li class="cd-empty"><p>${escapeHtml(t('load_error', err.message || String(err)))}</p></li>`;
            }
            if (els.paymentBars) {
                els.paymentBars.innerHTML = `<div class="cd-empty"><p>${escapeHtml(t('load_error', err.message || String(err)))}</p></div>`;
            }
        }

        setStatsLoading(false);
        els.refreshBtn?.classList.remove('spinning');
    }

    function startAutoRefresh() {
        const ms = cfg.autoRefreshMs || 60000;
        if (refreshTimer) clearInterval(refreshTimer);
        refreshTimer = setInterval(() => loadDashboard(true), ms);
    }

    els.refreshBtn?.addEventListener('click', () => loadDashboard(false));

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            loadDashboard(true);
        }
    });

    updateClockAndDates();
    setInterval(updateClockAndDates, 1000);
    loadDashboard(false);
    startAutoRefresh();

    if (typeof CashierShift !== 'undefined') {
        CashierShift.onChange(() => {
            if (els.shiftPanel) CashierShift.renderPanel(els.shiftPanel);
            updateSessionStat();
        });
    }
});
