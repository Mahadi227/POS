/**
 * Admin inventory — dynamic loading, filters, pagination, i18n
 */
(() => {
    const CFG = window.INVENTORY_CONFIG || { userId: 0, storeId: 1 };
    const i18n = window.INVENTORY_I18N || {};
    const locale = CFG.locale || (CFG.lang === 'fr' ? 'fr-FR' : 'en-US');
    const sortLocale = CFG.lang === 'fr' ? 'fr' : 'en';

    const PAGE_SIZE = 15;

    const $ = (id) => document.getElementById(id);
    const productTableBody = $('productTableBody');
    const modalOverlay = $('productModalOverlay');
    const productForm = $('productForm');
    const errorBanner = $('inventoryError');
    const toastEl = $('invToast');

    let allProducts = [];
    let allCategories = [];
    let stockFilter = 'all';
    let currentPage = 1;
    let searchDebounce = null;
    let html5QrcodeScanner = null;
    let barcodeBuffer = '';
    let barcodeTimeout = null;

    function t(key, ...args) {
        let str = i18n[key] || key;
        args.forEach((val) => {
            str = str.replace('%s', val);
        });
        return str;
    }

    function showError(msg) {
        if (!errorBanner) return;
        const text = errorBanner.querySelector('.ad-error-text');
        if (text) text.textContent = msg;
        errorBanner.classList.add('is-visible');
    }

    function hideError() {
        errorBanner?.classList.remove('is-visible');
    }

    function toast(message, type = 'success', html = false) {
        if (!toastEl) return;
        if (html) {
            toastEl.innerHTML = message;
        } else {
            toastEl.textContent = message;
        }
        toastEl.className = `inv-toast show ${type}`;
        clearTimeout(toastEl._t);
        toastEl._t = setTimeout(() => toastEl.classList.remove('show'), 4800);
    }

    const ADJUST_HIGHLIGHT_KEY = 'pos_inventory_adjust_highlights';

    function storeAdjustHighlight(result, productName) {
        let items = [];
        try {
            items = JSON.parse(sessionStorage.getItem(ADJUST_HIGHLIGHT_KEY) || '[]');
        } catch {
            items = [];
        }
        items.unshift({
            logId: result.log_id,
            ledgerId: result.ledger_id,
            productId: result.product_id,
            productName,
            changeAmount: result.change_amount,
            at: Date.now(),
        });
        sessionStorage.setItem(ADJUST_HIGHLIGHT_KEY, JSON.stringify(items.slice(0, 30)));
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value ?? '';
        return div.innerHTML;
    }

    function minLevel(p) {
        const m = parseInt(p.min_stock_level, 10);
        return Number.isFinite(m) ? m : 5;
    }

    function stockState(p) {
        const qty = parseInt(p.stock_quantity, 10) || 0;
        if (qty <= 0) return 'out';
        if (qty <= minLevel(p)) return 'low';
        return 'ok';
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
        const base = (CFG.appUrl || '').replace(/\/$/, '').replace(/ /g, '%20');
        if (base) {
            if (base.endsWith('/public')) {
                return `${base}/${encodedPath}`;
            }
            return `${base}/public/${encodedPath}`;
        }
        return `../${encodedPath}`;
    }

    let pendingImageClear = false;

    function updateImagePreview(url, { isNewFile = false } = {}) {
        const preview = $('productImagePreview');
        const clearBtn = $('clearProductImageBtn');
        if (!preview) return;

        const imgUrl = isNewFile ? url : resolveImageUrl(url);
        preview.innerHTML = imgUrl
            ? `<img src="${escapeAttr(imgUrl)}" alt="">`
            : `<span class="material-icons-round">image</span><span class="inv-image-preview-hint">${t('no_image')}</span>`;

        if (clearBtn) {
            clearBtn.hidden = !imgUrl;
        }
        pendingImageClear = false;
    }

    function resetImageField() {
        const input = $('productImage');
        if (input) input.value = '';
        pendingImageClear = false;
        updateImagePreview(null);
    }

    function setStatsLoading(loading) {
        document.querySelectorAll('.inv-stat').forEach((el) => {
            el.classList.toggle('is-loading', loading);
        });
    }

    function updateStatsUI(stats) {
        setStatsLoading(false);
        $('stat-total-val').textContent = stats.total_products;
        $('stat-low-val').textContent = stats.low_stock;
        $('stat-out-val').textContent = stats.out_of_stock;
        $('stat-value-val').textContent = AdminAPI.formatCurrency(stats.inventory_value);
        $('stat-categories-text').textContent = t('categories_count', stats.categories_count);
        $('stat-units-text').textContent = t('units_in_stock', stats.total_units.toLocaleString(locale));

        const badge = $('sidebar-low-stock-badge');
        if (badge) {
            const n = stats.low_stock + stats.out_of_stock;
            badge.textContent = n;
            badge.classList.toggle('hidden', n === 0);
        }
    }

    function updateChipCounts() {
        const counts = { all: 0, ok: 0, low: 0, out: 0 };
        allProducts.forEach((p) => {
            counts.all++;
            counts[stockState(p)]++;
        });
        $('chip-all').textContent = counts.all;
        $('chip-ok').textContent = counts.ok;
        $('chip-low').textContent = counts.low;
        $('chip-out').textContent = counts.out;
    }

    function getFilteredProducts() {
        const query = ($('searchInput')?.value || '').trim().toLowerCase();
        const categoryId = $('categoryFilter')?.value || '';
        const sort = $('sortSelect')?.value || 'name_asc';

        let list = allProducts.filter((p) => {
            const matchesSearch = !query ||
                String(p.name || '').toLowerCase().includes(query) ||
                String(p.sku || '').toLowerCase().includes(query) ||
                String(p.barcode || '').toLowerCase().includes(query);
            const matchesCategory = !categoryId || String(p.category_id) === String(categoryId);
            const matchesStock = stockFilter === 'all' || stockState(p) === stockFilter;
            return matchesSearch && matchesCategory && matchesStock;
        });

        list.sort((a, b) => {
            switch (sort) {
                case 'name_desc':
                    return String(b.name).localeCompare(String(a.name), sortLocale);
                case 'stock_asc':
                    return (parseInt(a.stock_quantity, 10) || 0) - (parseInt(b.stock_quantity, 10) || 0);
                case 'stock_desc':
                    return (parseInt(b.stock_quantity, 10) || 0) - (parseInt(a.stock_quantity, 10) || 0);
                case 'price_desc':
                    return (parseFloat(b.price) || 0) - (parseFloat(a.price) || 0);
                default:
                    return String(a.name).localeCompare(String(b.name), sortLocale);
            }
        });
        return list;
    }

    function updateDateHeader() {
        const header = $('inv-date');
        if (!header) return;
        header.textContent = new Date().toLocaleDateString(locale, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }

    function updateLastUpdated() {
        const el = $('lastUpdated');
        if (!el) return;
        const time = new Date().toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        el.textContent = t('last_updated', time);
    }

    function renderProducts() {
        const filtered = getFilteredProducts();
        const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * PAGE_SIZE;
        const pageItems = filtered.slice(start, start + PAGE_SIZE);

        $('tableSummary').textContent = filtered.length === 0
            ? t('no_products')
            : t('table_summary', filtered.length, currentPage, totalPages);
        $('pageInfo').textContent = `${currentPage} / ${totalPages}`;
        $('pagePrev').disabled = currentPage <= 1;
        $('pageNext').disabled = currentPage >= totalPages;

        productTableBody.innerHTML = '';

        if (pageItems.length === 0) {
            productTableBody.innerHTML =
                `<tr><td colspan="7" class="ad-empty-row">${t('no_products_found')}</td></tr>`;
            return;
        }

        const lbl = {
            image: t('col_image'),
            product: t('col_product'),
            sku: t('col_sku_barcode'),
            category: t('col_category'),
            price: t('col_price'),
            stock: t('stock'),
            actions: t('col_actions'),
        };

        pageItems.forEach((product) => {
            const state = stockState(product);
            const qty = parseInt(product.stock_quantity, 10) || 0;
            const stockHtml = state === 'out'
                ? `<span class="inv-stock-badge out"><span class="material-icons-round" style="font-size:14px;">block</span>${qty}</span>`
                : state === 'low'
                    ? `<span class="inv-stock-badge low"><span class="material-icons-round" style="font-size:14px;">warning</span>${qty}</span>`
                    : `<span class="inv-stock-badge ok">${qty}</span>`;

            const imgUrl = resolveImageUrl(product.image_url);
            const imageContent = imgUrl
                ? `<img src="${escapeAttr(imgUrl)}" alt="" loading="lazy" onerror="this.style.display='none';this.nextElementSibling?.classList.remove('hidden');">`
                  + `<span class="material-icons-round hidden" style="color:var(--text-muted);">broken_image</span>`
                : '<span class="material-icons-round" style="color:var(--text-muted);">image</span>';

            const barcodeBlock = product.barcode
                ? `<svg class="barcode-render" data-value="${escapeAttr(product.barcode)}"></svg>`
                : '<span style="color:var(--text-muted);font-size:0.8rem;">—</span>';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="${escapeAttr(lbl.image)}"><div class="inv-product-img">${imageContent}</div></td>
                <td data-label="${escapeAttr(lbl.product)}"><strong>${escapeHtml(product.name)}</strong></td>
                <td data-label="${escapeAttr(lbl.sku)}">
                    <div style="font-size:0.82rem;color:var(--text-muted);">${escapeHtml(product.sku || '—')}</div>
                    ${barcodeBlock}
                </td>
                <td data-label="${escapeAttr(lbl.category)}">${escapeHtml(product.category_name || t('uncategorized'))}</td>
                <td data-label="${escapeAttr(lbl.price)}" style="font-weight:600;">${AdminAPI.formatCurrency(product.price)}</td>
                <td data-label="${escapeAttr(lbl.stock)}">${stockHtml}</td>
                <td data-label="${escapeAttr(lbl.actions)}">
                    <div class="inv-row-actions">
                        <button type="button" class="icon-btn adjust-btn" data-id="${product.id}" title="${t('adjust_stock')}">
                            <span class="material-icons-round" style="font-size:18px;">add_box</span>
                        </button>
                        <button type="button" class="icon-btn edit-btn" data-id="${product.id}" title="${t('edit')}">
                            <span class="material-icons-round" style="font-size:18px;">edit</span>
                        </button>
                        <button type="button" class="icon-btn print-btn" data-barcode="${escapeAttr(product.barcode || '')}" data-name="${escapeAttr(product.name)}" title="${t('print')}">
                            <span class="material-icons-round" style="font-size:18px;color:var(--primary);">print</span>
                        </button>
                        <button type="button" class="icon-btn delete-btn" data-id="${product.id}" title="${t('delete')}" style="color:var(--danger);">
                            <span class="material-icons-round" style="font-size:18px;">delete</span>
                        </button>
                    </div>
                </td>
            `;
            productTableBody.appendChild(tr);
        });

        document.querySelectorAll('.barcode-render').forEach((svg) => {
            try {
                JsBarcode(svg, svg.getAttribute('data-value'), {
                    format: 'CODE128',
                    width: 1,
                    height: 24,
                    displayValue: true,
                    fontSize: 10,
                    margin: 0,
                });
            } catch (e) { /* invalid barcode */ }
        });

        bindRowActions();
    }

    function bindRowActions() {
        document.querySelectorAll('.edit-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                editProduct(btn.getAttribute('data-id'));
            });
        });
        document.querySelectorAll('.delete-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteProduct(btn.getAttribute('data-id'));
            });
        });
        document.querySelectorAll('.print-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                printBarcodeLabel(btn.getAttribute('data-barcode'), btn.getAttribute('data-name'));
            });
        });
        document.querySelectorAll('.adjust-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                openQuickAdjust(btn.getAttribute('data-id'));
            });
        });
    }

    function escapeAttr(s) {
        return String(s ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    async function loadStats() {
        setStatsLoading(true);
        try {
            const result = await AdminAPI.getInventoryStats();
            if (result.status === 'success') {
                updateStatsUI(result.data);
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadCategories() {
        const result = await AdminAPI.getInventoryCategories();
        if (result.status !== 'success') return;
        allCategories = result.data || [];
        const select = $('productCategory');
        const filterSelect = $('categoryFilter');
        if (select) {
            select.innerHTML = `<option value="">${t('select_category')}</option>`;
            allCategories.forEach((cat) => {
                const o = document.createElement('option');
                o.value = cat.id;
                o.textContent = cat.name;
                select.appendChild(o);
            });
        }
        if (filterSelect) {
            const current = filterSelect.value;
            filterSelect.innerHTML = `<option value="">${t('all_categories')}</option>`;
            allCategories.forEach((cat) => {
                const o = document.createElement('option');
                o.value = cat.id;
                o.textContent = cat.name;
                filterSelect.appendChild(o);
            });
            filterSelect.value = current;
        }
    }

    async function loadProducts() {
        productTableBody.innerHTML =
            `<tr><td colspan="7" class="ad-empty-row">${t('loading')}</td></tr>`;
        try {
            const result = await AdminAPI.getInventoryProducts();
            hideError();
            if (result.status === 'success') {
                allProducts = result.data || [];
                updateChipCounts();
                currentPage = 1;
                renderProducts();
            } else {
                showError(result.message || result.error || t('load_error'));
                productTableBody.innerHTML =
                    `<tr><td colspan="7" class="ad-empty-row" style="color:var(--danger);">${escapeHtml(result.message || t('error'))}</td></tr>`;
            }
        } catch (e) {
            console.error(e);
            showError(t('connection_error'));
            productTableBody.innerHTML =
                `<tr><td colspan="7" class="ad-empty-row">${t('network_error')}</td></tr>`;
        }
    }

    async function refreshAll() {
        const btn = $('refreshInventory');
        btn?.classList.add('spinning');
        await Promise.all([loadStats(), loadCategories(), loadProducts()]);
        btn?.classList.remove('spinning');
        updateLastUpdated();
        toast(t('refreshed'));
    }

    function openModal(id) {
        $(id)?.classList.add('active');
    }

    function closeModal(id) {
        $(id)?.classList.remove('active');
    }

    function editProduct(id) {
        const product = allProducts.find((p) => String(p.id) === String(id));
        if (!product) return;
        $('productId').value = product.id;
        $('productName').value = product.name;
        $('productSku').value = product.sku;
        $('productBarcode').value = product.barcode || '';
        $('productCategory').value = product.category_id || '';
        $('productPrice').value = product.price;
        $('productCost').value = product.cost ?? '';
        $('productStock').value = product.stock_quantity;
        $('productMinStock').value = product.min_stock_level ?? 5;
        $('productUnit').value = product.unit || 'piece';
        $('productExpiry').value = product.expiry_date || '';
        $('modalTitle').textContent = t('modal_edit_product');
        resetImageField();
        updateImagePreview(product.image_url);
        const stockInput = $('productStock');
        if (stockInput) stockInput.disabled = true;
        openModal('productModalOverlay');
    }

    function openQuickAdjust(id) {
        const product = allProducts.find((p) => String(p.id) === String(id));
        if (!product) return;
        $('qaProductId').value = product.id;
        $('qaProductName').textContent = product.name;
        $('qaProductSku').textContent = product.sku;
        $('qaCurrentStock').textContent = product.stock_quantity;
        $('qaAddStock').value = 1;
        openModal('quickAdjustModalOverlay');
    }

    async function deleteProduct(id) {
        if (!confirm(t('delete_confirm'))) return;
        const result = await AdminAPI.deleteProduct(id);
        if (result.status === 'success') {
            toast(t('product_deleted'));
            await refreshAll();
        } else {
            toast(result.error || result.message || t('error'), 'error');
        }
    }

    function playBeep() {
        const beep = $('scan-beep');
        if (beep) {
            beep.currentTime = 0;
            beep.play().catch(() => {});
        }
    }

    async function handleBarcodeScan(barcode) {
        if (!barcode?.trim()) return;
        playBeep();
        if (html5QrcodeScanner) {
            try { await html5QrcodeScanner.clear(); } catch (e) { /* */ }
            html5QrcodeScanner = null;
        }
        closeModal('scannerModalOverlay');

        const result = await AdminAPI.scanBarcode(barcode.trim());
        if (result.status === 'success') {
            openQuickAdjust(result.data.id);
        } else {
            productForm.reset();
            $('productId').value = '';
            $('productBarcode').value = barcode.trim();
            $('productStock').disabled = false;
            $('modalTitle').textContent = t('modal_new_scanned');
            openModal('productModalOverlay');
        }
    }

    function printBarcodeLabel(barcode, name) {
        if (!barcode) {
            toast(t('no_barcode'), 'error');
            return;
        }
        const w = window.open('', '_blank', 'width=400,height=300');
        w.document.write(`
            <html><head><title>${escapeHtml(t('label_title'))}</title>
            <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"><\/script>
            <style>body{font-family:sans-serif;text-align:center;padding:20px;}h3{font-size:14px;}</style>
            </head><body><h3>${escapeHtml(name)}</h3><svg id="bc"></svg>
            <script>JsBarcode("#bc","${barcode.replace(/"/g, '\\"')}",{format:"CODE128",width:2,height:60,displayValue:true});
            setTimeout(()=>{window.print();window.close();},500);<\/script></body></html>`);
        w.document.close();
    }

    function initEvents() {
        $('addProductBtn')?.addEventListener('click', () => {
            productForm.reset();
            $('productId').value = '';
            $('productStock').disabled = false;
            $('modalTitle').textContent = t('modal_add_product');
            resetImageField();
            openModal('productModalOverlay');
        });

        $('closeModalBtn')?.addEventListener('click', () => closeModal('productModalOverlay'));
        $('closeCategoryModalBtn')?.addEventListener('click', () => closeModal('categoryModalOverlay'));
        $('closeScannerBtn')?.addEventListener('click', async () => {
            if (html5QrcodeScanner) {
                try { await html5QrcodeScanner.clear(); } catch (e) { /* */ }
                html5QrcodeScanner = null;
            }
            closeModal('scannerModalOverlay');
        });
        $('closeQuickAdjustBtn')?.addEventListener('click', () => closeModal('quickAdjustModalOverlay'));

        $('generateBarcodeBtn')?.addEventListener('click', () => {
            $('productBarcode').value = String(Math.floor(100000000000 + Math.random() * 900000000000));
        });

        $('productImage')?.addEventListener('change', (e) => {
            const file = e.target.files?.[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                toast(t('image_required'), 'error');
                e.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onloadend = () => updateImagePreview(reader.result, { isNewFile: true });
            reader.readAsDataURL(file);
        });

        $('clearProductImageBtn')?.addEventListener('click', () => {
            const input = $('productImage');
            if (input) input.value = '';
            pendingImageClear = true;
            updateImagePreview(null);
        });

        $('addCategoryBtn')?.addEventListener('click', () => {
            $('categoryForm').reset();
            openModal('categoryModalOverlay');
        });

        $('importBtn')?.addEventListener('click', () => toast(t('import_csv_soon')));

        document.querySelectorAll('.inv-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                document.querySelectorAll('.inv-chip').forEach((c) => c.classList.remove('active'));
                chip.classList.add('active');
                stockFilter = chip.dataset.stock || 'all';
                currentPage = 1;
                renderProducts();
            });
        });

        $('searchInput')?.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                currentPage = 1;
                renderProducts();
            }, 280);
        });

        $('categoryFilter')?.addEventListener('change', () => {
            currentPage = 1;
            renderProducts();
        });
        $('sortSelect')?.addEventListener('change', renderProducts);

        $('pagePrev')?.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderProducts();
            }
        });
        $('pageNext')?.addEventListener('click', () => {
            currentPage++;
            renderProducts();
        });

        $('refreshInventory')?.addEventListener('click', refreshAll);

        document.addEventListener('store-switched', () => refreshAll());

        productForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const productId = $('productId').value;
            const imageInput = $('productImage');
            let imageBase64 = null;
            if (imageInput?.files?.[0]) {
                const file = imageInput.files[0];
                imageBase64 = await new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result);
                    reader.readAsDataURL(file);
                });
            }

            const payload = {
                name: $('productName').value.trim(),
                sku: $('productSku').value.trim(),
                barcode: $('productBarcode').value.trim(),
                category_id: $('productCategory').value,
                price: $('productPrice').value,
                cost: $('productCost').value,
                stock_quantity: $('productStock').value,
                min_stock_level: $('productMinStock').value,
                unit: $('productUnit').value,
                expiry_date: $('productExpiry').value,
                store_id: CFG.storeId,
            };
            if (imageBase64) payload.image = imageBase64;
            if (pendingImageClear && productId) payload.remove_image = true;

            const result = productId
                ? await AdminAPI.updateProduct(productId, payload)
                : await AdminAPI.createProduct(payload);

            if (result.status === 'success') {
                closeModal('productModalOverlay');
                toast(productId ? t('product_updated') : t('product_created'));
                await refreshAll();
            } else {
                toast(result.error || result.message || t('error'), 'error');
            }
        });

        $('categoryForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const result = await AdminAPI.createCategory({
                name: $('categoryName').value.trim(),
                description: $('categoryDescription').value.trim(),
            });
            if (result.status === 'success') {
                closeModal('categoryModalOverlay');
                await loadCategories();
                $('productCategory').value = result.id;
                toast(t('category_created'));
            } else {
                toast(result.error || t('error'), 'error');
            }
        });

        $('quickAdjustForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const productId = $('qaProductId').value;
            const productName = $('qaProductName')?.textContent || '';
            const result = await AdminAPI.adjustStock({
                product_id: productId,
                change_amount: $('qaAddStock').value,
                reason: 'restock',
                user_id: CFG.userId,
                store_id: CFG.storeId,
            });
            if (result.status === 'success') {
                closeModal('quickAdjustModalOverlay');
                storeAdjustHighlight(result, productName);
                const historyUrl = `inventory_history.php?highlight_log=${encodeURIComponent(result.log_id || '')}&highlight_product=${encodeURIComponent(productId)}`;
                toast(
                    `${escapeHtml(t('stock_updated'))} <a href="${historyUrl}" class="inv-toast-link">${escapeHtml(t('view_in_history'))}</a>`,
                    'success',
                    true,
                );
                await refreshAll();
            } else {
                toast(result.error || t('error'), 'error');
            }
        });

        $('scanBarcodeBtn')?.addEventListener('click', () => {
            openModal('scannerModalOverlay');
            if (typeof Html5QrcodeScanner === 'undefined') {
                toast(t('scanner_not_loaded'), 'error');
                return;
            }
            html5QrcodeScanner = new Html5QrcodeScanner('qr-reader', { fps: 10, qrbox: { width: 250, height: 250 } }, false);
            html5QrcodeScanner.render(
                (text) => handleBarcodeScan(text),
                () => {},
            );
        });

        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
            if (e.key === 'Enter') {
                if (barcodeBuffer.length > 3) handleBarcodeScan(barcodeBuffer);
                barcodeBuffer = '';
                clearTimeout(barcodeTimeout);
            } else if (e.key.length === 1) {
                barcodeBuffer += e.key;
                clearTimeout(barcodeTimeout);
                barcodeTimeout = setTimeout(() => { barcodeBuffer = ''; }, 100);
            }
        });

        [productModalOverlay, categoryModalOverlay, scannerModalOverlay, quickAdjustModalOverlay].forEach((el) => {
            el?.addEventListener('click', (ev) => {
                if (ev.target === el) {
                    if (el.id === 'scannerModalOverlay' && html5QrcodeScanner) {
                        html5QrcodeScanner.clear().catch(() => {});
                        html5QrcodeScanner = null;
                    }
                    el.classList.remove('active');
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        updateDateHeader();
        initEvents();
        await refreshAll();
        const editParam = new URLSearchParams(window.location.search).get('edit');
        if (editParam) editProduct(editParam);
    });
})();
