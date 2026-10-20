-- Jobs, with a weighted full-text search vector maintained by a trigger. (A generated column
-- is not possible: array_to_string() is not IMMUTABLE.)
-- Rollback: DROP TABLE IF EXISTS jobs; DROP FUNCTION IF EXISTS jobs_search_update();

CREATE TABLE IF NOT EXISTS jobs (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title            text NOT NULL CHECK (length(btrim(title)) BETWEEN 1 AND 200),
    slug             text NOT NULL CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
    company          text NOT NULL CHECK (length(btrim(company)) BETWEEN 1 AND 200),
    location         text NOT NULL CHECK (length(btrim(location)) BETWEEN 1 AND 120),
    is_remote        boolean NOT NULL DEFAULT false,
    job_type         text NOT NULL CHECK (job_type IN ('full-time', 'part-time', 'contract', 'internship')),
    experience_level text NOT NULL CHECK (experience_level IN ('entry', 'mid', 'senior')),
    category_id      bigint NOT NULL REFERENCES job_categories (id) ON DELETE RESTRICT,
    salary_min       bigint CHECK (salary_min >= 0),
    salary_max       bigint CHECK (salary_max >= 0),
    currency         char(3) NOT NULL DEFAULT 'UGX' CHECK (currency ~ '^[A-Z]{3}$'),
    summary          text NOT NULL,
    responsibilities jsonb NOT NULL DEFAULT '[]'::jsonb CHECK (jsonb_typeof(responsibilities) = 'array'),
    requirements     jsonb NOT NULL DEFAULT '[]'::jsonb CHECK (jsonb_typeof(requirements) = 'array'),
    skills           text[] NOT NULL DEFAULT '{}',
    apply_url        text CHECK (apply_url IS NULL OR apply_url ~* '^https?://'),
    apply_email      text,
    status           text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'closed', 'archived')),
    published_at     timestamptz,
    expires_at       timestamptz,
    deleted_at       timestamptz,
    search           tsvector,
    created_at       timestamptz NOT NULL DEFAULT now(),
    updated_at       timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT jobs_slug_key UNIQUE (slug),
    CONSTRAINT jobs_salary_range_chk CHECK (salary_min IS NULL OR salary_max IS NULL OR salary_max >= salary_min),
    CONSTRAINT jobs_published_at_chk CHECK (status = 'draft' OR published_at IS NOT NULL)
);

CREATE OR REPLACE FUNCTION jobs_search_update() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    NEW.search :=
        setweight(to_tsvector('english', coalesce(NEW.title, '')), 'A') ||
        setweight(to_tsvector('english', array_to_string(coalesce(NEW.skills, '{}'), ' ')), 'B') ||
        setweight(to_tsvector('english', coalesce(NEW.company, '')), 'B') ||
        setweight(to_tsvector('english', coalesce(NEW.summary, '')), 'C');
    RETURN NEW;
END
$$;

DROP TRIGGER IF EXISTS jobs_search_trg ON jobs;
CREATE TRIGGER jobs_search_trg BEFORE INSERT OR UPDATE OF title, company, summary, skills ON jobs
    FOR EACH ROW EXECUTE FUNCTION jobs_search_update();

DROP TRIGGER IF EXISTS jobs_updated_at ON jobs;
CREATE TRIGGER jobs_updated_at BEFORE UPDATE ON jobs
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

CREATE INDEX IF NOT EXISTS jobs_search_idx ON jobs USING gin (search);
CREATE INDEX IF NOT EXISTS jobs_status_published_idx ON jobs (status, published_at DESC) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS jobs_published_at_idx ON jobs (published_at DESC);
CREATE INDEX IF NOT EXISTS jobs_category_id_idx ON jobs (category_id);
CREATE INDEX IF NOT EXISTS jobs_salary_min_idx ON jobs (salary_min) WHERE deleted_at IS NULL;
