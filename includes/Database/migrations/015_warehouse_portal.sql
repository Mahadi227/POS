-- 015_warehouse_portal.sql — Warehouse portal tasks, extended roles, location hierarchy

CREATE TABLE IF NOT EXISTS warehouse_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    zone_code VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_wh_zone (warehouse_id, zone_code),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_aisles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    aisle_code VARCHAR(50) NOT NULL,
    name VARCHAR(120) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_zone_aisle (zone_id, aisle_code),
    FOREIGN KEY (zone_id) REFERENCES warehouse_zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_racks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aisle_id INT NOT NULL,
    rack_code VARCHAR(50) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_aisle_rack (aisle_id, rack_code),
    FOREIGN KEY (aisle_id) REFERENCES warehouse_aisles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_shelves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rack_id INT NOT NULL,
    shelf_code VARCHAR(50) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rack_shelf (rack_id, shelf_code),
    FOREIGN KEY (rack_id) REFERENCES warehouse_racks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_bins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shelf_id INT NOT NULL,
    bin_code VARCHAR(50) NOT NULL,
    capacity_units INT NULL DEFAULT 0,
    status ENUM('active', 'inactive', 'full') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_shelf_bin (shelf_id, bin_code),
    FOREIGN KEY (shelf_id) REFERENCES warehouse_shelves(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS warehouse_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    task_type ENUM(
        'receiving', 'dispatch', 'transfer', 'inventory_count',
        'inspection', 'approval', 'picking', 'packing', 'shipping', 'other'
    ) NOT NULL DEFAULT 'other',
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    reference_type VARCHAR(60) NULL,
    reference_id INT NULL,
    assigned_to INT NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    due_date DATE NULL,
    completed_at TIMESTAMP NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wt_warehouse_status (warehouse_id, status),
    INDEX idx_wt_assigned (assigned_to, status),
    INDEX idx_wt_due (due_date, status),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventory_counts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    count_number VARCHAR(50) NOT NULL,
    count_type ENUM('cycle', 'full', 'spot') NOT NULL DEFAULT 'cycle',
    status ENUM('draft', 'in_progress', 'submitted', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    scheduled_date DATE NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_by INT NULL,
    approved_by INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_count_number (count_number),
    INDEX idx_ic_wh_status (warehouse_id, status),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roles (id, name, description) VALUES
(12, 'Warehouse Auditor', 'Read-only warehouse audits and reports'),
(13, 'Storekeeper', 'Warehouse inventory operations');

INSERT IGNORE INTO permissions (slug, description) VALUES
('warehouse.audit', 'Warehouse audit read-only access'),
('warehouse.tasks', 'Manage warehouse tasks');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE slug IN (
    'manage_warehouse','manage_inventory','warehouse.receive','warehouse.dispatch',
    'warehouse.inventory','warehouse.tasks','approve_transfers'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions WHERE slug IN ('warehouse.inventory','manage_inventory','inventory.view','inventory.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions WHERE slug IN ('warehouse.receive','warehouse.inventory','inventory.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 9, id FROM permissions WHERE slug IN ('warehouse.dispatch','warehouse.inventory','inventory.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 12, id FROM permissions WHERE slug IN ('warehouse.audit','warehouse.inventory','inventory.view','reports.view','dashboard.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 13, id FROM permissions WHERE slug IN ('warehouse.inventory','manage_inventory','inventory.view','inventory.manage');
