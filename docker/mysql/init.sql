-- Runs once when the volume is first created. The test suite uses its own database.
CREATE DATABASE IF NOT EXISTS kaziug_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON kaziug_test.* TO 'kaziug'@'%';
