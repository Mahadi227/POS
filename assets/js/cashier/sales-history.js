/**
 * Sales history — cashier
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.SALES_CONFIG || {};
    const i18n = window.SALES_I18N || {};
    const locale = cfg.locale || (cfg.lang === 'fr' ? 'fr-FR' : 'en-US');

    if (window.POS_CONFIG && !window.POS_CONFIG.locale) {
        window.POS_CONFIG.locale = locale;
        window.POS_CONFIG.lang = cfg.lang || 'en';
    }

    const PAY_CLASS = {
        cash: 'sh-pay-badge--cash',
        mobile_money: 'sh-pay-badge--mobile_money',
        card: 'sh-pay-badge--card',
    };

    let allSales = [];
    let period = cfg.period || 'today';
    let searchQuery = '';
    let lastFetchAt = null;

    const els = {
        tbody: document.getElementById('salesTableBody'),
        searchInput: document.getElementById('salesSearch'),
        searchClear: document.getElementById('salesSearchClear'),
        refreshBtn: document.getElementById('salesRefreshBtn'),
        countLabel: document.getElementById('salesCountLabel'),
        summaryCount: document.getElementById('summaryCount'),
        summaryRevenue: document.getElementById('summaryRevenue'),
        summaryFiltered: document.getElementById('summaryFiltered'),
        errorBanner: document.getElementById('salesError'),
        headerDate: document.getElementById('shHeaderDate'),
        lastUpdated: document.getElementById('lastUpdated'),
    };

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function escapeAttr(str) {
        return String(str ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function columnLabels() {
        return {
            receipt: t('col_receipt'),
            datetime: t('col_datetime'),
            total: t('col_total'),
            payment: t('col_payment'),
            customer: t('col_customer'),
            actions: t('col_actions'),
        };
    }

    function paymentLabel(method) {
        const map = {
            cash: t('pay_cash'),
            card: t('pay_card'),
            mobile_money: t('pay_mobile_money'),
            split: t('pay_split'),
        };
        return map[method] || CashierAPI.paymentLabel(method);
    }

    function saleDate(sale) {
        return sale.created_at || sale.sale_date;
    }

    function saleTotal(sale) {
        return sale.total ?? sale.total_amount ?? 0;
    }

    function saleReceipt(sale) {
        return sale.receipt_no || sale.receipt_number || `#${sale.id}`;
    }

    function payBadgeClass(method) {
        return PAY_CLASS[method] || 'sh-pay-badge--default';
    }

    function periodLabel() {
        return period === 'today' ? t('period_today_label') : t('period_all_label');
    }

    function updateHeaderDate() {
        const now = new Date();
        const dateStr = now.toLocaleDateString(locale, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
        if (els.headerDate) els.headerDate.textContent = dateStr;
        if (els.lastUpdated && lastFetchAt) {
            const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
            els.lastUpdated.textContent = `${t('last_updated')} · ${time}`;
        }
    }

    function showError(msg) {
        if (!els.errorBanner) return;
        els.errorBanner.classList.add('is-visible');
        const text = els.errorBanner.querySelector('.sh-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        els.errorBanner?.classList.remove('is-visible');
    }

    function setSummaryLoading(loading) {
        document.querySelectorAll('.sh-summary-card').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function getFilteredSales() {
        let list = [...allSales];

        if (searchQuery.trim()) {
            const q = searchQuery.trim().toLowerCase();
            list = list.filter((s) => {
                const receipt = saleReceipt(s).toLowerCase();
                const customer = (s.customer_name || '').toLowerCase();
                return receipt.includes(q) || customer.includes(q) || String(s.id).includes(q);
            });
        }

        list.sort((a, b) => new Date(saleDate(b)) - new Date(saleDate(a)));
        return list;
    }

    function updateSummary(filtered) {
        const revenue = filtered.reduce((sum, s) => sum + parseFloat(saleTotal(s)), 0);
        if (els.summaryCount) els.summaryCount.textContent = String(filtered.length);
        if (els.summaryRevenue) els.summaryRevenue.textContent = CashierAPI.formatCurrency(revenue);
        if (els.summaryFiltered) {
            if (searchQuery.trim() && filtered.length !== allSales.length) {
                els.summaryFiltered.textContent = t('filtered_of_total', String(filtered.length), String(allSales.length));
            } else {
                els.summaryFiltered.textContent = `${t('tickets_count', String(allSales.length))} · ${periodLabel()}`;
            }
        }
        if (els.countLabel) {
            els.countLabel.textContent = t('results_count', String(filtered.length));
        }
    }

    function printReceipt(sale) {
        const url = new URL('../../receipts/templates/thermal-80mm.php', window.location.href);
        if (sale.id) url.searchParams.set('id', String(sale.id));
        else if (sale.receipt_no || sale.receipt_number) {
            url.searchParams.set('receipt_no', sale.receipt_no || sale.receipt_number);
        }
        const win = window.open(url.toString(), 'ReceiptPrint', 'width=420,height=720,scrollbars=yes');
        if (!win) alert(t('popup_blocked'));
    }

    function bindPrintButtons(root) {
        root.querySelectorAll('[data-print-id]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = parseInt(btn.dataset.printId, 10);
                const sale = allSales.find((s) => s.id === id);
                if (sale) printReceipt(sale);
            });
        });
    }

    function renderState(type, title, message, icon = 'inbox') {
        const html = `
            <div class="sh-state ${type === 'error' ? 'sh-state--error' : ''}">
                <span class="material-icons-round">${icon}</span>
                <h3>${escapeHtml(title)}</h3>
                <p>${escapeHtml(message)}</p>
            </div>`;

        if (els.tbody) {
            els.tbody.innerHTML = `<tr><td colspan="6" class="sh-empty">${html}</td></tr>`;
        }
    }

    function renderSales() {
        const filtered = getFilteredSales();
        updateSummary(filtered);

        if (!filtered.length) {
            const msg = searchQuery.trim()
                ? t('no_search_match')
                : period === 'today'
                  ? t('no_sales_today')
                  : t('no_sales_found');
            renderState('', t('no_sales'), msg, searchQuery.trim() ? 'search_off' : 'receipt_long');
            return;
        }

        if (!els.tbody) return;

        const L = columnLabels();
        els.tbody.innerHTML = filtered
            .map((sale) => {
                const receipt = escapeHtml(saleReceipt(sale));
                const date = escapeHtml(CashierAPI.formatDate(saleDate(sale)));
                const total = escapeHtml(CashierAPI.formatCurrency(saleTotal(sale)));
                const pay = escapeHtml(paymentLabel(sale.payment_method));
                const payClass = payBadgeClass(sale.payment_method);
                const customer = sale.customer_name
                    ? `<small>${escapeHtml(sale.customer_name)}</small>`
                    : '';
                const customerCell = sale.customer_name
                    ? escapeHtml(sale.customer_name)
                    : `<span class="sh-muted">${escapeHtml(t('walk_in'))}</span>`;
                const viewUrl = `view_sale.php?id=${sale.id}`;

                return `
                    <tr>
                        <td data-label="${escapeAttr(L.receipt)}">
                            <span class="sh-receipt">${receipt}${customer}</span>
                        </td>
                        <td data-label="${escapeAttr(L.datetime)}"><span class="sh-date">${date}</span></td>
                        <td data-label="${escapeAttr(L.total)}"><span class="sh-total">${total}</span></td>
                        <td data-label="${escapeAttr(L.payment)}"><span class="sh-pay-badge ${payClass}">${pay}</span></td>
                        <td data-label="${escapeAttr(L.customer)}">${customerCell}</td>
                        <td class="sh-col-actions" data-label="${escapeAttr(L.actions)}">
                            <div class="sh-actions">
                                <a href="${viewUrl}" class="sh-action-btn" title="${escapeHtml(t('view_detail'))}">
                                    <span class="material-icons-round">visibility</span>
                                </a>
                                <button type="button" class="sh-action-btn sh-action-btn--print" data-print-id="${sale.id}" title="${escapeHtml(t('reprint'))}">
                                    <span class="material-icons-round">print</span>
                                </button>
                            </div>
                        </td>
                    </tr>`;
            })
            .join('');
        bindPrintButtons(els.tbody);
    }

    async function loadSales() {
        renderState('', t('loading_title'), t('loading_message'), 'hourglass_empty');
        els.refreshBtn?.classList.add('spinning');
        setSummaryLoading(true);
        hideError();

        try {
            const result = await CashierAPI.getSales({
                today: period === 'today',
                limit: 200,
            });

            if (result.status !== 'success') {
                throw new Error(result.message || t('error'));
            }

            allSales = result.data || [];
            lastFetchAt = new Date();
            updateHeaderDate();
            renderSales();
            hideError();
        } catch (err) {
            console.error('Sales history:', err);
            showError(err.message || t('load_error'));
            renderState('error', t('error'), err.message || t('load_error'), 'error_outline');
        }

        setSummaryLoading(false);
        els.refreshBtn?.classList.remove('spinning');
    }

    document.querySelectorAll('.sh-filter-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.sh-filter-btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            period = btn.dataset.period || 'today';
            loadSales();
        });
    });

    els.searchInput?.addEventListener('input', (e) => {
        searchQuery = e.target.value;
        els.searchClear?.classList.toggle('visible', searchQuery.length > 0);
        renderSales();
    });

    els.searchClear?.addEventListener('click', () => {
        if (els.searchInput) els.searchInput.value = '';
        searchQuery = '';
        els.searchClear?.classList.remove('visible');
        renderSales();
        els.searchInput?.focus();
    });

    els.refreshBtn?.addEventListener('click', loadSales);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            loadSales();
        }
    });

    updateHeaderDate();
    loadSales();
});
