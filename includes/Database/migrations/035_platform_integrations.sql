-- 035 — Platform integration providers and tenant connections

CREATE TABLE IF NOT EXISTS platform_integration_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    short_description VARCHAR(255) NOT NULL DEFAULT '',
    category ENUM('payments','communications','developer','analytics','shipping','other') NOT NULL DEFAULT 'other',
    icon VARCHAR(64) NOT NULL DEFAULT 'hub',
    brand_color VARCHAR(16) NOT NULL DEFAULT '#6366f1',
    status ENUM('enabled','disabled') NOT NULL DEFAULT 'enabled',
    is_official TINYINT(1) NOT NULL DEFAULT 1,
    docs_url VARCHAR(512) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_integration_provider_slug (slug),
    KEY idx_integration_provider_category (category),
    KEY idx_integration_provider_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_integrations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    provider_id BIGINT UNSIGNED NOT NULL,
    status ENUM('connected','disconnected','pending','error') NOT NULL DEFAULT 'pending',
    external_ref VARCHAR(128) NULL,
    notes VARCHAR(255) NULL,
    last_sync_at DATETIME NULL,
    error_message VARCHAR(512) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_integration (tenant_id, provider_id),
    KEY idx_ti_provider (provider_id),
    KEY idx_ti_status (status),
    KEY idx_ti_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
