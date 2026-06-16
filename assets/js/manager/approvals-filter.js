/**
 * Filtered approval pages (returns, discounts, voids)
 */
(() => {
    const root = document.querySelector('.mgr-approvals-filter-root');
    if (!root) return;

    const filter = root.dataset.approvalFilter;
    if (!filter) return;

    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    const emptyKey = root.dataset.emptyKey || 'no_pending_approvals';
    const typeLabelKey = root.dataset.typeLabelKey || 'type_return';
    const typeIcon = root.dataset.typeIcon || 'pending_actions';
    const badgeClass = root.dataset.badgeClass || 'mgr-badge--idle';

    let lastFetchAt = null;

    const els = {
        errorBanner: document.getElementById('mgrError'),
        lastUpdated: document.getElementById('lastUpdated'),
        countPending: document.getElementById('apprFilterCount'),
        totalAmount: document.getElementById('apprFilterTotal'),
        avgAmount: document.getElementById('apprFilterAvg'),
        listCount: document.getElementById('apprFilterListCount'),
        summaryCards: document.querySelectorAll('#apprFilterSummary .ad-stat-card'),
        sidebarBadge: document.getElementById('sidebar-pending-approvals'),
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

    function toast(msg, type = 'success') {
        const el = document.getElementById('mgrToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast mgr-toast show ${type}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
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

    function filterItems(items) {
        return items.filter((row) => String(row.type || '') === filter);
    }

    function updateSummary(items) {
        const count = items.length;
        const total = items.reduce((sum, row) => sum + Number(row.amount || 0), 0);
        const avg = count > 0 ? total / count : 0;

        if (els.countPending) els.countPending.textContent = String(count);
        if (els.totalAmount) els.totalAmount.textContent = ManagerAPI.formatCurrency(total);
        if (els.avgAmount) els.avgAmount.textContent = ManagerAPI.formatCurrency(avg);
        if (els.listCount) els.listCount.textContent = String(count);
    }

    function resetSummary() {
        ['countPending', 'totalAmount', 'avgAmount', 'listCount'].forEach((key) => {
            if (els[key]) els[key].textContent = '—';
        });
    }

    function updateSidebarBadge(total) {
        if (!els.sidebarBadge) return;
        els.sidebarBadge.textContent = String(total);
        els.sidebarBadge.classList.toggle('hidden', total <= 0);
    }

    function renderList(items) {
        if (!items.length) {
            root.innerHTML = `<p class="mgr-empty">${esc(t(emptyKey))}</p>`;
            return;
        }

        root.innerHTML = items.map((a) => {
            const payload = typeof a.payload === 'string' ? (() => {
                try { return JSON.parse(a.payload); } catch { return {}; }
            })() : (a.payload || {});
            const receipt = payload.receipt_no ? `#${payload.receipt_no}` : '';
            return `
            <article class="mgr-approval-card" data-id="${esc(String(a.id))}">
                <header>
                    <span class="mgr-approval-type">
                        <span class="material-icons-round">${esc(typeIcon)}</span>
                        <span class="mgr-badge ${esc(badgeClass)}">${esc(t(typeLabelKey))}</span>
                    </span>
                    <time>${esc(ManagerAPI.formatRelative(a.created_at))}</time>
                </header>
                <div class="mgr-approval-body">
                    <p class="mgr-approval-main">
                        <strong>${esc(a.requester_name)}</strong>
                        <span class="mgr-approval-amount">${esc(ManagerAPI.formatCurrency(a.amount))}</span>
                    </p>
                    ${receipt ? `<p class="mgr-muted">${esc(receipt)}</p>` : ''}
                    <p class="mgr-muted">${esc(a.reason || t('no_reason'))}</p>
                    <p class="mgr-approval-meta">${esc(ManagerAPI.formatDate(a.created_at))}</p>
                </div>
                <footer>
                    <button type="button" class="inv-btn inv-btn-outline mgr-reject">${esc(t('reject_btn'))}</button>
                    <button type="button" class="inv-btn inv-btn-primary mgr-approve">${esc(t('approve_btn'))}</button>
                </footer>
            </article>`;
        }).join('');

        root.querySelectorAll('.mgr-approve').forEach((btn) => {
            btn.addEventListener('click', () => act(btn, 'approve'));
        });
        root.querySelectorAll('.mgr-reject').forEach((btn) => {
            btn.addEventListener('click', () => act(btn, 'reject'));
        });
    }

    async function load() {
        if (typeof ManagerAPI === 'undefined') {
            showError(t('load_error'));
            return;
        }

        hideError();
        setSummaryLoading(true);
        root.innerHTML = `<div class="mgr-list mgr-list--loading">${esc(t('loading'))}</div>`;

        try {
            const [typedRes, allRes] = await Promise.all([
                ManagerAPI.getApprovals(filter),
                ManagerAPI.getApprovals(),
            ]);

            let items = [];
            if (typedRes.status === 'success') {
                items = typedRes.data || [];
            }
            if (!items.length && allRes.status === 'success') {
                items = filterItems(allRes.data || []);
            }
            if (typedRes.status !== 'success' && allRes.status !== 'success') {
                throw new Error(typedRes.message || allRes.message || t('load_error'));
            }

            const allItems = allRes.status === 'success' ? (allRes.data || []) : items;

            updateSummary(items);
            updateSidebarBadge(allItems.length);
            lastFetchAt = new Date();
            updateLastUpdated();
            renderList(items);
        } catch (e) {
            console.error(e);
            const msg = e.message || t('load_error');
            showError(msg);
            root.innerHTML = `<p class="mgr-empty">${esc(msg)}</p>`;
            resetSummary();
        }

        setSummaryLoading(false);
    }

    async function act(btn, kind) {
        const card = btn.closest('.mgr-approval-card');
        const id = card?.dataset.id;
        if (!id) return;

        const note = prompt(kind === 'approve' ? t('approve_prompt') : t('reject_prompt')) || '';
        const cardFooter = card.querySelector('footer');
        cardFooter?.querySelectorAll('button').forEach((b) => { b.disabled = true; });

        try {
            const res = kind === 'approve'
                ? await ManagerAPI.approve(id, note)
                : await ManagerAPI.reject(id, note);

            if (res.status !== 'success') {
                throw new Error(res.message || t('action_error'));
            }

            toast(kind === 'approve' ? t('approved_ok') : t('rejected_ok'));
            await load();
        } catch (e) {
            toast(e.message || t('action_error'), 'error');
            cardFooter?.querySelectorAll('button').forEach((b) => { b.disabled = false; });
        }
    }

    document.addEventListener('mgr:refresh', load);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
