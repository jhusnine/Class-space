-- Non-destructive, idempotent MySQL migration for user-hidden notifications.
-- Requires the existing `notification` table. A NULL timestamp means visible.
-- No notification rows or message contents are deleted or rewritten.

SET @notif_soft_delete_column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'notification'
      AND COLUMN_NAME = 'notif_user_deleted_at'
);

SET @notif_soft_delete_ddl := IF(
    @notif_soft_delete_column_exists = 0,
    'ALTER TABLE `notification` ADD COLUMN `notif_user_deleted_at` DATETIME NULL DEFAULT NULL',
    'SELECT ''notif_user_deleted_at already exists; no change performed'' AS migration_status'
);

PREPARE notif_soft_delete_stmt FROM @notif_soft_delete_ddl;
EXECUTE notif_soft_delete_stmt;
DEALLOCATE PREPARE notif_soft_delete_stmt;

ALTER TABLE `account`
    MODIFY COLUMN `account_password` VARCHAR(255) NOT NULL;