-- Companies (employers): name, optional website and a logo stored in the database, referenced by jobs.
-- Existing jobs are linked to companies created from their distinct company names (those have no logo yet).
-- The utf8mb4_unicode_ci collation makes the unique name case-insensitive, so "MTN" and "mtn" are one company.
-- Rollback: ALTER TABLE jobs ADD COLUMN company VARCHAR(200) NOT NULL DEFAULT '' AFTER slug;
--   UPDATE jobs j JOIN companies c ON c.id = j.company_id SET j.company = c.name;
--   ALTER TABLE jobs DROP FOREIGN KEY jobs_company_id_fkey, DROP INDEX jobs_company_id_idx, DROP COLUMN company_id;
--   DROP TABLE companies;

CREATE TABLE companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    website VARCHAR(2048) NULL,
    logo_mime VARCHAR(20) NULL,
    logo MEDIUMBLOB NULL,
    -- Bumped on every logo change; logo URLs carry it so browsers can cache them forever.
    logo_version INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY companies_name_key (name),
    CONSTRAINT companies_logo_mime_check CHECK (logo_mime IN ('image/png', 'image/jpeg', 'image/webp'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO companies (name)
SELECT DISTINCT TRIM(company) FROM jobs
ON DUPLICATE KEY UPDATE name = companies.name;

ALTER TABLE jobs ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER slug;

UPDATE jobs j JOIN companies c ON c.name = TRIM(j.company) SET j.company_id = c.id;

ALTER TABLE jobs
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD KEY jobs_company_id_idx (company_id),
    ADD CONSTRAINT jobs_company_id_fkey FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE RESTRICT,
    DROP COLUMN company;
