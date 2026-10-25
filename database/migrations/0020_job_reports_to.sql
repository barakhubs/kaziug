-- Who the role reports to (e.g. "Consultant – Adult Cardiology"), as public-sector adverts state.
-- Rollback: ALTER TABLE jobs DROP COLUMN IF EXISTS reports_to;

ALTER TABLE jobs
    ADD COLUMN IF NOT EXISTS reports_to text
        CONSTRAINT jobs_reports_to_chk CHECK (reports_to IS NULL OR length(btrim(reports_to)) BETWEEN 1 AND 150);
