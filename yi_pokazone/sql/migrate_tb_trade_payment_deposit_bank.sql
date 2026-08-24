-- 구매자 입금 신청 시 tb_trade_payment 에 저장할 계좌 스냅샷
SET NAMES utf8mb4;

SET @has_pay_bank_name := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_bank_name'
);
SET @sql_pay_bank_name := IF(
    @has_pay_bank_name = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_bank_name` VARCHAR(50) NOT NULL DEFAULT '''' COMMENT ''입금 안내 은행명'' AFTER `depositor_name`',
    'SELECT 1'
);
PREPARE stmt_pay_bank_name FROM @sql_pay_bank_name;
EXECUTE stmt_pay_bank_name;
DEALLOCATE PREPARE stmt_pay_bank_name;

SET @has_pay_account_no := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_account_no'
);
SET @sql_pay_account_no := IF(
    @has_pay_account_no = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_account_no` VARCHAR(50) NOT NULL DEFAULT '''' COMMENT ''입금 안내 계좌번호'' AFTER `pay_bank_name`',
    'SELECT 1'
);
PREPARE stmt_pay_account_no FROM @sql_pay_account_no;
EXECUTE stmt_pay_account_no;
DEALLOCATE PREPARE stmt_pay_account_no;

SET @has_pay_account_holder := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_account_holder'
);
SET @sql_pay_account_holder := IF(
    @has_pay_account_holder = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_account_holder` VARCHAR(80) NOT NULL DEFAULT '''' COMMENT ''입금 안내 예금주'' AFTER `pay_account_no`',
    'SELECT 1'
);
PREPARE stmt_pay_account_holder FROM @sql_pay_account_holder;
EXECUTE stmt_pay_account_holder;
DEALLOCATE PREPARE stmt_pay_account_holder;
