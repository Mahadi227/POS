/**
 * Cashier shift — open/close drawer session
 */
const CashierShift = (() => {
    let shift = null;
    let moduleReady = false;
    let availableRegisters = [];
    let listeners = [];

    function i18n() {
        return window.DASHBOARD_I18N || window.POS_I18N || {};
    }

    function t(key, ...args) {
        let str = i18n()[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function fmt(amount) {
        return CashierAPI.formatCurrency(amount);
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function notify() {
        listeners.forEach((fn) => {
            try { fn(shift); } catch (e) { console.error(e); }
        });
    }

    async function load() {
        const res = await CashierAPI.getShift();
        if (res.status === 'success') {
            shift = res.data?.shift || null;
            moduleReady = !!res.data?.shift_module_ready;
            availableRegisters = Array.isArray(res.data?.available_registers) ? res.data.available_registers : [];
        }
        notify();
        return shift;
    }

    async function openShift(openingFloat, notes = '', registerId = null) {
        const payload = {
            opening_float: Number(openingFloat) || 0,
            notes: notes || undefined,
        };
        if (registerId) payload.register_id = Number(registerId);
        const res = await CashierAPI.openShift(payload);
        if (res.status === 'success') {
            shift = res.data || null;
            notify();
        }
        return res;
    }

    async function closeShift(countedCash, notes = '') {
        const res = await CashierAPI.closeShift({
            counted_cash: Number(countedCash) || 0,
            notes: notes || undefined,
        });
        if (res.status === 'success') {
            shift = null;
            notify();
        }
        return res;
    }

    function isOpen() {
        return !!shift;
    }

    function getShift() {
        return shift;
    }

    function isModuleReady() {
        return moduleReady;
    }

    function onChange(fn) {
        listeners.push(fn);
        return () => {
            listeners = listeners.filter((item) => item !== fn);
        };
    }

    function promptOpen() {
        const def = '0';
        let registerId = null;
        if (availableRegisters.length > 0) {
            const options = availableRegisters.map((r, i) => `${i + 1}. ${r.name} (${r.register_code})`).join('\n');
            const pick = prompt(`${t('shift_select_register')}\n${options}\n\n#`, '1');
            if (pick === null) return Promise.resolve({ status: 'cancelled' });
            const idx = parseInt(pick, 10) - 1;
            if (!Number.isNaN(idx) && availableRegisters[idx]) {
                registerId = availableRegisters[idx].id;
            }
        }
        const val = prompt(t('shift_open_prompt'), def);
        if (val === null) return Promise.resolve({ status: 'cancelled' });
        const amount = parseFloat(String(val).replace(',', '.'));
        if (Number.isNaN(amount) || amount < 0) {
            return Promise.resolve({ status: 'error', message: t('shift_invalid_float') });
        }
        return openShift(amount, '', registerId);
    }

    function promptClose() {
        if (!shift) {
            return Promise.resolve({ status: 'error', message: t('shift_none_open') });
        }
        const hint = t('shift_close_hint', fmt(shift.expected_cash ?? 0));
        const val = prompt(`${t('shift_close_prompt')}\n${hint}`, String(shift.expected_cash ?? 0));
        if (val === null) return Promise.resolve({ status: 'cancelled' });
        const amount = parseFloat(String(val).replace(',', '.'));
        if (Number.isNaN(amount) || amount < 0) {
            return Promise.resolve({ status: 'error', message: t('shift_invalid_count') });
        }
        return closeShift(amount);
    }

    function renderPanel(container) {
        if (!container) return;

        if (!moduleReady) {
            container.innerHTML = `<div class="cd-shift cd-shift--warn">
                <span class="material-icons-round">info</span>
                <p>${esc(t('shift_migration_hint'))}</p>
            </div>`;
            return;
        }

        if (!shift) {
            container.innerHTML = `<div class="cd-shift cd-shift--closed">
                <div class="cd-shift__head">
                    <span class="material-icons-round">schedule</span>
                    <div>
                        <h3>${esc(t('shift_closed_title'))}</h3>
                        <p>${esc(t('shift_closed_desc'))}</p>
                    </div>
                </div>
                <button type="button" class="cd-btn-shift cd-btn-shift--open" id="shiftOpenBtn">
                    <span class="material-icons-round">play_circle</span>
                    ${esc(t('shift_open_btn'))}
                </button>
            </div>`;
            container.querySelector('#shiftOpenBtn')?.addEventListener('click', async () => {
                const res = await promptOpen();
                if (res.status === 'success') renderPanel(container);
                else if (res.status === 'error') alert(res.message || t('load_error'));
            });
            return;
        }

        container.innerHTML = `<div class="cd-shift cd-shift--open">
            <div class="cd-shift__head">
                <span class="material-icons-round">schedule</span>
                <div>
                    <h3>${esc(t('shift_open_title'))}</h3>
                    <p>${esc(t('shift_opened_at', CashierAPI.formatDate(shift.opened_at)))}</p>
                </div>
                <span class="cd-shift__badge">${esc(t('shift_status_open'))}</span>
            </div>
            <div class="cd-shift__stats">
                <div><span>${esc(t('shift_float'))}</span><strong>${esc(fmt(shift.opening_float))}</strong></div>
                <div><span>${esc(t('shift_sales'))}</span><strong>${esc(fmt(shift.total_sales))}</strong></div>
                <div><span>${esc(t('shift_tx'))}</span><strong>${esc(String(shift.transaction_count ?? 0))}</strong></div>
                <div><span>${esc(t('shift_expected'))}</span><strong>${esc(fmt(shift.expected_cash))}</strong></div>
            </div>
            <div class="cd-shift__actions">
                <a href="pos.php" class="cd-btn-shift cd-btn-shift--pos">
                    <span class="material-icons-round">point_of_sale</span>
                    ${esc(t('open_pos'))}
                </a>
                <button type="button" class="cd-btn-shift cd-btn-shift--close" id="shiftCloseBtn">
                    <span class="material-icons-round">stop_circle</span>
                    ${esc(t('shift_close_btn'))}
                </button>
            </div>
        </div>`;

        container.querySelector('#shiftCloseBtn')?.addEventListener('click', async () => {
            const res = await promptClose();
            if (res.status === 'success') {
                alert(res.message || t('shift_closed_ok'));
                renderPanel(container);
            } else if (res.status === 'error') {
                alert(res.message || t('load_error'));
            }
        });
    }

    function renderPosBadge(badgeEl) {
        if (!badgeEl) return;
        if (!moduleReady) {
            badgeEl.classList.add('hidden');
            return;
        }
        badgeEl.classList.remove('hidden');
        if (shift) {
            badgeEl.className = 'pos-cashier__shift pos-cashier__shift--open';
            badgeEl.innerHTML = `<span class="material-icons-round">schedule</span><span>${esc(t('shift_status_open'))}</span>`;
            badgeEl.title = t('shift_open_title');
        } else {
            badgeEl.className = 'pos-cashier__shift pos-cashier__shift--closed';
            badgeEl.innerHTML = `<span class="material-icons-round">schedule</span><span>${esc(t('shift_closed_title'))}</span>`;
            badgeEl.title = t('shift_closed_desc');
        }
    }

    async function ensureOpenForSale() {
        if (!moduleReady) return { ok: true };
        if (shift) return { ok: true };
        const open = confirm(t('shift_required_confirm'));
        if (!open) return { ok: false };
        const res = await promptOpen();
        if (res.status !== 'success') {
            return { ok: false, message: res.message };
        }
        return { ok: true };
    }

    return {
        load,
        openShift,
        closeShift,
        promptOpen,
        promptClose,
        isOpen,
        getShift,
        isModuleReady,
        onChange,
        renderPanel,
        renderPosBadge,
        ensureOpenForSale,
    };
})();
