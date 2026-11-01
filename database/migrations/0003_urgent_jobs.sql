-- Urgent jobs: flagged by the admin, shown with an "Urgent" badge and listed before all other jobs.
-- Rollback: ALTER TABLE jobs DROP INDEX jobs_urgent_published_idx, DROP COLUMN is_urgent;

ALTER TABLE jobs ADD COLUMN is_urgent TINYINT(1) NOT NULL DEFAULT 0 AFTER is_remote;

CREATE INDEX jobs_urgent_published_idx ON jobs (is_urgent, published_at DESC);
