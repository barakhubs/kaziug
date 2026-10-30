-- KaziUG schema for MySQL 8.0+ / MariaDB 10.6+. Replaces the earlier PostgreSQL migrations.
-- Timestamps are DATETIME(6) holding UTC (the connection runs with time_zone '+00:00').
-- TEXT and JSON defaults are expressions in parentheses, the only form MySQL accepts for those types.
-- Rollback: DROP TABLE IF EXISTS newsletter_deliveries, newsletters, subscribers, job_status_history,
--   analytics_events, analytics_daily, analytics_salts, jobs, job_categories, admin_users, audit_logs,
--   login_attempts, sessions, settings, site_assets, smtp_settings, social_profiles;

CREATE TABLE admin_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    singleton TINYINT(1) NOT NULL DEFAULT 1,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    session_version INT NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY admin_users_email_key (email),
    UNIQUE KEY admin_users_singleton_key (singleton),
    CONSTRAINT admin_users_singleton_chk CHECK (singleton = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(191) NOT NULL,
    description TEXT NOT NULL DEFAULT (''),
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY job_categories_slug_key (slug),
    KEY job_categories_sort_idx (sort_order, name),
    CONSTRAINT job_categories_slug_check CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    company VARCHAR(200) NOT NULL,
    location VARCHAR(120) NOT NULL,
    is_remote TINYINT(1) NOT NULL DEFAULT 0,
    job_type VARCHAR(20) NOT NULL,
    experience_level VARCHAR(20) NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    salary_min BIGINT NULL,
    salary_max BIGINT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'UGX',
    summary TEXT NOT NULL,
    responsibilities JSON NOT NULL DEFAULT ('[]'),
    requirements JSON NOT NULL DEFAULT ('[]'),
    skills JSON NOT NULL DEFAULT ('[]'),
    apply_url VARCHAR(2048) NULL,
    apply_email VARCHAR(254) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    published_at DATETIME(6) NULL,
    expires_at DATETIME(6) NULL,
    deleted_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    positions INT NOT NULL DEFAULT 1,
    salary_scale VARCHAR(30) NULL,
    application_instructions TEXT NULL,
    reports_to VARCHAR(150) NULL,
    -- Written by the repository from title, skills, company and summary; feeds the FULLTEXT index.
    search_text TEXT NOT NULL DEFAULT (''),
    PRIMARY KEY (id),
    UNIQUE KEY jobs_slug_key (slug),
    KEY jobs_category_id_idx (category_id),
    KEY jobs_published_at_idx (published_at DESC),
    KEY jobs_salary_min_idx (salary_min),
    KEY jobs_status_published_idx (status, published_at DESC),
    FULLTEXT KEY jobs_search_idx (search_text),
    CONSTRAINT jobs_category_id_fkey FOREIGN KEY (category_id) REFERENCES job_categories (id) ON DELETE RESTRICT,
    CONSTRAINT jobs_status_check CHECK (status IN ('draft', 'published', 'closed', 'archived')),
    CONSTRAINT jobs_job_type_check CHECK (job_type IN ('full-time', 'part-time', 'contract', 'internship')),
    CONSTRAINT jobs_experience_level_check CHECK (experience_level IN ('entry', 'mid', 'senior')),
    CONSTRAINT jobs_published_at_chk CHECK (status = 'draft' OR published_at IS NOT NULL),
    CONSTRAINT jobs_positions_chk CHECK (positions BETWEEN 1 AND 10000),
    CONSTRAINT jobs_salary_min_check CHECK (salary_min >= 0),
    CONSTRAINT jobs_salary_max_check CHECK (salary_max >= 0),
    CONSTRAINT jobs_salary_range_chk CHECK (salary_min IS NULL OR salary_max IS NULL OR salary_max >= salary_min),
    CONSTRAINT jobs_salary_amount_or_scale_chk CHECK (salary_scale IS NULL OR (salary_min IS NULL AND salary_max IS NULL)),
    CONSTRAINT jobs_slug_check CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'),
    CONSTRAINT jobs_currency_check CHECK (currency REGEXP '^[A-Z]{3}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_status_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(20) NULL,
    to_status VARCHAR(20) NOT NULL,
    changed_by BIGINT UNSIGNED NULL,
    changed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY job_status_history_job_idx (job_id, changed_at DESC),
    KEY job_status_history_changed_by_idx (changed_by),
    CONSTRAINT job_status_history_job_id_fkey FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE,
    CONSTRAINT job_status_history_changed_by_fkey FOREIGN KEY (changed_by) REFERENCES admin_users (id) ON DELETE SET NULL,
    CONSTRAINT job_status_history_from_status_check CHECK (from_status IN ('draft', 'published', 'closed', 'archived')),
    CONSTRAINT job_status_history_to_status_check CHECK (to_status IN ('draft', 'published', 'closed', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE analytics_daily (
    day DATE NOT NULL,
    dimension VARCHAR(20) NOT NULL,
    `key` VARCHAR(300) NOT NULL,
    pageviews INT NOT NULL DEFAULT 0,
    visitors INT NOT NULL DEFAULT 0,
    applies INT NOT NULL DEFAULT 0,
    searches INT NOT NULL DEFAULT 0,
    zero_results INT NOT NULL DEFAULT 0,
    PRIMARY KEY (day, dimension, `key`),
    KEY analytics_daily_dimension_day_idx (dimension, day),
    CONSTRAINT analytics_daily_dimension_check CHECK (dimension IN ('total', 'path', 'page_type', 'job', 'category', 'referrer', 'utm_source', 'utm_campaign', 'device', 'search', 'filter', 'channel'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE analytics_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    type VARCHAR(20) NOT NULL,
    visitor_hash CHAR(32) NOT NULL,
    path VARCHAR(300) NOT NULL,
    page_type VARCHAR(20) NOT NULL,
    job_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NULL,
    referrer_host VARCHAR(255) NULL,
    utm_source VARCHAR(100) NULL,
    utm_medium VARCHAR(100) NULL,
    utm_campaign VARCHAR(100) NULL,
    device VARCHAR(10) NOT NULL,
    apply_channel VARCHAR(10) NULL,
    search_query VARCHAR(100) NULL,
    search_filters JSON NOT NULL DEFAULT ('[]'),
    result_count INT NULL,
    PRIMARY KEY (id),
    KEY analytics_events_occurred_at_idx (occurred_at),
    KEY analytics_events_job_id_idx (job_id),
    KEY analytics_events_category_id_idx (category_id),
    CONSTRAINT analytics_events_job_id_fkey FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL,
    CONSTRAINT analytics_events_category_id_fkey FOREIGN KEY (category_id) REFERENCES job_categories (id) ON DELETE SET NULL,
    CONSTRAINT analytics_events_type_check CHECK (type IN ('pageview', 'apply_click')),
    CONSTRAINT analytics_events_device_check CHECK (device IN ('mobile', 'tablet', 'desktop')),
    CONSTRAINT analytics_events_page_type_check CHECK (page_type IN ('home', 'category', 'city', 'job', 'page')),
    CONSTRAINT analytics_events_apply_channel_check CHECK (apply_channel IN ('link', 'email')),
    CONSTRAINT analytics_events_apply_channel_chk CHECK ((type = 'apply_click') = (apply_channel IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE analytics_salts (
    day DATE NOT NULL,
    salt VARBINARY(32) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor VARCHAR(254) NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity VARCHAR(100) NOT NULL,
    entity_id VARCHAR(100) NULL,
    metadata JSON NOT NULL DEFAULT ('{}'),
    ip VARCHAR(45) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY audit_logs_action_idx (action),
    KEY audit_logs_created_at_idx (created_at DESC),
    KEY audit_logs_entity_idx (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    `key` VARCHAR(255) NOT NULL,
    failure_count INT NOT NULL DEFAULT 0,
    locked_until DATETIME(6) NULL,
    window_started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`key`),
    KEY login_attempts_updated_at_idx (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscribers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(254) NOT NULL,
    name VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    consent_at DATETIME(6) NULL,
    consent_ip VARCHAR(45) NULL,
    confirm_token_hash VARCHAR(128) NULL,
    confirm_sent_at DATETIME(6) NULL,
    confirmed_at DATETIME(6) NULL,
    unsubscribe_token VARCHAR(128) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    unsubscribed_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY subscribers_email_lower_key (email),
    UNIQUE KEY subscribers_unsubscribe_token_key (unsubscribe_token),
    UNIQUE KEY subscribers_confirm_token_hash_key (confirm_token_hash),
    KEY subscribers_status_idx (status),
    KEY subscribers_created_at_idx (created_at DESC),
    CONSTRAINT subscribers_status_check CHECK (status IN ('pending', 'active', 'unsubscribed', 'bounced'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subject VARCHAR(200) NOT NULL,
    preheader VARCHAR(255) NOT NULL DEFAULT '',
    body_html MEDIUMTEXT NOT NULL DEFAULT (''),
    body_text MEDIUMTEXT NOT NULL DEFAULT (''),
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    scheduled_at DATETIME(6) NULL,
    sent_at DATETIME(6) NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY newsletters_created_by_idx (created_by),
    KEY newsletters_status_scheduled_idx (status, scheduled_at),
    CONSTRAINT newsletters_created_by_fkey FOREIGN KEY (created_by) REFERENCES admin_users (id) ON DELETE SET NULL,
    CONSTRAINT newsletters_status_check CHECK (status IN ('draft', 'scheduled', 'sending', 'sent', 'failed')),
    CONSTRAINT newsletters_scheduled_chk CHECK (status <> 'scheduled' OR scheduled_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    newsletter_id BIGINT UNSIGNED NOT NULL,
    subscriber_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    attempts INT NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    next_attempt_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    sent_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY newsletter_deliveries_unique (newsletter_id, subscriber_id),
    KEY newsletter_deliveries_due_idx (status, next_attempt_at),
    KEY newsletter_deliveries_newsletter_status_idx (newsletter_id, status),
    KEY newsletter_deliveries_subscriber_idx (subscriber_id),
    CONSTRAINT newsletter_deliveries_newsletter_id_fkey FOREIGN KEY (newsletter_id) REFERENCES newsletters (id) ON DELETE CASCADE,
    CONSTRAINT newsletter_deliveries_subscriber_id_fkey FOREIGN KEY (subscriber_id) REFERENCES subscribers (id) ON DELETE CASCADE,
    CONSTRAINT newsletter_deliveries_status_check CHECK (status IN ('queued', 'sent', 'failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
    id VARCHAR(128) NOT NULL,
    payload MEDIUMTEXT NOT NULL,
    last_activity DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY sessions_last_activity_idx (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    `key` VARCHAR(100) NOT NULL,
    value JSON NOT NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_assets (
    `key` VARCHAR(100) NOT NULL,
    mime_type VARCHAR(20) NOT NULL,
    content LONGBLOB NOT NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`key`),
    CONSTRAINT site_assets_mime_type_check CHECK (mime_type IN ('image/png', 'image/jpeg', 'image/webp'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE smtp_settings (
    id SMALLINT NOT NULL DEFAULT 1,
    host VARCHAR(255) NOT NULL DEFAULT '',
    port INT NOT NULL DEFAULT 587,
    encryption VARCHAR(10) NOT NULL DEFAULT 'tls',
    username VARCHAR(255) NOT NULL DEFAULT '',
    password_encrypted TEXT NULL,
    from_name VARCHAR(255) NOT NULL DEFAULT '',
    from_email VARCHAR(254) NOT NULL DEFAULT '',
    reply_to VARCHAR(254) NOT NULL DEFAULT '',
    last_tested_at DATETIME(6) NULL,
    last_test_result TEXT NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT smtp_settings_id_check CHECK (id = 1),
    CONSTRAINT smtp_settings_port_check CHECK (port BETWEEN 1 AND 65535),
    CONSTRAINT smtp_settings_encryption_check CHECK (encryption IN ('none', 'tls', 'ssl'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE social_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    platform VARCHAR(60) NOT NULL,
    label VARCHAR(100) NOT NULL,
    url VARCHAR(2048) NOT NULL,
    icon_key VARCHAR(20) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY social_profiles_sort_idx (sort_order, id),
    CONSTRAINT social_profiles_icon_key_check CHECK (icon_key IN ('facebook', 'x', 'instagram', 'linkedin', 'youtube', 'tiktok', 'whatsapp', 'telegram', 'website'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
