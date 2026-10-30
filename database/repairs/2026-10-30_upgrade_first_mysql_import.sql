-- One-off repair for a database imported from the FIRST kaziug-mysql.sql export (made before the app
-- itself moved to MySQL). That file lacks jobs.search_text, has the old search index, has no defaults
-- on TEXT/JSON columns and lists the 22 old PostgreSQL migrations as applied.
-- Run it once in phpMyAdmin (select the database, SQL tab). Data is kept. Safe to check afterwards
-- with: php bin/console config:check (expects "migrations up to date").
-- Rollback: not needed; it only adds a column, swaps an index, sets defaults and fixes schema_migrations.

SET NAMES utf8mb4;

ALTER TABLE jobs ADD COLUMN search_text TEXT NOT NULL DEFAULT ('') AFTER reports_to;
ALTER TABLE jobs DROP INDEX jobs_search_idx;

-- Same text the app writes on every save (FullTextQuery::document): title twice, skills, company, summary.
UPDATE jobs j SET search_text = CONCAT_WS('\n', j.title, j.title,
    COALESCE((SELECT GROUP_CONCAT(s.v SEPARATOR ' ')
              FROM JSON_TABLE(j.skills, '$[*]' COLUMNS (v VARCHAR(200) PATH '$')) AS s), ''),
    j.company, j.summary);

ALTER TABLE jobs ADD FULLTEXT KEY jobs_search_idx (search_text);

ALTER TABLE jobs
    MODIFY responsibilities JSON NOT NULL DEFAULT ('[]'),
    MODIFY requirements JSON NOT NULL DEFAULT ('[]'),
    MODIFY skills JSON NOT NULL DEFAULT ('[]');
ALTER TABLE job_categories MODIFY description TEXT NOT NULL DEFAULT ('');
ALTER TABLE analytics_events MODIFY search_filters JSON NOT NULL DEFAULT ('[]');
ALTER TABLE audit_logs MODIFY metadata JSON NOT NULL DEFAULT ('{}');
ALTER TABLE newsletters
    MODIFY body_html MEDIUMTEXT NOT NULL DEFAULT (''),
    MODIFY body_text MEDIUMTEXT NOT NULL DEFAULT ('');

-- The schema now matches migrations 0001 and 0002.
DELETE FROM schema_migrations;
INSERT INTO schema_migrations (version) VALUES ('0001_initial_schema'), ('0002_default_settings');
