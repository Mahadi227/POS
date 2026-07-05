-- Platform support tickets (SaaS Phase 10)
CREATE TABLE IF NOT EXISTS platform_support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(24) NOT NULL,
    tenant_id BIGINT UNSIGNED NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status ENUM('open','in_progress','waiting','resolved','closed') NOT NULL DEFAULT 'open',
    priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
    category ENUM('billing','technical','onboarding','account','other') NOT NULL DEFAULT 'other',
    assigned_to BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pst_number (ticket_number),
    KEY idx_pst_status (status),
    KEY idx_pst_tenant (tenant_id),
    KEY idx_pst_assigned (assigned_to),
    KEY idx_pst_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_support_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    platform_user_id BIGINT UNSIGNED NULL,
    message TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_psr_ticket (ticket_id),
    CONSTRAINT fk_psr_ticket FOREIGN KEY (ticket_id) REFERENCES platform_support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
