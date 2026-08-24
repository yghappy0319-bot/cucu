-- 거래 결제: 판매 취소 · 구매자 캐시 환불 (pay_status=4)
SET NAMES utf8mb4;

SET @has_pay_refunded_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_refunded_at'
);
SET @sql_pay_refunded_at := IF(
    @has_pay_refunded_at = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_refunded_at` DATETIME NULL DEFAULT NULL COMMENT ''판매취소 환불 시각''',
    'SELECT 1'
);
PREPARE stmt_pay_refunded_at FROM @sql_pay_refunded_at;
EXECUTE stmt_pay_refunded_at;
DEALLOCATE PREPARE stmt_pay_refunded_at;

SET @has_pay_refund_amount := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_refund_amount'
);
SET @sql_pay_refund_amount := IF(
    @has_pay_refund_amount = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_refund_amount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''환불 금액(원)''',
    'SELECT 1'
);
PREPARE stmt_pay_refund_amount FROM @sql_pay_refund_amount;
EXECUTE stmt_pay_refund_amount;
DEALLOCATE PREPARE stmt_pay_refund_amount;
