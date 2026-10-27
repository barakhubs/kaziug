-- Daily analytics totals, one row per day, dimension and key, rebuilt from analytics_events by
-- AnalyticsRollup. Kept indefinitely; reports read only this table. Visitors are unique per day
-- (the visitor hash changes daily), so summing them over a range counts daily visitors.
-- key is '' for the 'total' dimension, a job or category id for 'job' / 'category'.
-- Rollback: DROP TABLE IF EXISTS analytics_daily;

CREATE TABLE IF NOT EXISTS analytics_daily (
    day          date NOT NULL,
    dimension    text NOT NULL CHECK (dimension IN (
        'total', 'path', 'page_type', 'job', 'category', 'referrer',
        'utm_source', 'utm_campaign', 'device', 'search', 'filter', 'channel'
    )),
    key          text NOT NULL CHECK (length(key) <= 300),
    pageviews    integer NOT NULL DEFAULT 0 CHECK (pageviews >= 0),
    visitors     integer NOT NULL DEFAULT 0 CHECK (visitors >= 0),
    applies      integer NOT NULL DEFAULT 0 CHECK (applies >= 0),
    searches     integer NOT NULL DEFAULT 0 CHECK (searches >= 0),
    zero_results integer NOT NULL DEFAULT 0 CHECK (zero_results >= 0),
    PRIMARY KEY (day, dimension, key)
);

CREATE INDEX IF NOT EXISTS analytics_daily_dimension_day_idx ON analytics_daily (dimension, day);
