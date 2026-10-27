-- First-party, cookieless analytics. Raw events are kept for a year (see AnalyticsRollup), then only
-- the daily totals in analytics_daily remain. No IP address, user agent or cookie is stored:
-- visitor_hash = sha256(daily salt + IP + user agent), and each salt is deleted once its day is
-- over, so a visitor cannot be recognised from one day to the next or traced back afterwards.
-- Rollback: DROP TABLE IF EXISTS analytics_events; DROP TABLE IF EXISTS analytics_salts;

CREATE TABLE IF NOT EXISTS analytics_events (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    occurred_at    timestamptz NOT NULL DEFAULT now(),
    type           text NOT NULL CHECK (type IN ('pageview', 'apply_click')),
    visitor_hash   text NOT NULL CHECK (visitor_hash ~ '^[a-f0-9]{32}$'),
    path           text NOT NULL CHECK (length(path) BETWEEN 1 AND 300),
    page_type      text NOT NULL CHECK (page_type IN ('home', 'category', 'city', 'job', 'page')),
    job_id         bigint REFERENCES jobs (id) ON DELETE SET NULL,
    category_id    bigint REFERENCES job_categories (id) ON DELETE SET NULL,
    referrer_host  text CHECK (length(referrer_host) BETWEEN 1 AND 255),
    utm_source     text CHECK (length(utm_source) BETWEEN 1 AND 100),
    utm_medium     text CHECK (length(utm_medium) BETWEEN 1 AND 100),
    utm_campaign   text CHECK (length(utm_campaign) BETWEEN 1 AND 100),
    device         text NOT NULL CHECK (device IN ('mobile', 'tablet', 'desktop')),
    apply_channel  text CHECK (apply_channel IN ('link', 'email')),
    -- Set only on the first results page of a search; result_count IS NOT NULL marks a search.
    search_query   text CHECK (length(search_query) BETWEEN 1 AND 100),
    search_filters text[] NOT NULL DEFAULT '{}',
    result_count   integer CHECK (result_count >= 0),
    CONSTRAINT analytics_events_apply_channel_chk CHECK ((type = 'apply_click') = (apply_channel IS NOT NULL))
);

CREATE INDEX IF NOT EXISTS analytics_events_occurred_at_idx ON analytics_events (occurred_at);
CREATE INDEX IF NOT EXISTS analytics_events_job_id_idx ON analytics_events (job_id);
CREATE INDEX IF NOT EXISTS analytics_events_category_id_idx ON analytics_events (category_id);

-- One random salt per calendar day (app timezone). Older salts are deleted as soon as a new day starts.
CREATE TABLE IF NOT EXISTS analytics_salts (
    day        date PRIMARY KEY,
    salt       bytea NOT NULL CHECK (length(salt) = 32),
    created_at timestamptz NOT NULL DEFAULT now()
);
