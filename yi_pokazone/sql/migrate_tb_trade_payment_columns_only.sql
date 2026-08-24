-- migrate_tb_trade_payment.sql 의 ALTER 만 별도 적용 (테이블은 이미 있을 때)
SET NAMES utf8mb4;

SET @has_msg_type := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room_msg'
      AND COLUMN_NAME = 'msg_type'
);
SET @sql_msg_type := IF(
    @has_msg_type = 0,
    'ALTER TABLE `tb_trade_room_msg` ADD COLUMN `msg_type` VARCHAR(20) NOT NULL DEFAULT ''text'' COMMENT ''text|payment_request'' AFTER `msg_body`',
    'SELECT 1'
);
PREPARE stmt_msg_type FROM @sql_msg_type;
EXECUTE stmt_msg_type;
DEALLOCATE PREPARE stmt_msg_type;

SET @has_msg_pay_idx := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room_msg'
      AND COLUMN_NAME = 'msg_pay_idx'
);
SET @sql_msg_pay_idx := IF(
    @has_msg_pay_idx = 0,
    'ALTER TABLE `tb_trade_room_msg` ADD COLUMN `msg_pay_idx` INT UNSIGNED NULL DEFAULT NULL COMMENT ''tb_trade_payment.pay_idx'' AFTER `msg_type`',
    'SELECT 1'
);
PREPARE stmt_msg_pay_idx FROM @sql_msg_pay_idx;
EXECUTE stmt_msg_pay_idx;
DEALLOCATE PREPARE stmt_msg_pay_idx;
