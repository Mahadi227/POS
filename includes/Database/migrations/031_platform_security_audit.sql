-- Platform security & audit instrumentation (SaaS Phase 13)

CREATE TABLE IF NOT EXISTS platform_login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    platform_user_id BIGINT UNSIGNED NULL,
    status ENUM('success','failed','locked') NOT NULL DEFAULT 'failed',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pla_email (email),
    KEY idx_pla_status (status),
    KEY idx_pla_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_security_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(64) NOT NULL,
    severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
    platform_user_id BIGINT UNSIGNED NULL,
    email VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    details_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pse_type (event_type),
    KEY idx_pse_severity (severity),
    KEY idx_pse_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
