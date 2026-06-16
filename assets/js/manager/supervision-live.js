/**
 * Live registers supervision page
 */
(() => {
    const root = document.getElementById('liveRegistersRoot');
    if (!root) return;

    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    let lastFetchAt = null;

    const els = {
        errorBanner: document.getElementById('mgrError'),
        lastUpdated: document.getElementById('lastUpdated'),
        countOnline: document.getElementById('liveCountOnline'),
        countIdle: document.getElementById('liveCountIdle'),
        countShift: document.getElementById('liveCountShift'),
        countTotal: document.getElementById('liveCountTotal'),
        tableCount: document.getElementById('liveTableCount'),
        summaryCards: document.querySelectorAll('#liveSummary .ad-stat-card'),
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

    function statusLabel(status) {
        const map = {
            online: t('register_online'),
            idle: t('register_idle'),
            offline: t('register_offline'),
        };
        return map[status] || status;
    }

    function statusBadgeClass(status) {
        if (status === 'online') return 'mgr-badge--ok';
        if (status === 'idle') return 'mgr-badge--idle';
        return 'mgr-badge--off';
    }

    function sourceLabel(r) {
        if (r.shift_open) return t('source_shift');
        return t('source_presence');
    }

    function updateSummary(items) {
        const online = items.filter((r) => (r.status || (r.online ? 'online' : 'offline')) === 'online').length;
        const idle = items.filter((r) => (r.status || '') === 'idle').length;
        const onShift = items.filter((r) => r.shift_open).length;

        if (els.countOnline) els.countOnline.textContent = String(online);
        if (els.countIdle) els.countIdle.textContent = String(idle);
        if (els.countShift) els.countShift.textContent = String(onShift);
        if (els.countTotal) els.countTotal.textContent = String(items.length);
        if (els.tableCount) els.tableCount.textContent = String(items.length);
    }

    function renderTable(items) {
        const cols = {
            cashier: t('cashier_label'),
            status: t('col_status'),
            activity: t('col_last_activity'),
            sales: t('col_sales_today'),
            source: t('col_source'),
        };

        root.innerHTML = `<div class="mgr-table-wrap"><table class="modern-table mgr-live-table"><thead><tr>
            <th>${esc(cols.cashier)}</th>
            <th>${esc(cols.status)}</th>
            <th>${esc(cols.activity)}</th>
            <th>${esc(cols.sales)}</th>
            <th>${esc(cols.source)}</th>
        </tr></thead><tbody>${items.map((r) => {
            const status = r.status || (r.online ? 'online' : 'offline');
            const activity = r.last_activity_at || r.last_seen || r.last_sale_at || r.opened_at;
            const sales = (r.sales_today ?? 0) > 0
                ? `${r.sales_today} · ${ManagerAPI.formatCurrency(r.sales_today_amount)}`
                : '—';
            const rowClass = status === 'online' ? 'mgr-live-row--online' : '';
            return `<tr class="${rowClass}">
            <td class="mgr-cashier-cell" data-label="${escAttr(cols.cashier)}">
                <strong>${esc(r.cashier_name)}</strong>
                ${r.current_page ? `<span class="mgr-muted">${esc(r.current_page)}</span>` : ''}
            </td>
            <td data-label="${escAttr(cols.status)}"><span class="mgr-badge ${statusBadgeClass(status)}">${esc(statusLabel(status))}</span></td>
            <td data-label="${escAttr(cols.activity)}">${esc(ManagerAPI.formatRelative(activity))}<br><span class="mgr-muted">${esc(ManagerAPI.formatDate(activity))}</span></td>
            <td data-label="${escAttr(cols.sales)}">${esc(sales)}</td>
            <td data-label="${escAttr(cols.source)}">${esc(sourceLabel(r))}</td>
        </tr>`;
        }).join('')}</tbody></table></div>`;
    }

    async function load() {
        hideError();
        setSummaryLoading(true);
        root.innerHTML = `<div class="mgr-list mgr-list--loading">${esc(t('loading'))}</div>`;

        try {
            const res = await ManagerAPI.getLiveRegisters();
            if (res.status !== 'success') {
                throw new Error(res.message || t('load_error'));
            }

            const items = res.data || [];
            updateSummary(items);
            lastFetchAt = new Date();
            updateLastUpdated();

            if (!items.length) {
                root.innerHTML = `<p class="mgr-empty">${esc(t('no_terminals'))}</p>`;
            } else {
                renderTable(items);
            }
        } catch (e) {
            console.error(e);
            const msg = e.message || t('load_error');
            showError(msg);
            root.innerHTML = `<p class="mgr-empty">${esc(msg)}</p>`;
            if (els.countOnline) els.countOnline.textContent = '—';
            if (els.countIdle) els.countIdle.textContent = '—';
            if (els.countShift) els.countShift.textContent = '—';
            if (els.countTotal) els.countTotal.textContent = '—';
            if (els.tableCount) els.tableCount.textContent = '—';
        }

        setSummaryLoading(false);
    }

    document.addEventListener('DOMContentLoaded', load);
    document.addEventListener('mgr:refresh', load);
})();
