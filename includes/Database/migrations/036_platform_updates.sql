-- 036 — Platform software releases and changelog

CREATE TABLE IF NOT EXISTS platform_releases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(32) NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary VARCHAR(512) NOT NULL DEFAULT '',
    changelog TEXT NULL,
    release_type ENUM('major','minor','patch','hotfix','migration') NOT NULL DEFAULT 'minor',
    status ENUM('draft','scheduled','released','rolled_back') NOT NULL DEFAULT 'draft',
    migration_version VARCHAR(64) NULL,
    requires_maintenance TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    released_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_platform_release_version (version),
    KEY idx_platform_release_status (status),
    KEY idx_platform_release_type (release_type),
    KEY idx_platform_release_published (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
