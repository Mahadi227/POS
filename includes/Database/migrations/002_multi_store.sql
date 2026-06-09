-- Multi-succursales — exécuter dans phpMyAdmin (base pos_system_db)

-- Colonnes succursale
ALTER TABLE stores ADD COLUMN code VARCHAR(20) NULL AFTER id;
ALTER TABLE stores ADD COLUMN phone VARCHAR(30) NULL AFTER location;
ALTER TABLE stores ADD COLUMN email VARCHAR(255) NULL AFTER phone;
ALTER TABLE stores ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER currency;
ALTER TABLE stores ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

-- Accès utilisateur → plusieurs succursales
CREATE TABLE IF NOT EXISTS user_stores (
    user_id INT NOT NULL,
    store_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, store_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);

-- SKU unique par succursale (ignorer si erreur "Duplicate key name")
-- ALTER TABLE products DROP INDEX sku;
-- ALTER TABLE products ADD UNIQUE KEY uq_products_store_sku (store_id, sku);

CREATE INDEX IF NOT EXISTS idx_products_store ON products(store_id);
CREATE INDEX IF NOT EXISTS idx_stock_movements_status ON stock_movements(status);
