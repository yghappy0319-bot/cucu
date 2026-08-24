-- 무통장 입금 자동 확인 (pay_status 2 · 확인 시각 · 은행 알림 원문)
SET NAMES utf8mb4;

SET @has_pay_confirmed_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_confirmed_at'
);
SET @sql_pay_confirmed_at := IF(
    @has_pay_confirmed_at = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_confirmed_at` DATETIME NULL DEFAULT NULL COMMENT ''입금 확인 완료 시각'' AFTER `pay_submitted_at`',
    'SELECT 1'
);
PREPARE stmt_pay_confirmed_at FROM @sql_pay_confirmed_at;
EXECUTE stmt_pay_confirmed_at;
DEALLOCATE PREPARE stmt_pay_confirmed_at;

SET @has_bank_confirm_msg := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'bank_confirm_msg'
);
SET @sql_bank_confirm_msg := IF(
    @has_bank_confirm_msg = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `bank_confirm_msg` VARCHAR(500) NOT NULL DEFAULT '''' COMMENT ''은행 입금 알림 원문'' AFTER `pay_confirmed_at`',
    'SELECT 1'
);
PREPARE stmt_bank_confirm_msg FROM @sql_bank_confirm_msg;
EXECUTE stmt_bank_confirm_msg;
DEALLOCATE PREPARE stmt_bank_confirm_msg;
