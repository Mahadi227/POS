-- 007_cash_registers.sql
-- Enterprise cash register management (multi-store)

CREATE TABLE IF NOT EXISTS cash_registers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    register_code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    assigned_user_id INT NULL,
    status ENUM('active', 'inactive', 'maintenance') NOT NULL DEFAULT 'active',
    opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    current_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    config JSON NULL,
    last_activity_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    UNIQUE KEY uk_cash_register_code (store_id, register_code),
    INDEX idx_cash_registers_store_status (store_id, status),
    INDEX idx_cash_registers_assigned (assigned_user_id),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_register_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    register_id INT NOT NULL,
    store_id INT NOT NULL,
    user_id INT NOT NULL,
    shift_type ENUM('morning', 'afternoon', 'evening', 'night', 'custom') NOT NULL DEFAULT 'morning',
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    closing_balance DECIMAL(12,2) NULL,
    expected_cash DECIMAL(12,2) NULL,
    counted_cash DECIMAL(12,2) NULL,
    variance DECIMAL(12,2) NULL,
    total_sales DECIMAL(12,2) NOT NULL DEFAULT 0,
    cash_sales DECIMAL(12,2) NOT NULL DEFAULT 0,
    card_sales DECIMAL(12,2) NOT NULL DEFAULT 0,
    mobile_sales DECIMAL(12,2) NOT NULL DEFAULT 0,
    refunds DECIMAL(12,2) NOT NULL DEFAULT 0,
    expenses DECIMAL(12,2) NOT NULL DEFAULT 0,
    transaction_count INT NOT NULL DEFAULT 0,
    cashier_shift_id INT NULL,
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    opening_notes TEXT NULL,
    closing_notes TEXT NULL,
    opened_by INT NULL,
    closed_by INT NULL,
    INDEX idx_crs_register_status (register_id, status),
    INDEX idx_crs_store_opened (store_id, opened_at),
    INDEX idx_crs_user (user_id),
    FOREIGN KEY (register_id) REFERENCES cash_registers(id) ON DELETE CASCADE,
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    register_id INT NULL,
    session_id INT NULL,
    movement_type ENUM(
        'opening_cash', 'sale', 'refund', 'expense', 'deposit',
        'withdrawal', 'transfer_out', 'transfer_in', 'closing_cash', 'adjustment'
    ) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_after DECIMAL(12,2) NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    payment_method VARCHAR(30) NULL,
    reason TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sync_status ENUM('synced', 'pending', 'conflict') NOT NULL DEFAULT 'synced',
    local_uuid VARCHAR(64) NULL,
    INDEX idx_cm_store_date (store_id, created_at),
    INDEX idx_cm_register (register_id),
    INDEX idx_cm_session (session_id),
    INDEX idx_cm_type (movement_type),
    INDEX idx_cm_sync (sync_status),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (register_id) REFERENCES cash_registers(id) ON DELETE SET NULL,
    FOREIGN KEY (session_id) REFERENCES cash_register_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_reconciliation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    register_id INT NOT NULL,
    session_id INT NOT NULL,
    expected_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
    physical_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
    difference DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    manager_id INT NULL,
    admin_id INT NULL,
    manager_note TEXT NULL,
    admin_note TEXT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cr_store_status (store_id, status),
    INDEX idx_cr_session (session_id),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (register_id) REFERENCES cash_registers(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES cash_register_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    transfer_type ENUM(
        'register_to_register', 'register_to_safe', 'safe_to_register',
        'branch_to_branch', 'warehouse_to_branch'
    ) NOT NULL,
    from_register_id INT NULL,
    to_register_id INT NULL,
    from_store_id INT NULL,
    to_store_id INT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason TEXT NULL,
    status ENUM('pending', 'approved', 'in_transit', 'completed', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    requested_by INT NOT NULL,
    approved_by INT NULL,
    received_by INT NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ct_store_status (store_id, status),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (from_register_id) REFERENCES cash_registers(id) ON DELETE SET NULL,
    FOREIGN KEY (to_register_id) REFERENCES cash_registers(id) ON DELETE SET NULL,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cash_register_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NULL,
    register_id INT NULL,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_crl_store_created (store_id, created_at),
    INDEX idx_crl_register (register_id),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    FOREIGN KEY (register_id) REFERENCES cash_registers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link legacy cashier shifts to registers (optional columns)
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cashier_shifts' AND COLUMN_NAME = 'register_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE cashier_shifts ADD COLUMN register_id INT NULL AFTER user_id, ADD COLUMN session_id INT NULL AFTER register_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
