/**
 * Edit cash register — live preview + form
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('crEditForm');
    if (!form) return;

    const { t, esc, money, showError, hideError, updateLastUpdated } = CashRegistersUI;

    const registerId = Number(form.dataset.registerId || 0);
    const storeName = window.ADMIN_PAGE?.storeName || '—';
    const storeId = window.ADMIN_PAGE?.storeId;

    let cashiers = [];
    let register = null;

    const els = {
        loading: document.getElementById('crEditFormLoading'),
        heroHint: document.getElementById('crEditHeroHint'),
        statSession: document.getElementById('crEditStatSession'),
        statBalance: document.getElementById('crEditStatBalance'),
        sessionBadge: document.getElementById('crEditSessionBadge'),
        previewName: document.getElementById('crPreviewName'),
        previewCode: document.getElementById('crPreviewCode'),
        previewStatus: document.getElementById('crPreviewStatus'),
        previewBranch: document.getElementById('crPreviewBranch'),
        previewCashier: document.getElementById('crPreviewCashier'),
        previewBalance: document.getElementById('crPreviewBalance'),
        code: document.getElementById('crEditCode'),
        name: document.getElementById('crEditName'),
        cashier: document.getElementById('crEditCashier'),
        balance: document.getElementById('crEditBalance'),
        submit: document.getElementById('crEditSubmitBtn'),
        steps: document.querySelectorAll('.cr-create-step'),
    };

    function statusLabel(value) {
        if (value === 'inactive') return t('cr_status_inactive');
        if (value === 'maintenance') return t('cr_status_maintenance');
        return t('cr_status_active');
    }

    function statusBadgeClass(value) {
        if (value === 'inactive') return 'cr-badge--muted';
        if (value === 'maintenance') return 'cr-badge--warn';
        return 'cr-badge--ok';
    }

    function cashierName(id) {
        if (!id) return '—';
        const user = cashiers.find((u) => String(u.id) === String(id));
        return user?.name || register?.assigned_cashier || '—';
    }

    function isSessionOpen() {
        return register?.session_status === 'open';
    }

    function updateSteps() {
        const hasIdentity = Boolean(els.code?.value.trim() && els.name?.value.trim());
        const hasAssignment = Boolean(els.cashier?.value) || hasIdentity;
        const hasBalance = els.balance?.value !== '' && els.balance?.value !== null;

        const map = {
            identity: hasIdentity,
            assignment: hasAssignment,
            balance: hasBalance,
        };

        els.steps.forEach((step) => {
            step.classList.toggle('is-done', Boolean(map[step.dataset.step]));
        });
    }

    function updateHeroStats() {
        if (els.statSession) {
            els.statSession.textContent = isSessionOpen() ? t('cr_session_open') : t('cr_session_closed');
            els.statSession.classList.remove('is-loading');
        }
        if (els.statBalance && register) {
            els.statBalance.textContent = money(register.current_balance);
            els.statBalance.classList.remove('is-loading');
        }
        if (els.heroHint) {
            const branch = register?.store_name || storeName;
            els.heroHint.textContent = branch && branch !== '—'
                ? t('cr_edit_branch_hint').replace('%s', branch)
                : t('cr_edit_subtitle');
        }
        if (els.sessionBadge) {
            if (isSessionOpen()) {
                els.sessionBadge.hidden = false;
                els.sessionBadge.className = 'cr-edit-session-badge is-open';
                els.sessionBadge.innerHTML = `<span class="material-icons-round">lock_open</span>${esc(t('cr_session_open'))}`;
            } else {
                els.sessionBadge.hidden = true;
            }
        }
    }

    function updatePreview() {
        const code = els.code?.value.trim() || '—';
        const name = els.name?.value.trim() || t('cr_register_name');
        const status = form.querySelector('[name="status"]:checked')?.value || 'active';

        if (els.previewName) els.previewName.textContent = name;
        if (els.previewCode) els.previewCode.textContent = code;
        if (els.previewStatus) {
            els.previewStatus.textContent = statusLabel(status);
            els.previewStatus.className = `cr-badge ${statusBadgeClass(status)}`;
        }
        if (els.previewBranch) {
            els.previewBranch.textContent = register?.store_name || storeName;
        }
        if (els.previewCashier) els.previewCashier.textContent = cashierName(els.cashier?.value);
        if (els.previewBalance && register) {
            els.previewBalance.textContent = money(register.current_balance);
        }

        updateSteps();
    }

    function fillCashierSelect() {
        if (!els.cashier) return;
        const current = String(register?.assigned_user_id || els.cashier.value || '');
        els.cashier.innerHTML = `<option value="">—</option>${cashiers.map((u) => `<option value="${u.id}">${esc(u.name)}</option>`).join('')}`;
        if (current) els.cashier.value = current;
    }

    function populateForm(data) {
        if (els.code) els.code.value = data.register_code || '';
        if (els.name) els.name.value = data.name || '';
        if (els.balance) els.balance.value = String(data.opening_balance ?? 0);

        const status = data.status || 'active';
        const statusInput = form.querySelector(`[name="status"][value="${status}"]`);
        if (statusInput) statusInput.checked = true;

        fillCashierSelect();
    }

    function initForm() {
        form.querySelectorAll('input:not([readonly]), select').forEach((el) => {
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
                register_code: fd.get('register_code'),
                name: String(fd.get('name') || '').trim(),
                assigned_user_id: fd.get('assigned_user_id') || null,
                opening_balance: parseFloat(fd.get('opening_balance')) || 0,
                status: fd.get('status') || 'active',
            };

            try {
                const res = await AdminAPI.updateCashRegister(registerId, payload);
                if (res.status !== 'success') throw new Error(res.message);
                window.location.href = `register_details.php?id=${registerId}`;
            } catch (err) {
                showError(err.message || t('error'));
                els.submit.disabled = false;
            }
        });
    }

    async function load() {
        hideError();
        if (!registerId) {
            showError(t('load_error'));
            return;
        }

        try {
            const [registerRes, usersRes] = await Promise.all([
                AdminAPI.getCashRegister(registerId),
                AdminAPI.getUsers({ role: 'cashier', store_id: storeId }).catch(() => ({ data: [] })),
            ]);

            if (registerRes.status !== 'success' || !registerRes.data) {
                throw new Error(registerRes.message || t('load_error'));
            }

            register = registerRes.data;
            cashiers = (usersRes.data?.users || usersRes.data || []).filter(Boolean);

            populateForm(register);
            updateHeroStats();
            updatePreview();

            if (els.loading) els.loading.hidden = true;
            form.hidden = false;
            updateLastUpdated();
        } catch (e) {
            showError(e.message || t('load_error'));
            if (els.loading) els.loading.textContent = e.message || t('load_error');
        }
    }

    initForm();
    load();
    document.addEventListener('cr:refresh', load);
});
