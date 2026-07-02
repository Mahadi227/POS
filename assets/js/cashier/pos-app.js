/**
 * RetailPOS — Logique caisse (DOM fourni par pos.php)
 */
document.addEventListener('DOMContentLoaded', () => {
    const config = window.POS_CONFIG || {};
    const posI18n = window.POS_I18N || {};
    const locale = config.locale || (config.lang === 'fr' ? 'fr-FR' : 'en-US');
    const settings = config.settings || { tax_rate: 0.18, tax_percent: 18 };
    const user = config.user || { id: null, name: 'Cashier' };
    const store = config.store || { id: 1, name: 'RetailPOS' };
    const currencySymbol = (config.settings && config.settings.currency_symbol) || (config.store && config.store.currency) || 'FCFA';
    const fmt = (n) => CashierAPI.formatCurrency(n);

    function t(key, ...args) {
        let str = posI18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function taxLabelText() {
        const n = Math.round(taxPercent);
        return t('tax_label', String(n)).replace(/%%/g, '%');
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function resolveImageUrl(url) {
        if (!url) return null;
        const value = String(url).trim();
        if (!value) return null;

        if (value.startsWith('data:')) return value;

        const normalized = value.replace(/\\/g, '/');
        const fileMatch = normalized.match(/(?:public\/)?uploads\/products\/([^/?#]+)/i);
        if (fileMatch) {
            const filename = decodeURIComponent(fileMatch[1]);
            const encoded = ['uploads', 'products', encodeURIComponent(filename)].join('/');
            try {
                return new URL(`../${encoded}`, window.location.href).href;
            } catch {
                return null;
            }
        }

        if (/^https?:\/\//i.test(value)) {
            try {
                const parsed = new URL(value);
                if (parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1') {
                    parsed.hostname = window.location.hostname;
                    parsed.protocol = window.location.protocol;
                    parsed.port = window.location.port;
                }
                return parsed.href;
            } catch {
                return value.replace(/ /g, '%20');
            }
        }

        let path = normalized.replace(/^(?:\.\.\/)+/, '').replace(/^\/+/, '');
        if (path.startsWith('public/')) path = path.slice(7);
        const filename = path.split('/').pop();
        if (!filename) return null;
        const encoded = ['uploads', 'products', encodeURIComponent(filename)].join('/');
        try {
            return new URL(`../${encoded}`, window.location.href).href;
        } catch {
            return null;
        }
    }

    function bindProductImageFallback(container) {
        container?.querySelectorAll('.pos-cashier__product-img img, .pos-cashier__line-thumb img').forEach((img) => {
            if (img.dataset.fallbackBound) return;
            img.dataset.fallbackBound = '1';
            img.loading = 'lazy';
            img.decoding = 'async';
            img.addEventListener('error', () => {
                const wrap = img.parentElement;
                if (!wrap) return;
                wrap.innerHTML = '<span class="material-icons-round">inventory_2</span>';
                wrap.classList.add('pos-cashier__product-img--placeholder');
            }, { once: true });
        });
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
    let discountMode = 'amount';
    let discountValue = 0;
    let currentPaymentMethod = 'cash';
    let mobileMoneyProvider = 'orange_money';
    let finalTotal = 0;
    let customers = [];
    let selectedCustomerId = '';
    let scrollToCartProductId = null;
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
        discountDisplay: document.getElementById('discountAmountDisplay'),
        discountInput: document.getElementById('discountInput'),
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
        shiftBadge: document.getElementById('shiftBadge'),
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
            els.liveClock.textContent = new Date().toLocaleTimeString(locale, {
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
        if (text) text.textContent = online ? t('online') : t('offline');
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

    function customersForStore(list) {
        const targetStoreId = store?.id != null ? String(store.id) : '';
        const seen = new Set();
        return (list || [])
            .filter((c) => {
                if (!c || c.id == null) return false;
                if (!targetStoreId) return true;
                if (c.store_id == null || c.store_id === '') return true;
                return String(c.store_id) === targetStoreId;
            })
            .filter((c) => {
                const key = String(c.id);
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            })
            .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), locale));
    }

    customers = customersForStore(config.customers || []);

    function renderCustomers() {
        if (!els.customerSelect) return;
        const current = els.customerSelect.value;
        els.customerSelect.innerHTML = `<option value="">${escapeHtml(t('walk_in_customer'))}</option>`;
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
            `<button type="button" class="pos-cashier__cat active" data-id="all">${escapeHtml(t('category_all'))}</button>`;
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
            els.productGrid.innerHTML = `<div class="pos-cashier__empty">${escapeHtml(t('no_products'))}</div>`;
            return;
        }
        const threshold = settings.low_stock_threshold ?? 5;
        products.forEach((p, index) => {
            const stock = parseInt(p.stock_quantity, 10) || 0;
            const outOfStock = stock <= 0;
            const lowStock = stock > 0 && stock <= (p.min_stock_level ?? threshold);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-cashier__product';
            if (lowStock) btn.classList.add('pos-cashier__product--low');
            btn.disabled = outOfStock;
            btn.style.setProperty('--product-delay', `${Math.min(index, 14) * 35}ms`);
            btn.setAttribute('aria-label', `${p.name} — ${fmt(p.price)}`);
            const imgUrl = resolveImageUrl(p.image_url);
            const imgWrapCls = imgUrl ? '' : ' pos-cashier__product-img--placeholder';
            const img = imgUrl
                ? `<img src="${escapeHtml(imgUrl)}" alt="">`
                : '<span class="material-icons-round">inventory_2</span>';
            const stockBadgeCls = outOfStock
                ? 'pos-cashier__product-stock-badge--out'
                : lowStock
                  ? 'pos-cashier__product-stock-badge--low'
                  : '';
            const stockIcon = outOfStock ? 'block' : 'inventory_2';
            const stockTitle = outOfStock
                ? escapeHtml(t('out_of_stock'))
                : escapeHtml(t('stock_label', String(stock)));
            const stockLabel = outOfStock
                ? escapeHtml(t('out_of_stock'))
                : escapeHtml(String(stock));
            const addOverlay = outOfStock
                ? `<span class="pos-cashier__product-out-badge">${escapeHtml(t('out_of_stock'))}</span>`
                : '<span class="pos-cashier__product-add" aria-hidden="true"><span class="material-icons-round">add_shopping_cart</span></span>';
            btn.innerHTML = `
                <div class="pos-cashier__product-img${imgWrapCls}">${img}</div>
                <div class="pos-cashier__product-body">
                    <div class="pos-cashier__product-name">${escapeHtml(p.name)}</div>
                    <div class="pos-cashier__product-footer">
                        <div class="pos-cashier__product-price">${fmt(p.price)}</div>
                        <span class="pos-cashier__product-stock-badge ${stockBadgeCls}" title="${stockTitle}">
                            <span class="material-icons-round" aria-hidden="true">${stockIcon}</span>
                            <span class="pos-cashier__product-stock-text">${stockLabel}</span>
                        </span>
                    </div>
                </div>
                ${addOverlay}`;
            if (!outOfStock) btn.addEventListener('click', () => addToCart(p));
            els.productGrid.appendChild(btn);
        });
        bindProductImageFallback(els.productGrid);
    }

    function filterStoreProducts(products) {
        if (!store?.id) return products;
        return products.filter((p) => String(p.store_id) === String(store.id));
    }

    function categoriesForStore(categories) {
        const targetStoreId = store?.id != null ? String(store.id) : '';
        const seen = new Set();
        return (categories || [])
            .filter((c) => {
                if (!c || c.id == null) return false;
                if (!targetStoreId) return true;
                if (c.store_id == null || c.store_id === '') return true;
                return String(c.store_id) === targetStoreId;
            })
            .filter((c) => {
                const key = String(c.id);
                if (seen.has(key)) return false;
                seen.add(key);
                return true;
            });
    }

    async function loadLocalProducts(query = '', categoryId = 'all') {
        let products = filterStoreProducts(await db.products.toArray());
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

    async function refreshCustomers() {
        if (!navigator.onLine) return;
        try {
            const res = await CashierAPI.getCustomers({ limit: 200 });
            if (res.status === 'success') {
                customers = customersForStore(res.data || []);
                renderCustomers();
            }
        } catch (e) {
            console.warn('refreshCustomers', e);
        }
    }

    async function syncCatalog(showToast = true) {
        if (!navigator.onLine) {
            await loadLocalProducts(els.searchInput?.value || '');
            if (showToast) toast(t('offline_catalog'), 'warning');
            return;
        }
        if (els.productGrid) {
            els.productGrid.innerHTML = `<div class="pos-cashier__loading">${escapeHtml(t('syncing'))}</div>`;
        }
        try {
            const [prodRes, catRes] = await Promise.all([
                CashierAPI.getProducts(),
                CashierAPI.getCategories(),
            ]);
            if (prodRes.status === 'success') {
                const storeProducts = filterStoreProducts(prodRes.data || []);
                await db.products.clear();
                if (storeProducts.length) await db.products.bulkAdd(storeProducts);
                await loadLocalProducts(els.searchInput?.value || '');
                if (showToast) toast(t('products_loaded', String(storeProducts.length)), 'success');
            } else throw new Error(prodRes.message);
            if (catRes.status === 'success') {
                renderCategories(categoriesForStore(catRes.data || []));
            }
            await refreshCustomers();
        } catch (e) {
            console.error(e);
            await loadLocalProducts(els.searchInput?.value || '');
            toast(t('sync_failed'), 'warning');
        }
    }

    async function refreshBootstrap() {
        if (!navigator.onLine) return;
        const res = await CashierAPI.getPosBootstrap();
        if (res.status === 'success' && res.data) {
            if (res.data.settings?.tax_rate != null) {
                taxRate = res.data.settings.tax_rate;
                taxPercent = res.data.settings.tax_percent ?? taxRate * 100;
                if (els.taxLabel) els.taxLabel.textContent = taxLabelText();
            }
            const storeEl = document.getElementById('storeName');
            if (storeEl && res.data.store?.name) storeEl.textContent = res.data.store.name;
            if (res.data.customers) {
                customers = customersForStore(res.data.customers);
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
                    lines === 1 ? t('cart_lines_one', String(units)) : t('cart_lines_many', String(lines), String(units));
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
        const modalRecap = document.getElementById('modalRecapItems');
        if (modalRecap) {
            modalRecap.innerHTML = `${units} ${escapeHtml(t('items_suffix'))}`;
        }
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
                let discountText = `- ${fmt(currentDiscount)}`;
                if (discountMode === 'percent' && discountValue > 0) {
                    discountText += ` (${discountValue}%)`;
                }
                set('modalDiscount', discountText);
            } else {
                discountRow.hidden = true;
            }
        }

        if (els.modalTotalDisplay) els.modalTotalDisplay.textContent = fmt(finalTotal);
        calculateChange();
    }

    function openCheckoutModal() {
        if (!cart.length) return;
        void openCheckoutFlow();
    }

    async function openCheckoutFlow() {
        if (typeof CashierShift !== 'undefined') {
            const check = await CashierShift.ensureOpenForSale();
            if (!check.ok) {
                if (check.message) toast(check.message, 'warning');
                else toast(t('shift_required'), 'warning');
                CashierShift.renderPosBadge(els.shiftBadge);
                return;
            }
            CashierShift.renderPosBadge(els.shiftBadge);
        }

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
            if (!silent) toast(t('invalid_qty'), 'warning');
            return false;
        }
        if (qty > max) {
            qty = max;
            if (!silent) toast(t('stock_max_item', String(max), item.name), 'warning');
        }

        if (qty === item.qty) return true;

        item.qty = qty;
        return true;
    }

    function scrollCartToProduct(productId) {
        if (!els.cartItems || productId == null) return;
        requestAnimationFrame(() => {
            const targetRow = els.cartItems.querySelector(
                `.pos-cashier__line[data-product-id="${String(productId).replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"]`
            );
            if (!targetRow) return;
            targetRow.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            targetRow.classList.remove('pos-cashier__line--highlight');
            void targetRow.offsetWidth;
            targetRow.classList.add('pos-cashier__line--highlight');
            window.setTimeout(() => targetRow.classList.remove('pos-cashier__line--highlight'), 1400);
        });
    }

    function addToCart(product) {
        const stock = parseInt(product.stock_quantity, 10) || 0;
        const existing = cart.find((item) => item.id === product.id);
        if (existing) {
            if (existing.qty >= stock) {
                toast(t('stock_max', String(stock)), 'warning');
                return { ok: false, reason: 'max_stock', message: t('stock_max', String(stock)) };
            }
            existing.qty++;
            scrollToCartProductId = product.id;
            updateCart();
            return { ok: true, action: 'incremented' };
        }
        if (stock < 1) {
            toast(t('product_out_of_stock'), 'warning');
            return { ok: false, reason: 'out_of_stock', message: t('product_out_of_stock') };
        }
        cart.push({ ...product, qty: 1 });
        scrollToCartProductId = product.id;
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
                    <p>${escapeHtml(t('cart_empty'))}</p>
                    <small>${escapeHtml(t('cart_empty_hint'))}</small>
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
            row.dataset.productId = String(item.id);
            row.innerHTML = `
                <div class="pos-cashier__line-thumb">${cartThumbHtml(item)}</div>
                <div class="pos-cashier__line-main">
                    <div class="pos-cashier__line-top">
                        <h4 class="pos-cashier__line-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</h4>
                        <strong class="pos-cashier__line-total" data-line-total>${fmt(lineTotal)}</strong>
                    </div>
                    <div class="pos-cashier__line-meta">
                        <span class="pos-cashier__line-unit">${escapeHtml(fmt(item.price))}</span>
                        ${sku ? `<span class="pos-cashier__line-sku">${escapeHtml(sku)}</span>` : ''}
                        <span class="pos-cashier__line-stock">${escapeHtml(t('stock_max', String(max)))}</span>
                    </div>
                    <div class="pos-cashier__line-actions">
                        <div class="pos-cashier__line-qty">
                            <button type="button" class="pos-cashier__qty-btn btn-minus" aria-label="${escapeHtml(t('decrease'))}">−</button>
                            <input
                                type="number"
                                class="pos-cashier__qty-input"
                                value="${item.qty}"
                                min="1"
                                max="${max}"
                                step="1"
                                inputmode="numeric"
                                aria-label="${escapeHtml(t('qty_aria'))}"
                                title="${escapeHtml(t('qty_title', String(max)))}"
                            />
                            <button type="button" class="pos-cashier__qty-btn btn-plus" aria-label="${escapeHtml(t('increase'))}">+</button>
                        </div>
                        <button type="button" class="pos-cashier__line-del btn-delete" aria-label="${escapeHtml(t('remove_item', item.name))}">
                            <span class="material-icons-round">delete_outline</span>
                        </button>
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
        bindProductImageFallback(els.cartItems);

        if (scrollToCartProductId != null) {
            const productId = scrollToCartProductId;
            scrollToCartProductId = null;
            scrollCartToProduct(productId);
        }

        updateTotals();
    }

    function computeDiscountAmount(subtotal, tax) {
        const maxDiscount = subtotal + tax;
        if (discountValue <= 0) return 0;
        if (discountMode === 'percent') {
            const pct = Math.min(100, discountValue);
            return Math.min(maxDiscount, subtotal * (pct / 100));
        }
        return Math.min(maxDiscount, discountValue);
    }

    function syncDiscountModeUi() {
        document.querySelectorAll('.pos-cashier__discount-mode-btn').forEach((btn) => {
            btn.classList.toggle('active', btn.dataset.mode === discountMode);
        });
        if (!els.discountInput) return;
        if (discountMode === 'percent') {
            els.discountInput.max = '100';
            els.discountInput.step = '1';
        } else {
            els.discountInput.removeAttribute('max');
            els.discountInput.step = '0.01';
        }
    }

    function resetDiscount() {
        discountValue = 0;
        discountMode = 'amount';
        if (els.discountInput) els.discountInput.value = '0';
        syncDiscountModeUi();
    }

    function applyDiscountInput() {
        if (!els.discountInput) return;
        let val = parseFloat(els.discountInput.value) || 0;
        if (discountMode === 'percent') {
            val = Math.min(100, Math.max(0, val));
        } else {
            val = Math.max(0, val);
        }
        discountValue = val;
        els.discountInput.value = String(val);
        updateTotals();
    }

    function updateTotals() {
        const subtotal = cart.reduce((s, i) => s + i.price * i.qty, 0);
        const tax = subtotal * taxRate;
        currentDiscount = computeDiscountAmount(subtotal, tax);
        finalTotal = Math.max(0, subtotal + tax - currentDiscount);
        if (els.subtotalDisplay) els.subtotalDisplay.textContent = fmt(subtotal);
        if (els.taxDisplay) els.taxDisplay.textContent = fmt(tax);
        if (els.discountDisplay) {
            let discountText = `- ${fmt(currentDiscount)}`;
            if (discountMode === 'percent' && discountValue > 0) {
                discountText += ` (${discountValue}%)`;
            }
            els.discountDisplay.textContent = discountText;
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
            toast(t('sales_synced', String(synced)), 'success');
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
            toast(t('popup_blocked'), 'warning');
        }
    }

    async function processPayment() {
        if (typeof CashierShift !== 'undefined' && CashierShift.isModuleReady() && !CashierShift.isOpen()) {
            toast(t('shift_required'), 'warning');
            return;
        }
        if (currentPaymentMethod === 'cash' && (parseFloat(els.amountTendered?.value) || 0) < finalTotal) {
            toast(t('insufficient_amount'), 'warning');
            return;
        }
        const payload = buildSalePayload();
        if (els.confirmPaymentBtn) els.confirmPaymentBtn.disabled = true;
        try {
            if (navigator.onLine) {
                const result = await CashierAPI.createSale(payload);
                if (result.status === 'success') {
                    toast(t('sale_recorded'), 'success');
                    openReceipt({
                        saleId: result.sale_id,
                        receiptNo: payload.receipt_no,
                    });
                } else {
                    toast(result.message || t('error'), 'error');
                    return;
                }
            } else {
                await db.pending_sales.add({
                    local_uuid: crypto.randomUUID(),
                    payload,
                    timestamp: new Date().toISOString(),
                });
                toast(t('sale_queued_offline'), 'warning');
                openReceipt({
                    local: true,
                    printPayload: buildLocalReceiptPayload(payload),
                });
                await updatePendingBadge();
            }
            cart = [];
            resetDiscount();
            updateCart();
            closeCheckoutModal();
            await syncCatalog(false);
            if (typeof CashierShift !== 'undefined' && navigator.onLine) {
                await CashierShift.load();
                CashierShift.renderPosBadge(els.shiftBadge);
            }
        } catch (e) {
            console.error(e);
            toast(t('checkout_error'), 'error');
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
        if (cart.length && confirm(t('clear_cart_confirm'))) {
            cart = [];
            resetDiscount();
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

    document.querySelectorAll('.pos-cashier__discount-mode-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const mode = btn.dataset.mode === 'percent' ? 'percent' : 'amount';
            if (mode === discountMode) return;
            discountMode = mode;
            discountValue = 0;
            if (els.discountInput) els.discountInput.value = '0';
            syncDiscountModeUi();
            updateTotals();
        });
    });

    els.discountInput?.addEventListener('change', applyDiscountInput);
    els.discountInput?.addEventListener('blur', applyDiscountInput);
    els.discountInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyDiscountInput();
            els.discountInput?.blur();
        }
    });

    syncDiscountModeUi();

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
        onNotFound: (code) => toast(t('product_not_found', code), 'error'),
        onOutOfStock: (product) => toast(t('product_out_of_stock_named', product.name), 'warning'),
        onSearchCleared: () => loadLocalProducts(''),
    });

    (async () => {
        await refreshBootstrap();
        if (typeof CashierShift !== 'undefined') {
            await CashierShift.load();
            CashierShift.renderPosBadge(els.shiftBadge);
            CashierShift.onChange(() => CashierShift.renderPosBadge(els.shiftBadge));
        }
        await syncCatalog(false);
        await syncPendingSales();
        await updatePendingBadge();
        await sendSyncHeartbeat();
        setInterval(sendSyncHeartbeat, 60000);
        updateCart();
        toast(t('pos_ready', store.name), 'success');
    })();
});
