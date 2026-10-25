-- Free-text application instructions (documents to attach, where to deliver a hard copy, etc.).
-- Rollback: ALTER TABLE jobs DROP COLUMN IF EXISTS application_instructions;

ALTER TABLE jobs
    ADD COLUMN IF NOT EXISTS application_instructions text
        CONSTRAINT jobs_application_instructions_chk
            CHECK (application_instructions IS NULL OR length(btrim(application_instructions)) BETWEEN 1 AND 2000);
