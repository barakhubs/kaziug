-- Newsletter subscribers (double opt-in). Only a SHA-256 hash of the confirmation token is stored.
-- Rollback: DROP TABLE IF EXISTS subscribers;

CREATE TABLE IF NOT EXISTS subscribers (
    id                 bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email              text NOT NULL CHECK (length(email) BETWEEN 3 AND 254),
    name               text,
    status             text NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'unsubscribed', 'bounced')),
    consent_at         timestamptz,
    consent_ip         inet,
    confirm_token_hash text,
    confirm_sent_at    timestamptz,
    confirmed_at       timestamptz,
    unsubscribe_token  text NOT NULL,
    created_at         timestamptz NOT NULL DEFAULT now(),
    unsubscribed_at    timestamptz,
    CONSTRAINT subscribers_unsubscribe_token_key UNIQUE (unsubscribe_token)
);

CREATE UNIQUE INDEX IF NOT EXISTS subscribers_email_lower_key ON subscribers (lower(email));
CREATE UNIQUE INDEX IF NOT EXISTS subscribers_confirm_token_hash_key ON subscribers (confirm_token_hash)
    WHERE confirm_token_hash IS NOT NULL;
CREATE INDEX IF NOT EXISTS subscribers_status_idx ON subscribers (status);
CREATE INDEX IF NOT EXISTS subscribers_created_at_idx ON subscribers (created_at DESC);
