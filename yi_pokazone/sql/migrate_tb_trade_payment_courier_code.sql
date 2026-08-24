-- 택배사 코드 (선택)
SET NAMES utf8mb4;

SET @has_pay_courier_code := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_courier_code'
);
SET @sql_pay_courier_code := IF(
    @has_pay_courier_code = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_courier_code` VARCHAR(10) NOT NULL DEFAULT '''' COMMENT ''택배사 코드''',
    'SELECT 1'
);
PREPARE stmt_pay_courier_code FROM @sql_pay_courier_code;
EXECUTE stmt_pay_courier_code;
DEALLOCATE PREPARE stmt_pay_courier_code;
