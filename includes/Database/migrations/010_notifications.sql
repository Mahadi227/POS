-- 010_notifications.sql — Enterprise Notification Management System

CREATE TABLE IF NOT EXISTS notification_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL UNIQUE,
    name_en VARCHAR(80) NOT NULL,
    name_fr VARCHAR(80) NOT NULL,
  icon VARCHAR(40) NULL DEFAULT 'notifications',
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_categories (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    module VARCHAR(40) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    name_fr VARCHAR(100) NOT NULL,
    icon VARCHAR(40) NULL DEFAULT 'folder',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_channels (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(30) NOT NULL UNIQUE,
    name_en VARCHAR(60) NOT NULL,
    name_fr VARCHAR(60) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    category_id SMALLINT UNSIGNED NULL,
    type_slug VARCHAR(40) NOT NULL DEFAULT 'info',
    title_en VARCHAR(200) NOT NULL,
    title_fr VARCHAR(200) NOT NULL,
    body_en TEXT NOT NULL,
    body_fr TEXT NOT NULL,
    default_priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    default_channels JSON NULL,
    variables JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES notification_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    user_id INT NOT NULL,
    template_slug VARCHAR(100) NULL,
    type_slug VARCHAR(40) NOT NULL DEFAULT 'info',
    category_slug VARCHAR(50) NOT NULL,
    module VARCHAR(40) NOT NULL DEFAULT 'system',
    priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal',
    severity ENUM('info','success','warning','error','critical') NOT NULL DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    payload JSON NULL,
    action_url VARCHAR(500) NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT UNSIGNED NULL,
    store_id INT UNSIGNED NULL,
    branch_id INT UNSIGNED NULL,
    warehouse_id INT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at TIMESTAMP NULL,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    archived_at TIMESTAMP NULL,
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    pinned_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    UNIQUE KEY uq_notifications_uuid (uuid),
    INDEX idx_notif_user (user_id, is_read, is_archived, deleted_at),
    INDEX idx_notif_user_created (user_id, created_at),
    INDEX idx_notif_category (category_slug),
    INDEX idx_notif_priority (priority, severity),
    INDEX idx_notif_store (store_id),
    INDEX idx_notif_warehouse (warehouse_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id INT NOT NULL PRIMARY KEY,
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
    push_enabled TINYINT(1) NOT NULL DEFAULT 1,
    whatsapp_enabled TINYINT(1) NOT NULL DEFAULT 0,
    whatsapp_phone VARCHAR(20) NULL,
    browser_enabled TINYINT(1) NOT NULL DEFAULT 1,
    sound_enabled TINYINT(1) NOT NULL DEFAULT 1,
    quiet_hours_start TIME NULL,
    quiet_hours_end TIME NULL,
    min_priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'low',
    language VARCHAR(5) NOT NULL DEFAULT 'en',
    category_filters JSON NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    notification_id BIGINT NULL,
    user_id INT NOT NULL,
    channel_slug VARCHAR(30) NOT NULL,
    recipient VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    body TEXT NOT NULL,
    payload JSON NULL,
    status ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    error_message TEXT NULL,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nq_status (status, scheduled_at),
    INDEX idx_nq_user (user_id),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    notification_id BIGINT NULL,
    user_id INT NULL,
    channel_slug VARCHAR(30) NULL,
    action VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'success',
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nl_notif (notification_id),
    INDEX idx_nl_user (user_id),
    INDEX idx_nl_action (action),
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO notification_types (slug, name_en, name_fr, icon, sort_order) VALUES
('info', 'Information', 'Information', 'info', 1),
('success', 'Success', 'Succès', 'check_circle', 2),
('warning', 'Warning', 'Avertissement', 'warning_amber', 3),
('error', 'Error', 'Erreur', 'error_outline', 4),
('critical', 'Critical', 'Critique', 'report', 5),
('reminder', 'Reminder', 'Rappel', 'schedule', 6),
('approval', 'Approval Required', 'Approbation requise', 'pending_actions', 7),
('announcement', 'Announcement', 'Annonce', 'campaign', 8),
('system', 'System Alert', 'Alerte système', 'settings', 9);

INSERT IGNORE INTO notification_channels (slug, name_en, name_fr) VALUES
('in_app', 'In-App', 'Dans l''application'),
('browser', 'Browser', 'Navigateur'),
('push', 'Push (PWA)', 'Push (PWA)'),
('email', 'Email', 'Courriel'),
('sms', 'SMS', 'SMS'),
('whatsapp', 'WhatsApp', 'WhatsApp'),
('webhook', 'Webhook', 'Webhook');

INSERT IGNORE INTO notification_categories (slug, module, name_en, name_fr, icon, sort_order) VALUES
('inventory_low_stock', 'inventory', 'Low Stock', 'Stock faible', 'inventory_2', 10),
('inventory_out_of_stock', 'inventory', 'Out of Stock', 'Rupture de stock', 'remove_shopping_cart', 11),
('inventory_adjustment', 'inventory', 'Stock Adjustment', 'Ajustement de stock', 'tune', 12),
('inventory_expired', 'inventory', 'Expired Products', 'Produits expirés', 'event_busy', 13),
('warehouse_transfer', 'warehouse', 'Transfer', 'Transfert', 'swap_horiz', 20),
('warehouse_receiving', 'warehouse', 'Receiving', 'Réception', 'local_shipping', 21),
('warehouse_dispatch', 'warehouse', 'Dispatch', 'Expédition', 'outbound', 22),
('pos_sale', 'pos', 'Sales', 'Ventes', 'point_of_sale', 30),
('pos_refund', 'pos', 'Refunds', 'Remboursements', 'undo', 31),
('accounting_expense', 'accounting', 'Expenses', 'Dépenses', 'receipt_long', 40),
('accounting_invoice', 'accounting', 'Invoices', 'Factures', 'description', 41),
('cash_register', 'cash_register', 'Cash Register', 'Caisse', 'payments', 50),
('user_management', 'users', 'User Management', 'Gestion utilisateurs', 'people', 60),
('system', 'system', 'System', 'Système', 'settings', 70),
('purchase', 'purchasing', 'Purchase Orders', 'Bons de commande', 'shopping_bag', 80),
('security', 'system', 'Security', 'Sécurité', 'security', 90);

INSERT IGNORE INTO notification_templates (slug, category_id, type_slug, title_en, title_fr, body_en, body_fr, default_priority, default_channels) VALUES
('inventory.low_stock', (SELECT id FROM notification_categories WHERE slug='inventory_low_stock' LIMIT 1), 'warning', 'Low Stock Alert', 'Alerte stock faible', 'Low stock detected for {product} ({qty} remaining).', 'Le stock du produit {product} est faible ({qty} restants).', 'high', '["in_app","browser"]'),
('inventory.out_of_stock', (SELECT id FROM notification_categories WHERE slug='inventory_out_of_stock' LIMIT 1), 'critical', 'Out of Stock', 'Rupture de stock', '{product} is out of stock.', '{product} est en rupture de stock.', 'critical', '["in_app","email"]'),
('warehouse.transfer_request', (SELECT id FROM notification_categories WHERE slug='warehouse_transfer' LIMIT 1), 'approval', 'Transfer Request', 'Demande de transfert', 'Transfer {reference} requires approval.', 'Le transfert {reference} nécessite une approbation.', 'high', '["in_app"]'),
('warehouse.transfer_approved', (SELECT id FROM notification_categories WHERE slug='warehouse_transfer' LIMIT 1), 'success', 'Transfer Approved', 'Transfert approuvé', 'Transfer {reference} has been approved.', 'Le transfert {reference} a été approuvé.', 'normal', '["in_app"]'),
('warehouse.transfer_rejected', (SELECT id FROM notification_categories WHERE slug='warehouse_transfer' LIMIT 1), 'warning', 'Transfer Rejected', 'Transfert rejeté', 'Transfer {reference} was rejected.', 'Le transfert {reference} a été rejeté.', 'high', '["in_app"]'),
('warehouse.receiving_completed', (SELECT id FROM notification_categories WHERE slug='warehouse_receiving' LIMIT 1), 'success', 'Receiving Completed', 'Réception terminée', 'Goods receipt {reference} completed.', 'Réception {reference} terminée.', 'normal', '["in_app"]'),
('warehouse.dispatch_completed', (SELECT id FROM notification_categories WHERE slug='warehouse_dispatch' LIMIT 1), 'success', 'Dispatch Completed', 'Expédition terminée', 'Dispatch {reference} completed.', 'Expédition {reference} terminée.', 'normal', '["in_app"]'),
('pos.sale_completed', (SELECT id FROM notification_categories WHERE slug='pos_sale' LIMIT 1), 'success', 'Sale Completed', 'Vente terminée', 'Sale {reference} completed for {amount}.', 'Vente {reference} terminée pour {amount}.', 'low', '["in_app"]'),
('pos.large_sale', (SELECT id FROM notification_categories WHERE slug='pos_sale' LIMIT 1), 'info', 'Large Sale', 'Vente importante', 'Large sale of {amount} recorded.', 'Vente importante de {amount} enregistrée.', 'normal', '["in_app"]'),
('pos.refund_completed', (SELECT id FROM notification_categories WHERE slug='pos_refund' LIMIT 1), 'warning', 'Refund Completed', 'Remboursement effectué', 'Refund of {amount} processed.', 'Remboursement de {amount} traité.', 'normal', '["in_app"]'),
('accounting.expense_added', (SELECT id FROM notification_categories WHERE slug='accounting_expense' LIMIT 1), 'info', 'Expense Added', 'Dépense ajoutée', 'New expense of {amount} added.', 'Nouvelle dépense de {amount} ajoutée.', 'normal', '["in_app"]'),
('accounting.invoice_overdue', (SELECT id FROM notification_categories WHERE slug='accounting_invoice' LIMIT 1), 'warning', 'Invoice Overdue', 'Facture en retard', 'Invoice {reference} is overdue.', 'La facture {reference} est en retard.', 'high', '["in_app","email"]'),
('cash_register.opened', (SELECT id FROM notification_categories WHERE slug='cash_register' LIMIT 1), 'info', 'Register Opened', 'Caisse ouverte', 'Register {register} opened with {amount}.', 'Caisse {register} ouverte avec {amount}.', 'normal', '["in_app"]'),
('cash_register.closed', (SELECT id FROM notification_categories WHERE slug='cash_register' LIMIT 1), 'info', 'Register Closed', 'Caisse fermée', 'Register {register} closed (variance: {variance}).', 'Caisse {register} fermée (écart: {variance}).', 'normal', '["in_app"]'),
('cash_register.cash_difference', (SELECT id FROM notification_categories WHERE slug='cash_register' LIMIT 1), 'warning', 'Cash Difference', 'Écart de caisse', 'Cash difference of {variance} detected.', 'Écart de caisse de {variance} détecté.', 'high', '["in_app","browser"]'),
('cash_register.reconciliation', (SELECT id FROM notification_categories WHERE slug='cash_register' LIMIT 1), 'approval', 'Reconciliation Required', 'Rapprochement requis', 'Cash reconciliation required for {register}.', 'Rapprochement de caisse requis pour {register}.', 'high', '["in_app"]'),
('users.role_changed', (SELECT id FROM notification_categories WHERE slug='user_management' LIMIT 1), 'info', 'Role Changed', 'Rôle modifié', 'Your role was changed to {role}.', 'Votre rôle a été modifié en {role}.', 'high', '["in_app","email"]'),
('users.login_failed', (SELECT id FROM notification_categories WHERE slug='security' LIMIT 1), 'warning', 'Failed Login', 'Échec de connexion', 'Failed login attempt on your account.', 'Tentative de connexion échouée sur votre compte.', 'high', '["in_app","email"]'),
('users.account_locked', (SELECT id FROM notification_categories WHERE slug='security' LIMIT 1), 'critical', 'Account Locked', 'Compte verrouillé', 'Your account has been locked.', 'Votre compte a été verrouillé.', 'critical', '["in_app","email","sms"]'),
('system.backup_completed', (SELECT id FROM notification_categories WHERE slug='system' LIMIT 1), 'success', 'Backup Completed', 'Sauvegarde terminée', 'System backup completed successfully.', 'Sauvegarde système terminée avec succès.', 'low', '["in_app"]'),
('system.backup_failed', (SELECT id FROM notification_categories WHERE slug='system' LIMIT 1), 'critical', 'Backup Failed', 'Échec de sauvegarde', 'System backup failed.', 'Échec de la sauvegarde système.', 'critical', '["in_app","email"]'),
('system.offline_sync', (SELECT id FROM notification_categories WHERE slug='system' LIMIT 1), 'info', 'Offline Sync', 'Synchronisation hors ligne', 'Offline data synchronized.', 'Données hors ligne synchronisées.', 'low', '["in_app"]'),
('system.security_alert', (SELECT id FROM notification_categories WHERE slug='security' LIMIT 1), 'critical', 'Security Alert', 'Alerte de sécurité', '{message}', '{message}', 'critical', '["in_app","email"]');
