-- Number of open positions per job, and a salary scale for public-sector jobs
-- (e.g. "U4"), which advertise a scale instead of an amount.
-- Rollback: ALTER TABLE jobs DROP COLUMN IF EXISTS positions, DROP COLUMN IF EXISTS salary_scale;

ALTER TABLE jobs
    ADD COLUMN IF NOT EXISTS positions integer NOT NULL DEFAULT 1
        CONSTRAINT jobs_positions_chk CHECK (positions BETWEEN 1 AND 10000),
    ADD COLUMN IF NOT EXISTS salary_scale text
        CONSTRAINT jobs_salary_scale_chk CHECK (salary_scale IS NULL OR length(btrim(salary_scale)) BETWEEN 1 AND 30);
