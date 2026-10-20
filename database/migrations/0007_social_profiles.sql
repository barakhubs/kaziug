-- Rollback: DROP TABLE IF EXISTS social_profiles;

CREATE TABLE IF NOT EXISTS social_profiles (
    id         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    platform   text NOT NULL CHECK (length(btrim(platform)) BETWEEN 1 AND 60),
    label      text NOT NULL CHECK (length(btrim(label)) BETWEEN 1 AND 100),
    url        text NOT NULL CHECK (url ~* '^https?://'),
    icon_key   text NOT NULL CHECK (icon_key IN (
                   'facebook', 'x', 'instagram', 'linkedin', 'youtube', 'tiktok', 'whatsapp', 'telegram', 'website')),
    sort_order integer NOT NULL DEFAULT 0,
    is_visible boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS social_profiles_sort_idx ON social_profiles (sort_order, id);

DROP TRIGGER IF EXISTS social_profiles_updated_at ON social_profiles;
CREATE TRIGGER social_profiles_updated_at BEFORE UPDATE ON social_profiles
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
