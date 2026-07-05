-- Platform settings and application logs (SaaS Phase 14)

CREATE TABLE IF NOT EXISTS platform_settings (
    key_name VARCHAR(64) NOT NULL PRIMARY KEY,
    value_json JSON NOT NULL,
    category VARCHAR(32) NOT NULL DEFAULT 'general',
    description VARCHAR(255) NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ps_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_application_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level ENUM('debug','info','warning','error','critical') NOT NULL DEFAULT 'info',
    channel VARCHAR(64) NOT NULL,
    message TEXT NOT NULL,
    context_json JSON NULL,
    tenant_id BIGINT UNSIGNED NULL,
    platform_user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_papl_level (level),
    KEY idx_papl_channel (channel),
    KEY idx_papl_created (created_at),
    KEY idx_papl_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO platform_settings (key_name, value_json, category, description) VALUES
('product_name', '"RetailPOS Cloud"', 'general', 'Platform product display name'),
('support_email', '"support@retailpos.local"', 'general', 'Support contact email'),
('default_locale', '"en"', 'general', 'Default locale for new tenants'),
('lockout_threshold', '5', 'security', 'Failed login attempts before lockout'),
('lockout_window_minutes', '15', 'security', 'Lockout window in minutes'),
('email_from', '"noreply@retailpos.local"', 'communications', 'Default transactional email sender'),
('trial_days', '14', 'billing', 'Default trial period in days');

INSERT IGNORE INTO platform_application_logs (level, channel, message, context_json) VALUES
('info', 'provisioning', 'Platform console modules initialized', '{"phase":14}'),
('info', 'migration', 'SaaS Phase 14 schema applied', '{"version":"032_saas_phase14"}'),
('warning', 'webhook', 'Sample webhook retry scheduled', '{"endpoint":"demo","attempt":2}');
