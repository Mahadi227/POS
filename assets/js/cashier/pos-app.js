/**
 * RetailPOS — Logique caisse (DOM fourni par pos.php)
 */
document.addEventListener('DOMContentLoaded', () => {
    const config = window.POS_CONFIG || {};
    const settings = config.settings || { tax_rate: 0.18, tax_percent: 18 };
    const user = config.user || { id: null, name: 'Caissier' };
    const store = config.store || { id: 1, name: 'RetailPOS' };
    const currencySymbol = (config.settings && config.settings.currency_symbol) || (config.store && config.store.currency) || 'FCFA';
    const fmt = (n) => CashierAPI.formatCurrency(n);

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function resolveImageUrl(url) {
        if (!url) return null;
        const value = String(url);
        if (value.startsWith('http') || value.startsWith('data:')) {
            try {
                return new URL(value).href;
            } catch {
                return value.replace(/ /g, '%20');
            }
        }
        let path = value.replace(/^(?:\.\.\/)+/, '').replace(/^\/+/, '');
        if (path.startsWith('public/')) path = path.slice(7);
        if (path.startsWith('./')) path = path.slice(2);
        const filename = path.split('/').pop();
        if (!filename) return null;
        const relativePath = `uploads/products/${filename}`;
        const encodedPath = relativePath.split('/').map(encodeURIComponent).join('/');
        const pageInPublic = location.pathname.includes('/public/');
        if (pageInPublic) {
            return `../${encodedPath}`;
        }
        const base = (config.appUrl || '').replace(/\/$/, '').replace(/ /g, '%20');
        if (base) {
            if (base.endsWith('/public')) {
                return `${base}/${encodedPath}`;
            }
            return `${base}/public/${encodedPath}`;
        }
        return `../${encodedPath}`;
    }

    function cartThumbHtml(item) {
        const imgUrl = resolveImageUrl(item.image_url);
        if (imgUrl) {
            return `<img src="${escapeHtml(imgUrl)}" alt="">`;
        }
        return '<span class="material-icons-round">inventory_2</span>';
    }

    const db = new Dexie('RetailPOS_Cashier');
    db.version(2).stores({
        products: 'id, sku, barcode, category_id, name, store_id',
        pending_sales: '++id, local_uuid, payload, timestamp',
        meta: 'key',
    });

    let cart = [];
    let taxRate = settings.tax_rate ?? 0.18;
    let taxPercent = settings.tax_percent ?? 18;
    let currentDiscount = 0;
    let currentPaymentMethod = 'cash';
    let mobileMoneyProvider = 'orange_money';
    let finalTotal = 0;
    let customers = config.customers || [];
    let selectedCustomerId = '';
    let prevCartLineCount = 0;
    let mobileView = 'catalog';
    const MOBILE_BP = 768;
    const appRoot = document.getElementById('pos-cashier-app');

    const els = {
        searchInput: document.getElementById('searchInput'),
        productGrid: document.getElementById('productGrid'),
        categoriesWrapper: document.getElementById('categoriesWrapper'),
        cartItems: document.getElementById('cartItems'),
        cartCount: document.getElementById('cartCount'),
        subtotalDisplay: document.getElementById('subtotalDisplay'),
        taxDisplay: document.getElementById('taxDisplay'),
        taxLabel: document.getElementById('taxLabel'),
        discountDisplay: document.getElementById('discountDisplay'),
        totalDisplay: document.getElementById('totalDisplay'),
        checkoutBtn: document.getElementById('checkoutBtn'),
        checkoutModal: document.getElementById('checkoutModal'),
        modalTotalDisplay: document.getElementById('modalTotalDisplay'),
        confirmPaymentBtn: document.getElementById('confirmPaymentBtn'),
        amountTendered: document.getElementById('amountTendered'),
        changeDisplay: document.getElementById('changeDisplay'),
        customerSelect: document.getElementById('customerSelect'),
        connectionBadge: document.getElementById('connectionBadge'),
        pendingBadge: document.getElementById('pendingBadge'),
        liveClock: document.getElementById('liveClock'),
        toastContainer: document.getElementById('toastContainer'),
    };

    function toast(message, type = 'info') {
        const t = document.createElement('div');
        t.className = 'pos-cashier__toast';
        if (type === 'success') t.classList.add('pos-cashier__toast--ok');
        if (type === 'warning') t.classList.add('pos-cashier__toast--warn');
        if (type === 'error') t.classList.add('pos-cashier__toast--err');
        t.textContent = message;
        els.toastContainer.appendChild(t);
        requestAnimationFrame(() => t.classList.add('show'));
        setTimeout(() => {
            t.classList.remove('show');
            setTimeout(() => t.remove(), 300);
        }, 3200);
    }

    function updateClock() {
        if (els.liveClock) {
            els.liveClock.textContent = new Date().toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        }
    }

    function setOnlineStatus(online) {
        const badge = els.connectionBadge;
        if (!badge) return;
        badge.classList.toggle('pos-cashier__status--online', online);
        badge.classList.toggle('pos-cashier__status--offline', !online);
        const text = badge.querySelector('.pos-cashier__status-text');
        if (text) text.textContent = online ? 'En ligne' : 'Hors ligne';
    }

    async function updatePendingBadge() {
        const count = await db.pending_sales.count();
        if (!els.pendingBadge) return;
        if (count > 0) {
            els.pendingBadge.textContent = count;
            els.pendingBadge.classList.remove('hidden');
        } else {
            els.pendingBadge.classList.add('hidden');
        }
    }

    function renderCustomers() {
        if (!els.customerSelect) return;
        const current = els.customerSelect.value;
        els.customerSelect.innerHTML = '<option value="">Client passage</option>';
        customers.forEach((c) => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.phone ? `${c.name} (${c.phone})` : c.name;
            els.customerSelect.appendChild(opt);
        });
        if (current) els.customerSelect.value = current;
    }

    function renderCategories(categories) {
        if (!els.categoriesWrapper) return;
        els.categoriesWrapper.innerHTML =
            '<button type="button" class="pos-cashier__cat active" data-id="all">Tout</button>';
        categories.forEach((c) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-cashier__cat';
            btn.dataset.id = c.id;
            btn.textContent = c.name;
            els.categoriesWrapper.appendChild(btn);
        });
        els.categoriesWrapper.querySelectorAll('.pos-cashier__cat').forEach((chip) => {
            chip.addEventListener('click', (e) => {
                els.categoriesWrapper.querySelectorAll('.pos-cashier__cat').forEach((c) =>
                    c.classList.remove('active')
                );
                e.currentTarget.classList.add('active');
                loadLocalProducts(els.searchInput?.value || '', e.currentTarget.dataset.id);
            });
        });
    }

    function renderCatalog(products) {
        if (!els.productGrid) return;
        els.productGrid.innerHTML = '';
        if (!products.length) {
            els.productGrid.innerHTML = '<div class="pos-cashier__empty">Aucun produit trouvé</div>';
            return;
        }
        const threshold = settings.low_stock_threshold ?? 5;
        products.forEach((p) => {
            const stock = parseInt(p.stock_quantity, 10) || 0;
            const outOfStock = stock <= 0;
            const lowStock = stock > 0 && stock <= (p.min_stock_level ?? threshold);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-cashier__product';
            if (lowStock) btn.classList.add('pos-cashier__product--low');
            btn.disabled = outOfStock;
            const imgUrl = resolveImageUrl(p.image_url);
            const img = imgUrl
                ? `<img src="${escapeHtml(imgUrl)}" alt="">`
                : '<span class="material-icons-round">inventory_2</span>';
            const stockCls = outOfStock ? 'pos-cashier__product-stock--out' : lowStock ? 'pos-cashier__product-stock--low' : '';
            btn.innerHTML = `
                <div class="pos-cashier__product-img">${img}</div>
                <div class="pos-cashier__product-body">
                    <div class="pos-cashier__product-name">${p.name}</div>
                    <div class="pos-cashier__product-price">${fmt(p.price)}</div>
                    <div class="pos-cashier__product-stock ${stockCls}">${outOfStock ? 'Rupture' : `Stock : ${stock}`}</div>
                </div>`;
            if (!outOfStock) btn.addEventListener('click', () => addToCart(p));
            els.productGrid.appendChild(btn);
        });
    }

    async function loadLocalProducts(query = '', categoryId = 'all') {
        let products = await db.products.toArray();
        const q = String(query).trim().toLowerCase();
        if (q) {
            products = products.filter(
                (p) =>
                    p.name.toLowerCase().includes(q) ||
                    String(p.sku || '').toLowerCase().includes(q) ||
                    String(p.barcode || '').includes(q)
            );
        }
        if (categoryId !== 'all') {
            products = products.filter((p) => String(p.category_id) === String(categoryId));
        }
        renderCatalog(products);
    }

    async function syncCatalog(showToast = true) {
        if (!navigator.onLine) {
            await loadLocalProducts(els.searchInput?.value || '');
            if (showToast) toast('Mode hors ligne — catalogue local', 'warning');
            return;
        }
        if (els.productGrid) {
            els.productGrid.innerHTML = '<div class="pos-cashier__loading">Synchronisation…</div>';
        }
        try {
            const [prodRes, catRes] = await Promise.all([
                CashierAPI.getProducts(),
                CashierAPI.getCategories(),
            ]);
            if (prodRes.status === 'success') {
                await db.products.clear();
                if (prodRes.data?.length) await db.products.bulkAdd(prodRes.data);
                await loadLocalProducts(els.searchInput?.value || '');
                if (showToast) toast(`${prodRes.data.length} produits chargés`, 'success');
            } else throw new Error(prodRes.message);
            if (catRes.status === 'success') renderCategories(catRes.data || []);
        } catch (e) {
            console.error(e);
            await loadLocalProducts(els.searchInput?.value || '');
            toast('Sync échouée — catalogue local', 'warning');
        }
    }

    async function refreshBootstrap() {
        if (!navigator.onLine) return;
        const res = await CashierAPI.getPosBootstrap();
        if (res.status === 'success' && res.data) {
            if (res.data.settings?.tax_rate != null) {
                taxRate = res.data.settings.tax_rate;
                taxPercent = res.data.settings.tax_percent ?? taxRate * 100;
                if (els.taxLabel) els.taxLabel.textContent = `TVA (${taxPercent}%)`;
            }
            const storeEl = document.getElementById('storeName');
            if (storeEl && res.data.store?.name) storeEl.textContent = res.data.store.name;
            if (res.data.customers) {
                customers = res.data.customers;
                renderCustomers();
            }
        }
    }

    function isMobileLayout() {
        return window.innerWidth <= MOBILE_BP;
    }

    function setMobileView(view) {
        if (!appRoot || !isMobileLayout()) return;
        mobileView = view === 'cart' ? 'cart' : 'catalog';
        appRoot.classList.toggle('pos-cashier--view-cart', mobileView === 'cart');
        appRoot.classList.toggle('pos-cashier--view-catalog', mobileView === 'catalog');
        document.querySelectorAll('.pos-cashier__mobile-nav-btn').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.view === mobileView);
        });
        updateMobileBar();
    }

    function syncMobileLayoutClass() {
        if (!appRoot) return;
        if (!isMobileLayout()) {
            appRoot.classList.remove('pos-cashier--view-cart');
            appRoot.classList.add('pos-cashier--view-catalog');
            return;
        }
        setMobileView(mobileView);
    }

    function updateMobileBar() {
        const units = cart.reduce((n, i) => n + i.qty, 0);
        const hasItems = cart.length > 0;
        const dock = document.getElementById('mobileCartDock');
        const navBadge = document.getElementById('mobileNavBadge');
        const unitsEl = document.getElementById('mobileCartUnits');
        const totalEl = document.getElementById('mobileCartTotal');
        const dockPay = document.getElementById('mobileDockPay');

        if (unitsEl) unitsEl.textContent = String(units);
        if (totalEl) totalEl.textContent = fmt(finalTotal);
        if (dockPay) dockPay.disabled = !hasItems;
        if (navBadge) {
            navBadge.textContent = String(units);
            navBadge.classList.toggle('hidden', units === 0);
        }
        if (dock && isMobileLayout()) {
            dock.hidden = !hasItems || mobileView === 'cart';
        } else if (dock) {
            dock.hidden = true;
        }
    }

    function updateCartCount() {
        const units = cart.reduce((n, i) => n + i.qty, 0);
        const lines = cart.length;
        if (els.cartCount) els.cartCount.textContent = units;
        const listHead = document.getElementById('cartListHead');
        const articlesLabel = document.getElementById('cartArticlesLabel');
        if (listHead && articlesLabel) {
            if (lines > 0) {
                listHead.hidden = false;
                articlesLabel.textContent =
                    lines === 1 ? `1 produit · ${units} unité(s)` : `${lines} produits · ${units} unité(s)`;
            } else {
                listHead.hidden = true;
            }
        }
        updateMobileBar();
    }

    function switchPaymentPanel(method) {
        document.querySelectorAll('.pos-cashier__pay-panel').forEach((panel) => {
            const show = panel.dataset.panel === method;
            panel.classList.toggle('hidden', !show);
        });
        if (method === 'cash' && els.amountTendered) {
            setTimeout(() => els.amountTendered.focus(), 100);
        }
    }

    function refreshPaymentModal() {
        const units = cart.reduce((n, i) => n + i.qty, 0);
        const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
        const tax = subtotal * taxRate;

        const set = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        set('modalItemCount', String(units));
        set('modalSubtotal', fmt(subtotal));
        set('modalTax', fmt(tax));
        if (els.taxLabel) {
            const taxLbl = document.getElementById('modalTaxLabel');
            if (taxLbl) taxLbl.textContent = els.taxLabel.textContent;
        }

        const discountRow = document.getElementById('modalDiscountRow');
        if (discountRow) {
            if (currentDiscount > 0) {
                discountRow.hidden = false;
                set('modalDiscount', `- ${fmt(currentDiscount)}`);
            } else {
                discountRow.hidden = true;
            }
        }

        if (els.modalTotalDisplay) els.modalTotalDisplay.textContent = fmt(finalTotal);
        calculateChange();
    }

    function openCheckoutModal() {
        els.checkoutModal?.classList.add('is-open');
        els.checkoutModal?.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        refreshPaymentModal();
        switchPaymentPanel(currentPaymentMethod);
        if (currentPaymentMethod === 'cash' && els.amountTendered) {
            els.amountTendered.value = '';
            setTimeout(() => els.amountTendered.focus(), 150);
        }
    }

    function closeCheckoutModal() {
        els.checkoutModal?.classList.remove('is-open');
        els.checkoutModal?.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (els.confirmPaymentBtn) {
            els.confirmPaymentBtn.disabled = false;
            els.confirmPaymentBtn.classList.remove('is-disabled');
        }
    }

    function getMaxQty(item) {
        const stock = parseInt(item.stock_quantity, 10);
        return stock > 0 ? stock : 9999;
    }

    /** Met à jour la quantité manuellement (saisie clavier ou +/-). */
    function setCartQty(index, rawQty, { silent = false } = {}) {
        if (index < 0 || index >= cart.length) return false;
        const item = cart[index];
        const max = getMaxQty(item);
        let qty = parseInt(String(rawQty).trim(), 10);

        if (Number.isNaN(qty) || qty < 1) {
            if (!silent) toast('Quantité invalide (minimum 1)', 'warning');
            return false;
        }
        if (qty > max) {
            qty = max;
            if (!silent) toast(`Stock max : ${max} pour « ${item.name} »`, 'warning');
        }

        if (qty === item.qty) return true;

        item.qty = qty;
        return true;
    }

    function addToCart(product) {
        const stock = parseInt(product.stock_quantity, 10) || 0;
        const existing = cart.find((item) => item.id === product.id);
        if (existing) {
            if (existing.qty >= stock) {
                toast(`Stock max : ${stock}`, 'warning');
                return { ok: false, reason: 'max_stock', message: `Stock max : ${stock}` };
            }
            existing.qty++;
            updateCart();
            return { ok: true, action: 'incremented' };
        }
        if (stock < 1) {
            toast('Produit en rupture', 'warning');
            return { ok: false, reason: 'out_of_stock', message: 'Produit en rupture' };
        }
        cart.push({ ...product, qty: 1 });
        updateCart();
        return { ok: true, action: 'added' };
    }

    function updateCart() {
        updateCartCount();
        if (!els.cartItems) return;

        if (!cart.length) {
            els.cartItems.innerHTML = `
                <div class="pos-cashier__cart-empty">
                    <span class="material-icons-round">shopping_cart</span>
                    <p>Le panier est vide</p>
                    <small>Cliquez sur un produit ou scannez un code-barres</small>
                </div>`;
            if (els.checkoutBtn) els.checkoutBtn.disabled = true;
            updateTotals();
            return;
        }

        if (els.checkoutBtn) els.checkoutBtn.disabled = false;
        els.cartItems.innerHTML = '';

        cart.forEach((item, index) => {
            const max = getMaxQty(item);
            const lineTotal = item.price * item.qty;
            const sku = item.sku || item.barcode || '';
            const row = document.createElement('article');
            row.className = 'pos-cashier__line';
            row.dataset.index = String(index);
            row.innerHTML = `
                <div class="pos-cashier__line-index">${index + 1}</div>
                <div class="pos-cashier__line-thumb">${cartThumbHtml(item)}</div>
                <div class="pos-cashier__line-body">
                    <div class="pos-cashier__line-top">
                        <h4 class="pos-cashier__line-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</h4>
                        <button type="button" class="pos-cashier__line-del btn-delete" aria-label="Retirer ${escapeHtml(item.name)}">
                            <span class="material-icons-round">close</span>
                        </button>
                    </div>
                    ${sku ? `<div class="pos-cashier__line-sku">${escapeHtml(sku)}</div>` : ''}
                    <div class="pos-cashier__line-prices">
                        <span class="pos-cashier__line-unit">${fmt(item.price)} / unité</span>
                        <span class="pos-cashier__line-stock">Stock max : ${max}</span>
                    </div>
                    <div class="pos-cashier__line-footer">
                        <div class="pos-cashier__line-qty">
                            <span class="pos-cashier__qty-label">Qté</span>
                            <button type="button" class="pos-cashier__qty-btn btn-minus" aria-label="Diminuer">−</button>
                            <input
                                type="number"
                                class="pos-cashier__qty-input"
                                value="${item.qty}"
                                min="1"
                                max="${max}"
                                step="1"
                                inputmode="numeric"
                                aria-label="Quantité"
                                title="Quantité (max ${max})"
                            />
                            <button type="button" class="pos-cashier__qty-btn btn-plus" aria-label="Augmenter">+</button>
                        </div>
                        <div class="pos-cashier__line-total-box">
                            <span class="pos-cashier__line-total-label">Total ligne</span>
                            <strong class="pos-cashier__line-total" data-line-total>${fmt(lineTotal)}</strong>
                        </div>
                    </div>
                </div>`;

            const qtyInput = row.querySelector('.pos-cashier__qty-input');
            const lineTotalEl = row.querySelector('[data-line-total]');

            const applyQtyFromInput = () => {
                const prev = cart[index].qty;
                if (setCartQty(index, qtyInput.value)) {
                    qtyInput.value = cart[index].qty;
                    qtyInput.max = getMaxQty(cart[index]);
                    if (lineTotalEl) {
                        lineTotalEl.textContent = fmt(cart[index].price * cart[index].qty);
                    }
                    updateCartCount();
                    updateTotals();
                } else {
                    qtyInput.value = prev;
                }
            };

            qtyInput.addEventListener('change', applyQtyFromInput);
            qtyInput.addEventListener('blur', applyQtyFromInput);
            qtyInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    qtyInput.blur();
                }
            });
            qtyInput.addEventListener('click', (e) => e.stopPropagation());
            qtyInput.addEventListener('focus', () => qtyInput.select());

            row.querySelector('.btn-minus').addEventListener('click', () => {
                if (cart[index].qty <= 1) {
                    cart.splice(index, 1);
                    updateCart();
                } else {
                    setCartQty(index, cart[index].qty - 1, { silent: true });
                    updateCart();
                }
            });
            row.querySelector('.btn-plus').addEventListener('click', () => {
                setCartQty(index, cart[index].qty + 1);
                updateCart();
            });
            row.querySelector('.btn-delete').addEventListener('click', () => {
                cart.splice(index, 1);
                updateCart();
            });
            els.cartItems.appendChild(row);
        });

        if (cart.length > prevCartLineCount && els.cartItems.lastElementChild) {
            requestAnimationFrame(() => {
                els.cartItems.lastElementChild.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        }
        prevCartLineCount = cart.length;
        updateTotals();
    }

    function updateTotals() {
        const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
        const tax = subtotal * taxRate;
        finalTotal = Math.max(0, subtotal + tax - currentDiscount);
        if (els.subtotalDisplay) els.subtotalDisplay.textContent = fmt(subtotal);
        if (els.taxDisplay) els.taxDisplay.textContent = fmt(tax);
        if (els.discountDisplay) {
            els.discountDisplay.innerHTML = `<span>Remise</span><span>- ${fmt(currentDiscount)}</span>`;
        }
        if (els.totalDisplay) els.totalDisplay.textContent = fmt(finalTotal);
        if (els.modalTotalDisplay) els.modalTotalDisplay.textContent = fmt(finalTotal);
        updateMobileBar();
        if (els.checkoutModal?.classList.contains('is-open')) {
            refreshPaymentModal();
        } else {
            calculateChange();
        }
    }

    function calculateChange() {
        const changeBox = document.getElementById('changeBox');
        if (!els.changeDisplay) return;

        if (currentPaymentMethod !== 'cash' || !els.amountTendered) {
            els.changeDisplay.textContent = fmt(0);
            changeBox?.classList.remove('pos-cashier__change--insufficient');
            if (els.confirmPaymentBtn) {
                els.confirmPaymentBtn.disabled = cart.length === 0;
                els.confirmPaymentBtn.classList.remove('is-disabled');
            }
            return;
        }

        const tendered = parseFloat(els.amountTendered.value) || 0;
        const change = tendered - finalTotal;
        const ok = change >= 0;

        els.changeDisplay.textContent = ok ? fmt(change) : fmt(0);
        changeBox?.classList.toggle('pos-cashier__change--insufficient', !ok && tendered > 0);

        if (els.confirmPaymentBtn) {
            const canPay =
                currentPaymentMethod !== 'cash' ||
                tendered >= finalTotal;
            els.confirmPaymentBtn.disabled = !canPay || cart.length === 0;
            els.confirmPaymentBtn.classList.toggle('is-disabled', !canPay);
        }
    }

    function buildReceiptNo() {
        const d = new Date();
        const p = (n) => String(n).padStart(2, '0');
        return `R${store.id}-${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}-${user.id || 0}`;
    }

    function buildSalePayload() {
        const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
        return {
            receipt_no: buildReceiptNo(),
            store_id: store.id,
            user_id: user.id,
            customer_id: selectedCustomerId || null,
            total: finalTotal,
            tax: subtotal * taxRate,
            discount: currentDiscount,
            payment_method: currentPaymentMethod,
            payment_provider:
                currentPaymentMethod === 'mobile_money'
                    ? mobileMoneyProvider
                    : currentPaymentMethod === 'card'
                      ? 'card'
                      : null,
            payment_ref:
                currentPaymentMethod === 'mobile_money'
                    ? document.getElementById('momoRef')?.value?.trim() || null
                    : currentPaymentMethod === 'card'
                      ? document.getElementById('cardRef')?.value?.trim() || null
                      : null,
            items: cart.map((i) => ({
                product_id: i.id,
                quantity: i.qty,
                unit_price: parseFloat(i.price),
            })),
        };
    }

    async function sendSyncHeartbeat() {
        if (!navigator.onLine) {
            try {
                await CashierAPI.syncHeartbeat({
                    store_id: store.id,
                    pending_count: await db.pending_sales.count(),
                    is_online: false,
                });
            } catch (e) {
                /* ignore */
            }
            return;
        }
        try {
            await CashierAPI.syncHeartbeat({
                store_id: store.id,
                pending_count: await db.pending_sales.count(),
                is_online: true,
            });
        } catch (e) {
            console.warn('sync heartbeat', e);
        }
    }

    async function syncPendingSales() {
        if (!navigator.onLine) {
            await sendSyncHeartbeat();
            return;
        }
        const pending = await db.pending_sales.toArray();
        let synced = 0;
        for (const row of pending) {
            const res = await CashierAPI.createSale(row.payload);
            if (res.status === 'success') {
                await db.pending_sales.delete(row.id);
                synced++;
            } else {
                try {
                    await CashierAPI.reportSyncFailure({
                        store_id: store.id,
                        local_uuid: row.local_uuid || row.payload?.receipt_no,
                        payload: row.payload,
                        error_message: res.message || 'Échec synchronisation vente',
                    });
                } catch (e) {
                    console.warn('reportSyncFailure', e);
                }
            }
        }
        await updatePendingBadge();
        await sendSyncHeartbeat();
        if (synced > 0) {
            toast(`${synced} vente(s) synchronisée(s)`, 'success');
            await syncCatalog(false);
        }
    }

    function buildLocalReceiptPayload(salePayload) {
        const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
        const customer = customers.find((c) => String(c.id) === String(selectedCustomerId));
        const tendered =
            currentPaymentMethod === 'cash' ? parseFloat(els.amountTendered?.value) || 0 : null;

        return {
            receipt_no: salePayload.receipt_no,
            created_at: new Date().toISOString(),
            store,
            cashier_name: user.name,
            customer_name: customer?.name || null,
            items: cart.map((i) => ({
                product_name: i.name,
                quantity: i.qty,
                unit_price: parseFloat(i.price),
                subtotal: i.price * i.qty,
            })),
            subtotal,
            tax: salePayload.tax,
            discount: salePayload.discount,
            total: salePayload.total,
            payment_method: salePayload.payment_method,
            payment_provider: salePayload.payment_provider,
            payment_ref: salePayload.payment_ref,
            tendered,
            change: tendered !== null ? Math.max(0, tendered - finalTotal) : null,
        };
    }

    /** Ouvre thermal-80mm.php et déclenche l’impression navigateur. */
    function openReceipt({ saleId = null, receiptNo = null, local = false, printPayload = null } = {}) {
        const template = config.receipt?.template || '../../receipts/templates/thermal-80mm.php';
        const url = new URL(template, window.location.href);

        if (saleId) {
            url.searchParams.set('id', String(saleId));
        } else if (receiptNo) {
            url.searchParams.set('receipt_no', receiptNo);
        } else if (!local) {
            return;
        }

        if (local && printPayload) {
            try {
                sessionStorage.setItem('pos_receipt_print', JSON.stringify(printPayload));
            } catch (e) {
                console.warn('Receipt sessionStorage', e);
            }
            url.searchParams.set('local', '1');
        }

        if (currentPaymentMethod === 'cash' && els.amountTendered) {
            const tendered = parseFloat(els.amountTendered.value) || 0;
            if (tendered > 0) {
                url.searchParams.set('tendered', String(tendered));
                url.searchParams.set('change', String(Math.max(0, tendered - finalTotal)));
            }
        }

        const win = window.open(
            url.toString(),
            'ReceiptPrint',
            'width=420,height=720,scrollbars=yes'
        );
        if (!win) {
            toast('Autorisez les fenêtres pop-up pour imprimer le reçu', 'warning');
        }
    }

    async function processPayment() {
        if (currentPaymentMethod === 'cash' && (parseFloat(els.amountTendered?.value) || 0) < finalTotal) {
            toast('Montant insuffisant', 'warning');
            return;
        }
        const payload = buildSalePayload();
        if (els.confirmPaymentBtn) els.confirmPaymentBtn.disabled = true;
        try {
            if (navigator.onLine) {
                const result = await CashierAPI.createSale(payload);
                if (result.status === 'success') {
                    toast('Vente enregistrée', 'success');
                    openReceipt({
                        saleId: result.sale_id,
                        receiptNo: payload.receipt_no,
                    });
                } else {
                    toast(result.message || 'Erreur', 'error');
                    return;
                }
            } else {
                await db.pending_sales.add({
                    local_uuid: crypto.randomUUID(),
                    payload,
                    timestamp: new Date().toISOString(),
                });
                toast('Vente en file (hors ligne)', 'warning');
                openReceipt({
                    local: true,
                    printPayload: buildLocalReceiptPayload(payload),
                });
                await updatePendingBadge();
            }
            cart = [];
            currentDiscount = 0;
            updateCart();
            closeCheckoutModal();
            await syncCatalog(false);
        } catch (e) {
            console.error(e);
            toast('Erreur encaissement', 'error');
        }
        if (els.confirmPaymentBtn) els.confirmPaymentBtn.disabled = false;
    }

    // --- Events ---
    els.searchInput?.addEventListener('input', (e) => loadLocalProducts(e.target.value));

    document.getElementById('clearSearchBtn')?.addEventListener('click', () => {
        if (els.searchInput) els.searchInput.value = '';
        loadLocalProducts();
    });

    document.getElementById('clearCartBtn')?.addEventListener('click', () => {
        if (cart.length && confirm('Vider le panier ?')) {
            cart = [];
            updateCart();
        }
    });

    document.getElementById('syncBtn')?.addEventListener('click', async () => {
        document.getElementById('syncBtn')?.classList.add('spinning');
        await syncCatalog();
        await syncPendingSales();
        document.getElementById('syncBtn')?.classList.remove('spinning');
    });

    els.checkoutBtn?.addEventListener('click', openCheckoutModal);

    document.querySelectorAll('.pos-cashier__mobile-nav-btn').forEach((btn) => {
        btn.addEventListener('click', () => setMobileView(btn.dataset.view || 'catalog'));
    });
    document.getElementById('mobileDockOpenCart')?.addEventListener('click', () => setMobileView('cart'));
    document.getElementById('mobileDockPay')?.addEventListener('click', openCheckoutModal);

    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(syncMobileLayoutClass, 120);
    });
    syncMobileLayoutClass();
    document.querySelector('.close-modal')?.addEventListener('click', closeCheckoutModal);
    document.querySelectorAll('[data-close-modal]').forEach((el) => {
        el.addEventListener('click', closeCheckoutModal);
    });

    document.querySelectorAll('.pos-cashier__pay-method').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('.pos-cashier__pay-method').forEach((b) => b.classList.remove('active'));
            e.currentTarget.classList.add('active');
            currentPaymentMethod = e.currentTarget.dataset.method;
            switchPaymentPanel(currentPaymentMethod);
            calculateChange();
        });
    });

    document.querySelectorAll('.pos-cashier__momo-chip').forEach((chip) => {
        chip.addEventListener('click', (e) => {
            document.querySelectorAll('.pos-cashier__momo-chip').forEach((c) => c.classList.remove('active'));
            e.currentTarget.classList.add('active');
            mobileMoneyProvider = e.currentTarget.dataset.provider || 'orange_money';
        });
    });

    els.amountTendered?.addEventListener('input', calculateChange);
    document.querySelectorAll('.pos-cashier__quick').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            if (!els.amountTendered) return;
            const q = e.currentTarget;
            els.amountTendered.value = q.classList.contains('exact-btn')
                ? String(finalTotal)
                : q.dataset.val || '';
            calculateChange();
        });
    });

    els.discountDisplay?.addEventListener('click', () => {
        const val = prompt(`Remise en ${currencySymbol} :`, String(currentDiscount));
        if (val === null) return;
        currentDiscount = Math.max(0, parseFloat(val) || 0);
        updateTotals();
    });

    els.customerSelect?.addEventListener('change', (e) => {
        selectedCustomerId = e.target.value;
    });

    els.confirmPaymentBtn?.addEventListener('click', processPayment);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F2') {
            e.preventDefault();
            els.searchInput?.focus();
        }
        if (e.key === 'F3') {
            e.preventDefault();
            BarcodeScanner.openCamera();
        }
        if (e.key === 'Escape') {
            if (document.getElementById('barcodeScannerModal')?.classList.contains('is-open')) {
                BarcodeScanner.closeCamera();
            } else {
                closeCheckoutModal();
            }
        }
        if (els.checkoutModal?.classList.contains('is-open') && e.key === 'Enter') {
            const tag = document.activeElement?.tagName?.toLowerCase();
            if (tag !== 'textarea') {
                e.preventDefault();
                processPayment();
            }
        }
        if (
            e.key === 'Enter' &&
            cart.length &&
            !els.checkoutModal?.classList.contains('is-open') &&
            document.activeElement !== els.searchInput
        ) {
            openCheckoutModal();
        }
        if (e.altKey && e.key.toLowerCase() === 'c') {
            cart = [];
            updateCart();
        }
    });

    window.addEventListener('online', () => {
        setOnlineStatus(true);
        syncPendingSales();
        syncCatalog(false);
        sendSyncHeartbeat();
    });
    window.addEventListener('offline', () => {
        setOnlineStatus(false);
        sendSyncHeartbeat();
    });

    renderCustomers();
    updateClock();
    setInterval(updateClock, 1000);
    setOnlineStatus(navigator.onLine);

    BarcodeScanner.init({
        db,
        apiScan: (code) => CashierAPI.scanBarcode(code),
        onAddToCart: (product) => addToCart(product),
        onNotFound: (code) => toast(`Produit introuvable (${code})`, 'error'),
        onOutOfStock: (product) => toast(`${product.name} — rupture de stock`, 'warning'),
        onSearchCleared: () => loadLocalProducts(''),
    });

    (async () => {
        await refreshBootstrap();
        await syncCatalog(false);
        await syncPendingSales();
        await updatePendingBadge();
        await sendSyncHeartbeat();
        setInterval(sendSyncHeartbeat, 120000);
        updateCart();
        toast(`Caisse prête — ${store.name}`, 'success');
    })();
});
