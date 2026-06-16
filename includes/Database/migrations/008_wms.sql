-- 008_wms.sql — Enterprise Warehouse Management System

CREATE TABLE IF NOT EXISTS warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NULL,
    warehouse_code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    warehouse_type ENUM(
        'central', 'regional', 'store', 'distribution', 'cold_storage', 'temporary'
    ) NOT NULL DEFAULT 'central',
    manager_id INT NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    country VARCHAR(100) NULL DEFAULT 'Senegal',
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    capacity_units INT NULL DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY uk_warehouse_code (warehouse_code),
    INDEX idx_wh_store_status (store_id, status),
    INDEX idx_wh_type (warehouse_type),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    zone VARCHAR(50) NOT NULL DEFAULT 'A',
    aisle VARCHAR(50) NULL,
    rack VARCHAR(50) NULL,
    shelf VARCHAR(50) NULL,
    bin VARCHAR(50) NULL,
    location_code VARCHAR(100) NOT NULL,
    capacity_units INT NULL DEFAULT 0,
    status ENUM('active', 'inactive', 'full') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_wh_location (warehouse_id, location_code),
    INDEX idx_wl_warehouse (warehouse_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS batch_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    product_id INT NOT NULL,
    batch_number VARCHAR(80) NOT NULL,
    barcode VARCHAR(120) NULL,
    serial_number VARCHAR(120) NULL,
    manufacturing_date DATE NULL,
    expiry_date DATE NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    status ENUM('active', 'expired', 'recalled', 'depleted') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_batch_wh_product (warehouse_id, product_id),
    INDEX idx_batch_expiry (expiry_date),
    INDEX idx_batch_number (batch_number),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    product_id INT NOT NULL,
    location_id INT NULL,
    batch_id INT NULL,
    quantity INT NOT NULL DEFAULT 0,
    reserved_qty INT NOT NULL DEFAULT 0,
    damaged_qty INT NOT NULL DEFAULT 0,
    expired_qty INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 5,
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    stock_value DECIMAL(16,4) NOT NULL DEFAULT 0,
    last_movement_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_wh_product (warehouse_id, product_id),
    INDEX idx_wi_warehouse (warehouse_id),
    INDEX idx_wi_product (product_id),
    INDEX idx_wi_low_stock (warehouse_id, quantity, reorder_level),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES warehouse_locations(id) ON DELETE SET NULL,
    FOREIGN KEY (batch_id) REFERENCES batch_tracking(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) NOT NULL,
    supplier_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    store_id INT NULL,
    status ENUM('draft', 'pending', 'approved', 'partial', 'received', 'cancelled') NOT NULL DEFAULT 'draft',
    total_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    expected_date DATE NULL,
    notes TEXT NULL,
    created_by INT NOT NULL,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_po_number (po_number),
    INDEX idx_po_supplier (supplier_id),
    INDEX idx_po_warehouse (warehouse_id),
    INDEX idx_po_status (status),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_ordered INT NOT NULL DEFAULT 0,
    quantity_received INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goods_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grn_number VARCHAR(50) NOT NULL,
    warehouse_id INT NOT NULL,
    supplier_id INT NULL,
    purchase_order_id INT NULL,
    status ENUM('pending', 'inspecting', 'accepted', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    inspection_status ENUM('pending', 'passed', 'failed', 'partial') NOT NULL DEFAULT 'pending',
    total_items INT NOT NULL DEFAULT 0,
    total_value DECIMAL(14,2) NOT NULL DEFAULT 0,
    received_by INT NULL,
    inspected_by INT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    inspected_at TIMESTAMP NULL,
    notes TEXT NULL,
    sync_status ENUM('synced', 'pending', 'conflict') NOT NULL DEFAULT 'synced',
    local_uuid VARCHAR(64) NULL,
    UNIQUE KEY uk_grn_number (grn_number),
    INDEX idx_grn_warehouse (warehouse_id),
    INDEX idx_grn_status (status),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (inspected_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS goods_receipt_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    goods_receipt_id INT NOT NULL,
    product_id INT NOT NULL,
    batch_id INT NULL,
    location_id INT NULL,
    quantity_expected INT NOT NULL DEFAULT 0,
    quantity_received INT NOT NULL DEFAULT 0,
    quantity_damaged INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    batch_number VARCHAR(80) NULL,
    expiry_date DATE NULL,
    barcode VARCHAR(120) NULL,
    FOREIGN KEY (goods_receipt_id) REFERENCES goods_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (batch_id) REFERENCES batch_tracking(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES warehouse_locations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_dispatches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispatch_number VARCHAR(50) NOT NULL,
    from_warehouse_id INT NOT NULL,
    to_store_id INT NULL,
    to_warehouse_id INT NULL,
    status ENUM('draft', 'picking', 'packed', 'dispatched', 'in_transit', 'delivered', 'cancelled') NOT NULL DEFAULT 'draft',
    driver_name VARCHAR(100) NULL,
    vehicle_number VARCHAR(50) NULL,
    delivery_date DATE NULL,
    received_by INT NULL,
    received_at TIMESTAMP NULL,
    total_items INT NOT NULL DEFAULT 0,
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dispatch_number (dispatch_number),
    INDEX idx_wd_from (from_warehouse_id),
    INDEX idx_wd_status (status),
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
    FOREIGN KEY (to_store_id) REFERENCES stores(id) ON DELETE SET NULL,
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_dispatch_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispatch_id INT NOT NULL,
    product_id INT NOT NULL,
    batch_id INT NULL,
    quantity INT NOT NULL DEFAULT 0,
    quantity_picked INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    FOREIGN KEY (dispatch_id) REFERENCES warehouse_dispatches(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (batch_id) REFERENCES batch_tracking(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(50) NOT NULL,
    transfer_type ENUM('warehouse_to_warehouse', 'warehouse_to_store', 'store_to_warehouse', 'branch_to_branch') NOT NULL,
    from_warehouse_id INT NULL,
    to_warehouse_id INT NULL,
    from_store_id INT NULL,
    to_store_id INT NULL,
    status ENUM('requested', 'approved', 'picking', 'in_transit', 'received', 'completed', 'rejected', 'cancelled') NOT NULL DEFAULT 'requested',
    reason TEXT NULL,
    requested_by INT NOT NULL,
    approved_by INT NULL,
    received_by INT NULL,
    approved_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sync_status ENUM('synced', 'pending', 'conflict') NOT NULL DEFAULT 'synced',
    local_uuid VARCHAR(64) NULL,
    UNIQUE KEY uk_transfer_number (transfer_number),
    INDEX idx_wt_status (status),
    INDEX idx_wt_from_wh (from_warehouse_id),
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    FOREIGN KEY (from_store_id) REFERENCES stores(id) ON DELETE SET NULL,
    FOREIGN KEY (to_store_id) REFERENCES stores(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_transfer_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    product_id INT NOT NULL,
    batch_id INT NULL,
    quantity_requested INT NOT NULL DEFAULT 0,
    quantity_sent INT NOT NULL DEFAULT 0,
    quantity_received INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    FOREIGN KEY (transfer_id) REFERENCES warehouse_transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    FOREIGN KEY (batch_id) REFERENCES batch_tracking(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) NOT NULL,
    store_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    status ENUM('pending', 'manager_approved', 'warehouse_approved', 'dispatched', 'delivered', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    notes TEXT NULL,
    requested_by INT NOT NULL,
    manager_id INT NULL,
    warehouse_approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_request_number (request_number),
    INDEX idx_wr_store (store_id),
    INDEX idx_wr_status (status),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (warehouse_approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_requested INT NOT NULL DEFAULT 0,
    quantity_approved INT NOT NULL DEFAULT 0,
    quantity_delivered INT NOT NULL DEFAULT 0,
    FOREIGN KEY (request_id) REFERENCES warehouse_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_stock_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    product_id INT NOT NULL,
    batch_id INT NULL,
    movement_type ENUM(
        'purchase', 'sale', 'transfer_in', 'transfer_out', 'return_in', 'return_out',
        'adjustment', 'damaged', 'expired', 'lost', 'manual', 'dispatch_out', 'receipt_in'
    ) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    balance_after INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    stock_value DECIMAL(16,4) NOT NULL DEFAULT 0,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sync_status ENUM('synced', 'pending', 'conflict') NOT NULL DEFAULT 'synced',
    local_uuid VARCHAR(64) NULL,
    INDEX idx_wsm_wh_date (warehouse_id, created_at),
    INDEX idx_wsm_product (product_id),
    INDEX idx_wsm_type (movement_type),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (batch_id) REFERENCES batch_tracking(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    audit_type ENUM('cycle_count', 'physical_count', 'spot_check') NOT NULL DEFAULT 'cycle_count',
    status ENUM('draft', 'in_progress', 'pending_approval', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    expected_value DECIMAL(16,4) NOT NULL DEFAULT 0,
    counted_value DECIMAL(16,4) NOT NULL DEFAULT 0,
    variance_value DECIMAL(16,4) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    conducted_by INT NULL,
    approved_by INT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (conducted_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_audit_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_id INT NOT NULL,
    product_id INT NOT NULL,
    system_qty INT NOT NULL DEFAULT 0,
    counted_qty INT NOT NULL DEFAULT 0,
    variance_qty INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
    FOREIGN KEY (audit_id) REFERENCES warehouse_audits(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NULL,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wlog_wh_created (warehouse_id, created_at),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: warehouse_id on inventory_ledger for accounting traceability
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_ledger' AND COLUMN_NAME = 'warehouse_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE inventory_ledger ADD COLUMN warehouse_id INT NULL AFTER store_id, ADD INDEX idx_ledger_warehouse (warehouse_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
