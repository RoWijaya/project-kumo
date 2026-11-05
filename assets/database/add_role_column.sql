-- Run this with the intended Kumo database selected in phpMyAdmin.
SET @role_column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'userdata' AND COLUMN_NAME = 'role'
);
SET @role_sql = IF(
    @role_column_exists = 0,
    "ALTER TABLE userdata ADD COLUMN role ENUM('user','admin','owner') NOT NULL DEFAULT 'user'",
    "ALTER TABLE userdata MODIFY COLUMN role ENUM('user','admin','owner') NOT NULL DEFAULT 'user'"
);
PREPARE role_stmt FROM @role_sql;
EXECUTE role_stmt;
DEALLOCATE PREPARE role_stmt;
-- Legacy seller accounts are ordinary users in Kumo.
UPDATE userdata SET role = 'user' WHERE role = 'seller';
