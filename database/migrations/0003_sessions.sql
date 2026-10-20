-- Server-side session storage. "id" is the SHA-256 of the session cookie value, never the value itself.
-- Rollback: DROP TABLE IF EXISTS sessions;

CREATE TABLE IF NOT EXISTS sessions (
    id            text PRIMARY KEY,
    payload       text NOT NULL,
    last_activity timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS sessions_last_activity_idx ON sessions (last_activity);
