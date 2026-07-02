-- 019 — SaaS tenant foundation (reference; applied via TenantSchemaMigrator)
-- Run: php tools/migrate.php

CREATE TABLE IF NOT EXISTS tenants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    slug VARCHAR(63) NOT NULL,
    name VARCHAR(255) NOT NULL,
    country_code CHAR(2) NOT NULL DEFAULT 'SN',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Dakar',
    default_currency VARCHAR(8) NOT NULL DEFAULT 'XOF',
    status ENUM('trial','active','suspended','cancelled') NOT NULL DEFAULT 'active',
    settings_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_tenants_uuid (uuid),
    UNIQUE KEY uq_tenants_slug (slug),
    KEY idx_tenants_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscription_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(128) NOT NULL,
    max_stores INT UNSIGNED NULL,
    max_users INT UNSIGNED NULL,
    modules_json JSON NOT NULL,
    price_monthly DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'EUR',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_plans_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('platform_admin','support') NOT NULL DEFAULT 'platform_admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_platform_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(32) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
