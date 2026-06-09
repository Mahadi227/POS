-- 005_manager_supervision.sql
-- Manager supervision: approvals, shifts, audit trail

CREATE TABLE IF NOT EXISTS manager_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    type ENUM('return', 'discount', 'void', 'stock_adjustment') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reference_type VARCHAR(50) NULL COMMENT 'sale, return, product, etc.',
    reference_id INT NULL,
    requested_by INT NOT NULL,
    reviewed_by INT NULL,
    amount DECIMAL(12,2) NULL DEFAULT 0,
    reason TEXT NULL,
    manager_note TEXT NULL,
    payload JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_mgr_approvals_store_status (store_id, status),
    INDEX idx_mgr_approvals_type (type),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cashier_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    opening_float DECIMAL(12,2) NOT NULL DEFAULT 0,
    expected_cash DECIMAL(12,2) NULL,
    counted_cash DECIMAL(12,2) NULL,
    variance DECIMAL(12,2) NULL,
    total_sales DECIMAL(12,2) NULL DEFAULT 0,
    transaction_count INT NULL DEFAULT 0,
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL,
    notes TEXT NULL,
    INDEX idx_shifts_store_status (store_id, status),
    INDEX idx_shifts_user (user_id),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS manager_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_id INT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_store (store_id),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_created (created_at),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
