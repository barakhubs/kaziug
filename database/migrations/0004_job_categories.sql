-- Rollback: DROP TABLE IF EXISTS job_categories;

CREATE TABLE IF NOT EXISTS job_categories (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name        text NOT NULL CHECK (length(btrim(name)) BETWEEN 1 AND 100),
    slug        text NOT NULL CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
    description text NOT NULL DEFAULT '',
    sort_order  integer NOT NULL DEFAULT 0,
    is_active   boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT job_categories_slug_key UNIQUE (slug)
);

CREATE INDEX IF NOT EXISTS job_categories_sort_idx ON job_categories (sort_order, name);

DROP TRIGGER IF EXISTS job_categories_updated_at ON job_categories;
CREATE TRIGGER job_categories_updated_at BEFORE UPDATE ON job_categories
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
