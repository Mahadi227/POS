/**
 * Gestion clients — caissier
 */
document.addEventListener('DOMContentLoaded', () => {
    let allCustomers = [];
    let searchQuery = '';
    let editingId = null;

    const els = {
        tbody: document.getElementById('customersTableBody'),
        cards: document.getElementById('customersCards'),
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
    };

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
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
            els.filteredCount.textContent =
                searchQuery.trim() ? `${filtered.length} affiché(s)` : `${filtered.length} client(s)`;
        }
        const panelLbl = document.getElementById('panelCountLabel');
        if (panelLbl) panelLbl.textContent = `${filtered.length} résultat(s)`;
    }

    function openModal(customer = null) {
        editingId = customer?.id ?? null;
        if (els.modalTitle) {
            els.modalTitle.textContent = editingId ? 'Modifier le client' : 'Nouveau client';
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
        updateCounts(filtered);

        if (!filtered.length) {
            const empty = `
                <div class="cu-state">
                    <span class="material-icons-round">people_outline</span>
                    <p>${searchQuery.trim() ? 'Aucun client ne correspond.' : 'Aucun client enregistré.'}</p>
                </div>`;
            if (els.tbody) els.tbody.innerHTML = `<tr><td colspan="5">${empty}</td></tr>`;
            if (els.cards) els.cards.innerHTML = empty;
            return;
        }

        if (els.tbody) {
            els.tbody.innerHTML = filtered
                .map((c) => {
                    const initial = (c.name || '?').charAt(0).toUpperCase();
                    const loyal =
                        c.loyalty_points > 0
                            ? `<span class="cu-badge cu-badge--loyal">${c.loyalty_points} pts</span>`
                            : '';
                    return `
                        <tr>
                            <td>
                                <span class="cu-customer-name">
                                    <span class="cu-avatar-sm">${escapeHtml(initial)}</span>
                                    ${escapeHtml(c.name)}
                                </span>
                            </td>
                            <td>${c.phone ? escapeHtml(c.phone) : '<span style="color:var(--text-muted)">—</span>'}</td>
                            <td>${c.email ? escapeHtml(c.email) : '<span style="color:var(--text-muted)">—</span>'}</td>
                            <td>
                                <span class="cu-badge">${c.sales_count || 0} vente(s)</span>
                                ${loyal}
                            </td>
                            <td>
                                <div class="cu-actions">
                                    <button type="button" class="cu-icon-btn" data-edit="${c.id}" title="Modifier">
                                        <span class="material-icons-round">edit</span>
                                    </button>
                                    <button type="button" class="cu-icon-btn cu-icon-btn--danger" data-delete="${c.id}" title="Supprimer">
                                        <span class="material-icons-round">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>`;
                })
                .join('');
            bindRowActions(els.tbody);
        }

        if (els.cards) {
            els.cards.innerHTML = filtered
                .map((c) => {
                    const initial = (c.name || '?').charAt(0).toUpperCase();
                    return `
                        <article class="cu-card">
                            <div class="cu-card__top">
                                <span class="cu-avatar-sm">${escapeHtml(initial)}</span>
                                <div>
                                    <strong>${escapeHtml(c.name)}</strong>
                                    <div class="cu-badge" style="margin-top:4px;">${c.sales_count || 0} vente(s)</div>
                                </div>
                            </div>
                            <div class="cu-card__meta">
                                ${c.phone ? `<span><span class="material-icons-round" style="font-size:14px;vertical-align:middle;">phone</span> ${escapeHtml(c.phone)}</span>` : ''}
                                ${c.email ? `<span><span class="material-icons-round" style="font-size:14px;vertical-align:middle;">email</span> ${escapeHtml(c.email)}</span>` : ''}
                            </div>
                            <div class="cu-card__actions">
                                <button type="button" class="cu-btn cu-btn--outline" data-edit="${c.id}">
                                    <span class="material-icons-round">edit</span> Modifier
                                </button>
                                <button type="button" class="cu-btn cu-btn--outline" data-delete="${c.id}" style="color:var(--danger);">
                                    <span class="material-icons-round">delete</span>
                                </button>
                            </div>
                        </article>`;
                })
                .join('');
            bindRowActions(els.cards);
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
        if (els.tbody) {
            els.tbody.innerHTML =
                '<tr><td colspan="5" class="cu-state">Chargement…</td></tr>';
        }
        try {
            const result = await CashierAPI.getCustomers({ limit: 500 });
            if (result.status === 'success') {
                allCustomers = result.data || [];
                renderCustomers();
            } else {
                toast(result.message || 'Erreur', 'err');
            }
        } catch (err) {
            console.error(err);
            toast('Erreur de chargement', 'err');
        }
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
                toast(result.message || 'Enregistré', 'ok');
                closeModal();
                await loadCustomers();
            } else {
                toast(result.message || 'Erreur', 'err');
            }
        } catch (err) {
            console.error(err);
            toast('Erreur de connexion', 'err');
        }
        els.saveBtn.disabled = false;
    }

    async function deleteCustomer(id) {
        const customer = allCustomers.find((c) => c.id === id);
        if (!customer) return;
        if (!confirm(`Supprimer le client « ${customer.name} » ?`)) return;

        try {
            const result = await CashierAPI.deleteCustomer(id);
            if (result.status === 'success') {
                toast('Client supprimé', 'ok');
                await loadCustomers();
            } else {
                toast(result.message || 'Erreur', 'err');
            }
        } catch (err) {
            toast('Erreur', 'err');
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

    document.getElementById('refreshCustomersBtn')?.addEventListener('click', loadCustomers);

    const menuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    menuBtn?.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
        overlay?.classList.toggle('active');
    });
    overlay?.addEventListener('click', () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('active');
    });

    loadCustomers();
});
