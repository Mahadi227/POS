document.addEventListener('DOMContentLoaded', () => {

    // --- Inject SPA UI ---
    const appContainer = document.getElementById('app');
    if (appContainer) {
        appContainer.innerHTML = `
            <!-- Animated Glassmorphism Background -->
            <div class="bg-blobs">
                <div class="blob blob-1"></div>
                <div class="blob blob-2"></div>
                <div class="blob blob-3"></div>
            </div>

            <div class="pos-container">
                
                <!-- Left Side: Product Grid & Search -->
                <div class="pos-catalog glass-panel">
                    <header class="catalog-header" style="display:flex; align-items:center;">
                        <a href="dashboard.php" class="back-to-dash" title="Retour au tableau de bord">
                            <span class="material-icons-round">arrow_back</span>
                        </a>
                        <div class="search-bar" style="flex:1;">
                            <span class="material-icons-round">search</span>
                            <input type="text" id="searchInput" placeholder="Scanner un code-barre ou chercher un produit (F2)..." autocomplete="off" autofocus>
                            <button class="icon-btn" id="clearSearchBtn"><span class="material-icons-round">close</span></button>
                        </div>
                    </header>
                    <div class="categories-wrapper" style="padding: 0 20px 10px;">
                        <button class="category-chip active" data-id="all">Tout</button>
                        <!-- Categories will be injected here -->
                    </div>
                    
                    <div class="product-grid" id="productGrid">
                        <!-- Product cards injected here -->
                        <div class="loading-state">Chargement du catalogue...</div>
                    </div>
                </div>

                <!-- Right Side: Cart & Checkout -->
                <div class="pos-cart-section glass-panel">
                    <header class="cart-header">
                        <div class="customer-selection">
                            <span class="material-icons-round">person_outline</span>
                            <select id="customerSelect" class="minimal-select">
                                <option value="">Client Standard (Passager)</option>
                                <!-- Customers injected here -->
                            </select>
                        </div>
                        <div class="cart-actions">
                            <button class="icon-btn" id="clearCartBtn" title="Vider le panier (Alt+C)"><span class="material-icons-round">delete_sweep</span></button>
                            <button class="icon-btn" id="syncBtn" title="Synchroniser"><span class="material-icons-round">sync</span></button>
                        </div>
                    </header>

                    <div class="cart-items-container" id="cartItems">
                        <!-- Cart items injected here -->
                        <div class="empty-cart-state">
                            <span class="material-icons-round">shopping_cart</span>
                            <p>Le panier est vide</p>
                        </div>
                    </div>

                    <div class="cart-summary">
                        <div class="summary-row">
                            <span>Sous-total</span>
                            <span id="subtotalDisplay">0 FCFA</span>
                        </div>
                        <div class="summary-row">
                            <span>Taxe (18%)</span>
                            <span id="taxDisplay">0 FCFA</span>
                        </div>
                        <div class="summary-row">
                            <span>Remise</span>
                            <span class="discount-display" id="discountDisplay" style="cursor:pointer; color:var(--primary);">- 0 FCFA</span>
                        </div>
                        <div class="summary-row total-row">
                            <span>Total</span>
                            <span id="totalDisplay">0 FCFA</span>
                        </div>
                        
                        <button class="btn-checkout" id="checkoutBtn" disabled>
                            <span class="material-icons-round">payments</span>
                            <span>Encaisser (Entrée)</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Checkout / Payment Modal -->
            <div class="modal-overlay" id="checkoutModal">
                <div class="modal payment-modal">
                    <div class="modal-header">
                        <h2>Paiement</h2>
                        <button class="icon-btn close-modal"><span class="material-icons-round">close</span></button>
                    </div>
                    
                    <div class="payment-content">
                        <div class="amount-due">
                            <p>Montant à Payer</p>
                            <h1 id="modalTotalDisplay">0 FCFA</h1>
                        </div>

                        <div class="payment-methods">
                            <button class="method-btn active" data-method="cash">
                                <span class="material-icons-round">payments</span> Espèces
                            </button>
                            <button class="method-btn" data-method="mobile_money">
                                <span class="material-icons-round">smartphone</span> Mobile Money
                            </button>
                            <button class="method-btn" data-method="card">
                                <span class="material-icons-round">credit_card</span> Carte
                            </button>
                        </div>

                        <div class="payment-details" id="cashDetails">
                            <label>Montant Reçu</label>
                            <div class="numpad-input">
                                <input type="number" id="amountTendered" class="large-input" placeholder="0">
                            </div>
                            <div class="quick-cash">
                                <button class="quick-btn" data-val="5000">5,000</button>
                                <button class="quick-btn" data-val="10000">10,000</button>
                                <button class="quick-btn" data-val="20000">20,000</button>
                                <button class="quick-btn exact-btn">Montant Exact</button>
                            </div>
                            
                            <div class="change-due">
                                <span>Monnaie à Rendre:</span>
                                <span id="changeDisplay">0 FCFA</span>
                            </div>
                        </div>

                        <div class="payment-details hidden" id="momoDetails">
                            <label>Numéro de Téléphone Client</label>
                            <input type="text" id="momoPhone" class="large-input" placeholder="+221 77 000 00 00">
                            <p class="helper-text">Une demande de paiement sera envoyée au client.</p>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-primary btn-block" id="confirmPaymentBtn">Confirmer & Imprimer</button>
                    </div>
                </div>
            </div>
        `;
    }

    // --- Dynamic Server Configuration ---
    const config = window.APP_CONFIG || {
        user: { id: null, name: 'Caissier', role: 'cashier' },
        api: {
            inventory: '../api/v1/index.php?request=inventory',
            sales: '../api/v1/index.php?request=sales'
        },
        settings: { taxRate: 0.18, currency: 'FCFA' }
    };

    const API_URL = config.api.inventory;
    const SALES_API = config.api.sales;
    const db = new Dexie('RetailPOS_Local');
    db.version(1).stores({
        products: 'id, sku, barcode, category_id, name',
        pending_sales: '++id, local_uuid, payload, timestamp'
    });

    // --- State ---
    let cart = [];
    let taxRate = config.settings.taxRate; // Dynamically loaded from PHP
    let currentDiscount = 0;
    
    // --- DOM Elements ---
    const searchInput = document.getElementById('searchInput');
    const productGrid = document.getElementById('productGrid');
    const cartItemsContainer = document.getElementById('cartItems');
    const subtotalDisplay = document.getElementById('subtotalDisplay');
    const taxDisplay = document.getElementById('taxDisplay');
    const discountDisplay = document.getElementById('discountDisplay');
    const totalDisplay = document.getElementById('totalDisplay');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    // Modal Elements
    const checkoutModal = document.getElementById('checkoutModal');
    const closeModalBtn = document.querySelector('.close-modal');
    const modalTotalDisplay = document.getElementById('modalTotalDisplay');
    const confirmPaymentBtn = document.getElementById('confirmPaymentBtn');
    const methodBtns = document.querySelectorAll('.method-btn');
    const amountTenderedInput = document.getElementById('amountTendered');
    const changeDisplay = document.getElementById('changeDisplay');
    
    let currentPaymentMethod = 'cash';
    let finalTotal = 0;

    // --- Initialization & Sync ---
    async function syncProducts() {
        if (!navigator.onLine) {
            loadLocalProducts();
            return;
        }
        try {
            // Fetch Products
            const res = await fetch(`${API_URL}/products`);
            const json = await res.json();
            if (json.status === 'success') {
                await db.products.clear();
                await db.products.bulkAdd(json.data);
                renderCatalog(json.data);
            }

            // Fetch Categories
            const catRes = await fetch(`${API_URL}/categories`);
            const catJson = await catRes.json();
            if (catJson.status === 'success') {
                renderCategories(catJson.data);
            }
        } catch (e) {
            console.error("Sync failed, loading local.", e);
            loadLocalProducts();
        }
    }

    function renderCategories(categories) {
        const wrapper = document.querySelector('.categories-wrapper');
        wrapper.innerHTML = '<button class="category-chip active" data-id="all">Tout</button>';
        
        categories.forEach(c => {
            const btn = document.createElement('button');
            btn.className = 'category-chip';
            btn.dataset.id = c.id;
            btn.innerText = c.name;
            wrapper.appendChild(btn);
        });

        // Add event listeners
        document.querySelectorAll('.category-chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                document.querySelectorAll('.category-chip').forEach(c => c.classList.remove('active'));
                e.currentTarget.classList.add('active');
                const catId = e.currentTarget.dataset.id;
                loadLocalProducts('', catId);
            });
        });
    }

    async function loadLocalProducts(query = "", categoryId = "all") {
        let products = await db.products.toArray();
        
        if (query) {
            products = products.filter(p => 
                p.name.toLowerCase().includes(query.toLowerCase()) || 
                p.sku.includes(query) || 
                (p.barcode && p.barcode.includes(query))
            );
        }

        if (categoryId !== "all") {
            products = products.filter(p => p.category_id == categoryId);
        }

        renderCatalog(products);
    }

    function renderCatalog(products) {
        productGrid.innerHTML = '';
        if (products.length === 0) {
            productGrid.innerHTML = '<p style="grid-column: 1/-1; text-align:center;">Aucun produit trouvé</p>';
            return;
        }

        products.forEach(p => {
            const imageHtml = p.image_url ? `<img src="${p.image_url}" style="width:100%; height:100%; object-fit:cover; border-radius:12px 12px 0 0;">` : `<span class="material-icons-round">image</span>`;

            const card = document.createElement('div');
            card.className = 'product-card';
            card.innerHTML = `
                <div class="img-placeholder" style="padding:0; overflow:hidden;">${imageHtml}</div>
                <h4>${p.name}</h4>
                <div class="price">${Number(p.price).toLocaleString()} FCFA</div>
                <div class="stock">En stock: ${p.stock_quantity || '---'}</div>
            `;
            card.addEventListener('click', () => addToCart(p));
            productGrid.appendChild(card);
        });
    }

    // --- Cart Logic ---
    function addToCart(product) {
        const existing = cart.find(item => item.id === product.id);
        if (existing) {
            existing.qty++;
        } else {
            cart.push({ ...product, qty: 1 });
        }
        updateCart();
    }

    function updateCart() {
        cartItemsContainer.innerHTML = '';
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = `
                <div class="empty-cart-state">
                    <span class="material-icons-round">shopping_cart</span>
                    <p>Le panier est vide</p>
                </div>
            `;
            checkoutBtn.disabled = true;
            updateTotals();
            return;
        }

        checkoutBtn.disabled = false;

        cart.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'cart-item';
            row.innerHTML = `
                <div class="item-details">
                    <h4>${item.name}</h4>
                    <div class="price">${Number(item.price).toLocaleString()} FCFA</div>
                </div>
                <div class="item-qty-controls">
                    <button class="qty-btn btn-minus">-</button>
                    <input type="text" class="qty-input" value="${item.qty}" readonly>
                    <button class="qty-btn btn-plus">+</button>
                </div>
                <div class="item-total">${(item.price * item.qty).toLocaleString()}</div>
                <button class="icon-btn btn-delete" style="color:var(--danger);"><span class="material-icons-round">delete</span></button>
            `;

            // Bind events securely
            row.querySelector('.btn-minus').addEventListener('click', () => {
                cart[index].qty -= 1;
                if (cart[index].qty <= 0) cart.splice(index, 1);
                updateCart();
            });

            row.querySelector('.btn-plus').addEventListener('click', () => {
                cart[index].qty += 1;
                updateCart();
            });

            row.querySelector('.btn-delete').addEventListener('click', () => {
                cart.splice(index, 1);
                updateCart();
            });

            cartItemsContainer.appendChild(row);
        });

        updateTotals();
    }

    function updateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
        const tax = subtotal * taxRate;
        finalTotal = subtotal + tax - currentDiscount;

        subtotalDisplay.innerText = `${subtotal.toLocaleString()} FCFA`;
        taxDisplay.innerText = `${tax.toLocaleString()} FCFA`;
        totalDisplay.innerText = `${finalTotal.toLocaleString()} FCFA`;
        modalTotalDisplay.innerText = `${finalTotal.toLocaleString()} FCFA`;
        
        calculateChange();
    }

    // Expose functions for inline onclick handlers
    window.appContext = {
        changeQty: (index, delta) => {
            cart[index].qty += delta;
            if (cart[index].qty <= 0) cart.splice(index, 1);
            updateCart();
        },
        removeItem: (index) => {
            cart.splice(index, 1);
            updateCart();
        }
    };

    // --- Search & Barcode Scanner ---
    // A barcode scanner acts as a fast keyboard typing followed by Enter
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        loadLocalProducts(query);
    });

    searchInput.addEventListener('keypress', async (e) => {
        if (e.key === 'Enter') {
            const query = e.target.value;
            // Exact barcode match? Add to cart immediately
            const exactProduct = await db.products.where('barcode').equals(query).first() 
                                 || await db.products.where('sku').equals(query).first();
            if (exactProduct) {
                addToCart(exactProduct);
                searchInput.value = '';
                loadLocalProducts(); // reset grid
            }
        }
    });

    const clearSearchBtn = document.getElementById('clearSearchBtn');
    if (clearSearchBtn) {
        clearSearchBtn.addEventListener('click', () => {
            searchInput.value = '';
            loadLocalProducts();
        });
    }

    // --- Checkout Workflow ---
    checkoutBtn.addEventListener('click', () => {
        checkoutModal.style.display = 'flex';
        amountTenderedInput.value = '';
        amountTenderedInput.focus();
    });

    const clearCartBtn = document.getElementById('clearCartBtn');
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', () => {
            if (confirm("Vider le panier actuel ?")) {
                cart = [];
                updateCart();
            }
        });
    }

    const syncBtn = document.getElementById('syncBtn');
    if (syncBtn) {
        syncBtn.addEventListener('click', () => {
            syncProducts();
        });
    }

    closeModalBtn.addEventListener('click', () => {
        checkoutModal.style.display = 'none';
    });

    methodBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            methodBtns.forEach(b => b.classList.remove('active'));
            const target = e.currentTarget;
            target.classList.add('active');
            currentPaymentMethod = target.dataset.method;
            
            document.getElementById('cashDetails').classList.add('hidden');
            document.getElementById('momoDetails').classList.add('hidden');
            
            if (currentPaymentMethod === 'cash') document.getElementById('cashDetails').classList.remove('hidden');
            if (currentPaymentMethod === 'mobile_money') document.getElementById('momoDetails').classList.remove('hidden');
        });
    });

    amountTenderedInput.addEventListener('input', calculateChange);

    document.querySelectorAll('.quick-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (e.target.classList.contains('exact-btn')) {
                amountTenderedInput.value = finalTotal;
            } else {
                amountTenderedInput.value = e.target.dataset.val;
            }
            calculateChange();
        });
    });

    function calculateChange() {
        const tendered = parseFloat(amountTenderedInput.value) || 0;
        const change = tendered - finalTotal;
        changeDisplay.innerText = change >= 0 ? `${change.toLocaleString()} FCFA` : `0 FCFA`;
        changeDisplay.style.color = change >= 0 ? 'var(--success)' : 'var(--danger)';
    }

    // Process Payment
    confirmPaymentBtn.addEventListener('click', async () => {
        if (currentPaymentMethod === 'cash' && (parseFloat(amountTenderedInput.value) || 0) < finalTotal) {
            alert("Montant reçu insuffisant.");
            return;
        }

        const salePayload = {
            receipt_no: `REC-${Date.now()}`,
            store_id: 1, // default
            user_id: 1, // default
            total: finalTotal,
            tax: finalTotal - (finalTotal / 1.18),
            discount: currentDiscount,
            payment_method: currentPaymentMethod,
            items: cart.map(i => ({ product_id: i.id, quantity: i.qty, unit_price: i.price }))
        };

        if (navigator.onLine) {
            // Push to server
            try {
                const response = await fetch(SALES_API, { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(salePayload) 
                });
                const result = await response.json();
                
                if (result.status === 'success') {
                    alert("Vente enregistrée en ligne ! Impression du ticket...");
                    salePayload.receipt_no = result.sale_id; // Optionally update with real ID
                } else {
                    alert("Erreur: " + result.message);
                    return;
                }
            } catch (e) {
                console.error("Transaction error:", e);
                alert("Erreur de connexion lors de l'enregistrement de la vente.");
                return;
            }
        } else {
            // Save to offline queue
            await db.pending_sales.add({
                local_uuid: crypto.randomUUID(),
                payload: salePayload,
                timestamp: new Date().toISOString()
            });
            alert("Hors ligne : Vente mise en file d'attente. Impression du ticket...");
        }

        // Generate thermal receipt
        printReceipt(salePayload);

        // Reset
        cart = [];
        updateCart();
        checkoutModal.style.display = 'none';
        syncProducts(); // Refresh stock
    });

    function printReceipt(sale) {
        // Save sale data to localStorage for receipt.html to read
        localStorage.setItem('lastReceipt', JSON.stringify(sale));

        // Open receipt.html in a new popup window
        const receiptWindow = window.open('receipt.html', 'ReceiptWindow', 'width=400,height=600');
        
        if (!receiptWindow) {
            alert("Veuillez autoriser les pop-ups pour imprimer le ticket.");
        }
    }

    // Keyboard Shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F2') {
            e.preventDefault();
            searchInput.focus();
        }
        if (e.key === 'Enter' && cart.length > 0 && checkoutModal.style.display !== 'flex') {
            checkoutBtn.click();
        }
        if (e.altKey && e.key === 'c') {
            cart = [];
            updateCart();
        }
    });

    // Start
    syncProducts();
});
