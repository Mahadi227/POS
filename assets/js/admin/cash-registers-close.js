/**
 * Close cash register session — register picker + counted cash form
 */
document.addEventListener('DOMContentLoaded', () => {
    const listRoot = document.getElementById('crCloseRegisterList');
    const form = document.getElementById('crCloseForm');
    if (!listRoot || !form) return;

    const { t, esc, money, showError, hideError, setMigrationHint, updateLastUpdated } = CashRegistersUI;

    const VARIANCE_TOLERANCE = 500;

    let registers = [];
    let selectedId = null;

    const els = {
        statOpen: document.getElementById('crCloseStatOpen'),
        statExpected: document.getElementById('crCloseStatExpected'),
        heroHint: document.getElementById('crCloseHeroHint'),
        summary: document.getElementById('crCloseSummary'),
        sessionId: document.getElementById('crCloseSessionId'),
        expectedValue: document.getElementById('crCloseExpectedValue'),
        counted: document.getElementById('crCloseCounted'),
        varianceBox: document.getElementById('crCloseVarianceBox'),
        varianceValue: document.getElementById('crCloseVarianceValue'),
        varianceHint: document.getElementById('crCloseVarianceHint'),
        useExpected: document.getElementById('crCloseUseExpected'),
        submit: document.getElementById('crCloseSubmitBtn'),
    };

    function openSessions() {
        return registers.filter((r) => r.session_status === 'open' && r.open_session_id);
    }

    function selectedRegister() {
        return registers.find((r) => r.id === selectedId) || null;
    }

    function expectedCash(reg) {
        if (!reg) return 0;
        return Number(reg.current_balance ?? reg.opening_balance ?? 0);
    }

    function totalExpected() {
        return openSessions().reduce((sum, r) => sum + expectedCash(r), 0);
    }

    function updateStats() {
        const open = openSessions();
        if (els.statOpen) {
            els.statOpen.textContent = String(open.length);
            els.statOpen.classList.remove('is-loading');
        }
        if (els.statExpected) {
            els.statExpected.textContent = open.length ? money(totalExpected()) : '—';
            els.statExpected.classList.remove('is-loading');
        }
        if (els.heroHint) {
            els.heroHint.textContent = open.length
                ? t('cr_close_select_register')
                : t('cr_no_open_sessions');
        }
    }

    function varianceState(variance) {
        const abs = Math.abs(variance);
        if (abs < VARIANCE_TOLERANCE) return 'ok';
        if (abs < VARIANCE_TOLERANCE * 10) return 'warn';
        return 'danger';
    }

    function updateVariance() {
        const reg = selectedRegister();
        if (!reg || !els.varianceValue) return;

        const expected = expectedCash(reg);
        const counted = parseFloat(els.counted?.value) || 0;
        const variance = Math.round((counted - expected) * 100) / 100;
        const state = varianceState(variance);

        els.varianceValue.textContent = money(variance);
        els.varianceValue.className = `cr-close-variance__value is-${state}`;
        if (els.varianceBox) {
            els.varianceBox.className = `cr-close-variance is-${state}`;
        }
        if (els.varianceHint) {
            els.varianceHint.textContent = state === 'ok'
                ? t('cr_variance_within')
                : t('cr_variance_alert_short');
        }
    }

    function renderPickerItem(r) {
        const isSelected = r.id === selectedId;
        const expected = expectedCash(r);
        return `
            <button type="button" class="cr-open-reg-item cr-close-reg-item${isSelected ? ' is-selected' : ''}"
                data-id="${r.id}">
                <span class="cr-open-reg-item__icon material-icons-round">point_of_sale</span>
                <span class="cr-open-reg-item__main">
                    <strong>${esc(r.name)}</strong>
                    <span class="cr-muted">${esc(r.register_code)} · ${esc(r.store_name)}</span>
                    ${r.assigned_cashier ? `<span class="cr-muted">${esc(t('cr_assigned_cashier'))}: ${esc(r.assigned_cashier)}</span>` : ''}
                </span>
                <span class="cr-close-reg-item__expected">
                    <span class="cr-muted">${esc(t('cr_stat_expected'))}</span>
                    <strong>${esc(money(expected))}</strong>
                </span>
            </button>`;
    }

    function renderSummary(reg) {
        if (!reg || !els.summary) return;
        const expected = expectedCash(reg);
        els.summary.innerHTML = `
            <div class="cr-open-summary__card">
                <div class="cr-open-summary__icon cr-close-summary__icon"><span class="material-icons-round">lock_open</span></div>
                <div>
                    <strong>${esc(reg.name)}</strong>
                    <span class="cr-muted">${esc(reg.register_code)} · ${esc(reg.store_name)}</span>
                    <span class="cr-badge cr-badge--ok">${esc(t('cr_session_open'))}</span>
                </div>
            </div>
            <dl class="cr-open-summary__dl">
                <div><dt>${esc(t('cr_assigned_cashier'))}</dt><dd>${esc(reg.assigned_cashier || '—')}</dd></div>
                <div><dt>${esc(t('cr_stat_expected'))}</dt><dd>${esc(money(expected))}</dd></div>
            </dl>`;
    }

    function selectRegister(id) {
        if (!id) {
            selectedId = null;
            form.hidden = true;
            if (els.summary) {
                els.summary.innerHTML = `<p class="cr-open-summary__placeholder">${esc(t('cr_close_select_register'))}</p>`;
            }
            return;
        }

        selectedId = id;
        const reg = selectedRegister();
        if (!reg || !reg.open_session_id) return;

        els.sessionId.value = String(reg.open_session_id);
        const expected = expectedCash(reg);

        if (els.expectedValue) els.expectedValue.textContent = money(expected);
        if (els.counted) els.counted.value = String(expected);

        renderSummary(reg);
        form.hidden = false;
        updateVariance();

        listRoot.querySelectorAll('.cr-close-reg-item').forEach((btn) => {
            btn.classList.toggle('is-selected', Number(btn.dataset.id) === id);
        });

        els.counted?.focus();
        els.counted?.select();
    }

    function render() {
        updateStats();
        const open = openSessions();

        if (!open.length) {
            listRoot.innerHTML = `
                <div class="cr-reg-empty">
                    <span class="material-icons-round">lock</span>
                    <p>${esc(t('cr_no_open_sessions'))}</p>
                    <a href="open_register.php" class="cr-btn cr-btn--ghost">${esc(t('cr_open_register'))}</a>
                </div>`;
            form.hidden = true;
            if (els.summary) {
                els.summary.innerHTML = `<p class="cr-open-summary__placeholder">${esc(t('cr_no_open_sessions'))}</p>`;
            }
            return;
        }

        listRoot.innerHTML = `
            <div class="cr-open-picker__section">
                <h4>${esc(t('cr_close_open_sessions'))}</h4>
                ${open.map((r) => renderPickerItem(r)).join('')}
            </div>`;

        listRoot.querySelectorAll('.cr-close-reg-item').forEach((btn) => {
            btn.addEventListener('click', () => selectRegister(Number(btn.dataset.id)));
        });

        if (!selectedId || !open.find((r) => r.id === selectedId)) {
            selectRegister(open[0].id);
        } else {
            selectRegister(selectedId);
        }
    }

    function initForm() {
        els.counted?.addEventListener('input', updateVariance);

        els.useExpected?.addEventListener('click', () => {
            const reg = selectedRegister();
            if (!reg || !els.counted) return;
            els.counted.value = String(expectedCash(reg));
            updateVariance();
            els.counted.focus();
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const reg = selectedRegister();
            if (!reg?.open_session_id) return;

            const fd = new FormData(form);
            els.submit.disabled = true;
            hideError();

            try {
                const res = await AdminAPI.closeCashSession(reg.open_session_id, {
                    counted_cash: parseFloat(fd.get('counted_cash')) || 0,
                    notes: String(fd.get('notes') || '').trim() || undefined,
                });
                if (res.status !== 'success') throw new Error(res.message);
                window.location.href = 'reconciliation.php';
            } catch (err) {
                showError(err.message || t('error'));
                els.submit.disabled = false;
            }
        });
    }

    async function load() {
        hideError();
        try {
            const res = await AdminAPI.getCashRegisters();
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
