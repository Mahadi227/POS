-- E-commerce storefront (SaaS Phase 15)

CREATE TABLE IF NOT EXISTS brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    store_id INT NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    logo_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_brand_tenant_slug (tenant_id, slug),
    KEY idx_brands_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ecommerce_storefront_accounts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    customer_id INT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(32) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    UNIQUE KEY uq_esa_tenant_email (tenant_id, email),
    KEY idx_esa_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ecommerce_wishlist_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    session_key VARCHAR(64) NULL,
    product_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ewi_account (account_id),
    KEY idx_ewi_session (session_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ecommerce_blog_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(160) NOT NULL,
    title VARCHAR(255) NOT NULL,
    excerpt TEXT NULL,
    body TEXT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ebp_tenant_slug (tenant_id, slug),
    KEY idx_ebp_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ecommerce_settings (
    tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    default_store_id INT NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'EUR',
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add columns if missing (idempotent via migrator checks)
