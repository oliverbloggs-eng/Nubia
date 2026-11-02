-- 003: "Title verified" on listings. The website shows the "Title Verified" badge and the
-- "What we verified" box only for listings an administrator has ticked. Existing listings start unticked.
-- Run by hand: phpMyAdmin > your database > SQL tab > paste > Go. Safe to run more than once.

SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'listings' AND COLUMN_NAME = 'title_verified');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE listings ADD COLUMN title_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER featured',
  'SELECT ''title_verified already exists'' AS note');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
