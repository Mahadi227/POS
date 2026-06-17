/**
 * Open cash register session — register picker + form
 */
document.addEventListener('DOMContentLoaded', () => {
    const listRoot = document.getElementById('crOpenRegisterList');
    const form = document.getElementById('crOpenForm');
    if (!listRoot || !form) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = CashRegistersUI;

    let registers = [];
    let selectedId = null;

    const els = {
        statAvailable: document.getElementById('crOpenStatAvailable'),
        statBusy: document.getElementById('crOpenStatBusy'),
        heroHint: document.getElementById('crOpenHeroHint'),
        summary: document.getElementById('crOpenSummary'),
        registerId: document.getElementById('crOpenRegisterId'),
        balance: document.getElementById('crOpenBalance'),
        submit: document.getElementById('crOpenSubmitBtn'),
    };

    function available() {
        return registers.filter((r) => r.session_status !== 'open' && (r.status || 'active') === 'active');
    }

    function busy() {
        return registers.filter((r) => r.session_status === 'open');
    }

    function selectedRegister() {
        return registers.find((r) => r.id === selectedId) || null;
    }

    function updateStats() {
        const avail = available();
        const open = busy();
        if (els.statAvailable) {
            els.statAvailable.textContent = String(avail.length);
            els.statAvailable.classList.remove('is-loading');
        }
        if (els.statBusy) {
            els.statBusy.textContent = String(open.length);
            els.statBusy.classList.remove('is-loading');
        }
        if (els.heroHint) {
            els.heroHint.textContent = avail.length
                ? t('cr_open_available')
                : t('cr_no_open_available');
        }
    }

    function renderPickerItem(r, disabled = false) {
        const isSelected = r.id === selectedId;
        return `
            <button type="button" class="cr-open-reg-item${isSelected ? ' is-selected' : ''}${disabled ? ' is-disabled' : ''}"
                data-id="${r.id}" ${disabled ? 'disabled' : ''}>
                <span class="cr-open-reg-item__icon material-icons-round">${disabled ? 'lock' : 'storefront'}</span>
                <span class="cr-open-reg-item__main">
                    <strong>${esc(r.name)}</strong>
                    <span class="cr-muted">${esc(r.register_code)} · ${esc(r.store_name)}</span>
                    ${r.assigned_cashier ? `<span class="cr-muted">${esc(t('cr_assigned_cashier'))}: ${esc(r.assigned_cashier)}</span>` : ''}
                </span>
                ${disabled
                    ? `<span class="cr-badge cr-badge--ok">${esc(t('cr_session_open'))}</span>`
                    : `<span class="cr-muted">${esc(money(r.current_balance))}</span>`}
            </button>`;
    }

    function renderSummary(reg) {
        if (!reg || !els.summary) return;
        els.summary.innerHTML = `
            <div class="cr-open-summary__card">
                <div class="cr-open-summary__icon"><span class="material-icons-round">point_of_sale</span></div>
                <div>
                    <strong>${esc(reg.name)}</strong>
                    <span class="cr-muted">${esc(reg.register_code)} · ${esc(reg.store_name)}</span>
                </div>
            </div>
            <dl class="cr-open-summary__dl">
                <div><dt>${esc(t('cr_assigned_cashier'))}</dt><dd>${esc(reg.assigned_cashier || '—')}</dd></div>
                <div><dt>${esc(t('cr_stat_cash_balance'))}</dt><dd>${esc(money(reg.current_balance))}</dd></div>
            </dl>`;
    }

    function selectRegister(id) {
        if (!id) {
            selectedId = null;
            form.hidden = true;
            if (els.summary) {
                els.summary.innerHTML = `<p class="cr-open-summary__placeholder">${esc(t('cr_open_select_register'))}</p>`;
            }
            return;
        }
        selectedId = id;
        const reg = selectedRegister();
        if (!reg) return;

        els.registerId.value = String(id);
        renderSummary(reg);
        form.hidden = false;
        els.summary.querySelector('.cr-open-summary__placeholder')?.remove();

        listRoot.querySelectorAll('.cr-open-reg-item:not(.is-disabled)').forEach((btn) => {
            btn.classList.toggle('is-selected', Number(btn.dataset.id) === id);
        });

        els.balance.focus();
    }

    function render() {
        updateStats();
        const avail = available();
        const open = busy();

        if (!registers.length) {
            listRoot.innerHTML = `
                <div class="cr-reg-empty">
                    <span class="material-icons-round">storefront</span>
                    <p>${esc(t('cr_no_registers'))}</p>
                </div>`;
            form.hidden = true;
            return;
        }

        let html = '';
        if (avail.length) {
            html += `<div class="cr-open-picker__section"><h4>${esc(t('cr_open_available'))}</h4>${avail.map((r) => renderPickerItem(r)).join('')}</div>`;
        }
        if (open.length) {
            html += `<div class="cr-open-picker__section cr-open-picker__section--muted"><h4>${esc(t('cr_open_already_open'))}</h4>${open.map((r) => renderPickerItem(r, true)).join('')}</div>`;
        }
        if (!avail.length) {
            html += `<div class="cr-reg-empty cr-reg-empty--compact"><span class="material-icons-round">lock</span><p>${esc(t('cr_no_open_available'))}</p><a href="close_register.php" class="cr-btn cr-btn--ghost">${esc(t('cr_close_register'))}</a></div>`;
            form.hidden = true;
        }

        listRoot.innerHTML = html;

        listRoot.querySelectorAll('.cr-open-reg-item:not(.is-disabled)').forEach((btn) => {
            btn.addEventListener('click', () => selectRegister(Number(btn.dataset.id)));
        });

        if (avail.length && !selectedId) {
            selectRegister(avail[0].id);
        } else if (selectedId && !avail.find((r) => r.id === selectedId)) {
            selectRegister(avail[0]?.id || null);
        } else if (selectedId) {
            selectRegister(selectedId);
        }
    }

    function initForm() {
        document.querySelectorAll('[data-preset]').forEach((btn) => {
            btn.addEventListener('click', () => {
                els.balance.value = btn.dataset.preset || '0';
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!selectedId) return;
            const fd = new FormData(form);
            els.submit.disabled = true;
            hideError();
            try {
                const res = await AdminAPI.openCashRegisterSession(selectedId, {
                    opening_balance: parseFloat(fd.get('opening_balance')) || 0,
                    shift_type: fd.get('shift_type'),
                    notes: String(fd.get('notes') || '').trim() || undefined,
                });
                if (res.status !== 'success') throw new Error(res.message);
                window.location.href = 'registers.php';
            } catch (err) {
                showError(err.message || t('error'));
                els.submit.disabled = false;
            }
        });
    }

    async function load() {
        hideError();
        try {
            const res = await AdminAPI.getCashRegisters('active');
            if (res.status !== 'success') throw new Error(res.message);
            setMigrationHint(res.data?.module_ready ?? true);
            registers = Array.isArray(res.data) ? res.data : (res.data?.items || []);
            render();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            listRoot.innerHTML = `<p class="cr-empty">${esc(e.message)}</p>`;
        }
    }

    initForm();
    load();
    document.addEventListener('cr:refresh', load);
});
