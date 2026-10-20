-- Failure counters for throttling. Keys are namespaced: "login:ip:<ip>", "login:email:<sha256>",
-- "subscribe:ip:<ip>". failure_count resets once window_started_at is older than the window.
-- Rollback: DROP TABLE IF EXISTS login_attempts;

CREATE TABLE IF NOT EXISTS login_attempts (
    key               text PRIMARY KEY,
    failure_count     integer NOT NULL DEFAULT 0 CHECK (failure_count >= 0),
    locked_until      timestamptz,
    window_started_at timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS login_attempts_updated_at_idx ON login_attempts (updated_at);
