-- Rollback: DROP TABLE IF EXISTS newsletters;

CREATE TABLE IF NOT EXISTS newsletters (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    subject      text NOT NULL CHECK (length(btrim(subject)) BETWEEN 1 AND 200),
    preheader    text NOT NULL DEFAULT '',
    body_html    text NOT NULL DEFAULT '',
    body_text    text NOT NULL DEFAULT '',
    status       text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'scheduled', 'sending', 'sent', 'failed')),
    scheduled_at timestamptz,
    sent_at      timestamptz,
    created_by   bigint REFERENCES admin_users (id) ON DELETE SET NULL,
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT newsletters_scheduled_chk CHECK (status <> 'scheduled' OR scheduled_at IS NOT NULL)
);

CREATE INDEX IF NOT EXISTS newsletters_status_scheduled_idx ON newsletters (status, scheduled_at);
CREATE INDEX IF NOT EXISTS newsletters_created_by_idx ON newsletters (created_by);

DROP TRIGGER IF EXISTS newsletters_updated_at ON newsletters;
CREATE TRIGGER newsletters_updated_at BEFORE UPDATE ON newsletters
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
