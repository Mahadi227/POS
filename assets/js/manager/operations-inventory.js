/**
 * Inventory alerts — manager operations
 */
(() => {
    const root = document.getElementById('inventoryAlertsRoot');
    if (!root) return;

    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    let activeFilter = 'all';
    let lastFetchAt = null;

    const els = {
        errorBanner: document.getElementById('mgrError'),
        lastUpdated: document.getElementById('lastUpdated'),
        countTotal: document.getElementById('invCountTotal'),
        countOut: document.getElementById('invCountOut'),
        countLow: document.getElementById('invCountLow'),
        countExpiring: document.getElementById('invCountExpiring'),
        tableCount: document.getElementById('invTableCount'),
        summaryCards: document.querySelectorAll('#invSummary .ad-stat-card'),
        filterBar: document.getElementById('invFilterBar'),
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

    function alertLabel(type) {
        const map = {
            out_of_stock: t('alert_out_of_stock'),
            low_stock: t('alert_low_stock'),
            expired: t('alert_expired'),
            expiring: t('alert_expiring'),
        };
        return map[type] || type;
    }

    function alertBadgeClass(type) {
        if (type === 'out_of_stock') return 'mgr-badge--off';
        if (type === 'expired') return 'mgr-badge--off';
        if (type === 'expiring') return 'mgr-badge--idle';
        return 'mgr-badge--idle';
    }

    function formatExpiry(row) {
        if (!row.expiry_date) return '—';
        const days = Number(row.days_until_expiry);
        if (Number.isNaN(days)) return ManagerAPI.formatDate(row.expiry_date);
        if (days < 0) return t('days_expired', String(Math.abs(days)));
        if (days === 0) return t('expires_today');
        if (days <= 7) return t('days_until_expiry', String(days));
        return ManagerAPI.formatDate(row.expiry_date);
    }

    function updateSummary(summary) {
        const s = summary || {};
        if (els.countTotal) els.countTotal.textContent = String(s.total ?? 0);
        if (els.countOut) els.countOut.textContent = String(s.out_of_stock ?? 0);
        if (els.countLow) els.countLow.textContent = String(s.low_stock ?? 0);
        if (els.countExpiring) els.countExpiring.textContent = String((s.expired ?? 0) + (s.expiring_soon ?? 0));
    }

    function resetSummary() {
        ['countTotal', 'countOut', 'countLow', 'countExpiring', 'tableCount'].forEach((key) => {
            if (els[key]) els[key].textContent = '—';
        });
    }

    function syncFilterBar() {
        if (!els.filterBar) return;
        els.filterBar.querySelectorAll('[data-filter]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.filter === activeFilter);
        });
    }

    function renderTable(items) {
        const cols = {
            product: t('col_product'),
            sku: t('col_sku'),
            category: t('col_category'),
            stock: t('col_stock'),
            min: t('col_min_stock'),
            expiry: t('col_expiry'),
            alert: t('col_alert'),
        };

        root.innerHTML = `<div class="mgr-table-wrap"><table class="modern-table mgr-inv-table"><thead><tr>
            <th>${esc(cols.product)}</th>
            <th>${esc(cols.sku)}</th>
            <th>${esc(cols.category)}</th>
            <th>${esc(cols.stock)}</th>
            <th>${esc(cols.min)}</th>
            <th>${esc(cols.expiry)}</th>
            <th>${esc(cols.alert)}</th>
        </tr></thead><tbody>${items.map((row) => {
            const type = row.alert_type || 'low_stock';
            const rowClass = type === 'out_of_stock' ? 'mgr-inv-row--critical' : type === 'expired' ? 'mgr-inv-row--critical' : '';
            return `<tr class="${rowClass}">
            <td class="mgr-product-cell" data-label="${escAttr(cols.product)}"><strong>${esc(row.name)}</strong></td>
            <td data-label="${escAttr(cols.sku)}">${esc(row.sku || '—')}</td>
            <td data-label="${escAttr(cols.category)}">${esc(row.category_name || '—')}</td>
            <td data-label="${escAttr(cols.stock)}">${esc(String(row.stock_quantity ?? 0))}</td>
            <td data-label="${escAttr(cols.min)}">${esc(String(row.min_stock_level ?? 5))}</td>
            <td data-label="${escAttr(cols.expiry)}">${esc(formatExpiry(row))}</td>
            <td data-label="${escAttr(cols.alert)}"><span class="mgr-badge ${alertBadgeClass(type)}">${esc(alertLabel(type))}</span></td>
        </tr>`;
        }).join('')}</tbody></table></div>`;
    }

    async function load() {
        hideError();
        setSummaryLoading(true);
        syncFilterBar();
        root.innerHTML = `<div class="mgr-list mgr-list--loading">${esc(t('loading'))}</div>`;

        try {
            const res = await ManagerAPI.getInventoryAlerts(activeFilter);
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
                root.innerHTML = `<p class="mgr-empty">${esc(t('no_inventory_alerts'))}</p>`;
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
