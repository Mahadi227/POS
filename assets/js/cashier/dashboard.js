/**
 * Tableau de bord caissier
 */
document.addEventListener('DOMContentLoaded', () => {
    const PAY_ICONS = {
        cash: 'payments',
        mobile_money: 'smartphone',
        card: 'credit_card',
    };

    const els = {
        storeName: document.getElementById('dashStoreName'),
        heroGreeting: document.getElementById('heroGreeting'),
        liveClock: document.getElementById('dashLiveClock'),
        todaySalesCount: document.getElementById('todaySalesCount'),
        todayRevenue: document.getElementById('todayRevenue'),
        avgTicket: document.getElementById('avgTicket'),
        lastSaleHint: document.getElementById('lastSaleHint'),
        recentSalesList: document.getElementById('recentSalesList'),
        paymentBars: document.getElementById('paymentBars'),
        refreshBtn: document.getElementById('dashRefreshBtn'),
    };

    function setStatValue(el, text, loading = false) {
        if (!el) return;
        el.textContent = text;
        el.classList.toggle('is-loading', loading);
    }

    function updateClock() {
        if (!els.liveClock) return;
        const now = new Date();
        els.liveClock.textContent = now.toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    }

    function greeting() {
        const h = new Date().getHours();
        if (h < 12) return 'Bonjour';
        if (h < 18) return 'Bon après-midi';
        return 'Bonsoir';
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function renderRecentSales(sales) {
        if (!els.recentSalesList) return;

        if (!sales?.length) {
            els.recentSalesList.innerHTML = `
                <li class="cd-empty">
                    <span class="material-icons-round">receipt_long</span>
                    <p>Aucune vente aujourd'hui</p>
                    <small>Ouvrez la caisse pour commencer</small>
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
                const pay = CashierAPI.paymentLabel(sale.payment_method);
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

    function renderPaymentSummary(summary, totalRevenue) {
        if (!els.paymentBars) return;

        if (!summary?.length) {
            els.paymentBars.innerHTML = `
                <div class="cd-empty">
                    <span class="material-icons-round">pie_chart</span>
                    <p>Pas de paiement enregistré</p>
                </div>`;
            return;
        }

        const maxAmount = Math.max(...summary.map((p) => p.amount), 1);

        els.paymentBars.innerHTML = summary
            .map((p) => {
                const pct = Math.round((p.amount / maxAmount) * 100);
                const label = CashierAPI.paymentLabel(p.method);
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

    async function loadDashboard() {
        setStatValue(els.todaySalesCount, 'Chargement…', true);
        setStatValue(els.todayRevenue, 'Chargement…', true);
        setStatValue(els.avgTicket, 'Chargement…', true);
        if (els.lastSaleHint) els.lastSaleHint.textContent = '…';

        els.refreshBtn?.classList.add('spinning');

        try {
            const result = await CashierAPI.getDashboardStats();

            if (result.status !== 'success' || !result.data) {
                throw new Error(result.message || 'Erreur API');
            }

            const d = result.data;
            const name = d.cashier_name || window.CASHIER_CONTEXT?.name || 'Caissier';

            if (els.heroGreeting) {
                els.heroGreeting.textContent = `${greeting()}, ${name} !`;
            }
            if (els.storeName && d.store_name) {
                els.storeName.textContent = d.store_name;
            }

            setStatValue(els.todaySalesCount, String(d.sales_count ?? 0));
            setStatValue(els.todayRevenue, CashierAPI.formatCurrency(d.revenue));
            setStatValue(els.avgTicket, CashierAPI.formatCurrency(d.avg_ticket ?? 0));

            const recent = d.recent_sales || [];
            if (els.lastSaleHint) {
                if (recent.length) {
                    const last = recent[0];
                    els.lastSaleHint.textContent = `Dernière : ${CashierAPI.formatDate(last.created_at || last.sale_date, { hour: '2-digit', minute: '2-digit' })}`;
                } else {
                    els.lastSaleHint.textContent = 'Aucune vente encore';
                }
            }

            renderRecentSales(recent);
            renderPaymentSummary(d.payment_summary || [], d.revenue);
        } catch (err) {
            console.error('Dashboard:', err);
            setStatValue(els.todaySalesCount, '—');
            setStatValue(els.todayRevenue, 'Erreur');
            setStatValue(els.avgTicket, '—');
            if (els.recentSalesList) {
                els.recentSalesList.innerHTML = `<li class="cd-empty"><p>Impossible de charger les données</p></li>`;
            }
        }

        els.refreshBtn?.classList.remove('spinning');
    }

    /* Sidebar mobile */
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

    els.refreshBtn?.addEventListener('click', loadDashboard);

    updateClock();
    setInterval(updateClock, 1000);
    loadDashboard();
});
