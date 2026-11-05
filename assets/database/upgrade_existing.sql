-- Comprehensive compatibility migration for an existing Kumo database.
-- Run this with the Kumo database selected in phpMyAdmin or mysql CLI.
-- This script is safe to rerun multiple times and preserves all existing user data.

SET @db_name = DATABASE();

-- 1. Ensure role column exists and is typed correctly
SET @has_role = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='role');
SET @sql_role = IF(@has_role=0, "ALTER TABLE userdata ADD COLUMN role ENUM('user','admin','owner') NOT NULL DEFAULT 'user'", "UPDATE userdata SET role = 'user' WHERE role = 'seller'");
PREPARE stmt_role FROM @sql_role; EXECUTE stmt_role; DEALLOCATE PREPARE stmt_role;
SET @has_role = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='role');
SET @sql_role_mod = IF(@has_role>0, "ALTER TABLE userdata MODIFY COLUMN role ENUM('user','admin','owner') NOT NULL DEFAULT 'user'", "SELECT 'role column check' AS note");
PREPARE stmt_role_mod FROM @sql_role_mod; EXECUTE stmt_role_mod; DEALLOCATE PREPARE stmt_role_mod;

-- 2. Ensure setup_completed column exists
SET @has_setup = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='setup_completed');
SET @sql_setup = IF(@has_setup=0, "ALTER TABLE userdata ADD COLUMN setup_completed TINYINT(1) NOT NULL DEFAULT 0", "SELECT 'setup_completed already exists' AS message");
PREPARE stmt_setup FROM @sql_setup; EXECUTE stmt_setup; DEALLOCATE PREPARE stmt_setup;

-- 3. Ensure auth_version column exists
SET @has_auth_version = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='auth_version');
SET @sql_auth = IF(@has_auth_version=0, "ALTER TABLE userdata ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 0", "SELECT 'auth_version already exists' AS message");
PREPARE stmt_auth FROM @sql_auth; EXECUTE stmt_auth; DEALLOCATE PREPARE stmt_auth;

-- 4. Ensure two_factor_enabled column exists
SET @has_2fa = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='two_factor_enabled');
SET @sql_2fa = IF(@has_2fa=0, "ALTER TABLE userdata ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0", "SELECT 'two_factor_enabled already exists' AS message");
PREPARE stmt_2fa FROM @sql_2fa; EXECUTE stmt_2fa; DEALLOCATE PREPARE stmt_2fa;

-- 5. Ensure last_login_at column exists
SET @has_last_login = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='last_login_at');
SET @sql_last_login = IF(@has_last_login=0, "ALTER TABLE userdata ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL", "SELECT 'last_login_at already exists' AS message");
PREPARE stmt_last_login FROM @sql_last_login; EXECUTE stmt_last_login; DEALLOCATE PREPARE stmt_last_login;

-- 6. Ensure full_name column exists (required for registration and profile)
SET @has_full_name = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='full_name');
SET @sql_full_name = IF(@has_full_name=0, 'ALTER TABLE userdata ADD COLUMN full_name VARCHAR(100) NOT NULL DEFAULT "" AFTER username', 'SELECT "full_name already exists" AS message');
PREPARE stmt_fn FROM @sql_full_name; EXECUTE stmt_fn; DEALLOCATE PREPARE stmt_fn;

-- 7. Ensure email_verified_at column exists
SET @has_email_verified = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='email_verified_at');
SET @sql_email_v = IF(@has_email_verified=0, 'ALTER TABLE userdata ADD COLUMN email_verified_at DATETIME NULL DEFAULT NULL AFTER email', 'SELECT "email_verified_at already exists" AS message');
PREPARE stmt_ev FROM @sql_email_v; EXECUTE stmt_ev; DEALLOCATE PREPARE stmt_ev;

-- 8. Ensure avatar_path column exists
SET @has_avatar = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='userdata' AND COLUMN_NAME='avatar_path');
SET @sql_avatar = IF(@has_avatar=0, 'ALTER TABLE userdata ADD COLUMN avatar_path VARCHAR(255) NULL DEFAULT NULL', 'SELECT "avatar_path already exists" AS message');
PREPARE stmt_av FROM @sql_avatar; EXECUTE stmt_av; DEALLOCATE PREPARE stmt_av;

-- 9. Create email_verification_tokens table
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

-- 10. Create user_preferences table
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

-- 11. Ensure clouds.updated_at exists
SET @has_clouds_updated = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='clouds' AND COLUMN_NAME='updated_at');
SET @sql_clouds_updated = IF(@has_clouds_updated=0, 'ALTER TABLE clouds ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', 'SELECT "clouds.updated_at already exists" AS message');
PREPARE stmt_cu FROM @sql_clouds_updated; EXECUTE stmt_cu; DEALLOCATE PREPARE stmt_cu;

-- 12. Normalize roles and mark legacy accounts as verified
UPDATE userdata SET role='user' WHERE role NOT IN ('user','admin','owner');
UPDATE userdata SET email_verified_at = NOW() WHERE email_verified_at IS NULL;

