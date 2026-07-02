/**
 * Admin sales — dynamic list, stats, details, print, i18n
 */
(() => {
    const cfg = window.ADMIN_CONFIG || {};
    const i18n = window.ADMIN_I18N || {};
    const locale = cfg.locale || (cfg.lang === 'fr' ? 'fr-FR' : 'en-US');

    const PAGE_SIZE = 20;
    const PAY_CLASS = {
        cash: 'as-pay-badge--cash',
        card: 'as-pay-badge--card',
        mobile_money: 'as-pay-badge--mobile_money',
    };

    const $ = (id) => document.getElementById(id);
    let allSales = [];
    let period = 'today';
    let paymentFilter = '';
    let searchQuery = '';
    let startDate = '';
    let endDate = '';
    let currentPage = 1;
    let currentSaleId = null;
    let editingSaleId = null;
    let searchDebounce = null;

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function showError(msg) {
        const banner = $('salesError');
        if (!banner) return;
        const text = banner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        banner.classList.add('is-visible');
    }

    function hideError() {
        $('salesError')?.classList.remove('is-visible');
    }

    function toast(msg, type = 'success') {
        const el = $('asToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `as-toast show ${type === 'error' ? 'error' : ''}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3000);
    }

    function saleReceipt(s) {
        return s.receipt_no || s.receipt_number || `#${s.id}`;
    }

    function saleTotal(s) {
        return parseFloat(s.total ?? s.total_amount ?? 0);
    }

    function saleDate(s) {
        return s.created_at || s.sale_date;
    }

    function formatMobileSaleDate(s) {
        const raw = saleDate(s);
        if (!raw) return '—';
        return AdminAPI.formatDate(raw, {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function shortText(str, max = 16) {
        const v = (str || '').trim();
        if (!v) return '—';
        return v.length > max ? `${v.slice(0, max - 1)}…` : v;
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('#salesSummaryCards .ad-kpi').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function clearKpiLoading(el) {
        el?.closest('.ad-kpi')?.classList.remove('is-loading');
    }

    function formatDateOnly(dateString) {
        if (!dateString) return '—';
        return new Date(dateString).toLocaleDateString(locale, { dateStyle: 'long' });
    }

    function formatSalesDateLabel() {
        if (startDate || endDate) {
            if (startDate && endDate) {
                return t('date_range', formatDateOnly(startDate), formatDateOnly(endDate));
            }
            if (startDate) {
                return t('date_from', formatDateOnly(startDate));
            }
            return t('date_until', formatDateOnly(endDate));
        }

        switch (period) {
            case 'today': return t('period_today');
            case 'week': return t('last_7_days');
            case 'month': return t('last_30_days');
            default: return t('period_all_sales');
        }
    }

    function updateDateHeader() {
        const label = formatSalesDateLabel();
        const header = document.getElementById('sales-date');
        if (header) header.textContent = label;

        const periodEl = document.getElementById('asSalesPeriod');
        if (periodEl) periodEl.textContent = label;

        const scopeEl = document.getElementById('asSalesStoreScope');
        if (scopeEl) {
            scopeEl.textContent = window.ADMIN_PAGE?.storeName || t('dash_all_stores');
        }
    }

    function updateLastUpdated() {
        const el = document.getElementById('lastUpdated');
        if (!el) return;
        const time = new Date().toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        el.textContent = t('last_updated', time);
    }

    function hasActiveFilters() {
        return !!(searchQuery.trim() || paymentFilter || startDate || endDate);
    }

    function getStatsPeriodLabel() {
        let label = formatSalesDateLabel();
        if (paymentFilter) {
            label += ` · ${AdminAPI.paymentLabel(paymentFilter)}`;
        }
        const q = searchQuery.trim();
        if (q) {
            label += ` · ${t('stat_search_filter', q.length > 20 ? `${q.slice(0, 19)}…` : q)}`;
        }
        return label;
    }

    function computeSaleStats(sales) {
        const completed = sales.filter((s) => (s.status || 'completed') !== 'cancelled');
        const cancelled = sales.filter((s) => (s.status || 'completed') === 'cancelled');
        const totalRevenue = completed.reduce((sum, s) => sum + saleTotal(s), 0);
        const cancelledRevenue = cancelled.reduce((sum, s) => sum + saleTotal(s), 0);
        const count = sales.length;
        const completedCount = completed.length;
        const cancelledCount = cancelled.length;
        const avg = completedCount > 0 ? totalRevenue / completedCount : 0;
        const completedShare = count > 0 ? Math.round((completedCount / count) * 100) : 0;

        return {
            count,
            totalRevenue,
            avg,
            completedCount,
            cancelledCount,
            cancelledRevenue,
            completedShare,
        };
    }

    function updateFilteredStats(sales) {
        const stats = computeSaleStats(sales);
        setStatsLoading(false);

        const title1 = $('stat-card-1-title');
        const title2 = $('stat-card-2-title');
        const title3 = $('stat-card-3-title');
        const title4 = $('stat-card-4-title');
        const sub2 = $('stat-card-2-sub');

        if (title1) title1.textContent = getStatsPeriodLabel();
        if (title2) title2.textContent = t('stat_avg_ticket');
        if (title3) title3.textContent = t('status_completed');
        if (title4) title4.textContent = t('status_cancelled');

        $('stat-today-count').textContent = String(stats.count);
        $('stat-today-revenue').textContent = `${t('stat_total_revenue')}: ${AdminAPI.formatCurrency(stats.totalRevenue)}`;
        clearKpiLoading($('stat-today-count'));

        $('stat-today-avg').textContent = AdminAPI.formatCurrency(stats.avg);
        clearKpiLoading($('stat-today-avg'));
        if (sub2) {
            sub2.textContent = stats.completedCount > 0
                ? t('per_transaction')
                : t('no_sales');
        }

        $('stat-week-count').textContent = String(stats.completedCount);
        $('stat-week-revenue').textContent = stats.count > 0
            ? t('stat_completed_share', stats.completedShare)
            : '—';
        clearKpiLoading($('stat-week-count'));

        $('stat-month-count').textContent = String(stats.cancelledCount);
        $('stat-month-revenue').textContent = stats.cancelledCount > 0
            ? t('stat_cancelled_amount', AdminAPI.formatCurrency(stats.cancelledRevenue))
            : '—';
        clearKpiLoading($('stat-month-count'));

        $('salesSummaryCards')?.classList.toggle('is-filtered', hasActiveFilters() || period !== 'today');
    }

    function getFilteredSales() {
        let list = [...allSales];
        const q = searchQuery.trim().toLowerCase();

        if (q) {
            list = list.filter((s) => {
                const receipt = saleReceipt(s).toLowerCase();
                const customer = (s.customer_name || '').toLowerCase();
                const cashier = (s.cashier_name || '').toLowerCase();
                return receipt.includes(q) || customer.includes(q) || cashier.includes(q) || String(s.id).includes(q);
            });
        }

        list.sort((a, b) => new Date(saleDate(b)) - new Date(saleDate(a)));
        return list;
    }

    function payBadge(method) {
        const cls = PAY_CLASS[method] || 'as-pay-badge--default';
        const label = AdminAPI.paymentLabel(method);
        return `<span class="as-pay-badge ${cls}">${escapeHtml(label)}</span>`;
    }

    function statusBadge(status) {
        const cls = AdminAPI.statusClass(status);
        return `<span class="status-badge ${cls}">${escapeHtml(AdminAPI.statusLabel(status))}</span>`;
    }

    function renderSales() {
        const filtered = getFilteredSales();
        updateFilteredStats(filtered);
        const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * PAGE_SIZE;
        const pageItems = filtered.slice(start, start + PAGE_SIZE);
        const filteredRevenue = filtered.reduce((sum, s) => sum + saleTotal(s), 0);

        $('tableSummary').textContent = filtered.length === 0
            ? t('no_sales')
            : t('table_summary', filtered.length, AdminAPI.formatCurrency(filteredRevenue), currentPage, totalPages);
        $('pageInfo').textContent = `${currentPage} / ${totalPages}`;
        $('pagePrev').disabled = currentPage <= 1;
        $('pageNext').disabled = currentPage >= totalPages;

        const tbody = $('salesTableBody');
        tbody.innerHTML = '';

        if (!pageItems.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="ad-empty-row">${t('no_sales_found')}</td></tr>`;
            return;
        }

        pageItems.forEach((sale) => {
            const tr = document.createElement('tr');
            const status = sale.status || 'completed';
            const canCancel = status !== 'cancelled';
            const canDelete = status === 'cancelled';
            if (status === 'cancelled') tr.classList.add('is-cancelled');
            const lbl = {
                receipt: t('col_receipt_no'),
                date: t('col_date'),
                customer: t('col_customer'),
                cashier: t('col_cashier'),
                total: t('col_total'),
                payment: t('col_payment'),
                status: t('col_status'),
                actions: t('col_actions'),
            };
            const customerName = sale.customer_name || '';
            const crudMenuItem = (cls, icon, label, id) => `
                <button type="button" class="as-act-menu-item ${cls}" data-id="${id}" role="menuitem">
                    <span class="material-icons-round" aria-hidden="true">${icon}</span>
                    <span>${escapeHtml(label)}</span>
                </button>`;
            const moreItems = [
                crudMenuItem('edit-btn', 'edit', t('edit'), sale.id),
                canCancel ? crudMenuItem('cancel-btn', 'block', t('action_cancel'), sale.id) : '',
                canDelete ? crudMenuItem('delete-btn', 'delete_outline', t('action_delete'), sale.id) : '',
            ].filter(Boolean).join('');
            const actionsHtml = `
                <div class="as-row-actions">
                    <button type="button" class="as-act-btn view-btn" data-id="${sale.id}" title="${escapeHtml(t('details'))}">
                        <span class="material-icons-round" aria-hidden="true">visibility</span>
                    </button>
                    <button type="button" class="as-act-btn print-btn" data-id="${sale.id}" title="${escapeHtml(t('print'))}">
                        <span class="material-icons-round" aria-hidden="true">print</span>
                    </button>
                    ${moreItems ? `
                    <div class="as-act-dropdown">
                        <button type="button" class="as-act-btn as-act-more" aria-label="${escapeHtml(t('col_actions'))}" aria-haspopup="true" aria-expanded="false">
                            <span class="material-icons-round" aria-hidden="true">more_vert</span>
                        </button>
                        <div class="as-act-menu" role="menu">${moreItems}</div>
                    </div>` : ''}
                </div>`;
            const mobileCard = `
                <article class="as-m-sale">
                    <div class="as-m-sale__content">
                        <div class="as-m-sale__info">
                            <div class="as-m-sale__line1">
                                <a href="#" class="as-m-sale__receipt receipt-link" data-view-id="${sale.id}">${escapeHtml(saleReceipt(sale))}</a>
                                <span class="as-m-sale__total">${AdminAPI.formatCurrency(saleTotal(sale))}</span>
                            </div>
                            <div class="as-m-sale__line2">
                                <time class="as-m-sale__date">${formatMobileSaleDate(sale)}</time>
                                <span class="as-m-sale__sep" aria-hidden="true">·</span>
                                <span class="as-m-sale__cashier">${escapeHtml(shortText(sale.cashier_name || t('system_cashier'), 18))}</span>
                                ${customerName ? `<span class="as-m-sale__sep" aria-hidden="true">·</span><span class="as-m-sale__customer">${escapeHtml(shortText(customerName, 14))}</span>` : ''}
                            </div>
                            <div class="as-m-sale__line3">
                                ${payBadge(sale.payment_method)}
                                ${statusBadge(status)}
                            </div>
                        </div>
                        <div class="as-m-act-bar" aria-label="${escapeHtml(lbl.actions)}">
                            <button type="button" class="as-m-act view-btn" data-id="${sale.id}" aria-label="${escapeHtml(t('details'))}">
                                <span class="material-icons-round" aria-hidden="true">visibility</span>
                            </button>
                            <button type="button" class="as-m-act print-btn" data-id="${sale.id}" aria-label="${escapeHtml(t('print'))}">
                                <span class="material-icons-round" aria-hidden="true">print</span>
                            </button>
                            ${moreItems ? `
                            <div class="as-act-dropdown">
                                <button type="button" class="as-m-act as-act-more" aria-label="${escapeHtml(t('col_actions'))}" aria-haspopup="true" aria-expanded="false">
                                    <span class="material-icons-round" aria-hidden="true">more_vert</span>
                                </button>
                                <div class="as-act-menu" role="menu">${moreItems}</div>
                            </div>` : ''}
                        </div>
                    </div>
                </article>`;
            tr.innerHTML = `
                <td colspan="8" class="as-sale-card-only">${mobileCard}</td>
                <td class="as-sale-desk col-receipt" data-label="${escapeHtml(lbl.receipt)}">
                    <a href="#" class="receipt-link" data-view-id="${sale.id}">${escapeHtml(saleReceipt(sale))}</a>
                </td>
                <td class="as-sale-desk col-date" data-label="${escapeHtml(lbl.date)}">${AdminAPI.formatDate(saleDate(sale))}</td>
                <td class="as-sale-desk col-customer" data-label="${escapeHtml(lbl.customer)}">${escapeHtml(customerName || '—')}</td>
                <td class="as-sale-desk col-cashier" data-label="${escapeHtml(lbl.cashier)}">${escapeHtml(sale.cashier_name || t('system_cashier'))}</td>
                <td class="as-sale-desk col-total" data-label="${escapeHtml(lbl.total)}">${AdminAPI.formatCurrency(saleTotal(sale))}</td>
                <td class="as-sale-desk col-payment" data-label="${escapeHtml(lbl.payment)}">${payBadge(sale.payment_method)}</td>
                <td class="as-sale-desk col-status" data-label="${escapeHtml(lbl.status)}">${statusBadge(status)}</td>
                <td class="as-sale-desk col-actions" data-label="${escapeHtml(lbl.actions)}">${actionsHtml}</td>
            `;
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('[data-view-id]').forEach((a) => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                viewSaleDetails(a.getAttribute('data-view-id'));
            });
        });
        tbody.querySelectorAll('.view-btn').forEach((btn) => {
            btn.addEventListener('click', () => viewSaleDetails(btn.getAttribute('data-id')));
        });
        tbody.querySelectorAll('.edit-btn').forEach((btn) => {
            btn.addEventListener('click', () => openEditSale(btn.getAttribute('data-id')));
        });
        tbody.querySelectorAll('.print-btn').forEach((btn) => {
            btn.addEventListener('click', () => printReceipt(parseInt(btn.getAttribute('data-id'), 10)));
        });
        tbody.querySelectorAll('.cancel-btn').forEach((btn) => {
            btn.addEventListener('click', () => cancelSale(btn.getAttribute('data-id')));
        });
        tbody.querySelectorAll('.delete-btn').forEach((btn) => {
            btn.addEventListener('click', () => deleteSale(btn.getAttribute('data-id')));
        });
        bindActionDropdowns(tbody);
    }

    function closeActionMenus() {
        document.querySelectorAll('.as-act-dropdown.is-open').forEach((el) => {
            el.classList.remove('is-open');
            el.querySelector('.as-act-more')?.setAttribute('aria-expanded', 'false');
            const menu = el.querySelector('.as-act-menu');
            if (menu) {
                menu.style.position = '';
                menu.style.right = '';
                menu.style.top = '';
                menu.style.left = '';
                menu.style.transform = '';
            }
        });
    }

    function positionActionMenu(dd) {
        const btn = dd.querySelector('.as-act-more');
        const menu = dd.querySelector('.as-act-menu');
        if (!btn || !menu) return;
        const rect = btn.getBoundingClientRect();
        menu.style.position = 'fixed';
        menu.style.left = `${Math.max(8, rect.right - 168)}px`;
        menu.style.top = `${rect.top - 6}px`;
        menu.style.transform = 'translateY(-100%)';
        menu.style.right = 'auto';
    }

    function bindActionDropdowns(root) {
        root.querySelectorAll('.as-act-more').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const dd = btn.closest('.as-act-dropdown');
                const wasOpen = dd?.classList.contains('is-open');
                closeActionMenus();
                if (dd && !wasOpen) {
                    dd.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                    positionActionMenu(dd);
                }
            });
        });
        root.querySelectorAll('.as-act-menu-item').forEach((item) => {
            item.addEventListener('click', () => closeActionMenus());
        });
    }

    function printReceipt(saleId) {
        const sale = allSales.find((s) => s.id === saleId);
        const url = new URL('../../receipts/templates/thermal-80mm.php', window.location.href);
        if (saleId) url.searchParams.set('id', String(saleId));
        else if (sale) {
            const r = sale.receipt_no || sale.receipt_number;
            if (r) url.searchParams.set('receipt_no', r);
        }
        const win = window.open(url.toString(), 'ReceiptPrint', 'width=420,height=720,scrollbars=yes');
        if (!win) toast(t('popup_blocked'), 'error');
    }

    async function loadSales() {
        const tbody = $('salesTableBody');
        tbody.innerHTML = `<tr><td colspan="8" class="ad-empty-row">${t('loading')}</td></tr>`;
        setStatsLoading(true);

        const query = { period, limit: 200 };
        if (paymentFilter) query.payment = paymentFilter;
        if (startDate) query.start_date = startDate;
        if (endDate) query.end_date = endDate;
        if (startDate || endDate) query.period = 'all';

        try {
            const result = await AdminAPI.getSales(query);
            hideError();
            if (result.status === 'success') {
                allSales = result.data || [];
                currentPage = 1;
                renderSales();
            } else {
                showError(result.message || t('load_error'));
                tbody.innerHTML = `<tr><td colspan="8" class="ad-empty-row">${escapeHtml(result.message || t('error'))}</td></tr>`;
                updateFilteredStats([]);
            }
        } catch (e) {
            console.error(e);
            showError(t('connection_error'));
            tbody.innerHTML = `<tr><td colspan="8" class="ad-empty-row">${t('network_error')}</td></tr>`;
            updateFilteredStats([]);
        }
    }

    async function refreshAll() {
        const btn = $('refreshSales');
        btn?.classList.add('spinning');
        await loadSales();
        updateLastUpdated();
        btn?.classList.remove('spinning');
    }

    function openModal() {
        $('saleDetailsModal')?.classList.add('active');
    }

    function closeModal() {
        $('saleDetailsModal')?.classList.remove('active');
        currentSaleId = null;
    }

    function openEditModal() {
        $('saleEditModal')?.classList.add('active');
    }

    function closeEditModal() {
        $('saleEditModal')?.classList.remove('active');
        editingSaleId = null;
    }

    async function openEditSale(id) {
        editingSaleId = parseInt(id, 10);
        const statusEl = $('editSaleStatus');
        const discountEl = $('editSaleDiscount');
        if (statusEl) statusEl.value = 'completed';
        if (discountEl) discountEl.value = '0';

        const cached = allSales.find((s) => s.id === editingSaleId);
        if (cached) {
            if (statusEl) statusEl.value = cached.status || 'completed';
            if (discountEl) discountEl.value = String(cached.discount ?? cached.discount_amount ?? 0);
            if ($('saleEditTitle')) {
                $('saleEditTitle').textContent = t('edit_sale') + ' — ' + saleReceipt(cached);
            }
            openEditModal();
            return;
        }

        try {
            const result = await AdminAPI.getSale(editingSaleId);
            if (result.status !== 'success') {
                toast(result.message || t('error'), 'error');
                return;
            }
            const info = result.data;
            if (statusEl) statusEl.value = info.status || 'completed';
            if (discountEl) discountEl.value = String(info.discount ?? info.discount_amount ?? 0);
            if ($('saleEditTitle')) {
                $('saleEditTitle').textContent = t('edit_sale') + ' — ' + saleReceipt(info);
            }
            openEditModal();
        } catch (e) {
            console.error(e);
            toast(t('connection_error'), 'error');
        }
    }

    async function saveEditSale(e) {
        e?.preventDefault();
        if (!editingSaleId) return;

        const status = $('editSaleStatus')?.value || 'completed';
        const discount = parseFloat($('editSaleDiscount')?.value) || 0;
        const payload = { status, discount };

        try {
            const result = await AdminAPI.updateSale(editingSaleId, payload);
            if (result.status === 'success') {
                toast(
                    status === 'cancelled' ? t('sale_cancelled') : t('sale_updated'),
                    'success'
                );
                closeEditModal();
                await refreshAll();
            } else {
                toast(result.message || t('error'), 'error');
            }
        } catch (err) {
            console.error(err);
            toast(t('connection_error'), 'error');
        }
    }

    async function cancelSale(id) {
        const sale = allSales.find((s) => String(s.id) === String(id));
        const label = sale ? saleReceipt(sale) : `#${id}`;
        if (!confirm(t('cancel_sale_confirm') + `\n\n${label}`)) return;

        try {
            const result = await AdminAPI.cancelSale(id);
            if (result.status === 'success') {
                toast(t('sale_cancelled'), 'success');
                await refreshAll();
            } else {
                toast(result.message || t('error'), 'error');
            }
        } catch (e) {
            console.error(e);
            toast(t('connection_error'), 'error');
        }
    }

    async function deleteSale(id) {
        const sale = allSales.find((s) => String(s.id) === String(id));
        const label = sale ? saleReceipt(sale) : `#${id}`;
        if (!confirm(t('delete_sale_confirm', label))) return;

        try {
            const result = await AdminAPI.deleteSale(id);
            if (result.status === 'success') {
                toast(t('sale_deleted'), 'success');
                await refreshAll();
            } else {
                toast(result.message || t('sale_delete_blocked'), 'error');
            }
        } catch (e) {
            console.error(e);
            toast(t('connection_error'), 'error');
        }
    }

    async function viewSaleDetails(id) {
        currentSaleId = parseInt(id, 10);
        const content = $('saleDetailsContent');
        content.innerHTML = `<p class="ad-empty-row">${t('loading')}</p>`;
        openModal();

        try {
            const result = await AdminAPI.getSale(id);
            if (result.status !== 'success') {
                content.innerHTML = `<p style="color:var(--danger);">${escapeHtml(result.message || t('error'))}</p>`;
                return;
            }

            const info = result.data;
            const items = info.items || [];

            let rows = '';
            items.forEach((item) => {
                rows += `
                    <tr>
                        <td>${escapeHtml(item.product_name)}<br><small style="color:var(--text-muted);">${escapeHtml(item.sku)}</small></td>
                        <td>${item.quantity}</td>
                        <td>${AdminAPI.formatCurrency(item.unit_price)}</td>
                        <td>${AdminAPI.formatCurrency(item.subtotal)}</td>
                    </tr>`;
            });

            content.innerHTML = `
                <div class="as-detail-meta">
                    <div><strong>${t('modal_receipt')}</strong>${escapeHtml(saleReceipt(info))}</div>
                    <div><strong>${t('modal_date')}</strong>${AdminAPI.formatDate(saleDate(info))}</div>
                    <div><strong>${t('modal_cashier')}</strong>${escapeHtml(info.cashier_name || '—')}</div>
                    <div><strong>${t('modal_customer')}</strong>${escapeHtml(info.customer_name || '—')}</div>
                    <div><strong>${t('modal_payment')}</strong>${payBadge(info.payment_method)}</div>
                    <div><strong>${t('modal_status')}</strong>${statusBadge(info.status)}</div>
                    ${info.store_name ? `<div><strong>${t('modal_store')}</strong>${escapeHtml(info.store_name)}</div>` : ''}
                </div>
                <table class="as-receipt-items">
                    <thead>
                        <tr><th>${t('col_product')}</th><th>${t('col_qty')}</th><th>${t('col_unit_price')}</th><th>${t('col_subtotal')}</th></tr>
                    </thead>
                    <tbody>${rows || `<tr><td colspan="4">${t('no_items')}</td></tr>`}</tbody>
                </table>
                <div class="as-receipt-summary">
                    <div><span style="color:var(--text-secondary);">${t('subtotal_label')}</span> <strong>${AdminAPI.formatCurrency(info.subtotal ?? 0)}</strong></div>
                    <div><span style="color:var(--text-secondary);">${t('tax_label')}</span> <strong>${AdminAPI.formatCurrency(info.tax_amount ?? info.tax ?? 0)}</strong></div>
                    <div><span style="color:var(--text-secondary);">${t('discount_label')}</span> <strong>${AdminAPI.formatCurrency(info.discount_amount ?? info.discount ?? 0)}</strong></div>
                    <div class="total-line">${t('total_label')} ${AdminAPI.formatCurrency(info.total_amount ?? info.total ?? 0)}</div>
                </div>`;

            $('modalTitle').textContent = t('sale_title', saleReceipt(info));
        } catch (e) {
            console.error(e);
            content.innerHTML = `<p style="color:var(--danger);">${t('connection_error')}</p>`;
        }
    }

    function setActivePeriodChip(periodValue) {
        document.querySelectorAll('.as-chip').forEach((c) => {
            const active = c.dataset.period === periodValue;
            c.classList.toggle('active', active);
            c.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function initEvents() {
        document.querySelectorAll('.as-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                setActivePeriodChip(chip.dataset.period || 'all');
                period = chip.dataset.period || 'all';
                startDate = '';
                endDate = '';
                if ($('salesStartDate')) $('salesStartDate').value = '';
                if ($('salesEndDate')) $('salesEndDate').value = '';
                updateDateHeader();
                loadSales();
            });
        });

        $('paymentFilter')?.addEventListener('change', (e) => {
            paymentFilter = e.target.value;
            loadSales();
        });

        $('applyDateFilter')?.addEventListener('click', () => {
            startDate = $('salesStartDate')?.value || '';
            endDate = $('salesEndDate')?.value || '';
            period = 'all';
            setActivePeriodChip('all');
            updateDateHeader();
            loadSales();
        });

        $('clearDateFilter')?.addEventListener('click', () => {
            startDate = '';
            endDate = '';
            period = 'all';
            setActivePeriodChip('all');
            if ($('salesStartDate')) $('salesStartDate').value = '';
            if ($('salesEndDate')) $('salesEndDate').value = '';
            updateDateHeader();
            loadSales();
        });

        $('searchInput')?.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            $('searchClear')?.classList.toggle('visible', !!searchQuery.trim());
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                currentPage = 1;
                renderSales();
            }, 280);
        });

        $('searchClear')?.addEventListener('click', () => {
            $('searchInput').value = '';
            searchQuery = '';
            $('searchClear')?.classList.remove('visible');
            currentPage = 1;
            renderSales();
        });

        $('pagePrev')?.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderSales();
            }
        });
        $('pageNext')?.addEventListener('click', () => {
            currentPage++;
            renderSales();
        });

        $('refreshSales')?.addEventListener('click', () => refreshAll());
        document.addEventListener('store-switched', () => refreshAll());

        $('closeModalBtn')?.addEventListener('click', closeModal);
        $('editFromDetailsBtn')?.addEventListener('click', () => {
            if (currentSaleId) {
                closeModal();
                openEditSale(currentSaleId);
            }
        });
        $('printReceiptBtn')?.addEventListener('click', () => {
            if (currentSaleId) printReceipt(currentSaleId);
        });

        $('saleDetailsModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'saleDetailsModal') closeModal();
        });

        $('saleEditForm')?.addEventListener('submit', saveEditSale);
        $('closeEditModalBtn')?.addEventListener('click', closeEditModal);
        $('saleEditModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'saleEditModal') closeEditModal();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.as-act-dropdown')) closeActionMenus();
        });
        window.addEventListener('scroll', closeActionMenus, true);
        window.addEventListener('resize', closeActionMenus);
    }

    document.addEventListener('DOMContentLoaded', () => {
        initEvents();
        updateDateHeader();
        refreshAll();
    });
})();
