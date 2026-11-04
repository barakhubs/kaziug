-- Blog posts: sanitised HTML articles with one cover image (stored in the database like logos).
-- A post is live when status = 'published' and published_at has passed; a future published_at schedules it.
-- An optional job category shows that category's open jobs under the article.
-- Also lets analytics record page views on blog pages as their own page type.
-- Rollback: DROP TABLE blog_posts;
--   ALTER TABLE analytics_events DROP CONSTRAINT analytics_events_page_type_check,
--     ADD CONSTRAINT analytics_events_page_type_check CHECK (page_type IN ('home', 'category', 'city', 'job', 'page'));

CREATE TABLE blog_posts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL,
    excerpt VARCHAR(300) NOT NULL DEFAULT '',
    meta_description VARCHAR(170) NOT NULL DEFAULT '',
    body_html MEDIUMTEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    published_at DATETIME(6) NULL,
    job_category_id BIGINT UNSIGNED NULL,
    cover_mime VARCHAR(20) NULL,
    cover MEDIUMBLOB NULL,
    -- Bumped on every cover change; cover URLs carry it so browsers can cache them forever.
    cover_version INT UNSIGNED NOT NULL DEFAULT 0,
    cover_alt VARCHAR(200) NOT NULL DEFAULT '',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY blog_posts_slug_key (slug),
    KEY blog_posts_status_published_idx (status, published_at DESC),
    KEY blog_posts_job_category_id_idx (job_category_id),
    CONSTRAINT blog_posts_job_category_id_fkey FOREIGN KEY (job_category_id) REFERENCES job_categories (id) ON DELETE SET NULL,
    CONSTRAINT blog_posts_status_check CHECK (status IN ('draft', 'published')),
    CONSTRAINT blog_posts_published_at_chk CHECK (status = 'draft' OR published_at IS NOT NULL),
    CONSTRAINT blog_posts_slug_check CHECK (slug REGEXP '^[a-z0-9]+(-[a-z0-9]+)*$'),
    CONSTRAINT blog_posts_cover_mime_check CHECK (cover_mime IN ('image/png', 'image/jpeg', 'image/webp'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE analytics_events
    DROP CONSTRAINT analytics_events_page_type_check,
    ADD CONSTRAINT analytics_events_page_type_check CHECK (page_type IN ('home', 'category', 'city', 'job', 'page', 'blog'));
