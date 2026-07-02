/**
 * Cash register admin dashboard — enterprise layout
 */
document.addEventListener('DOMContentLoaded', () => {
    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = CashRegistersUI;
    let collectionChart;
    let performanceChart;
    let paymentChart;

    const PAYMENT_COLORS = {
        cash: '#2563eb',
        card: '#7c3aed',
        mobile_money: '#059669',
        split: '#d97706',
    };

    const els = {
        total: document.getElementById('crTotalRegisters'),
        open: document.getElementById('crOpenRegisters'),
        closed: document.getElementById('crClosedRegisters'),
        balance: document.getElementById('crCashBalance'),
        expected: document.getElementById('crExpectedCash'),
        difference: document.getElementById('crCashDifference'),
        sales: document.getElementById('crSalesToday'),
        cash: document.getElementById('crCashCollected'),
        mobile: document.getElementById('crMobileCollected'),
        card: document.getElementById('crCardCollected'),
        pending: document.getElementById('crPendingRecon'),
        cashiers: document.getElementById('crActiveCashiers'),
        heroSales: document.getElementById('crHeroSales'),
        heroOpen: document.getElementById('crHeroOpen'),
        heroBalance: document.getElementById('crHeroBalance'),
        heroVariance: document.getElementById('crHeroVariance'),
        heroExpected: document.getElementById('crHeroExpected'),
        heroTransactions: document.getElementById('crHeroTransactions'),
        heroTotal: document.getElementById('crHeroTotalRegisters'),
        heroPending: document.getElementById('crHeroPendingRecon'),
        heroProgressBar: document.getElementById('crHeroProgressBar'),
        heroProgressLabel: document.getElementById('crHeroProgressLabel'),
        varianceMetric: document.querySelector('.cr-dash-hero__metric--variance'),
        alertsRoot: document.getElementById('crDashAlerts'),
        statusRoot: document.getElementById('crStatusList'),
        activityRoot: document.getElementById('crActivityList'),
        paymentLegend: document.getElementById('crPaymentLegend'),
        cards: document.querySelectorAll('.cr-kpi-card'),
        heroValues: document.querySelectorAll('.cr-dash-hero__value'),
        kpiSwitch: document.querySelector('.cr-dash-kpi-switch'),
    };

    function chartColors() {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        return {
            grid: dark ? 'rgba(148,163,184,0.12)' : 'rgba(0,0,0,0.06)',
            text: dark ? '#9ca3af' : '#6b7280',
            surface: dark ? '#1f2937' : '#ffffff',
        };
    }

    function setLoading(on) {
        els.cards.forEach((c) => c.classList.toggle('is-loading', on));
        els.heroValues.forEach((v) => v.classList.toggle('is-loading', on));
    }

    function paymentLabel(method) {
        const map = {
            cash: t('cr_stat_cash'),
            card: t('cr_stat_card'),
            mobile_money: t('cr_stat_mobile'),
            split: 'Split',
        };
        return map[method] || method;
    }

    function activityIcon(action) {
        const a = String(action || '').toLowerCase();
        if (a.includes('open')) return 'lock_open';
        if (a.includes('close')) return 'lock';
        if (a.includes('recon')) return 'account_balance_wallet';
        if (a.includes('transfer')) return 'sync_alt';
        if (a.includes('sale') || a.includes('vente')) return 'point_of_sale';
        if (a.includes('cash')) return 'payments';
        return 'history';
    }

    function formatRelativeTime(dateString) {
        if (!dateString) return '—';
        const date = new Date(dateString);
        const diffMs = Date.now() - date.getTime();
        const diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 1) return t('cr_time_just_now');
        if (diffMin < 60) return t('cr_time_minutes_ago', String(diffMin));
        const diffH = Math.floor(diffMin / 60);
        if (diffH < 24) return t('cr_time_hours_ago', String(diffH));
        return AdminAPI.formatDate(dateString, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function initKpiTabs() {
        if (!els.kpiSwitch) return;
        const sections = document.querySelectorAll('.cr-dash-section[data-cr-section]');
        const buttons = els.kpiSwitch.querySelectorAll('[data-cr-kpi-tab]');

        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.getAttribute('data-cr-kpi-tab');
                buttons.forEach((b) => {
                    const active = b === btn;
                    b.classList.toggle('is-active', active);
                    b.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                sections.forEach((section) => {
                    section.classList.toggle('is-active', section.getAttribute('data-cr-section') === id);
                });
            });
        });
    }

    function renderAlerts(summary) {
        if (!els.alertsRoot) return;
        const alerts = [];
        const variance = Math.abs(Number(summary.cash_difference ?? 0));
        const pending = Number(summary.pending_reconciliation ?? 0);

        if (variance >= 500) {
            alerts.push({
                type: 'warn',
                icon: 'warning_amber',
                title: t('cr_stat_difference'),
                text: t('cr_variance_alert', money(summary.cash_difference)),
                href: 'reconciliation.php',
            });
        }
        if (pending > 0) {
            alerts.push({
                type: 'info',
                icon: 'pending_actions',
                title: t('cr_stat_pending_recon'),
                text: t('cr_pending_recon_alert', String(pending)),
                href: 'reconciliation.php',
            });
        }

        if (!alerts.length) {
            els.alertsRoot.hidden = true;
            els.alertsRoot.innerHTML = '';
            return;
        }

        const validAlerts = alerts.filter((a) => {
            const title = String(a.title || '').trim();
            const text = String(a.text || '').trim();
            return !!(title || text);
        });

        if (!validAlerts.length) {
            els.alertsRoot.hidden = true;
            els.alertsRoot.innerHTML = '';
            return;
        }

        els.alertsRoot.hidden = false;
        els.alertsRoot.innerHTML = validAlerts.map((a, i) => {
            const title = String(a.title || '').trim();
            const text = String(a.text || '').trim();
            const msgHtml = text && text !== title
                ? `<span class="ad-alert-strip__msg">${esc(text)}</span>`
                : '';
            return `
            <a href="${esc(a.href)}" class="ad-alert-strip ad-alert-strip--${a.type}" role="status" style="--alert-i:${i}">
                <span class="ad-alert-strip__icon" aria-hidden="true">
                    <span class="material-icons-round">${esc(a.icon)}</span>
                </span>
                <span class="ad-alert-strip__body">
                    <strong class="ad-alert-strip__title">${esc(title || text)}</strong>
                    ${msgHtml}
                </span>
                <span class="ad-alert-strip__chev material-icons-round" aria-hidden="true">chevron_right</span>
            </a>`;
        }).join('');
    }

    function renderHero(summary) {
        const s = summary || {};
        const total = Number(s.total_registers ?? 0);
        const open = Number(s.open_registers ?? 0);
        const variance = Number(s.cash_difference ?? 0);
        const pending = Number(s.pending_reconciliation ?? 0);
        const txCount = Number(s.transactions_today ?? 0);
        const pct = total > 0 ? Math.round((open / total) * 100) : 0;

        if (els.heroSales) els.heroSales.textContent = money(s.sales_today);
        if (els.heroOpen) els.heroOpen.textContent = `${open} / ${total}`;
        if (els.heroBalance) els.heroBalance.textContent = money(s.current_cash_balance);
        if (els.heroVariance) els.heroVariance.textContent = money(variance);
        if (els.heroExpected) els.heroExpected.textContent = money(s.expected_cash);
        if (els.heroTransactions) {
            els.heroTransactions.textContent = t('cr_transactions_today', String(txCount));
        }
        if (els.heroTotal) {
            els.heroTotal.textContent = t('cr_registers_of_total', String(open), String(total));
        }
        if (els.heroPending && pending > 0) {
            els.heroPending.textContent = t('cr_pending_short', String(pending));
        } else if (els.heroPending) {
            els.heroPending.textContent = '';
        }
        if (els.heroProgressBar) {
            els.heroProgressBar.style.width = `${pct}%`;
        }
        if (els.heroProgressLabel) {
            els.heroProgressLabel.textContent = t('cr_registers_open_pct', String(pct));
        }

        if (els.varianceMetric) {
            els.varianceMetric.classList.remove('is-warn', 'is-danger');
            if (Math.abs(variance) >= 500) els.varianceMetric.classList.add('is-danger');
            else if (Math.abs(variance) > 0) els.varianceMetric.classList.add('is-warn');
        }
    }

    function renderStatus(items) {
        if (!els.statusRoot) return;
        if (!items?.length) {
            els.statusRoot.innerHTML = `<p class="cr-empty">${esc(t('cr_no_registers'))}</p>`;
            return;
        }
        els.statusRoot.innerHTML = items.map((r) => {
            const isOpen = r.session_status === 'open';
            return `
            <article class="cr-status-card${isOpen ? ' is-open' : ''}">
                <div class="cr-status-card__icon">
                    <span class="material-icons-round">${isOpen ? 'lock_open' : 'lock'}</span>
                </div>
                <div class="cr-status-card__main">
                    <strong>${esc(r.name)}</strong>
                    <div class="cr-status-card__meta">
                        <span>${esc(r.store_name)} · ${esc(r.code)}</span>
                        <span>${esc(t('cr_assigned_cashier'))}: ${esc(r.cashier || '—')}</span>
                    </div>
                </div>
                <div class="cr-status-card__right">
                    <span class="cr-badge cr-badge--${isOpen ? 'ok' : 'off'}">${esc(isOpen ? t('cr_session_open') : t('cr_session_closed'))}</span>
                    <span class="cr-status-card__balance">${esc(money(r.balance))}</span>
                    <a href="register_details.php?id=${encodeURIComponent(r.id)}" class="cr-status-card__link">${esc(t('cr_view_details'))}</a>
                </div>
            </article>`;
        }).join('');
    }

    function renderActivity(items) {
        if (!els.activityRoot) return;
        if (!items?.length) {
            els.activityRoot.innerHTML = `<p class="cr-empty">${esc(t('cr_no_data'))}</p>`;
            return;
        }
        els.activityRoot.innerHTML = items.map((a) => {
            const icon = activityIcon(a.action);
            return `
            <div class="cr-activity-row">
                <div class="cr-activity-row__icon"><span class="material-icons-round">${esc(icon)}</span></div>
                <div class="cr-activity-row__body">
                    <strong>${esc(a.action)}</strong>
                    <span class="cr-muted">${esc(a.user_name || '—')}</span>
                </div>
                <time class="cr-activity-row__time" datetime="${esc(a.created_at || '')}">${esc(formatRelativeTime(a.created_at))}</time>
            </div>`;
        }).join('');
    }

    function renderPaymentLegend(payments) {
        if (!els.paymentLegend) return;
        const entries = Object.entries(payments || {}).filter(([, v]) => Number(v) > 0);
        if (!entries.length) {
            els.paymentLegend.innerHTML = `<li class="cr-empty" style="padding:8px">${esc(t('cr_no_data'))}</li>`;
            return;
        }
        const total = entries.reduce((sum, [, v]) => sum + Number(v), 0);
        els.paymentLegend.innerHTML = entries.map(([method, amount]) => {
            const pct = total > 0 ? Math.round((Number(amount) / total) * 100) : 0;
            return `
            <li>
                <span class="cr-payment-legend__label">
                    <span class="cr-payment-legend__dot" style="background:${PAYMENT_COLORS[method] || '#94a3b8'}"></span>
                    ${esc(paymentLabel(method))}
                    <span class="cr-payment-legend__pct">${pct}%</span>
                </span>
                <span class="cr-payment-legend__value">${esc(money(amount))}</span>
            </li>`;
        }).join('');
    }

    function moneyTooltipLabel(value) {
        return money(value);
    }

    function baseChartOptions() {
        const c = chartColors();
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: c.surface,
                    titleColor: c.text,
                    bodyColor: c.text,
                    borderColor: c.grid,
                    borderWidth: 1,
                    callbacks: {
                        label(ctx) {
                            const val = ctx.parsed?.y ?? ctx.parsed ?? 0;
                            return moneyTooltipLabel(val);
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { color: c.grid },
                    ticks: { color: c.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: c.grid },
                    ticks: {
                        color: c.text,
                        callback: (v) => Number(v).toLocaleString(window.ADMIN_CONFIG?.locale || 'fr-FR'),
                    },
                },
            },
        };
    }

    function renderCharts(data) {
        const collCtx = document.getElementById('crCollectionChart');
        const perfCtx = document.getElementById('crPerformanceChart');
        const payCtx = document.getElementById('crPaymentChart');
        const c = chartColors();

        if (collCtx && window.Chart) {
            const labels = (data.collection_chart || []).map((h) => `${String(h.hour).padStart(2, '0')}:00`);
            const values = (data.collection_chart || []).map((h) => h.amount);
            collectionChart?.destroy();
            collectionChart = new Chart(collCtx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: t('cr_stat_cash'),
                        data: values,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37,99,235,0.12)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        borderWidth: 2,
                    }],
                },
                options: baseChartOptions(),
            });
        }

        if (perfCtx && window.Chart) {
            const perf = data.performance_chart || [];
            performanceChart?.destroy();
            performanceChart = new Chart(perfCtx, {
                type: 'bar',
                data: {
                    labels: perf.map((p) => p.label),
                    datasets: [{
                        label: t('cr_stat_sales_today'),
                        data: perf.map((p) => p.value),
                        backgroundColor: 'rgba(124, 58, 237, 0.75)',
                        borderRadius: 6,
                        maxBarThickness: 36,
                    }],
                },
                options: baseChartOptions(),
            });
        }

        const payments = data.payments_today || {};
        renderPaymentLegend(payments);

        if (payCtx && window.Chart) {
            const entries = Object.entries(payments).filter(([, v]) => Number(v) > 0);
            paymentChart?.destroy();
            if (!entries.length) {
                return;
            }
            paymentChart = new Chart(payCtx, {
                type: 'doughnut',
                data: {
                    labels: entries.map(([m]) => paymentLabel(m)),
                    datasets: [{
                        data: entries.map(([, v]) => v),
                        backgroundColor: entries.map(([m]) => PAYMENT_COLORS[m] || '#94a3b8'),
                        borderWidth: 0,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: c.surface,
                            titleColor: c.text,
                            bodyColor: c.text,
                            borderColor: c.grid,
                            borderWidth: 1,
                            callbacks: {
                                label(ctx) {
                                    return `${ctx.label}: ${moneyTooltipLabel(ctx.parsed)}`;
                                },
                            },
                        },
                    },
                },
            });
        }
    }

    async function load(silent = false) {
        if (!silent) { setLoading(true); hideError(); }
        try {
            const res = await AdminAPI.getCashRegisterDashboard();
            if (res.status !== 'success') throw new Error(res.message);
            const d = res.data || {};
            setMigrationHint(d.module_ready);
            const s = d.summary || {};

            renderHero(s);
            renderAlerts(s);

            if (els.total) els.total.textContent = String(s.total_registers ?? 0);
            if (els.open) els.open.textContent = String(s.open_registers ?? 0);
            if (els.closed) els.closed.textContent = String(s.closed_registers ?? 0);
            if (els.balance) els.balance.textContent = money(s.current_cash_balance);
            if (els.expected) els.expected.textContent = money(s.expected_cash);
            if (els.difference) els.difference.textContent = money(s.cash_difference);
            if (els.sales) els.sales.textContent = money(s.sales_today);
            if (els.cash) els.cash.textContent = money(s.cash_collected);
            if (els.mobile) els.mobile.textContent = money(s.mobile_collected);
            if (els.card) els.card.textContent = money(s.card_collected);
            if (els.pending) els.pending.textContent = String(s.pending_reconciliation ?? 0);
            if (els.cashiers) els.cashiers.textContent = String(s.active_cashiers ?? 0);

            renderStatus(d.register_status);
            renderActivity(d.recent_activities);
            renderCharts(d);
            updateLastUpdated();
            await CashRegisterOffline.sync();
        } catch (e) {
            console.error(e);
            if (!silent) showError(e.message || t('load_error'));
        }
        if (!silent) setLoading(false);
    }

    initKpiTabs();
    load();
    document.addEventListener('cr:refresh', () => load(true));
    window.addEventListener('app-theme-changed', () => {
        if (collectionChart || performanceChart || paymentChart) {
            load(true);
        }
    });
    setInterval(() => { if (document.visibilityState === 'visible') load(true); }, 60000);
});
