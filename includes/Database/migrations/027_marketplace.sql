-- 027 — Marketplace apps catalog (reference; applied via SaaSPhase9Migrator)

CREATE TABLE IF NOT EXISTS marketplace_apps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    short_description VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NULL,
    category ENUM('payments','developer','branding','analytics','shipping','other') NOT NULL DEFAULT 'other',
    icon VARCHAR(64) NOT NULL DEFAULT 'extension',
    vendor VARCHAR(128) NOT NULL DEFAULT 'RetailPOS',
    status ENUM('published','draft','deprecated') NOT NULL DEFAULT 'published',
    is_official TINYINT(1) NOT NULL DEFAULT 0,
    pricing ENUM('free','paid','contact') NOT NULL DEFAULT 'free',
    website_url VARCHAR(512) NULL,
    docs_url VARCHAR(512) NULL,
    modules_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_marketplace_slug (slug),
    KEY idx_marketplace_category (category),
    KEY idx_marketplace_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenant_marketplace_installs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    app_id BIGINT UNSIGNED NOT NULL,
    status ENUM('active','removed') NOT NULL DEFAULT 'active',
    installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    removed_at DATETIME NULL,
    UNIQUE KEY uq_tmi_tenant_app (tenant_id, app_id),
    KEY idx_tmi_app (app_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
