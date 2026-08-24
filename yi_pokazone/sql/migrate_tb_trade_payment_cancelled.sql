-- 결제요청취소(pay_status=3) · 취소 시각
SET NAMES utf8mb4;

SET @has_pay_cancelled_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_cancelled_at'
);
SET @sql_pay_cancelled_at := IF(
    @has_pay_cancelled_at = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_cancelled_at` DATETIME NULL DEFAULT NULL COMMENT ''결제요청 취소 시각'' AFTER `pay_submitted_at`',
    'SELECT 1'
);
PREPARE stmt_pay_cancelled_at FROM @sql_pay_cancelled_at;
EXECUTE stmt_pay_cancelled_at;
DEALLOCATE PREPARE stmt_pay_cancelled_at;
