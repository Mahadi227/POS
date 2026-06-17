/**
 * Create cash register — live preview + form
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('crCreateForm');
    if (!form) return;

    const { t, esc, money, showError, hideError, updateLastUpdated } = CashRegistersUI;

    const storeId = window.ADMIN_PAGE?.storeId;
    const storeName = window.ADMIN_PAGE?.storeName || '—';

    let cashiers = [];

    const els = {
        heroHint: document.getElementById('crCreateHeroHint'),
        statRegisters: document.getElementById('crCreateStatRegisters'),
        statCashiers: document.getElementById('crCreateStatCashiers'),
        previewName: document.getElementById('crPreviewName'),
        previewCode: document.getElementById('crPreviewCode'),
        previewStatus: document.getElementById('crPreviewStatus'),
        previewBranch: document.getElementById('crPreviewBranch'),
        previewCashier: document.getElementById('crPreviewCashier'),
        previewBalance: document.getElementById('crPreviewBalance'),
        code: document.getElementById('crCreateCode'),
        name: document.getElementById('crCreateName'),
        cashier: document.getElementById('crCreateCashier'),
        balance: document.getElementById('crCreateBalance'),
        suggestCode: document.getElementById('crCreateSuggestCode'),
        submit: document.getElementById('crCreateSubmitBtn'),
        steps: document.querySelectorAll('.cr-create-step'),
    };

    function suggestCode() {
        const suffix = String(Date.now()).slice(-4);
        return `CR${storeId || 1}-${suffix}`;
    }

    function statusLabel(value) {
        if (value === 'inactive') return t('cr_status_inactive');
        return t('cr_status_active');
    }

    function cashierName(id) {
        if (!id) return '—';
        const user = cashiers.find((u) => String(u.id) === String(id));
        return user?.name || '—';
    }

    function updateSteps() {
        const hasIdentity = Boolean(els.code?.value.trim() && els.name?.value.trim());
        const hasAssignment = Boolean(els.cashier?.value);
        const hasBalance = els.balance?.value !== '' && els.balance?.value !== null;

        const map = {
            identity: hasIdentity,
            assignment: hasAssignment || hasIdentity,
            balance: hasBalance || hasIdentity,
        };

        els.steps.forEach((step) => {
            const key = step.dataset.step;
            step.classList.toggle('is-done', Boolean(map[key]));
        });
    }

    function updatePreview() {
        const code = els.code?.value.trim() || '—';
        const name = els.name?.value.trim() || t('cr_register_name');
        const status = form.querySelector('[name="status"]:checked')?.value || 'active';
        const balance = parseFloat(els.balance?.value) || 0;

        if (els.previewName) els.previewName.textContent = name;
        if (els.previewCode) els.previewCode.textContent = code;
        if (els.previewStatus) {
            els.previewStatus.textContent = statusLabel(status);
            els.previewStatus.className = `cr-badge ${status === 'active' ? 'cr-badge--ok' : 'cr-badge--muted'}`;
        }
        if (els.previewBranch) els.previewBranch.textContent = storeName;
        if (els.previewCashier) els.previewCashier.textContent = cashierName(els.cashier?.value);
        if (els.previewBalance) els.previewBalance.textContent = money(balance);

        updateSteps();
    }

    function fillCashierSelect() {
        if (!els.cashier) return;
        const current = els.cashier.value;
        els.cashier.innerHTML = `<option value="">—</option>${cashiers.map((u) => `<option value="${u.id}">${esc(u.name)}</option>`).join('')}`;
        if (current) els.cashier.value = current;
    }

    function updateStats(registerCount) {
        if (els.statRegisters) {
            els.statRegisters.textContent = String(registerCount);
            els.statRegisters.classList.remove('is-loading');
        }
        if (els.statCashiers) {
            els.statCashiers.textContent = String(cashiers.length);
            els.statCashiers.classList.remove('is-loading');
        }
        if (els.heroHint) {
            els.heroHint.textContent = storeName && storeName !== '—'
                ? t('cr_create_branch_hint').replace('%s', storeName)
                : t('cr_create_subtitle');
        }
    }

    function initForm() {
        if (els.code && !els.code.value) {
            els.code.value = suggestCode();
        }

        els.suggestCode?.addEventListener('click', () => {
            if (!els.code) return;
            els.code.value = suggestCode();
            updatePreview();
            els.code.focus();
        });

        form.querySelectorAll('input, select').forEach((el) => {
            el.addEventListener('input', updatePreview);
            el.addEventListener('change', updatePreview);
        });

        document.querySelectorAll('[data-preset]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!els.balance) return;
                els.balance.value = btn.dataset.preset || '0';
                updatePreview();
            });
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideError();
            els.submit.disabled = true;

            const fd = new FormData(form);
            const payload = {
                store_id: storeId,
                register_code: String(fd.get('register_code') || '').trim(),
                name: String(fd.get('name') || '').trim(),
                assigned_user_id: fd.get('assigned_user_id') || null,
                opening_balance: parseFloat(fd.get('opening_balance')) || 0,
                status: fd.get('status') || 'active',
            };

            try {
                const res = await AdminAPI.createCashRegister(payload);
                if (res.status !== 'success') throw new Error(res.message);
                const id = res.data?.id;
                window.location.href = id ? `register_details.php?id=${id}` : 'registers.php';
            } catch (err) {
                showError(err.message || t('error'));
                els.submit.disabled = false;
            }
        });
    }

    async function load() {
        hideError();
        try {
            const [usersRes, registersRes] = await Promise.all([
                AdminAPI.getUsers({ role: 'cashier', store_id: storeId }).catch(() => ({ data: [] })),
                AdminAPI.getCashRegisters().catch(() => ({ data: [] })),
            ]);

            cashiers = (usersRes.data?.users || usersRes.data || []).filter(Boolean);
            const registers = Array.isArray(registersRes.data) ? registersRes.data : (registersRes.data?.items || []);

            fillCashierSelect();
            updateStats(registers.length);
            updatePreview();
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
        }
    }

    initForm();
    load();
});
