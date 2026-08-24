-- 거래글 게시중지(관리자 제재) 사유 컬럼
-- tr_status: 1 정상 / 2 게시중지 / 9 삭제(숨김)
SET NAMES utf8mb4;

SET @has_hold_code := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade'
      AND COLUMN_NAME = 'tr_hold_code'
);
SET @sql_hold_code := IF(
    @has_hold_code = 0,
    'ALTER TABLE `tb_trade` ADD COLUMN `tr_hold_code` VARCHAR(40) NULL DEFAULT NULL COMMENT ''게시중지 사유 코드'' AFTER `tr_status`',
    'SELECT 1'
);
PREPARE stmt_hold_code FROM @sql_hold_code;
EXECUTE stmt_hold_code;
DEALLOCATE PREPARE stmt_hold_code;

SET @has_hold_reason := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade'
      AND COLUMN_NAME = 'tr_hold_reason'
);
SET @sql_hold_reason := IF(
    @has_hold_reason = 0,
    'ALTER TABLE `tb_trade` ADD COLUMN `tr_hold_reason` VARCHAR(500) NULL DEFAULT NULL COMMENT ''게시중지 사유(표시용)'' AFTER `tr_hold_code`',
    'SELECT 1'
);
PREPARE stmt_hold_reason FROM @sql_hold_reason;
EXECUTE stmt_hold_reason;
DEALLOCATE PREPARE stmt_hold_reason;

SET @has_hold_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade'
      AND COLUMN_NAME = 'tr_hold_at'
);
SET @sql_hold_at := IF(
    @has_hold_at = 0,
    'ALTER TABLE `tb_trade` ADD COLUMN `tr_hold_at` DATETIME NULL DEFAULT NULL COMMENT ''게시중지 시각'' AFTER `tr_hold_reason`',
    'SELECT 1'
);
PREPARE stmt_hold_at FROM @sql_hold_at;
EXECUTE stmt_hold_at;
DEALLOCATE PREPARE stmt_hold_at;

SET @has_hold_ad := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade'
      AND COLUMN_NAME = 'tr_hold_ad_idx'
);
SET @sql_hold_ad := IF(
    @has_hold_ad = 0,
    'ALTER TABLE `tb_trade` ADD COLUMN `tr_hold_ad_idx` INT UNSIGNED NULL DEFAULT NULL COMMENT ''게시중지 처리 관리자'' AFTER `tr_hold_at`',
    'SELECT 1'
);
PREPARE stmt_hold_ad FROM @sql_hold_ad;
EXECUTE stmt_hold_ad;
DEALLOCATE PREPARE stmt_hold_ad;
