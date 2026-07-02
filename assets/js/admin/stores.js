/**
 * Admin stores — network management, i18n, filters, CRUD
 */
(() => {
    const CFG = window.STORES_PAGE || { canManage: false, isSuperAdmin: false };
    const i18n = window.STORES_I18N || {};
    const locale = CFG.locale || (CFG.lang === 'fr' ? 'fr-FR' : 'en-US');

    let storesList = [];
    let filteredStores = [];
    let pendingDeleteId = null;
    let activeStatusFilter = 'all';
    let debounceTimer = null;
    let lastFetchAt = null;

    const $ = (id) => document.getElementById(id);

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function toast(msg, type = 'success') {
        const el = $('storesToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `inv-toast show ${type === 'error' ? 'error' : ''}`;
        clearTimeout(el._t);
        el._t = setTimeout(() => el.classList.remove('show'), 3200);
    }

    function showModal(id) {
        $(id)?.classList.add('active');
    }

    function hideModal(id) {
        $(id)?.classList.remove('active');
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('#msSummaryCards .ad-kpi').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function clearKpiLoading(el) {
        if (!el) return;
        const card = el.closest ? el.closest('.ad-kpi') : el;
        card?.classList.remove('is-loading');
    }

    function updateDateHeader() {
        const label = new Date().toLocaleDateString(locale, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
        const header = $('storesDate');
        if (header) header.textContent = label;

        const periodEl = $('msHeroPeriod');
        if (periodEl) periodEl.textContent = label;

        const scopeEl = $('msHeroScope');
        if (scopeEl) scopeEl.textContent = t('stores_scope');
    }

    function updateLastUpdated() {
        const el = $('lastUpdated');
        if (!el || !lastFetchAt) return;
        const time = lastFetchAt.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        el.textContent = t('last_updated', time);
    }

    function updateStats(stores) {
        setStatsLoading(false);
        const active = stores.filter((s) => s.is_active !== false).length;
        const inactive = stores.length - active;

        const totalEl = $('stat-total-val');
        if (totalEl) {
            totalEl.textContent = String(stores.length);
            clearKpiLoading(totalEl);
        }
        const activeEl = $('stat-active-val');
        if (activeEl) {
            activeEl.textContent = String(active);
            clearKpiLoading(activeEl);
        }
        const inactiveEl = $('stat-inactive-val');
        if (inactiveEl) {
            inactiveEl.textContent = String(inactive);
            clearKpiLoading(inactiveEl);
        }
    }

    function updatePendingAlert(count) {
        const alertEl = $('msPendingAlert');
        const alertText = $('msPendingAlertText');
        if (!alertEl) return;

        let msg = '';
        const pending = parseInt(count, 10) || 0;
        if (pending > 0 && alertText) {
            const raw = t('pending_transfers_alert');
            if (raw && raw !== 'pending_transfers_alert') {
                msg = raw.includes('%s') ? raw.replace('%s', String(pending)) : `${pending} — ${raw}`;
                alertText.textContent = msg;
            }
        } else if (alertText) {
            alertText.textContent = '';
        }
        alertEl.hidden = !(pending > 0 && msg.trim());
    }

    async function loadTransferStats() {
        try {
            const res = await AdminAPI.getTransferStats();
            if (res.status === 'success') {
                const pending = res.data?.pending ?? 0;
                const pendingEl = $('stat-pending-tr-val');
                if (pendingEl) {
                    pendingEl.textContent = String(pending);
                    clearKpiLoading(pendingEl);
                }
                updatePendingAlert(pending);
            }
        } catch (e) {
            console.warn('transfer stats', e);
            clearKpiLoading($('stat-pending-tr-val'));
        }
    }

    function applyFilters() {
        const q = $('storesSearch')?.value.trim().toLowerCase() || '';

        filteredStores = storesList.filter((s) => {
            if (activeStatusFilter === 'active' && s.is_active === false) return false;
            if (activeStatusFilter === 'inactive' && s.is_active !== false) return false;

            if (!q) return true;
            const hay = [
                s.name,
                s.code,
                s.location,
                s.phone,
                s.email,
                s.currency,
            ].join(' ').toLowerCase();
            return hay.includes(q);
        });

        if ($('storesSummary')) {
            $('storesSummary').textContent = filteredStores.length
                ? t('stores_table_summary', filteredStores.length, 1, 1)
                : t('no_stores_found');
        }
    }

    function renderStores() {
        applyFilters();
        const grid = $('storesGrid');
        if (!grid) return;

        if (!filteredStores.length) {
            grid.innerHTML = `<p class="ad-empty-row">${escapeHtml(storesList.length ? t('no_stores_found') : t('no_stores'))}</p>`;
            return;
        }

        grid.innerHTML = filteredStores.map((s) => {
            const statusCls = s.is_active !== false ? 'active' : 'inactive';
            const statusLabel = s.is_active !== false ? t('store_active') : t('store_inactive');
            const cardCls = s.is_active !== false ? '' : ' inactive';
            const staff = s.staff_count ?? 0;
            const products = s.product_count ?? 0;

            const manageBtns = CFG.canManage ? `
                <div class="ms-card-actions">
                    <button type="button" class="as-btn edit-store" data-id="${s.id}" title="${escapeHtml(t('edit_store'))}">
                        <span class="material-icons-round">edit</span>
                        <span>${escapeHtml(t('edit_store'))}</span>
                    </button>
                    <button type="button" class="as-btn switch-store" data-id="${s.id}" title="${escapeHtml(t('switch_store'))}">
                        <span class="material-icons-round">store</span>
                    </button>
                    <button type="button" class="as-btn delete-store" data-id="${s.id}" title="${escapeHtml(t('delete_store'))}">
                        <span class="material-icons-round">delete</span>
                    </button>
                </div>` : '';

            return `
            <article class="ms-card${cardCls}" data-id="${s.id}">
                <div class="ms-card-top">
                    <div class="ms-code">${escapeHtml(s.code || '—')}</div>
                    <span class="ms-status-badge ${statusCls}">${escapeHtml(statusLabel)}</span>
                </div>
                <h3>${escapeHtml(s.name)}</h3>
                <div class="ms-meta">
                    <span class="material-icons-round">place</span>
                    ${escapeHtml(s.location || '—')}
                </div>
                ${s.phone ? `<div class="ms-meta"><span class="material-icons-round">phone</span>${escapeHtml(s.phone)}</div>` : ''}
                ${s.email ? `<div class="ms-meta"><span class="material-icons-round">email</span>${escapeHtml(s.email)}</div>` : ''}
                <div class="ms-stats">
                    <span title="${escapeHtml(t('staff_count', staff))}">
                        <span class="material-icons-round">groups</span>${staff}
                    </span>
                    <span title="${escapeHtml(t('product_count', products))}">
                        <span class="material-icons-round">inventory_2</span>${products}
                    </span>
                    <span>${escapeHtml(t('tax_rate_label', s.tax_rate ?? 18))}</span>
                    <span>${escapeHtml(s.currency || 'FCFA')}</span>
                </div>
                ${manageBtns}
            </article>`;
        }).join('');

        grid.querySelectorAll('.edit-store').forEach((btn) => {
            btn.addEventListener('click', () => openStoreForm(parseInt(btn.dataset.id, 10)));
        });
        grid.querySelectorAll('.switch-store').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const res = await AdminAPI.switchStore({ store_id: parseInt(btn.dataset.id, 10) });
                if (res.status === 'success') window.location.reload();
                else toast(res.message || t('error'), 'error');
            });
        });
        grid.querySelectorAll('.delete-store').forEach((btn) => {
            btn.addEventListener('click', () => openDeleteConfirm(parseInt(btn.dataset.id, 10)));
        });
    }

    async function loadStores() {
        const grid = $('storesGrid');
        setStatsLoading(true);
        if (grid) grid.innerHTML = `<p class="ad-empty-row">${escapeHtml(t('loading'))}</p>`;

        try {
            const res = await AdminAPI.listStores();
            if (res.status !== 'success') {
                if (grid) grid.innerHTML = `<p class="ad-empty-row">${escapeHtml(res.message || t('load_error'))}</p>`;
                setStatsLoading(false);
                return;
            }

            storesList = res.data || [];
            lastFetchAt = new Date();
            updateLastUpdated();
            updateStats(storesList);
            renderStores();
        } catch (e) {
            console.error(e);
            if (grid) grid.innerHTML = `<p class="ad-empty-row">${escapeHtml(t('connection_error'))}</p>`;
            setStatsLoading(false);
        }
    }

    async function openDeleteConfirm(id) {
        pendingDeleteId = id;
        const local = storesList.find((x) => x.id === id);
        const name = local?.name || `ID ${id}`;

        $('deleteStoreText').textContent = t('delete_store_confirm', name);

        const depsEl = $('deleteStoreDeps');
        if (depsEl) {
            depsEl.classList.add('hidden');
            depsEl.innerHTML = '';
        }

        showModal('deleteStoreModalOverlay');

        try {
            const res = await AdminAPI.getStore(id);
            if (res.status === 'success' && res.data) {
                const d = res.data;
                const deps = d.dependencies || {};
                const total = (deps.users || 0) + (deps.products || 0) + (deps.sales || 0);
                if (depsEl && total > 0) {
                    depsEl.classList.remove('hidden');
                    depsEl.innerHTML = `
                        <li>${escapeHtml(t('delete_store_deps_users', deps.users || 0))}</li>
                        <li>${escapeHtml(t('delete_store_deps_products', deps.products || 0))}</li>
                        <li>${escapeHtml(t('delete_store_deps_sales', deps.sales || 0))}</li>
                    `;
                }
                if (d.name) {
                    $('deleteStoreText').textContent = t('delete_store_confirm', d.name);
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
            hideModal('deleteStoreModalOverlay');
            pendingDeleteId = null;
            toast(res.message || t('store_deleted'));
            await loadStores();
            await loadTransferStats();
        } else {
            toast(res.message || t('store_delete_error'), 'error');
        }
    }

    function hideStoreFormError() {
        const box = $('storeFormError');
        if (box) box.style.display = 'none';
    }

    function showStoreFormError(msg) {
        const box = $('storeFormError');
        const text = box?.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        if (box) box.style.display = 'flex';
    }

    function openStoreForm(id = null) {
        $('storeForm')?.reset();
        $('storeFormId').value = id || '';
        $('storeModalTitle').textContent = id ? t('store_modal_edit') : t('store_modal_new');
        const activeCb = $('sfActive');
        if (activeCb) activeCb.checked = true;
        hideStoreFormError();

        if (id) {
            const s = storesList.find((x) => x.id === id);
            if (s) {
                $('sfName').value = s.name;
                $('sfCode').value = s.code || '';
                $('sfLocation').value = s.location || '';
                $('sfPhone').value = s.phone || '';
                $('sfEmail').value = s.email || '';
                $('sfTax').value = s.tax_rate;
                $('sfCurrency').value = s.currency || CFG.currency || 'FCFA';
                if (activeCb) activeCb.checked = s.is_active !== false;
            }
        } else if ($('sfCurrency')) {
            $('sfCurrency').value = CFG.currency || 'FCFA';
        }

        showModal('storeModalOverlay');
    }

    function syncStatusChips() {
        document.querySelectorAll('.ms-chips .inv-chip').forEach((c) => {
            const active = (c.dataset.status || 'all') === activeStatusFilter;
            c.classList.toggle('active', active);
            c.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    }

    function initEvents() {
        $('addStoreBtn')?.addEventListener('click', () => openStoreForm());
        $('addStoreBtnHero')?.addEventListener('click', () => openStoreForm());
        $('refreshStoresBtn')?.addEventListener('click', async () => {
            $('refreshStoresBtn')?.classList.add('spinning');
            await loadStores();
            await loadTransferStats();
            $('refreshStoresBtn')?.classList.remove('spinning');
        });
        $('closeStoreModal')?.addEventListener('click', () => hideModal('storeModalOverlay'));
        $('cancelStoreModal')?.addEventListener('click', () => hideModal('storeModalOverlay'));
        $('cancelDeleteStore')?.addEventListener('click', () => {
            pendingDeleteId = null;
            hideModal('deleteStoreModalOverlay');
        });
        $('confirmDeleteStore')?.addEventListener('click', confirmDelete);

        $('storeModalOverlay')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) hideModal('storeModalOverlay');
        });
        $('deleteStoreModalOverlay')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) hideModal('deleteStoreModalOverlay');
        });

        document.querySelectorAll('.ms-chips .inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                activeStatusFilter = chip.dataset.status || 'all';
                syncStatusChips();
                renderStores();
            });
        });

        $('storesSearch')?.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(renderStores, 300);
        });

        $('storeForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideStoreFormError();

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
                    hideModal('storeModalOverlay');
                    toast(res.message || t('store_saved'));
                    await loadStores();
                } else {
                    showStoreFormError(res.message || res.error || t('error'));
                }
            } catch (err) {
                showStoreFormError(t('connection_error'));
                console.error(err);
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                hideModal('storeModalOverlay');
                hideModal('deleteStoreModalOverlay');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        updateDateHeader();
        initEvents();
        await Promise.all([loadStores(), loadTransferStats()]);
    });
})();
