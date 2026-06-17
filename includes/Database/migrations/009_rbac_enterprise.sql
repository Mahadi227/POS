-- Enterprise RBAC upgrade (run once or via RbacSchemaMigrator)

-- Extend roles
INSERT IGNORE INTO roles (id, name, description) VALUES
(1, 'Super Admin', 'Full system access'),
(2, 'Admin', 'Store administration'),
(3, 'Manager', 'Branch supervision'),
(4, 'Cashier', 'Point of sale'),
(5, 'Staff', 'Limited staff access'),
(6, 'Warehouse Manager', 'Warehouse operations lead'),
(7, 'Inventory Officer', 'Inventory and stock control'),
(8, 'Receiving Officer', 'Goods receipt operations'),
(9, 'Dispatch Officer', 'Stock dispatch operations'),
(10, 'Accountant', 'Accounting and finance'),
(11, 'Customer', 'Customer portal (future)');

-- Enterprise permissions (dot + legacy names)
INSERT IGNORE INTO permissions (name, description) VALUES
('manage_users', 'Manage users and roles'),
('manage_products', 'Manage product catalog'),
('manage_sales', 'Manage sales'),
('manage_inventory', 'Manage inventory'),
('manage_warehouse', 'Manage warehouse operations'),
('manage_accounting', 'Manage accounting'),
('manage_reports', 'View and export reports'),
('manage_cash_register', 'Manage cash registers'),
('manage_settings', 'System settings'),
('view_dashboard', 'Access dashboards'),
('approve_transfers', 'Approve stock transfers'),
('approve_expenses', 'Approve expenses'),
('dashboard.view', 'View dashboard'),
('sales.view', 'View sales'),
('sales.manage', 'Manage sales'),
('inventory.view', 'View inventory'),
('inventory.manage', 'Manage inventory'),
('stores.manage', 'Manage stores/branches'),
('users.manage', 'Manage users'),
('pos.access', 'POS access'),
('reports.view', 'View reports'),
('warehouse.receive', 'Receive goods'),
('warehouse.dispatch', 'Dispatch stock'),
('warehouse.inventory', 'Warehouse inventory');

-- Super Admin — all permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Admin
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE name IN (
  'manage_users','manage_products','manage_sales','manage_inventory','manage_warehouse',
  'manage_reports','manage_cash_register','manage_settings','view_dashboard',
  'approve_transfers','dashboard.view','sales.view','sales.manage','inventory.view',
  'inventory.manage','stores.manage','users.manage','pos.access','reports.view'
);

-- Manager
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE name IN (
  'view_dashboard','manage_sales','manage_inventory','manage_reports','approve_transfers',
  'approve_expenses','dashboard.view','sales.view','inventory.view','inventory.manage',
  'pos.access','reports.view'
);

-- Cashier
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE name IN ('view_dashboard','pos.access','sales.view','dashboard.view');

-- Staff
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE name IN ('view_dashboard','pos.access','inventory.view','dashboard.view');

-- Warehouse Manager
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE name IN (
  'view_dashboard','manage_warehouse','manage_inventory','manage_reports','approve_transfers',
  'dashboard.view','inventory.view','inventory.manage','reports.view',
  'warehouse.receive','warehouse.dispatch','warehouse.inventory'
);

-- Inventory Officer
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions WHERE name IN (
  'view_dashboard','manage_inventory','manage_warehouse','inventory.view','inventory.manage',
  'warehouse.inventory','dashboard.view','reports.view'
);

-- Receiving Officer
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions WHERE name IN (
  'view_dashboard','warehouse.receive','warehouse.inventory','inventory.view','dashboard.view'
);

-- Dispatch Officer
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 9, id FROM permissions WHERE name IN (
  'view_dashboard','warehouse.dispatch','warehouse.inventory','inventory.view','dashboard.view'
);

-- Accountant
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 10, id FROM permissions WHERE name IN (
  'view_dashboard','manage_accounting','manage_reports','approve_expenses',
  'dashboard.view','reports.view'
);

-- Customer
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 11, id FROM permissions WHERE name IN ('view_dashboard','dashboard.view');

-- User permission overrides (optional per-user grants/denies)
CREATE TABLE IF NOT EXISTS user_permissions (
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Security audit trail (auth & RBAC events)
CREATE TABLE IF NOT EXISTS security_audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    browser VARCHAR(120) NULL,
    os_name VARCHAR(80) NULL,
    device_type VARCHAR(40) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sal_user (user_id),
    INDEX idx_sal_action (action),
    INDEX idx_sal_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
