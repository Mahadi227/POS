/**
 * Cash registers — list (grid / table), filters, session modals
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('crRegistersRoot');
    if (!root) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = CashRegistersUI;
    const canManage = window.ADMIN_PAGE?.canManage;

    const state = {
        items: [],
        filter: 'all',
        search: '',
        view: localStorage.getItem('cr-reg-view') || 'grid',
    };

    const els = {
        search: document.getElementById('crRegSearch'),
        filters: document.getElementById('crRegFilters'),
        count: document.getElementById('crRegCount'),
        statTotal: document.getElementById('crRegStatTotal'),
        statOpen: document.getElementById('crRegStatOpen'),
        statClosed: document.getElementById('crRegStatClosed'),
        statBalance: document.getElementById('crRegStatBalance'),
        openModal: document.getElementById('crOpenModal'),
        closeModal: document.getElementById('crCloseModal'),
        openForm: document.getElementById('crOpenModalForm'),
        closeForm: document.getElementById('crCloseModalForm'),
    };

    function registerStatusLabel(status) {
        const map = {
            active: t('cr_status_active'),
            inactive: t('cr_status_inactive'),
            maintenance: t('cr_status_maintenance'),
        };
        return map[status] || status;
    }

    function statusBadgeClass(status) {
        if (status === 'active') return 'ok';
        if (status === 'maintenance') return 'warn';
        return 'off';
    }

    function filteredItems() {
        let list = [...state.items];
        const q = state.search.trim().toLowerCase();

        if (q) {
            list = list.filter((r) => {
                const hay = [r.register_code, r.name, r.store_name, r.assigned_cashier]
                    .map((v) => String(v ?? '').toLowerCase()).join(' ');
                return hay.includes(q);
            });
        }

        switch (state.filter) {
            case 'session_open':
                list = list.filter((r) => r.session_status === 'open');
                break;
            case 'session_closed':
                list = list.filter((r) => r.session_status !== 'open');
                break;
            case 'active':
                list = list.filter((r) => (r.status || 'active') === 'active');
                break;
            case 'inactive':
                list = list.filter((r) => (r.status || 'active') !== 'active');
                break;
            default:
                break;
        }

        return list;
    }

    function updateHeroStats(items) {
        const total = items.length;
        const open = items.filter((r) => r.session_status === 'open').length;
        const closed = total - open;
        const balance = items.reduce((sum, r) => sum + Number(r.current_balance || 0), 0);

        const set = (el, val, loading = false) => {
            if (!el) return;
            el.textContent = val;
            el.classList.toggle('is-loading', loading);
        };

        set(els.statTotal, String(total));
        set(els.statOpen, String(open));
        set(els.statClosed, String(closed));
        set(els.statBalance, money(balance));

        if (els.count) {
            const shown = filteredItems().length;
            els.count.textContent = t('cr_registers_count', String(shown));
        }
    }

    function bindCardActions(container, items) {
        container.querySelectorAll('[data-open]').forEach((btn) => {
            btn.addEventListener('click', () => openModalForRegister(items.find((r) => r.id === Number(btn.dataset.open))));
        });
        container.querySelectorAll('[data-close-session]').forEach((btn) => {
            btn.addEventListener('click', () => closeModalForRegister(items.find((r) => r.id === Number(btn.dataset.closeSession))));
        });
    }

    function renderGrid(items) {
        return `
            <div class="cr-reg-grid">
                ${items.map((r) => {
                    const isOpen = r.session_status === 'open';
                    const regStatus = r.status || 'active';
                    return `
                    <article class="cr-reg-card${isOpen ? ' is-open' : ''}">
                        <header class="cr-reg-card__head">
                            <div class="cr-reg-card__icon"><span class="material-icons-round">${isOpen ? 'point_of_sale' : 'storefront'}</span></div>
                            <div class="cr-reg-card__title">
                                <strong>${esc(r.name)}</strong>
                                <span class="cr-muted">${esc(r.register_code)} · ${esc(r.store_name)}</span>
                            </div>
                            <span class="cr-badge cr-badge--${isOpen ? 'ok' : 'idle'}">${esc(isOpen ? t('cr_session_open') : t('cr_session_closed'))}</span>
                        </header>
                        <div class="cr-reg-card__body">
                            <div class="cr-reg-card__row">
                                <span>${esc(t('cr_assigned_cashier'))}</span>
                                <strong>${esc(r.assigned_cashier || '—')}</strong>
                            </div>
                            <div class="cr-reg-card__row">
                                <span>${esc(t('col_status'))}</span>
                                <span class="cr-badge cr-badge--${statusBadgeClass(regStatus)}">${esc(registerStatusLabel(regStatus))}</span>
                            </div>
                            <div class="cr-reg-card__balance">
                                <span>${esc(t('cr_stat_cash_balance'))}</span>
                                <strong>${esc(money(r.current_balance))}</strong>
                            </div>
                        </div>
                        <footer class="cr-reg-card__foot">
                            <a href="register_details.php?id=${encodeURIComponent(r.id)}" class="cr-btn cr-btn--ghost">${esc(t('cr_view_details'))}</a>
                            ${canManage ? `<a href="edit_register.php?id=${encodeURIComponent(r.id)}" class="cr-btn cr-btn--ghost">${esc(t('cr_edit_register'))}</a>` : ''}
                            ${canManage && !isOpen && regStatus === 'active' ? `<button type="button" class="cr-btn" data-open="${r.id}"><span class="material-icons-round">lock_open</span>${esc(t('cr_open_register'))}</button>` : ''}
                            ${canManage && isOpen && r.open_session_id ? `<button type="button" class="cr-btn cr-btn--warn" data-close-session="${r.id}"><span class="material-icons-round">lock</span>${esc(t('cr_close_register'))}</button>` : ''}
                        </footer>
                    </article>`;
                }).join('')}
            </div>`;
    }

    function renderTable(items) {
        return `
            <div class="cr-table-wrap cr-reg-table-wrap">
                <table class="modern-table cr-reg-table">
                    <thead><tr>
                        <th>${esc(t('cr_register_code'))}</th>
                        <th>${esc(t('cr_register_name'))}</th>
                        <th>${esc(t('cr_branch'))}</th>
                        <th>${esc(t('cr_assigned_cashier'))}</th>
                        <th>${esc(t('col_status'))}</th>
                        <th>${esc(t('cr_session_open'))}</th>
                        <th>${esc(t('cr_stat_cash_balance'))}</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                        ${items.map((r) => {
                            const isOpen = r.session_status === 'open';
                            const regStatus = r.status || 'active';
                            return `
                            <tr class="${isOpen ? 'is-open' : ''}">
                                <td><strong>${esc(r.register_code)}</strong></td>
                                <td>${esc(r.name)}</td>
                                <td>${esc(r.store_name)}</td>
                                <td>${esc(r.assigned_cashier || '—')}</td>
                                <td><span class="cr-badge cr-badge--${statusBadgeClass(regStatus)}">${esc(registerStatusLabel(regStatus))}</span></td>
                                <td><span class="cr-badge cr-badge--${isOpen ? 'ok' : 'off'}">${esc(isOpen ? t('cr_session_open') : t('cr_session_closed'))}</span></td>
                                <td><strong>${esc(money(r.current_balance))}</strong></td>
                                <td class="cr-actions">
                                    <a href="register_details.php?id=${r.id}" class="cr-btn cr-btn--ghost">${esc(t('cr_view_details'))}</a>
                                    ${canManage ? `<a href="edit_register.php?id=${r.id}" class="cr-btn cr-btn--ghost">${esc(t('cr_edit_register'))}</a>` : ''}
                                    ${canManage && !isOpen && regStatus === 'active' ? `<button type="button" class="cr-btn" data-open="${r.id}">${esc(t('cr_open_register'))}</button>` : ''}
                                    ${canManage && isOpen && r.open_session_id ? `<button type="button" class="cr-btn cr-btn--warn" data-close-session="${r.id}">${esc(t('cr_close_register'))}</button>` : ''}
                                </td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    function renderEmpty() {
        const createBtn = canManage
            ? `<a href="create_register.php" class="cr-btn"><span class="material-icons-round">add</span>${esc(t('cr_new_register'))}</a>`
            : '';
        return `
            <div class="cr-reg-empty">
                <span class="material-icons-round">storefront</span>
                <h3>${esc(t('cr_no_registers'))}</h3>
                <p>${esc(t('cr_no_registers_hint'))}</p>
                ${createBtn}
            </div>`;
    }

    function renderList() {
        const items = filteredItems();
        updateHeroStats(state.items);

        if (!state.items.length) {
            root.innerHTML = renderEmpty();
            return;
        }

        if (!items.length) {
            root.innerHTML = `<div class="cr-reg-empty cr-reg-empty--filter"><span class="material-icons-round">search_off</span><h3>${esc(t('cr_no_registers'))}</h3><p>${esc(t('clear_search'))}</p></div>`;
            return;
        }

        root.innerHTML = state.view === 'table' ? renderTable(items) : renderGrid(items);
        bindCardActions(root, state.items);
    }

    function showModal(modal) {
        if (!modal) return;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('cr-modal-open');
    }

    function hideModal(modal) {
        if (!modal) return;
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        if (!document.querySelector('.cr-modal:not([hidden])')) {
            document.body.classList.remove('cr-modal-open');
        }
    }

    function openModalForRegister(register) {
        if (!register || !els.openModal) return;
        document.getElementById('crOpenRegisterId').value = register.id;
        document.getElementById('crOpenRegisterName').textContent = `${register.name} (${register.register_code})`;
        els.openForm.querySelector('[name="opening_balance"]').value = '0';
        showModal(els.openModal);
        els.openForm.querySelector('[name="opening_balance"]').focus();
    }

    function closeModalForRegister(register) {
        if (!register?.open_session_id || !els.closeModal) return;
        document.getElementById('crCloseSessionId').value = register.open_session_id;
        document.getElementById('crCloseRegisterName').textContent = `${register.name} (${register.register_code})`;
        const expected = Number(register.current_balance ?? register.opening_balance ?? 0);
        document.getElementById('crCloseExpectedHint').textContent = `${t('cr_stat_expected')}: ${money(expected)}`;
        els.closeForm.querySelector('[name="counted_cash"]').value = String(expected);
        showModal(els.closeModal);
        els.closeForm.querySelector('[name="counted_cash"]').focus();
    }

    function initModals() {
        document.querySelectorAll('[data-close-modal]').forEach((el) => {
            el.addEventListener('click', () => {
                hideModal(el.closest('.cr-modal'));
            });
        });

        els.openForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(els.openForm);
            const registerId = Number(fd.get('register_id'));
            const btn = els.openForm.querySelector('[type="submit"]');
            btn.disabled = true;
            try {
                const res = await AdminAPI.openCashRegisterSession(registerId, {
                    opening_balance: parseFloat(fd.get('opening_balance')) || 0,
                    shift_type: fd.get('shift_type'),
                });
                if (res.status !== 'success') throw new Error(res.message);
                hideModal(els.openModal);
                await load(true);
            } catch (err) {
                showError(err.message || t('error'));
            }
            btn.disabled = false;
        });

        els.closeForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(els.closeForm);
            const sessionId = Number(fd.get('session_id'));
            const btn = els.closeForm.querySelector('[type="submit"]');
            btn.disabled = true;
            try {
                const res = await AdminAPI.closeCashSession(sessionId, {
                    counted_cash: parseFloat(fd.get('counted_cash')) || 0,
                });
                if (res.status !== 'success') throw new Error(res.message);
                hideModal(els.closeModal);
                await load(true);
            } catch (err) {
                showError(err.message || t('error'));
            }
            btn.disabled = false;
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideModal(els.openModal);
                hideModal(els.closeModal);
            }
        });
    }

    function initToolbar() {
        els.search?.addEventListener('input', () => {
            state.search = els.search.value;
            renderList();
        });

        els.filters?.querySelectorAll('.cr-reg-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                state.filter = chip.dataset.filter || 'all';
                els.filters.querySelectorAll('.cr-reg-chip').forEach((c) => {
                    c.classList.toggle('is-active', c === chip);
                    c.setAttribute('aria-selected', c === chip ? 'true' : 'false');
                });
                renderList();
            });
        });

        document.querySelectorAll('.cr-reg-view-btn').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.view === state.view);
            btn.addEventListener('click', () => {
                state.view = btn.dataset.view || 'grid';
                localStorage.setItem('cr-reg-view', state.view);
                document.querySelectorAll('.cr-reg-view-btn').forEach((b) => {
                    b.classList.toggle('is-active', b.dataset.view === state.view);
                });
                renderList();
            });
        });
    }

    async function load(silent = false) {
        if (!silent) {
            hideError();
            root.innerHTML = `<div class="cr-loading">${esc(t('loading'))}</div>`;
            document.querySelectorAll('.cr-reg-stat__value').forEach((el) => el.classList.add('is-loading'));
        }
        try {
            const res = await AdminAPI.getCashRegisters();
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.data?.module_ready ?? true);
            state.items = Array.isArray(res.data) ? res.data : (res.data?.items || res.data || []);
            renderList();
            updateLastUpdated();
            await CashRegisterOffline.sync();
        } catch (e) {
            console.error(e);
            if (!silent) {
                showError(e.message || t('load_error'));
                root.innerHTML = `<div class="cr-reg-empty"><span class="material-icons-round">error_outline</span><p>${esc(e.message)}</p></div>`;
            }
        }
        document.querySelectorAll('.cr-reg-stat__value').forEach((el) => el.classList.remove('is-loading'));
    }

    initModals();
    initToolbar();
    load();
    document.addEventListener('cr:refresh', () => load(true));
});
