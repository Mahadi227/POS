-- 018_warehouse_help.sql — Warehouse Help & Support Center

CREATE TABLE IF NOT EXISTS help_categories (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL UNIQUE,
    icon VARCHAR(40) NOT NULL DEFAULT 'help',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    name_en VARCHAR(120) NOT NULL,
    name_fr VARCHAR(120) NOT NULL,
    roles JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id SMALLINT UNSIGNED NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    article_type ENUM('article', 'guide', 'manual') NOT NULL DEFAULT 'article',
    title_en VARCHAR(200) NOT NULL,
    title_fr VARCHAR(200) NOT NULL,
    summary_en TEXT NULL,
    summary_fr TEXT NULL,
    body_en MEDIUMTEXT NOT NULL,
    body_fr MEDIUMTEXT NOT NULL,
    module VARCHAR(40) NULL,
    roles JSON NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ha_category (category_id),
    INDEX idx_ha_module (module),
    FOREIGN KEY (category_id) REFERENCES help_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_faq (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id SMALLINT UNSIGNED NULL,
    question_en VARCHAR(255) NOT NULL,
    question_fr VARCHAR(255) NOT NULL,
    answer_en TEXT NOT NULL,
    answer_fr TEXT NOT NULL,
    roles JSON NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES help_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_tutorial_videos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id SMALLINT UNSIGNED NULL,
    title_en VARCHAR(200) NOT NULL,
    title_fr VARCHAR(200) NOT NULL,
    description_en TEXT NULL,
    description_fr TEXT NULL,
    video_type ENUM('youtube', 'hosted') NOT NULL DEFAULT 'youtube',
    video_url VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(500) NULL,
    duration_seconds INT UNSIGNED NULL,
    roles JSON NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES help_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_support_tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    warehouse_id INT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL,
    role_slug VARCHAR(60) NULL,
    subject VARCHAR(200) NOT NULL,
    category VARCHAR(60) NOT NULL DEFAULT 'general',
    priority ENUM('low', 'normal', 'high', 'critical') NOT NULL DEFAULT 'normal',
    description TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    ticket_type ENUM('support', 'problem') NOT NULL DEFAULT 'support',
    problem_type VARCHAR(60) NULL,
    status ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hst_user (user_id),
    INDEX idx_hst_status (status),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_ticket_replies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    user_id INT NULL,
    message TEXT NOT NULL,
    is_staff TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES help_support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_system_updates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(30) NOT NULL,
    title_en VARCHAR(200) NOT NULL,
    title_fr VARCHAR(200) NOT NULL,
    body_en TEXT NOT NULL,
    body_fr TEXT NOT NULL,
    update_type ENUM('feature', 'improvement', 'bugfix', 'maintenance') NOT NULL DEFAULT 'feature',
    published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_published TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
