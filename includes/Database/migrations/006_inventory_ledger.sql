-- Inventory ledger table (compatible with main RetailPOS schema — no branches/warehouses)
CREATE TABLE IF NOT EXISTS inventory_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    store_id INT NOT NULL,
    user_id INT NOT NULL,
    movement_type ENUM(
        'purchase',
        'sale',
        'return',
        'transfer_in',
        'transfer_out',
        'adjustment',
        'damaged',
        'expired',
        'manual_edit'
    ) NOT NULL,
    reference_id VARCHAR(100) NULL,
    reference_type VARCHAR(100) NULL,
    opening_stock INT NOT NULL DEFAULT 0,
    stock_in INT NOT NULL DEFAULT 0,
    stock_out INT NOT NULL DEFAULT 0,
    current_stock INT NOT NULL DEFAULT 0,
    purchase_price DECIMAL(12, 4) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(12, 4) NOT NULL DEFAULT 0.00,
    opening_stock_value DECIMAL(16, 4) NOT NULL DEFAULT 0.00,
    stock_out_value DECIMAL(16, 4) NOT NULL DEFAULT 0.00,
    current_stock_value DECIMAL(16, 4) NOT NULL DEFAULT 0.00,
    estimated_profit DECIMAL(16, 4) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    movement_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product_store (product_id, store_id),
    INDEX idx_movement_type (movement_type),
    INDEX idx_user (user_id),
    INDEX idx_dates (movement_date),
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Backfill from existing inventory_logs (one-time, skip if already populated)
INSERT INTO inventory_ledger (
    product_id, store_id, user_id, movement_type,
    reference_id, reference_type,
    opening_stock, stock_in, stock_out, current_stock,
    purchase_price, selling_price,
    opening_stock_value, stock_out_value, current_stock_value, estimated_profit,
    notes, movement_date
)
SELECT
    il.product_id,
    il.store_id,
    il.user_id,
    CASE il.reason
        WHEN 'sale' THEN 'sale'
        WHEN 'restock' THEN 'adjustment'
        WHEN 'damage' THEN 'damaged'
        WHEN 'correction' THEN 'adjustment'
        WHEN 'transfer' THEN 'transfer_out'
        ELSE 'adjustment'
    END,
    CAST(il.id AS CHAR),
    'inventory_log',
    GREATEST(0, COALESCE(p.stock_quantity, 0) - il.change_amount),
    GREATEST(0, il.change_amount),
    GREATEST(0, -il.change_amount),
    COALESCE(p.stock_quantity, 0),
    COALESCE(p.cost, 0),
    COALESCE(p.price, 0),
    GREATEST(0, COALESCE(p.stock_quantity, 0) - il.change_amount) * COALESCE(p.cost, 0),
    GREATEST(0, -il.change_amount) * COALESCE(p.price, 0),
    COALESCE(p.stock_quantity, 0) * COALESCE(p.cost, 0),
    GREATEST(0, -il.change_amount) * (COALESCE(p.price, 0) - COALESCE(p.cost, 0)),
    CONCAT('Imported from inventory_logs #', il.id),
    il.created_at
FROM inventory_logs il
INNER JOIN products p ON p.id = il.product_id
WHERE NOT EXISTS (SELECT 1 FROM inventory_ledger LIMIT 1);
