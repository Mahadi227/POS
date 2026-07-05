-- 026 — Tenant license keys (reference; applied via SaaSPhase8Migrator)

CREATE TABLE IF NOT EXISTS tenant_licenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NULL,
    license_key_hash CHAR(64) NOT NULL,
    key_prefix VARCHAR(16) NOT NULL,
    license_type ENUM('cloud','on_prem','partner','trial') NOT NULL DEFAULT 'cloud',
    status ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',
    plan_code VARCHAR(32) NULL,
    max_seats INT UNSIGNED NULL,
    notes VARCHAR(512) NULL,
    issued_by BIGINT UNSIGNED NULL,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_license_hash (license_key_hash),
    KEY idx_license_tenant (tenant_id),
    KEY idx_license_status (status),
    KEY idx_license_type (license_type),
    KEY idx_license_prefix (key_prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
