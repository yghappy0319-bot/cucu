-- 회원 로그인(접속) 제한
SET NAMES utf8mb4;

SET @has_until := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_member'
      AND COLUMN_NAME = 'mb_login_block_until'
);
SET @sql_until := IF(
    @has_until = 0,
    'ALTER TABLE `tb_member` ADD COLUMN `mb_login_block_until` DATETIME NULL DEFAULT NULL COMMENT ''로그인 제한 해제 시각'' AFTER `mb_status`',
    'SELECT 1'
);
PREPARE stmt_until FROM @sql_until;
EXECUTE stmt_until;
DEALLOCATE PREPARE stmt_until;

SET @has_days := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_member'
      AND COLUMN_NAME = 'mb_login_block_days'
);
SET @sql_days := IF(
    @has_days = 0,
    'ALTER TABLE `tb_member` ADD COLUMN `mb_login_block_days` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT ''로그인 제한 일수'' AFTER `mb_login_block_until`',
    'SELECT 1'
);
PREPARE stmt_days FROM @sql_days;
EXECUTE stmt_days;
DEALLOCATE PREPARE stmt_days;

SET @has_reason := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_member'
      AND COLUMN_NAME = 'mb_login_block_reason'
);
SET @sql_reason := IF(
    @has_reason = 0,
    'ALTER TABLE `tb_member` ADD COLUMN `mb_login_block_reason` VARCHAR(500) NULL DEFAULT NULL COMMENT ''로그인 제한 사유'' AFTER `mb_login_block_days`',
    'SELECT 1'
);
PREPARE stmt_reason FROM @sql_reason;
EXECUTE stmt_reason;
DEALLOCATE PREPARE stmt_reason;

SET @has_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_member'
      AND COLUMN_NAME = 'mb_login_block_at'
);
SET @sql_at := IF(
    @has_at = 0,
    'ALTER TABLE `tb_member` ADD COLUMN `mb_login_block_at` DATETIME NULL DEFAULT NULL COMMENT ''로그인 제한 적용 시각'' AFTER `mb_login_block_reason`',
    'SELECT 1'
);
PREPARE stmt_at FROM @sql_at;
EXECUTE stmt_at;
DEALLOCATE PREPARE stmt_at;

SET @has_ad := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_member'
      AND COLUMN_NAME = 'mb_login_block_ad_idx'
);
SET @sql_ad := IF(
    @has_ad = 0,
    'ALTER TABLE `tb_member` ADD COLUMN `mb_login_block_ad_idx` INT UNSIGNED NULL DEFAULT NULL COMMENT ''로그인 제한 처리 관리자'' AFTER `mb_login_block_at`',
    'SELECT 1'
);
PREPARE stmt_ad FROM @sql_ad;
EXECUTE stmt_ad;
DEALLOCATE PREPARE stmt_ad;
