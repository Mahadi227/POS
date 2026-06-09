// assets/js/app/sync.js

/**
 * Synchronization Engine for RetailPOS
 * Handles Push (Offline Queue) and Pull (Server Updates)
 */
class SyncEngine {
    constructor() {
        this.API_SYNC_PULL = '../api/v1/index.php?request=sync/pull';
        this.API_SYNC_PUSH = '../api/v1/index.php?request=sync/push';
        this.db = new Dexie('RetailPOS_Local');
        this.db.version(1).stores({
            products: 'id, sku, barcode, category_id, name, last_updated_at',
            pending_sales: '++id, local_uuid, payload, timestamp',
            app_state: 'key, value'
        });

        this.initListeners();
    }

    initListeners() {
        // Trigger sync when coming back online
        window.addEventListener('online', () => {
            console.log('[Sync Engine] Network restored. Initiating Sync...');
            this.syncAll();
        });

        // Trigger sync from Service Worker
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data && event.data.action === 'TRIGGER_BACKGROUND_SYNC') {
                console.log('[Sync Engine] Background Sync requested by SW');
                this.syncAll();
            }
        });
    }

    async registerBackgroundSync() {
        if ('serviceWorker' in navigator && 'SyncManager' in window) {
            try {
                const swRegistration = await navigator.serviceWorker.ready;
                await swRegistration.sync.register('sync-offline-sales');
                console.log('[Sync Engine] Registered Background Sync Event');
            } catch (err) {
                console.error('[Sync Engine] Background Sync failed to register', err);
            }
        } else {
            // Fallback for browsers that don't support SyncManager
            this.syncAll();
        }
    }

    /**
     * Main Sync Workflow
     * 1. Push Offline Changes (Sales) -> Conflict Resolution happens on backend
     * 2. Pull Remote Changes (Products, config) -> Backend truth overrides local
     */
    async syncAll() {
        if (!navigator.onLine) return;

        try {
            await this.pushPendingSales();
            await this.pullRemoteUpdates();
            console.log('[Sync Engine] Sync completed successfully.');
            
            // Dispatch event to update UI
            document.dispatchEvent(new Event('SyncCompleted'));
        } catch (error) {
            console.error('[Sync Engine] Sync failed', error);
        }
    }

    // Phase 1: Push
    async pushPendingSales() {
        const pendingSales = await this.db.pending_sales.toArray();
        if (pendingSales.length === 0) return;

        console.log(`[Sync Engine] Pushing ${pendingSales.length} offline sales to server...`);

        const response = await fetch(this.API_SYNC_PUSH, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sales: pendingSales })
        });

        const result = await response.json();

        if (result.status === 'success') {
            // Clear successfully synced sales from local queue
            const syncedIds = result.synced_uuids || [];
            const idsToDelete = pendingSales
                .filter(s => syncedIds.includes(s.local_uuid))
                .map(s => s.id);
            
            await this.db.pending_sales.bulkDelete(idsToDelete);
            console.log(`[Sync Engine] Successfully synced ${idsToDelete.length} sales.`);
        } else {
            throw new Error(result.message || 'Push failed');
        }
    }

    // Phase 2: Pull
    async pullRemoteUpdates() {
        // Get last sync timestamp
        const stateRecord = await this.db.app_state.get('last_sync_timestamp');
        const lastSync = stateRecord ? stateRecord.value : '2000-01-01 00:00:00';

        console.log(`[Sync Engine] Pulling updates since ${lastSync}...`);

        const response = await fetch(`${this.API_SYNC_PULL}&since=${encodeURIComponent(lastSync)}`);
        const result = await response.json();

        if (result.status === 'success') {
            const { products, current_timestamp } = result.data;
            
            if (products && products.length > 0) {
                // Update local DB. bulkPut overwrites existing keys, adds new ones.
                await this.db.products.bulkPut(products);
                console.log(`[Sync Engine] Updated ${products.length} products locally.`);
            }

            // Update timestamp
            await this.db.app_state.put({ key: 'last_sync_timestamp', value: current_timestamp });
        } else {
            throw new Error(result.message || 'Pull failed');
        }
    }
}

// Instantiate Global Sync Engine
window.PosSyncEngine = new SyncEngine();
