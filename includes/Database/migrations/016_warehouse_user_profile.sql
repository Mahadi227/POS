-- 016_warehouse_user_profile.sql — Warehouse portal employee profile extensions

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INT NOT NULL PRIMARY KEY,
    theme ENUM('light', 'dark', 'system') NOT NULL DEFAULT 'system',
    date_format VARCHAR(20) NOT NULL DEFAULT 'Y-m-d',
    time_format ENUM('12h', '24h') NOT NULL DEFAULT '24h',
    items_per_page INT NOT NULL DEFAULT 50,
    dashboard_layout ENUM('compact', 'standard', 'expanded') NOT NULL DEFAULT 'standard',
    default_warehouse_view ENUM('assigned', 'all') NOT NULL DEFAULT 'assigned',
    warehouse_notif_dashboard TINYINT(1) NOT NULL DEFAULT 1,
    warehouse_notif_low_stock TINYINT(1) NOT NULL DEFAULT 1,
    warehouse_notif_transfer TINYINT(1) NOT NULL DEFAULT 1,
    warehouse_notif_receiving TINYINT(1) NOT NULL DEFAULT 1,
    warehouse_notif_dispatch TINYINT(1) NOT NULL DEFAULT 1,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
