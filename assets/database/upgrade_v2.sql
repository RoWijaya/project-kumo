-- Kumo v2 migration - adds columns and tables required for email verification and full-name support.
-- Import this with the Kumo database selected in phpMyAdmin or mysql CLI.
-- This migration is additive and safe to rerun; it preserves all existing data.

SET @db_name = DATABASE();

-- 1. Add full_name column to userdata (required for registration and profile display)
SET @has_full_name = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='full_name');
SET @sql1 = IF(@has_full_name=0, 'ALTER TABLE userdata ADD COLUMN full_name VARCHAR(100) NOT NULL DEFAULT "" AFTER username', 'SELECT "full_name already exists" AS message');
PREPARE stmt1 FROM @sql1; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- 2. Add email_verified_at column to userdata (required for verification state)
SET @has_email_verified = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='email_verified_at');
SET @sql2 = IF(@has_email_verified=0, 'ALTER TABLE userdata ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL AFTER email', 'SELECT "email_verified_at already exists" AS message');
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- 3. Add avatar_path column to userdata if missing
SET @has_avatar = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='avatar_path');
SET @sql3 = IF(@has_avatar=0, 'ALTER TABLE userdata ADD COLUMN avatar_path VARCHAR(255) NULL DEFAULT NULL', 'SELECT "avatar_path already exists" AS message');
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- 4. Create email_verification_tokens table
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_verification_hash (token_hash),
    KEY idx_email_verification_user (user_id),
    CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id) REFERENCES userdata(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create user_preferences table
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    theme VARCHAR(16) NOT NULL DEFAULT 'light',
    accent_color VARCHAR(16) NOT NULL DEFAULT 'sage',
    density VARCHAR(16) NOT NULL DEFAULT 'comfortable',
    reduce_motion TINYINT(1) NOT NULL DEFAULT 0,
    show_clouds TINYINT(1) NOT NULL DEFAULT 1,
    show_upcoming TINYINT(1) NOT NULL DEFAULT 1,
    show_recent_activity TINYINT(1) NOT NULL DEFAULT 1,
    show_progress TINYINT(1) NOT NULL DEFAULT 1,
    first_section VARCHAR(32) NOT NULL DEFAULT 'clouds',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_preferences_user (user_id),
    CONSTRAINT fk_user_preferences_user FOREIGN KEY (user_id) REFERENCES userdata(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Mark existing accounts as verified (backward compatibility so legacy accounts are not locked out)
UPDATE userdata SET email_verified_at = NOW() WHERE email_verified_at IS NULL;
