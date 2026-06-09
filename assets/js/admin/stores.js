/**
 * Gestion succursales + transferts de stock (CRUD complet)
 */
(() => {
    const CFG = window.STORES_PAGE || { canManage: false, isSuperAdmin: false };
    let storesList = [];
    let pendingDeleteId = null;

    const $ = (id) => document.getElementById(id);

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function showModal(id) {
        const el = $(id);
        if (el) {
            el.style.display = 'flex';
            el.classList.add('active');
        }
    }

    function hideModal(id) {
        const el = $(id);
        if (el) {
            el.style.display = 'none';
            el.classList.remove('active');
        }
    }

    async function loadStores() {
        const grid = $('storesGrid');
        if (!grid) return;
        grid.innerHTML = '<p class="ad-empty-row">Chargement…</p>';

        const res = await AdminAPI.listStores();
        if (res.status !== 'success') {
            grid.innerHTML = `<p class="ad-empty-row">${escapeHtml(res.message || 'Erreur')}</p>`;
            return;
        }
        storesList = res.data || [];
        if (!storesList.length) {
            grid.innerHTML = '<p class="ad-empty-row">Aucune succursale. Créez-en une avec « Nouvelle succursale ».</p>';
            return;
        }

        grid.innerHTML = storesList.map((s) => {
            const statusCls = s.is_active ? 'active' : 'inactive';
            const statusLabel = s.is_active ? 'Active' : 'Inactive';
            const cardCls = s.is_active ? '' : ' inactive';
            const manageBtns = CFG.canManage ? `
                <div class="ms-card-actions">
                    <button type="button" class="as-btn edit-store" data-id="${s.id}" title="Modifier">
                        <span class="material-icons-round">edit</span>
                    </button>
                    <button type="button" class="as-btn switch-store" data-id="${s.id}" title="Activer dans le header">
                        <span class="material-icons-round">store</span>
                    </button>
                    <button type="button" class="as-btn delete-store" data-id="${s.id}" title="Supprimer">
                        <span class="material-icons-round">delete</span>
                    </button>
                </div>` : '';

            return `
            <div class="ms-card${cardCls}" data-id="${s.id}">
                <div class="ms-code">${escapeHtml(s.code || '—')}</div>
                <h3>${escapeHtml(s.name)}</h3>
                <div class="ms-meta">${escapeHtml(s.location || '—')}</div>
                ${s.phone ? `<div class="ms-meta"><span class="material-icons-round" style="font-size:14px;vertical-align:middle;">phone</span> ${escapeHtml(s.phone)}</div>` : ''}
                <div class="ms-stats">
                    <span class="ms-status-badge ${statusCls}">${statusLabel}</span>
                    <span>TVA ${s.tax_rate}%</span>
                    <span>${escapeHtml(s.currency || 'FCFA')}</span>
                </div>
                ${manageBtns}
            </div>`;
        }).join('');

        grid.querySelectorAll('.edit-store').forEach((btn) => {
            btn.addEventListener('click', () => openStoreForm(parseInt(btn.dataset.id, 10)));
        });
        grid.querySelectorAll('.switch-store').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const res = await AdminAPI.switchStore({ store_id: parseInt(btn.dataset.id, 10) });
                if (res.status === 'success') window.location.reload();
                else alert(res.message || 'Erreur');
            });
        });
        grid.querySelectorAll('.delete-store').forEach((btn) => {
            btn.addEventListener('click', () => openDeleteConfirm(parseInt(btn.dataset.id, 10)));
        });
    }

    async function openDeleteConfirm(id) {
        pendingDeleteId = id;
        const local = storesList.find((x) => x.id === id);
        const name = local?.name || `ID ${id}`;

        $('deleteStoreText').innerHTML = `Voulez-vous vraiment supprimer <strong>${escapeHtml(name)}</strong> ?`;

        const depsEl = $('deleteStoreDeps');
        if (depsEl) {
            depsEl.classList.add('hidden');
            depsEl.innerHTML = '';
        }

        showModal('deleteStoreModal');

        try {
            const res = await AdminAPI.getStore(id);
            if (res.status === 'success' && res.data) {
                const d = res.data;
                const deps = d.dependencies || {};
                const total = (deps.users || 0) + (deps.products || 0) + (deps.sales || 0);
                if (depsEl && total > 0) {
                    depsEl.classList.remove('hidden');
                    depsEl.innerHTML = `
                        <li>${deps.users || 0} utilisateur(s) lié(s)</li>
                        <li>${deps.products || 0} produit(s)</li>
                        <li>${deps.sales || 0} vente(s) historique(s)</li>
                    `;
                }
                if (d.name) {
                    $('deleteStoreText').innerHTML =
                        `Voulez-vous vraiment supprimer <strong>${escapeHtml(d.name)}</strong> ?`;
                }
            }
        } catch (e) {
            console.warn('getStore for delete preview', e);
        }
    }

    async function confirmDelete() {
        if (!pendingDeleteId) return;
        const btn = $('confirmDeleteStore');
        if (btn) btn.disabled = true;

        const res = await AdminAPI.deleteStore(pendingDeleteId);
        if (btn) btn.disabled = false;

        if (res.status === 'success') {
            hideModal('deleteStoreModal');
            pendingDeleteId = null;
            alert(res.message || 'Succursale supprimée');
            window.location.reload();
        } else {
            alert(res.message || 'Impossible de supprimer');
        }
    }

    let transferFilterStatus = '';
    let selectedTransferProduct = null;
    let productSearchTimer = null;

    function transferStatusClass(status) {
        if (status === 'accepted') return 'success';
        if (status === 'rejected') return 'rejected';
        return 'pending';
    }

    async function loadTransferStats() {
        const res = await AdminAPI.getTransferStats();
        if (res.status !== 'success') return;
        const s = res.data || {};
        $('trStatPending') && ($('trStatPending').textContent = s.pending ?? 0);
        $('trStatAccepted') && ($('trStatAccepted').textContent = s.accepted ?? 0);
        $('trStatRejected') && ($('trStatRejected').textContent = s.rejected ?? 0);
        $('trStatUnits') && ($('trStatUnits').textContent = `${s.pending_units ?? 0} unités en attente`);
    }

    function fillTransferFilterSelects() {
        const active = storesList.filter((s) => s.is_active !== false);
        const opts = active.map((s) => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        ['trFilterFrom', 'trFilterTo', 'tfFrom', 'tfTo'].forEach((id) => {
            const sel = $(id);
            if (!sel) return;
            const isFilter = id.startsWith('trFilter');
            sel.innerHTML = (isFilter ? '<option value="">—</option>' : '') + opts;
        });
    }

    function getTransferQuery() {
        const q = { status: transferFilterStatus || undefined };
        const from = $('trFilterFrom')?.value;
        const to = $('trFilterTo')?.value;
        const search = $('trSearch')?.value?.trim();
        if (from) q.from_store = from;
        if (to) q.to_store = to;
        if (search) q.q = search;
        return q;
    }

    async function loadTransfers() {
        const tbody = $('transfersBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" class="ad-empty-row">Chargement…</td></tr>';

        await loadTransferStats();

        const res = await AdminAPI.listTransfers(getTransferQuery());
        if (res.status !== 'success') {
            tbody.innerHTML = `<tr><td colspan="6" class="ad-empty-row">${escapeHtml(res.message || 'Erreur')}</td></tr>`;
            return;
        }
        const rows = res.data || [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="ad-empty-row">Aucun transfert pour ces critères</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map((t) => {
            let actions = '—';
            if (t.status === 'pending' && t.can_accept) {
                actions = `
                    <button type="button" class="as-btn as-btn-primary accept-tr" data-id="${t.id}" title="Réceptionner le stock">
                        <span class="material-icons-round">check</span> Accepter
                    </button>
                    <button type="button" class="as-btn reject-tr" data-id="${t.id}">
                        <span class="material-icons-round">close</span> Refuser
                    </button>`;
            } else if (t.status === 'pending') {
                actions = '<span class="ms-tr-hint">En attente destination</span>';
            }

            return `<tr>
                <td style="white-space:nowrap;">${AdminAPI.formatDate(t.created_at)}</td>
                <td>
                    <strong>${escapeHtml(t.product_name)}</strong>
                    <br><small>SKU ${escapeHtml(t.sku || '—')}</small>
                </td>
                <td>
                    <div class="ms-tr-itineraire">
                        <span>${escapeHtml(t.from_store_name)}</span>
                        <span class="material-icons-round">arrow_forward</span>
                        <span>${escapeHtml(t.to_store_name)}</span>
                    </div>
                </td>
                <td><strong>${t.quantity}</strong></td>
                <td><span class="status-badge ${transferStatusClass(t.status)}">${escapeHtml(t.status_label || t.status)}</span></td>
                <td class="ms-tr-actions">${actions}</td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('.accept-tr').forEach((b) => {
            b.addEventListener('click', () => {
                if (!confirm('Confirmer la réception de ce transfert ? Le stock sera mis à jour.')) return;
                updateTransfer(b.dataset.id, 'accept');
            });
        });
        tbody.querySelectorAll('.reject-tr').forEach((b) => {
            b.addEventListener('click', () => {
                if (!confirm('Refuser ce transfert ?')) return;
                updateTransfer(b.dataset.id, 'reject');
            });
        });
    }

    async function updateTransfer(id, action) {
        const res = await AdminAPI.updateTransfer(id, { action });
        if (res.status === 'success') {
            await loadTransfers();
            if (res.message) toastTransfer(res.message);
        } else {
            alert(res.message || 'Erreur');
        }
    }

    function toastTransfer(msg) {
        const banner = $('storesError');
        if (!banner) {
            alert(msg);
            return;
        }
        banner.classList.remove('is-visible');
        banner.querySelector('.ad-error-text').textContent = msg;
        banner.style.background = 'var(--success-light)';
        banner.style.color = 'var(--success)';
        banner.classList.add('is-visible');
        setTimeout(() => {
            banner.classList.remove('is-visible');
            banner.style.background = '';
            banner.style.color = '';
        }, 3500);
    }

    function fillStoreSelects() {
        fillTransferFilterSelects();
    }

    function hideTransferFormError() {
        const box = $('transferFormError');
        if (box) box.style.display = 'none';
    }

    function showTransferFormError(msg) {
        const box = $('transferFormError');
        const text = box?.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        if (box) box.style.display = 'flex';
    }

    function selectTransferProduct(p) {
        selectedTransferProduct = p;
        $('tfProduct').value = p.id;
        $('tfSelectedName').textContent = p.name;
        $('tfSelectedMeta').textContent = `SKU ${p.sku || '—'} · Stock dispo: ${p.stock_quantity}`;
        $('tfSelectedBox')?.classList.remove('hidden');
        $('tfProductList')?.classList.add('hidden');
        const max = Math.max(1, p.stock_quantity);
        const qty = $('tfQty');
        if (qty) {
            qty.max = max;
            if (parseInt(qty.value, 10) > max) qty.value = max;
        }
        $('tfStockHint').textContent = `Maximum transférable : ${max} ${p.unit || 'unité(s)'}`;
    }

    function clearTransferProduct() {
        selectedTransferProduct = null;
        $('tfProduct').value = '';
        $('tfSelectedBox')?.classList.add('hidden');
        $('tfProductList')?.classList.remove('hidden');
        $('tfStockHint').textContent = '';
        loadTransferProducts($('tfProductSearch')?.value?.trim() || '');
    }

    async function loadTransferProducts(q = '') {
        const list = $('tfProductList');
        const fromId = parseInt($('tfFrom')?.value, 10);
        if (!list) return;

        if (!fromId) {
            list.innerHTML = '<p class="ad-empty-row">Choisissez une succursale source</p>';
            return;
        }

        list.innerHTML = '<p class="ad-empty-row">Chargement…</p>';
        const res = await AdminAPI.getTransferProducts(fromId, q);
        if (res.status !== 'success') {
            list.innerHTML = `<p class="ad-empty-row">${escapeHtml(res.message || 'Erreur')}</p>`;
            return;
        }

        const products = res.data || [];
        if (!products.length) {
            list.innerHTML = '<p class="ad-empty-row">Aucun produit trouvé</p>';
            return;
        }

        list.innerHTML = products.map((p) => {
            const out = p.stock_quantity <= 0;
            const cls = [
                'ms-tr-product-item',
                selectedTransferProduct?.id === p.id ? 'selected' : '',
                out ? 'out-of-stock' : '',
            ].filter(Boolean).join(' ');
            return `
            <div class="${cls}" data-id="${p.id}" data-stock="${p.stock_quantity}">
                <div>
                    <strong>${escapeHtml(p.name)}</strong>
                    <small>SKU ${escapeHtml(p.sku || '—')} · Stock: ${p.stock_quantity}</small>
                </div>
                <span class="material-icons-round" style="color:var(--primary);">add_circle</span>
            </div>`;
        }).join('');

        list.querySelectorAll('.ms-tr-product-item:not(.out-of-stock)').forEach((el) => {
            el.addEventListener('click', () => {
                const id = parseInt(el.dataset.id, 10);
                const p = products.find((x) => x.id === id);
                if (p) selectTransferProduct(p);
            });
        });
    }

    function openTransferModal() {
        hideTransferFormError();
        $('transferForm')?.reset();
        clearTransferProduct();
        fillTransferFilterSelects();
        const from = $('tfFrom');
        const to = $('tfTo');
        if (from && to && from.options.length > 1) {
            to.selectedIndex = Math.min(1, to.options.length - 1);
        }
        showModal('transferModal');
        loadTransferProducts();
    }

    function swapTransferStores() {
        const from = $('tfFrom');
        const to = $('tfTo');
        if (!from || !to) return;
        const tmp = from.value;
        from.value = to.value;
        to.value = tmp;
        clearTransferProduct();
        loadTransferProducts($('tfProductSearch')?.value?.trim() || '');
    }

    function openStoreForm(id = null) {
        $('storeForm').reset();
        $('storeFormId').value = id || '';
        $('storeModalTitle').textContent = id ? 'Modifier la succursale' : 'Nouvelle succursale';
        const activeCb = $('sfActive');
        if (activeCb) activeCb.checked = true;

        if (id) {
            const s = storesList.find((x) => x.id === id);
            if (s) {
                $('sfName').value = s.name;
                $('sfCode').value = s.code || '';
                $('sfLocation').value = s.location || '';
                $('sfPhone').value = s.phone || '';
                $('sfEmail').value = s.email || '';
                $('sfTax').value = s.tax_rate;
                $('sfCurrency').value = s.currency || 'FCFA';
                if (activeCb) activeCb.checked = s.is_active !== false;
            }
        }
        showModal('storeModal');
    }

    function initTabs() {
        document.querySelectorAll('.ms-tab').forEach((tab) => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.ms-tab').forEach((t) => t.classList.remove('active'));
                tab.classList.add('active');
                document.querySelectorAll('.ms-panel').forEach((p) => p.classList.add('hidden'));
                $(`panel-${tab.dataset.panel}`)?.classList.remove('hidden');
                if (tab.dataset.panel === 'transfers') {
                    fillTransferFilterSelects();
                    loadTransfers();
                }
            });
        });
    }

    function initEvents() {
        $('addStoreBtn')?.addEventListener('click', () => openStoreForm());
        $('refreshStoresBtn')?.addEventListener('click', loadStores);
        $('closeStoreModal')?.addEventListener('click', () => hideModal('storeModal'));
        $('closeTransferModal')?.addEventListener('click', () => hideModal('transferModal'));
        $('cancelDeleteStore')?.addEventListener('click', () => {
            pendingDeleteId = null;
            hideModal('deleteStoreModal');
        });
        $('confirmDeleteStore')?.addEventListener('click', confirmDelete);

        $('newTransferBtn')?.addEventListener('click', () => {
            fillStoreSelects();
            showModal('transferModal');
        });

        $('storeForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const errBox = $('storeFormError');
            const errText = errBox?.querySelector('.ad-error-text');
            const hideFormError = () => {
                if (errBox) errBox.style.display = 'none';
            };
            const showFormError = (msg) => {
                if (errText) errText.textContent = msg;
                if (errBox) errBox.style.display = 'flex';
            };
            hideFormError();

            const submitBtn = $('storeForm')?.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            const payload = {
                name: $('sfName').value.trim(),
                code: $('sfCode').value.trim() || undefined,
                location: $('sfLocation').value.trim() || null,
                phone: $('sfPhone').value.trim() || null,
                email: $('sfEmail').value.trim() || null,
                tax_rate: parseFloat($('sfTax').value) || 18,
                currency: $('sfCurrency').value.trim() || 'FCFA',
                is_active: $('sfActive')?.checked ?? true,
            };

            const id = $('storeFormId').value;
            try {
                const res = id
                    ? await AdminAPI.updateStore(id, payload)
                    : await AdminAPI.createStore(payload);

                if (res.status === 'success') {
                    hideModal('storeModal');
                    hideFormError();
                    await loadStores();
                    fillStoreSelects();
                } else {
                    showFormError(res.message || res.error || 'Erreur lors de l\'enregistrement');
                }
            } catch (err) {
                showFormError('Connexion API impossible. Vérifiez que vous êtes connecté.');
                console.error(err);
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });

        $('transferForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideTransferFormError();

            const fromId = parseInt($('tfFrom').value, 10);
            const toId = parseInt($('tfTo').value, 10);
            const productId = parseInt($('tfProduct').value, 10);
            const qty = parseInt($('tfQty').value, 10);

            if (fromId === toId) {
                showTransferFormError('La source et la destination doivent être différentes.');
                return;
            }
            if (!productId) {
                showTransferFormError('Sélectionnez un produit dans la liste.');
                return;
            }
            if (selectedTransferProduct && qty > selectedTransferProduct.stock_quantity) {
                showTransferFormError(`Stock insuffisant (max ${selectedTransferProduct.stock_quantity}).`);
                return;
            }

            const btn = $('tfSubmitBtn');
            if (btn) btn.disabled = true;

            const res = await AdminAPI.createTransfer({
                from_store_id: fromId,
                to_store_id: toId,
                product_id: productId,
                quantity: qty,
            });

            if (btn) btn.disabled = false;

            if (res.status === 'success') {
                hideModal('transferModal');
                document.querySelector('.ms-tab[data-panel="transfers"]')?.click();
                await loadTransfers();
                toastTransfer(res.message || 'Transfert créé');
            } else {
                showTransferFormError(res.message || 'Erreur');
            }
        });
    }

    async function checkHealth() {
        try {
            const h = await AdminAPI.getStoreHealth();
            if (h.status === 'success' && !h.data?.can_manage && CFG.canManage) {
                const banner = $('storesError');
                if (banner) {
                    banner.classList.add('is-visible');
                    banner.querySelector('.ad-error-text').textContent =
                        `Rôle « ${h.data.role} » : certaines actions peuvent être limitées.`;
                }
            }
        } catch (e) {
            console.warn('stores/health', e);
        }
    }

    document.addEventListener('DOMContentLoaded', async () => {
        initTabs();
        initEvents();
        await checkHealth();
        await loadStores();
        fillStoreSelects();
    });
})();
