-- Kumo fresh-install schema. Select the intended database in phpMyAdmin before importing.
-- For an existing Kumo database, import upgrade_v2.sql first.

CREATE TABLE IF NOT EXISTS userdata (
    id INT NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    full_name VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(254) NOT NULL,
    email_verified_at DATETIME NULL DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL DEFAULT NULL,
    role ENUM('user','admin','owner') NOT NULL DEFAULT 'user',
    setup_completed TINYINT(1) NOT NULL DEFAULT 0,
    auth_version INT UNSIGNED NOT NULL DEFAULT 0,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_userdata_username (username),
    UNIQUE KEY uq_userdata_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS clouds (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    parent_id INT NULL DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    cloud_type VARCHAR(32) NOT NULL DEFAULT 'cloud',
    icon VARCHAR(24) NOT NULL DEFAULT '☁️',
    color_theme VARCHAR(32) NOT NULL DEFAULT 'green',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_clouds_user_parent (user_id, parent_id),
    CONSTRAINT fk_clouds_user FOREIGN KEY (user_id) REFERENCES userdata(id) ON DELETE CASCADE,
    CONSTRAINT fk_clouds_parent FOREIGN KEY (parent_id) REFERENCES clouds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS class_schedules (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    cloud_id INT NOT NULL,
    class_day ENUM('monday','tuesday','wednesday','thursday','friday','saturday','sunday') NOT NULL,
    midterm_week TINYINT UNSIGNED NOT NULL,
    final_week TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_class_schedules_cloud (cloud_id),
    KEY idx_class_schedules_user (user_id),
    CONSTRAINT fk_class_schedules_user FOREIGN KEY (user_id) REFERENCES userdata(id) ON DELETE CASCADE,
    CONSTRAINT fk_class_schedules_cloud FOREIGN KEY (cloud_id) REFERENCES clouds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notes (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    cloud_id INT NOT NULL,
    content MEDIUMTEXT NOT NULL,
    last_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notes_user_cloud (user_id, cloud_id),
    KEY idx_notes_cloud (cloud_id),
    CONSTRAINT fk_notes_user FOREIGN KEY (user_id) REFERENCES userdata(id) ON DELETE CASCADE,
    CONSTRAINT fk_notes_cloud FOREIGN KEY (cloud_id) REFERENCES clouds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    cloud_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    due_date DATE NULL DEFAULT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tasks_user_cloud_due (user_id, cloud_id, due_date),
    CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES userdata(id) ON DELETE CASCADE,
    CONSTRAINT fk_tasks_cloud FOREIGN KEY (cloud_id) REFERENCES clouds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_hash (token_hash),
    KEY idx_password_reset_user_expiry (user_id, expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES userdata(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_rate_limits (
    id INT NOT NULL AUTO_INCREMENT,
    scope VARCHAR(32) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_auth_rate_scope_hash_time (scope, key_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    selector CHAR(24) NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    auth_version INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_remember_selector (selector),
    KEY idx_remember_user_created (user_id, created_at),
    KEY idx_remember_expiry (expires_at),
    CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES userdata(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
