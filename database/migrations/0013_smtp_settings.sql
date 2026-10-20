-- Single-row SMTP configuration. password_encrypted holds a libsodium secretbox (nonce + ciphertext, base64).
-- Rollback: DROP TABLE IF EXISTS smtp_settings;

CREATE TABLE IF NOT EXISTS smtp_settings (
    id                 smallint PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    host               text NOT NULL DEFAULT '',
    port               integer NOT NULL DEFAULT 587 CHECK (port BETWEEN 1 AND 65535),
    encryption         text NOT NULL DEFAULT 'tls' CHECK (encryption IN ('none', 'tls', 'ssl')),
    username           text NOT NULL DEFAULT '',
    password_encrypted text,
    from_name          text NOT NULL DEFAULT '',
    from_email         text NOT NULL DEFAULT '',
    reply_to           text NOT NULL DEFAULT '',
    last_tested_at     timestamptz,
    last_test_result   text,
    updated_at         timestamptz NOT NULL DEFAULT now()
);

DROP TRIGGER IF EXISTS smtp_settings_updated_at ON smtp_settings;
CREATE TRIGGER smtp_settings_updated_at BEFORE UPDATE ON smtp_settings
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
