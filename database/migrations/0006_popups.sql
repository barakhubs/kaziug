-- Marketing popups shown on the home and job pages, once per visitor (remembered in the browser's
-- localStorage, never on the server). At most one popup is active: active_key is 1 for the active row
-- and NULL otherwise, and a UNIQUE index allows any number of NULLs but only one 1.
-- Rollback: DROP TABLE popups;

CREATE TABLE popups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    heading VARCHAR(150) NOT NULL,
    body_html TEXT NOT NULL,
    button_label VARCHAR(60) NULL,
    button_url VARCHAR(2048) NULL,
    trigger_type VARCHAR(10) NOT NULL DEFAULT 'delay',
    delay_seconds INT NOT NULL DEFAULT 8,
    starts_at DATETIME(6) NULL,
    ends_at DATETIME(6) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    active_key TINYINT AS (CASE WHEN is_active = 1 THEN 1 ELSE NULL END) STORED,
    -- Bumped by "show again": browsers remember the id and version they have seen.
    content_version INT UNSIGNED NOT NULL DEFAULT 1,
    image_mime VARCHAR(20) NULL,
    image MEDIUMBLOB NULL,
    image_version INT UNSIGNED NOT NULL DEFAULT 0,
    image_alt VARCHAR(200) NOT NULL DEFAULT '',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY popups_one_active_key (active_key),
    CONSTRAINT popups_trigger_type_check CHECK (trigger_type IN ('delay', 'scroll', 'exit')),
    CONSTRAINT popups_delay_seconds_check CHECK (delay_seconds BETWEEN 0 AND 120),
    CONSTRAINT popups_dates_check CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at),
    CONSTRAINT popups_button_check CHECK ((button_label IS NULL) = (button_url IS NULL)),
    CONSTRAINT popups_image_mime_check CHECK (image_mime IN ('image/png', 'image/jpeg', 'image/webp'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
