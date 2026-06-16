/**
 * Customer management — cashier
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.CUSTOMERS_CONFIG || {};
    const i18n = window.CUSTOMERS_I18N || {};
    const locale = cfg.locale || (cfg.lang === 'fr' ? 'fr-FR' : 'en-US');

    if (window.POS_CONFIG && !window.POS_CONFIG.locale) {
        window.POS_CONFIG.locale = locale;
        window.POS_CONFIG.lang = cfg.lang || 'en';
    }

    let allCustomers = [];
    let searchQuery = '';
    let editingId = null;
    let lastFetchAt = null;

    const els = {
        tbody: document.getElementById('customersTableBody'),
        searchInput: document.getElementById('customerSearch'),
        totalCount: document.getElementById('totalCustomers'),
        filteredCount: document.getElementById('filteredCustomers'),
        modal: document.getElementById('customerModal'),
        form: document.getElementById('customerForm'),
        modalTitle: document.getElementById('modalTitle'),
        formName: document.getElementById('formName'),
        formPhone: document.getElementById('formPhone'),
        formEmail: document.getElementById('formEmail'),
        saveBtn: document.getElementById('saveCustomerBtn'),
        toast: document.getElementById('customerToast'),
        refreshBtn: document.getElementById('refreshCustomersBtn'),
        errorBanner: document.getElementById('customersError'),
        headerDate: document.getElementById('cuHeaderDate'),
        lastUpdated: document.getElementById('lastUpdated'),
    };

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function escapeAttr(str) {
        return String(str ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function columnLabels() {
        return {
            customer: t('col_customer'),
            phone: t('col_phone'),
            email: t('col_email'),
            activity: t('col_activity'),
            actions: t('col_actions'),
        };
    }

    function updateHeaderDate() {
        const now = new Date();
        const dateStr = now.toLocaleDateString(locale, {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
        if (els.headerDate) els.headerDate.textContent = dateStr;
        if (els.lastUpdated && lastFetchAt) {
            const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
            els.lastUpdated.textContent = `${t('last_updated')} · ${time}`;
        }
    }

    function showError(msg) {
        if (!els.errorBanner) return;
        els.errorBanner.classList.add('is-visible');
        const text = els.errorBanner.querySelector('.cu-error-text');
        if (text) text.textContent = msg;
    }

    function hideError() {
        els.errorBanner?.classList.remove('is-visible');
    }

    function setSummaryLoading(loading) {
        document.querySelectorAll('.cu-summary-card').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function toast(msg, type = 'ok') {
        if (!els.toast) return;
        els.toast.textContent = msg;
        els.toast.className = `cu-toast cu-toast--${type} show`;
        setTimeout(() => els.toast?.classList.remove('show'), 3000);
    }

    function getFiltered() {
        if (!searchQuery.trim()) return allCustomers;
        const q = searchQuery.trim().toLowerCase();
        return allCustomers.filter(
            (c) =>
                (c.name || '').toLowerCase().includes(q) ||
                (c.phone || '').toLowerCase().includes(q) ||
                (c.email || '').toLowerCase().includes(q)
        );
    }

    function updateCounts(filtered) {
        if (els.totalCount) els.totalCount.textContent = String(allCustomers.length);
        if (els.filteredCount) {
            els.filteredCount.textContent = searchQuery.trim()
                ? t('shown_count', filtered.length)
                : t('clients_count', filtered.length);
        }
        const panelLbl = document.getElementById('panelCountLabel');
        if (panelLbl) panelLbl.textContent = t('results_count', filtered.length);
    }

    function openModal(customer = null) {
        editingId = customer?.id ?? null;
        if (els.modalTitle) {
            els.modalTitle.textContent = editingId ? t('modal_edit') : t('modal_new');
        }
        if (els.formName) els.formName.value = customer?.name || '';
        if (els.formPhone) els.formPhone.value = customer?.phone || '';
        if (els.formEmail) els.formEmail.value = customer?.email || '';
        els.modal?.classList.add('is-open');
        els.formName?.focus();
    }

    function closeModal() {
        els.modal?.classList.remove('is-open');
        editingId = null;
        els.form?.reset();
    }

    function renderCustomers() {
        const filtered = getFiltered();
        const cols = columnLabels();
        updateCounts(filtered);

        if (!filtered.length) {
            const emptyMsg = searchQuery.trim() ? t('no_match') : t('no_customers');
            if (els.tbody) {
                els.tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="cu-empty">
                            <div class="cu-state">
                                <span class="material-icons-round">people_outline</span>
                                <p>${escapeHtml(emptyMsg)}</p>
                            </div>
                        </td>
                    </tr>`;
            }
            return;
        }

        if (els.tbody) {
            els.tbody.innerHTML = filtered
                .map((c) => {
                    const initial = (c.name || '?').charAt(0).toUpperCase();
                    const loyal =
                        c.loyalty_points > 0
                            ? `<span class="cu-badge cu-badge--loyal">${escapeHtml(t('points', c.loyalty_points))}</span>`
                            : '';
                    const phoneCell = c.phone
                        ? escapeHtml(c.phone)
                        : '<span style="color:var(--text-muted)">—</span>';
                    const emailCell = c.email
                        ? escapeHtml(c.email)
                        : '<span style="color:var(--text-muted)">—</span>';
                    return `
                        <tr>
                            <td data-label="${escapeAttr(cols.customer)}">
                                <span class="cu-customer-name">
                                    <span class="cu-avatar-sm">${escapeHtml(initial)}</span>
                                    ${escapeHtml(c.name)}
                                </span>
                            </td>
                            <td data-label="${escapeAttr(cols.phone)}">${phoneCell}</td>
                            <td data-label="${escapeAttr(cols.email)}">${emailCell}</td>
                            <td data-label="${escapeAttr(cols.activity)}">
                                <span class="cu-badge">${escapeHtml(t('sales_count', c.sales_count || 0))}</span>
                                ${loyal}
                            </td>
                            <td class="cu-col-actions" data-label="${escapeAttr(cols.actions)}">
                                <div class="cu-actions">
                                    <button type="button" class="cu-icon-btn" data-edit="${c.id}" title="${escapeAttr(t('edit'))}">
                                        <span class="material-icons-round">edit</span>
                                    </button>
                                    <button type="button" class="cu-icon-btn cu-icon-btn--danger" data-delete="${c.id}" title="${escapeAttr(t('delete'))}">
                                        <span class="material-icons-round">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>`;
                })
                .join('');
            bindRowActions(els.tbody);
        }
    }

    function bindRowActions(root) {
        root.querySelectorAll('[data-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.edit, 10);
                const customer = allCustomers.find((c) => c.id === id);
                if (customer) openModal(customer);
            });
        });
        root.querySelectorAll('[data-delete]').forEach((btn) => {
            btn.addEventListener('click', () => deleteCustomer(parseInt(btn.dataset.delete, 10)));
        });
    }

    async function loadCustomers() {
        hideError();
        setSummaryLoading(true);
        els.refreshBtn?.classList.add('spinning');

        if (els.tbody) {
            els.tbody.innerHTML = `<tr><td colspan="5" class="cu-loading-row">${escapeHtml(t('loading'))}</td></tr>`;
        }

        try {
            const result = await CashierAPI.getCustomers({ limit: 500 });
            if (result.status === 'success') {
                allCustomers = result.data || [];
                lastFetchAt = new Date();
                updateHeaderDate();
                renderCustomers();
            } else {
                const msg = result.message || t('error');
                showError(msg);
                toast(msg, 'err');
            }
        } catch (err) {
            console.error(err);
            showError(t('load_error'));
            toast(t('load_error'), 'err');
            if (els.tbody) {
                els.tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="cu-empty">
                            <div class="cu-state cu-state--error">
                                <span class="material-icons-round">error_outline</span>
                                <p>${escapeHtml(t('load_error'))}</p>
                            </div>
                        </td>
                    </tr>`;
            }
        }

        setSummaryLoading(false);
        els.refreshBtn?.classList.remove('spinning');
    }

    async function handleSubmit(e) {
        e.preventDefault();
        const payload = {
            name: els.formName?.value.trim(),
            phone: els.formPhone?.value.trim(),
            email: els.formEmail?.value.trim(),
        };

        els.saveBtn.disabled = true;
        try {
            const result = editingId
                ? await CashierAPI.updateCustomer(editingId, payload)
                : await CashierAPI.createCustomer(payload);

            if (result.status === 'success') {
                toast(result.message || t('saved'), 'ok');
                closeModal();
                await loadCustomers();
            } else {
                toast(result.message || t('error'), 'err');
            }
        } catch (err) {
            console.error(err);
            toast(t('connection_error'), 'err');
        }
        els.saveBtn.disabled = false;
    }

    async function deleteCustomer(id) {
        const customer = allCustomers.find((c) => c.id === id);
        if (!customer) return;
        if (!confirm(t('delete_confirm', customer.name))) return;

        try {
            const result = await CashierAPI.deleteCustomer(id);
            if (result.status === 'success') {
                toast(t('deleted'), 'ok');
                await loadCustomers();
            } else {
                toast(result.message || t('error'), 'err');
            }
        } catch (err) {
            toast(t('error'), 'err');
        }
    }

    document.getElementById('addCustomerBtn')?.addEventListener('click', () => openModal());
    document.getElementById('closeModalBtn')?.addEventListener('click', closeModal);
    document.querySelector('[data-close-modal]')?.addEventListener('click', closeModal);
    els.form?.addEventListener('submit', handleSubmit);

    let searchTimer;
    els.searchInput?.addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            searchQuery = e.target.value;
            renderCustomers();
        }, 200);
    });

    els.searchInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchQuery = e.target.value;
            renderCustomers();
        }
    });

    els.refreshBtn?.addEventListener('click', loadCustomers);

    updateHeaderDate();
    loadCustomers();
});
