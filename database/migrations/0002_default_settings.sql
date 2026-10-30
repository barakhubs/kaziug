-- Default site settings. Inserted once and never overwritten, so this file is safe to re-run.
-- Rollback: DELETE FROM settings;

INSERT INTO settings (`key`, value) VALUES
    ('site_name', '"KaziUG"'),
    ('tagline', '"Jobs in Uganda"'),
    ('contact_email', '""'),
    ('physical_address', '""'),
    ('footer_text', '"Find jobs across Kampala, Entebbe, Jinja, Mbarara, Gulu and beyond."'),
    ('jobs_per_page', '8'),
    ('meta_description', '"Find jobs in Uganda across Kampala, Entebbe, Jinja, Mbarara, Gulu, Kasese and remote roles."'),
    ('maintenance_mode', 'false'),
    ('whatsapp_number', '""'),
    ('cv_service_price', '100000')
ON DUPLICATE KEY UPDATE `key` = `key`;
