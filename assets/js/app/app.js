// assets/js/app/app.js

document.addEventListener('DOMContentLoaded', () => {
    const appContainer = document.getElementById('app');
    
    // Initialize IndexedDB with Dexie
    const db = new Dexie('RetailPOS_DB');
    db.version(1).stores({
        products: 'id, barcode, name, price, stock, store_id',
        pending_sales: '++id, total, status, timestamp',
        users: 'id, role'
    });

    // Check Network Status
    const updateNetworkStatus = () => {
        const isOnline = navigator.onLine;
        console.log(`Application is currently: ${isOnline ? 'ONLINE' : 'OFFLINE'}`);
        // Here we could trigger a UI notification or top-bar color change
    };

    window.addEventListener('online', updateNetworkStatus);
    window.addEventListener('offline', updateNetworkStatus);
    
    // Initial UI render
    setTimeout(() => {
        appContainer.innerHTML = `
            <div class="pos-layout">
                <nav class="sidebar">
                    <h2>RetailPOS</h2>
                    <ul>
                        <li><a href="#" class="active">Caisse</a></li>
                        <li><a href="#">Inventaire</a></li>
                        <li><a href="#">Synchronisation</a></li>
                    </ul>
                </nav>
                <main class="main-content">
                    <header class="topbar">
                        <div class="search-bar">
                            <input type="text" placeholder="Scanner un code barre ou chercher un produit..." autofocus>
                        </div>
                        <div class="user-info">
                            <span class="status-indicator ${navigator.onLine ? 'online' : 'offline'}"></span>
                            <span>Caissier Principal</span>
                        </div>
                    </header>
                    <div class="pos-workspace">
                        <div class="product-grid" id="product-grid">
                            <!-- Products will be injected here -->
                            <p class="placeholder-text">Prêt à scanner...</p>
                        </div>
                        <div class="cart-section">
                            <div class="cart-items" id="cart-items">
                                <!-- Cart items -->
                            </div>
                            <div class="cart-summary">
                                <div class="summary-row">
                                    <span>Sous-total</span>
                                    <span>0 FCFA</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total</span>
                                    <span>0 FCFA</span>
                                </div>
                                <button class="checkout-btn">Encaisser</button>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        `;
    }, 1500); // Simulate initial DB loading/sync
});
