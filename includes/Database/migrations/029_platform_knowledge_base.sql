-- Platform knowledge base (SaaS Phase 11)
CREATE TABLE IF NOT EXISTS platform_kb_categories (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL,
    icon VARCHAR(40) NOT NULL DEFAULT 'menu_book',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    name_en VARCHAR(120) NOT NULL,
    name_fr VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_pkb_cat_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS platform_kb_articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id SMALLINT UNSIGNED NOT NULL,
    slug VARCHAR(80) NOT NULL,
    article_type ENUM('article','guide','faq') NOT NULL DEFAULT 'article',
    title_en VARCHAR(200) NOT NULL,
    title_fr VARCHAR(200) NOT NULL,
    summary_en TEXT NULL,
    summary_fr TEXT NULL,
    body_en MEDIUMTEXT NOT NULL,
    body_fr MEDIUMTEXT NOT NULL,
    audience ENUM('tenant','support','public') NOT NULL DEFAULT 'tenant',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pkb_article_slug (slug),
    KEY idx_pkb_article_cat (category_id),
    KEY idx_pkb_article_pub (is_published),
    CONSTRAINT fk_pkb_article_cat FOREIGN KEY (category_id) REFERENCES platform_kb_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
