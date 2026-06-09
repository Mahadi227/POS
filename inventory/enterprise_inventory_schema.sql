-- Enterprise Inventory Ledger & Traceability Schema for RetailPOS
CREATE DATABASE IF NOT EXISTS pos_system_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE pos_system_db;

-- Branches / Warehouses / Stores
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    location VARCHAR(255) NULL,
    manager_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    location VARCHAR(255) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS stores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NULL,
    code VARCHAR(50) UNIQUE NULL,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NULL,
    currency VARCHAR(10) DEFAULT 'FCFA',
    tax_rate DECIMAL(6, 2) DEFAULT 18.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NULL,
    store_id INT NULL,
    role VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL,
    FOREIGN KEY (store_id) REFERENCES stores (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    branch_id INT NULL,
    warehouse_id INT NULL,
    category_id INT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    barcode VARCHAR(120) UNIQUE NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    unit VARCHAR(50) DEFAULT 'piece',
    purchase_price DECIMAL(12, 4) NOT NULL,
    selling_price DECIMAL(12, 4) NOT NULL,
    current_stock INT NOT NULL DEFAULT 0,
    min_stock_level INT NOT NULL DEFAULT 5,
    expiry_date DATE NULL,
    image_url VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (store_id) REFERENCES stores (id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ledger_id INT NULL,
    store_id INT NOT NULL,
    branch_id INT NULL,
    warehouse_id INT NULL,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
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
    FOREIGN KEY (store_id) REFERENCES stores (id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    from_store_id INT NULL,
    to_store_id INT NULL,
    from_branch_id INT NULL,
    to_branch_id INT NULL,
    from_warehouse_id INT NULL,
    to_warehouse_id INT NULL,
    quantity INT NOT NULL,
    movement_type ENUM(
        'transfer_in',
        'transfer_out',
        'adjustment',
        'purchase',
        'sale',
        'return',
        'damaged',
        'expired',
        'manual_edit'
    ) NOT NULL,
    status ENUM(
        'pending',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    reference_id VARCHAR(100) NULL,
    reference_type VARCHAR(100) NULL,
    notes TEXT NULL,
    performed_by INT NOT NULL,
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
    FOREIGN KEY (from_store_id) REFERENCES stores (id) ON DELETE SET NULL,
    FOREIGN KEY (to_store_id) REFERENCES stores (id) ON DELETE SET NULL,
    FOREIGN KEY (from_branch_id) REFERENCES branches (id) ON DELETE SET NULL,
    FOREIGN KEY (to_branch_id) REFERENCES branches (id) ON DELETE SET NULL,
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    FOREIGN KEY (performed_by) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS inventory_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    store_id INT NOT NULL,
    branch_id INT NULL,
    warehouse_id INT NULL,
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
    FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS inventory_audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_type VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    changes JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS inventory_sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    local_uuid VARCHAR(100) NULL,
    status ENUM(
        'pending',
        'synced',
        'failed',
        'conflict'
    ) NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    retry_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS inventory_valuation_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    branch_id INT NULL,
    warehouse_id INT NULL,
    total_opening_value DECIMAL(18, 4) NOT NULL DEFAULT 0.00,
    total_current_value DECIMAL(18, 4) NOT NULL DEFAULT 0.00,
    total_profit_estimate DECIMAL(18, 4) NOT NULL DEFAULT 0.00,
    snapshot_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (store_id) REFERENCES stores (id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches (id) ON DELETE SET NULL,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE SET NULL
);

-- Financial stock valuation indexes
CREATE INDEX idx_inventory_ledger_stock on inventory_ledger (current_stock, movement_type);

CREATE INDEX idx_stock_movements_status on stock_movements (status);

CREATE INDEX idx_inventory_logs_user on inventory_logs (user_id, movement_type);