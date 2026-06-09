-- Permissions par défaut (exécuter une fois dans phpMyAdmin)

INSERT IGNORE INTO permissions (name, description) VALUES
('dashboard.view', 'Voir le tableau de bord'),
('sales.view', 'Voir les ventes'),
('sales.manage', 'Gérer les ventes'),
('inventory.view', 'Voir inventaire'),
('inventory.manage', 'Gérer inventaire'),
('stores.manage', 'Gérer succursales'),
('users.manage', 'Gérer utilisateurs'),
('pos.access', 'Accès caisse'),
('reports.view', 'Voir rapports');

-- Admin (role_id 2)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE name IN (
  'dashboard.view','sales.view','sales.manage','inventory.view','inventory.manage',
  'stores.manage','pos.access','reports.view'
);

-- Manager (role_id 3)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE name IN (
  'dashboard.view','sales.view','inventory.view','inventory.manage','pos.access','reports.view'
);

-- Cashier (role_id 4)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE name IN ('pos.access','sales.view');

-- Staff (role_id 5)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE name IN ('inventory.view','pos.access');
