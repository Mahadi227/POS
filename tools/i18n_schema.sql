-- i18n additions and schema for language fields and templates

ALTER TABLE users ADD COLUMN language VARCHAR(8) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(128) NOT NULL UNIQUE,
    `value` TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- set default language (example)
INSERT IGNORE INTO
    system_settings (`key`, `value`)
VALUES ('default_language', 'en');

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    language VARCHAR(8) DEFAULT NULL,
    `type` VARCHAR(64),
    message TEXT,
    payload JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS email_templates (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(128) NOT NULL,
    language VARCHAR(8) NOT NULL,
    subject VARCHAR(255),
    body TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (slug, language)
);

CREATE TABLE IF NOT EXISTS sms_templates (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(128) NOT NULL,
    language VARCHAR(8) NOT NULL,
    body TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (slug, language)
);