-- Platform backup jobs and snapshots
CREATE TABLE IF NOT EXISTS platform_backups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(191) NOT NULL,
    scope ENUM('full', 'schema', 'tenant') NOT NULL DEFAULT 'full',
    tenant_id BIGINT UNSIGNED NULL,
    status ENUM('pending', 'running', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    size_bytes BIGINT UNSIGNED NULL,
    file_path VARCHAR(512) NULL,
    storage VARCHAR(32) NOT NULL DEFAULT 'local',
    triggered_by BIGINT UNSIGNED NULL,
    error_message TEXT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pb_status (status),
    KEY idx_pb_created (created_at),
    KEY idx_pb_tenant (tenant_id),
    KEY idx_pb_triggered (triggered_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
