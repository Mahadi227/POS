/**
 * Approvals queue — main index page
 */
(() => {
    const root = document.getElementById('approvalsQueueRoot');
    if (!root || root.dataset.approvalFilter) return;

    const i18n = window.MANAGER_I18N || {};
    const locale = window.MANAGER_CONFIG?.locale || 'fr-FR';
    let lastFetchAt = null;
    let allItems = [];
    let currentType = 'all';

    const els = {
        errorBanner: document.getElementById('mgrError'),
        lastUpdated: document.getElementById('lastUpdated'),
        typeFilter: document.getElementById('approvalsTypeFilter'),
        countTotal: document.getElementById('apprCountTotal'),
        countReturns: document.getElementById('apprCountReturns'),
        countDiscounts: document.getElementById('apprCountDiscounts'),
        countVoids: document.getElementById('apprCountVoids'),
        listCount: document.getElementById('approvalsListCount'),
        summaryCards: document.querySelectorAll('#approvalsSummary .ad-stat-card'),
        sidebarBadge: document.getElementById('sidebar-pending-approvals'),
    };

    const typeIcons = {
        return: 'assignment_return',
        discount: 'percent',
        void: 'block',
        stock_adjustment: 'inventory',
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

    function typeLabel(type) {
        const map = {
            return: t('type_return'),
            discount: t('type_discount'),
            void: t('type_void'),
            stock_adjustment: t('type_stock_adjustment'),
        };
        return map[type] || type || '—';
    }

    function typeBadgeClass(type) {
        if (type === 'return') return 'mgr-badge--idle';
        if (type === 'discount') return 'mgr-badge--ok';
        if (type === 'void') return 'mgr-badge--off';
        return '';
    }

    function countByType(items) {
        const counts = { return: 0, discount: 0, void: 0, stock_adjustment: 0 };
        items.forEach((item) => {
            if (counts[item.type] !== undefined) counts[item.type] += 1;
        });
        return counts;
    }

    function updateSummary(items) {
        const counts = countByType(items);
        const total = items.length;
        if (els.countTotal) els.countTotal.textContent = String(total);
        if (els.countReturns) els.countReturns.textContent = String(counts.return);
        if (els.countDiscounts) els.countDiscounts.textContent = String(counts.discount);
        if (els.countVoids) els.countVoids.textContent = String(counts.void + counts.stock_adjustment);
        if (els.sidebarBadge) {
            els.sidebarBadge.textContent = String(total);
            els.sidebarBadge.classList.toggle('hidden', total <= 0);
        }
    }

    function setTypeActive(type) {
        els.typeFilter?.querySelectorAll('.mgr-period-btn').forEach((btn) => {
            const active = btn.dataset.type === type;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function filteredItems() {
        if (currentType === 'all') return allItems;
        return allItems.filter((item) => item.type === currentType);
    }

    function renderList(items) {
        if (els.listCount) els.listCount.textContent = String(items.length);

        if (!items.length) {
            root.innerHTML = `<p class="mgr-empty">${esc(t('no_pending_approvals'))}</p>`;
            return;
        }

        root.innerHTML = items.map((a) => {
            const icon = typeIcons[a.type] || 'pending_actions';
            return `
            <article class="mgr-approval-card" data-id="${esc(String(a.id))}">
                <header>
                    <span class="mgr-approval-type">
                        <span class="material-icons-round">${esc(icon)}</span>
                        <span class="mgr-badge ${typeBadgeClass(a.type)}">${esc(typeLabel(a.type))}</span>
                    </span>
                    <time>${esc(ManagerAPI.formatRelative(a.created_at))}</time>
                </header>
                <div class="mgr-approval-body">
                    <p class="mgr-approval-main">
                        <strong>${esc(a.requester_name)}</strong>
                        <span class="mgr-approval-amount">${esc(ManagerAPI.formatCurrency(a.amount))}</span>
                    </p>
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

    function render() {
        renderList(filteredItems());
    }

    async function load() {
        hideError();
        setSummaryLoading(true);
        root.innerHTML = `<div class="mgr-list mgr-list--loading">${esc(t('loading'))}</div>`;

        try {
            const res = await ManagerAPI.getApprovals();
            if (res.status !== 'success') {
                throw new Error(res.message || t('load_error'));
            }

            allItems = res.data || [];
            updateSummary(allItems);
            lastFetchAt = new Date();
            updateLastUpdated();
            render();
        } catch (e) {
            console.error(e);
            const msg = e.message || t('load_error');
            showError(msg);
            root.innerHTML = `<p class="mgr-empty">${esc(msg)}</p>`;
            if (els.countTotal) els.countTotal.textContent = '—';
            if (els.countReturns) els.countReturns.textContent = '—';
            if (els.countDiscounts) els.countDiscounts.textContent = '—';
            if (els.countVoids) els.countVoids.textContent = '—';
            if (els.listCount) els.listCount.textContent = '—';
        }

        setSummaryLoading(false);
    }

    async function act(btn, kind) {
        const card = btn.closest('.mgr-approval-card');
        const id = card?.dataset.id;
        if (!id) return;

        const note = prompt(kind === 'approve' ? t('approve_prompt') : t('reject_prompt')) || '';
        btn.disabled = true;
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

    els.typeFilter?.addEventListener('click', (e) => {
        const btn = e.target.closest('.mgr-period-btn');
        if (!btn?.dataset.type || btn.dataset.type === currentType) return;
        currentType = btn.dataset.type;
        setTypeActive(currentType);
        render();
    });

    document.addEventListener('DOMContentLoaded', load);
    document.addEventListener('mgr:refresh', load);
})();
