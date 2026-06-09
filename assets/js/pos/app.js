// assets/js/pos/app.js
// Core POS UI bindings and Initialization

document.addEventListener('DOMContentLoaded', async () => {
    
    // Bindings (moved to top to ensure they attach even if sync fails/hangs)
    document.getElementById('clearCartBtn')?.addEventListener('click', () => {
        if(confirm("Vider le panier actuel ?")) window.Cart.clearCart();
    });

    document.getElementById('checkoutBtn')?.addEventListener('click', () => {
        if (window.Cart.getCart().length === 0) return;
        window.Checkout.openPaymentModal();
    });

    // Search Input Binding
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            renderProducts('all', query);
        });
    }

    // Scanner Button Binding
    const scannerBtn = document.getElementById('openCameraScannerBtn');
    if (scannerBtn) {
        scannerBtn.addEventListener('click', () => {
            const code = prompt("Simulate Scanner Input (Enter Barcode):");
            if (code && window.handleBarcodeScan) {
                window.handleBarcodeScan(code);
            } else if (code) {
                alert("Barcode scanner function not found.");
            }
        });
    }

    // Initialize Offline DB and Sync
    try {
        await window.OfflineSync.initDB();
        await window.OfflineSync.syncProductsCatalog();
        
        // Render UI from Local DB
        renderProducts();
    } catch(e) {
        console.error("Failed to init offline sync", e);
    }
});

async function renderProducts(category = 'all', searchQuery = '') {
    const grid = document.getElementById('productsGrid');
    grid.innerHTML = '<div class="loading-state"><span class="material-icons-round spin">sync</span></div>';
    
    let products = await window.OfflineSync.getAllProductsLocal();
    
    if (searchQuery) {
        products = products.filter(p => 
            (p.name && p.name.toLowerCase().includes(searchQuery)) || 
            (p.barcode && p.barcode.toLowerCase().includes(searchQuery)) ||
            (p.sku && p.sku.toLowerCase().includes(searchQuery))
        );
    }
    
    grid.innerHTML = '';
    
    if (products.length === 0) {
        grid.innerHTML = '<div class="empty-state">Aucun produit trouvé</div>';
        return;
    }

    products.forEach(p => {
        const imageHtml = p.image_url ? `<img src="${p.image_url}" style="width:100%; height:100%; object-fit:cover; border-radius:12px 12px 0 0;">` : `<span class="material-icons-round">image</span>`;
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `
            <div class="img-placeholder" style="padding:0; overflow:hidden;">
                ${imageHtml}
            </div>
            <div class="price">${Number(p.price).toLocaleString()} FCFA</div>
            <div class="name">${p.name}</div>
            <div class="stock">Stock: ${p.stock_quantity || 0}</div>
        `;
        card.addEventListener('click', () => {
            window.Cart.addItemToCart(p);
        });
        grid.appendChild(card);
    });
}
