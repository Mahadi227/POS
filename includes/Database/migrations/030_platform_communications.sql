-- Platform communications (SaaS Phase 12)
CREATE TABLE IF NOT EXISTS platform_broadcasts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title_en VARCHAR(200) NOT NULL,
    title_fr VARCHAR(200) NOT NULL,
    message_en TEXT NOT NULL,
    message_fr TEXT NOT NULL,
    audience ENUM('all','active','trial','suspended') NOT NULL DEFAULT 'all',
    status ENUM('draft','sent') NOT NULL DEFAULT 'draft',
    recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pb_status (status),
    KEY idx_pb_audience (audience)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_sms_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    template_key VARCHAR(64) NOT NULL,
    recipient VARCHAR(32) NOT NULL,
    status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    message VARCHAR(320) NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_psl_tenant (tenant_id),
    KEY idx_psl_template (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
