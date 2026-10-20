-- Binary site assets (the logo). Kept in the database because Laravel Cloud's local disk
-- does not survive deploys.
-- Rollback: DROP TABLE IF EXISTS site_assets;

CREATE TABLE IF NOT EXISTS site_assets (
    key        text PRIMARY KEY,
    mime_type  text NOT NULL CHECK (mime_type IN ('image/png', 'image/jpeg', 'image/webp')),
    content    bytea NOT NULL,
    updated_at timestamptz NOT NULL DEFAULT now()
);
