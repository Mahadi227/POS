-- Surveillance synchronisation hors ligne (RetailPOS)

CREATE TABLE IF NOT EXISTS store_sync_status (
    store_id INT NOT NULL PRIMARY KEY,
    is_online TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at TIMESTAMP NULL DEFAULT NULL,
    last_sync_at TIMESTAMP NULL DEFAULT NULL,
    pending_local_count INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

-- Colonnes optionnelles (SyncSchemaMigrator les ajoute aussi au runtime)
-- ALTER TABLE synchronization_queue ADD COLUMN error_message TEXT NULL;
-- ALTER TABLE synchronization_queue ADD COLUMN retry_count INT NOT NULL DEFAULT 0;
-- ALTER TABLE offline_transactions ADD COLUMN error_message TEXT NULL;
-- ALTER TABLE offline_transactions ADD COLUMN conflict_reason VARCHAR(255) NULL;
