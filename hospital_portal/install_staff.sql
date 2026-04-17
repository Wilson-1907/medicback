-- Run after phv_pilot_schema.sql (database phv_pilot must exist)
USE phv_pilot;

CREATE TABLE IF NOT EXISTS staff_users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(64) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  display_name    VARCHAR(128) NOT NULL DEFAULT 'Staff',
  created_at      DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  UNIQUE KEY uq_staff_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default password is set via hospital/setup.php (one-time) or insert your own hash.
