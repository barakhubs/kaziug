-- Site settings as key/value pairs. Defaults are inserted once and never overwritten.
-- Rollback: DROP TABLE IF EXISTS settings;

CREATE TABLE IF NOT EXISTS settings (
    key        text PRIMARY KEY,
    value      jsonb NOT NULL,
    updated_at timestamptz NOT NULL DEFAULT now()
);

INSERT INTO settings (key, value) VALUES
    ('site_name', '"KaziUG"'),
    ('tagline', '"Jobs in Uganda"'),
    ('contact_email', '""'),
    ('physical_address', '""'),
    ('footer_text', '"Find jobs across Kampala, Entebbe, Jinja, Mbarara, Gulu and beyond."'),
    ('jobs_per_page', '8'),
    ('meta_description', '"Find jobs in Uganda across Kampala, Entebbe, Jinja, Mbarara, Gulu, Kasese and remote roles."'),
    ('maintenance_mode', 'false')
ON CONFLICT (key) DO NOTHING;
