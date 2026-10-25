-- A job advertises either a salary amount (min/max) or a public-service salary scale, not both.
-- Rollback: ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_salary_amount_or_scale_chk;

ALTER TABLE jobs
    ADD CONSTRAINT jobs_salary_amount_or_scale_chk
        CHECK (salary_scale IS NULL OR (salary_min IS NULL AND salary_max IS NULL));
