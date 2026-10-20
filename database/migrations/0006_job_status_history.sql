-- Every job status change, including creation (from_status NULL).
-- Rollback: DROP TABLE IF EXISTS job_status_history;

CREATE TABLE IF NOT EXISTS job_status_history (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    job_id      bigint NOT NULL REFERENCES jobs (id) ON DELETE CASCADE,
    from_status text CHECK (from_status IN ('draft', 'published', 'closed', 'archived')),
    to_status   text NOT NULL CHECK (to_status IN ('draft', 'published', 'closed', 'archived')),
    changed_by  bigint REFERENCES admin_users (id) ON DELETE SET NULL,
    changed_at  timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS job_status_history_job_idx ON job_status_history (job_id, changed_at DESC);
CREATE INDEX IF NOT EXISTS job_status_history_changed_by_idx ON job_status_history (changed_by);
