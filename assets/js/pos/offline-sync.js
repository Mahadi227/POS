// assets/js/pos/offline-sync.js
// Handles IndexedDB storage and offline synchronization

const DB_NAME = 'RetailPOS_OfflineDB';
const DB_VERSION = 1;

let db;

function initDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        
        request.onerror = (event) => {
            console.error("IndexedDB error:", event.target.error);
            reject(event.target.error);
        };
        
        request.onsuccess = (event) => {
            db = event.target.result;
            resolve(db);
        };
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            // Create object stores
            if (!db.objectStoreNames.contains('products')) {
                db.createObjectStore('products', { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains('categories')) {
                db.createObjectStore('categories', { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains('offline_sales')) {
                db.createObjectStore('offline_sales', { keyPath: 'local_id', autoIncrement: true });
            }
        };
    });
}

// Function to fetch all products from API and store them locally
async function syncProductsCatalog() {
    try {
        const response = await fetch('../../api/v1/index.php?request=inventory/products', {
            credentials: 'same-origin',
        });
        const data = await response.json();
        
        if (data.status === 'success') {
            const tx = db.transaction('products', 'readwrite');
            const store = tx.objectStore('products');
            
            // Clear existing and add new
            store.clear();
            data.data.forEach(product => {
                store.put(product);
            });
            
            return new Promise((resolve) => {
                tx.oncomplete = () => resolve(true);
            });
        }
    } catch (e) {
        console.warn("Could not sync products from server (Offline mode)", e);
        return false;
    }
}

// Get product from IndexedDB by barcode
function getProductByBarcodeLocal(barcode) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('products', 'readonly');
        const store = tx.objectStore('products');
        const request = store.getAll();
        
        request.onsuccess = () => {
            const products = request.result;
            const found = products.find(p => p.barcode === barcode);
            resolve(found);
        };
        request.onerror = () => reject(request.error);
    });
}

// Get all products from IndexedDB
function getAllProductsLocal() {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('products', 'readonly');
        const store = tx.objectStore('products');
        const request = store.getAll();
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Save sale locally when offline
function saveOfflineSale(saleData) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('offline_sales', 'readwrite');
        const store = tx.objectStore('offline_sales');
        
        saleData.timestamp = new Date().toISOString();
        const request = store.add(saleData);
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Expose globally
window.OfflineSync = {
    initDB,
    syncProductsCatalog,
    getProductByBarcodeLocal,
    getAllProductsLocal,
    saveOfflineSale
};
