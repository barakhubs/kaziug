-- The single back-office administrator. The "singleton" column is always true and unique,
-- so the database itself refuses a second row, whatever the application code does.
-- Rollback: DROP TABLE IF EXISTS admin_users;

CREATE TABLE IF NOT EXISTS admin_users (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    singleton       boolean NOT NULL DEFAULT true,
    email           text NOT NULL,
    password_hash   text NOT NULL,
    -- Bumped by "sign out of all sessions"; sessions carrying an older value are rejected.
    session_version integer NOT NULL DEFAULT 1,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT admin_users_singleton_chk CHECK (singleton),
    CONSTRAINT admin_users_singleton_key UNIQUE (singleton),
    CONSTRAINT admin_users_email_lower_chk CHECK (email = lower(email)),
    CONSTRAINT admin_users_email_key UNIQUE (email)
);

DROP TRIGGER IF EXISTS admin_users_updated_at ON admin_users;
CREATE TRIGGER admin_users_updated_at BEFORE UPDATE ON admin_users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
