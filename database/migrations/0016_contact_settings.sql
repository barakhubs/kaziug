-- Settings for the Contact and CV help pages: the WhatsApp number (international digits, no +)
-- and the CV service price in UGX. Defaults only; never overwrites values already set.
-- Rollback: DELETE FROM settings WHERE key IN ('whatsapp_number', 'cv_service_price');

INSERT INTO settings (key, value) VALUES
    ('whatsapp_number', '""'),
    ('cv_service_price', '100000')
ON CONFLICT (key) DO NOTHING;
