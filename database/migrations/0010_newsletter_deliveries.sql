-- One row per (newsletter, subscriber). The worker picks queued rows whose next_attempt_at has passed.
-- Deleting a subscriber (privacy request) also deletes their delivery rows.
-- Rollback: DROP TABLE IF EXISTS newsletter_deliveries;

CREATE TABLE IF NOT EXISTS newsletter_deliveries (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    newsletter_id   bigint NOT NULL REFERENCES newsletters (id) ON DELETE CASCADE,
    subscriber_id   bigint NOT NULL REFERENCES subscribers (id) ON DELETE CASCADE,
    status          text NOT NULL DEFAULT 'queued' CHECK (status IN ('queued', 'sent', 'failed')),
    attempts        integer NOT NULL DEFAULT 0 CHECK (attempts >= 0),
    last_error      text,
    next_attempt_at timestamptz NOT NULL DEFAULT now(),
    sent_at         timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT newsletter_deliveries_unique UNIQUE (newsletter_id, subscriber_id)
);

CREATE INDEX IF NOT EXISTS newsletter_deliveries_newsletter_status_idx ON newsletter_deliveries (newsletter_id, status);
CREATE INDEX IF NOT EXISTS newsletter_deliveries_due_idx ON newsletter_deliveries (next_attempt_at) WHERE status = 'queued';
CREATE INDEX IF NOT EXISTS newsletter_deliveries_subscriber_idx ON newsletter_deliveries (subscriber_id);
