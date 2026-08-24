-- 거래 결제: 배송지 스냅샷 · 택배사 · 운송장
-- (pay_seller_settle_amount 등 선행 컬럼 없어도 실행 가능 — AFTER 미사용)
SET NAMES utf8mb4;

SET @has_pay_ship_label := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_ship_label'
);
SET @sql_pay_ship_label := IF(
    @has_pay_ship_label = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_ship_label` VARCHAR(30) NOT NULL DEFAULT '''' COMMENT ''배송지명''',
    'SELECT 1'
);
PREPARE stmt_pay_ship_label FROM @sql_pay_ship_label;
EXECUTE stmt_pay_ship_label;
DEALLOCATE PREPARE stmt_pay_ship_label;

SET @has_pay_ship_name := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_ship_name'
);
SET @sql_pay_ship_name := IF(
    @has_pay_ship_name = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_ship_name` VARCHAR(50) NOT NULL DEFAULT '''' COMMENT ''받는분''',
    'SELECT 1'
);
PREPARE stmt_pay_ship_name FROM @sql_pay_ship_name;
EXECUTE stmt_pay_ship_name;
DEALLOCATE PREPARE stmt_pay_ship_name;

SET @has_pay_ship_phone := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_ship_phone'
);
SET @sql_pay_ship_phone := IF(
    @has_pay_ship_phone = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_ship_phone` VARCHAR(30) NOT NULL DEFAULT '''' COMMENT ''연락처''',
    'SELECT 1'
);
PREPARE stmt_pay_ship_phone FROM @sql_pay_ship_phone;
EXECUTE stmt_pay_ship_phone;
DEALLOCATE PREPARE stmt_pay_ship_phone;

SET @has_pay_ship_zip := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_ship_zip'
);
SET @sql_pay_ship_zip := IF(
    @has_pay_ship_zip = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_ship_zip` VARCHAR(10) NOT NULL DEFAULT '''' COMMENT ''우편번호''',
    'SELECT 1'
);
PREPARE stmt_pay_ship_zip FROM @sql_pay_ship_zip;
EXECUTE stmt_pay_ship_zip;
DEALLOCATE PREPARE stmt_pay_ship_zip;

SET @has_pay_ship_address := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_ship_address'
);
SET @sql_pay_ship_address := IF(
    @has_pay_ship_address = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_ship_address` VARCHAR(300) NOT NULL DEFAULT '''' COMMENT ''배송 주소''',
    'SELECT 1'
);
PREPARE stmt_pay_ship_address FROM @sql_pay_ship_address;
EXECUTE stmt_pay_ship_address;
DEALLOCATE PREPARE stmt_pay_ship_address;

SET @has_pay_ship_message := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_ship_message'
);
SET @sql_pay_ship_message := IF(
    @has_pay_ship_message = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_ship_message` VARCHAR(200) NOT NULL DEFAULT '''' COMMENT ''배송 메시지''',
    'SELECT 1'
);
PREPARE stmt_pay_ship_message FROM @sql_pay_ship_message;
EXECUTE stmt_pay_ship_message;
DEALLOCATE PREPARE stmt_pay_ship_message;

SET @has_pay_courier_name := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_courier_name'
);
SET @sql_pay_courier_name := IF(
    @has_pay_courier_name = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_courier_name` VARCHAR(50) NOT NULL DEFAULT '''' COMMENT ''택배사''',
    'SELECT 1'
);
PREPARE stmt_pay_courier_name FROM @sql_pay_courier_name;
EXECUTE stmt_pay_courier_name;
DEALLOCATE PREPARE stmt_pay_courier_name;

SET @has_pay_tracking_no := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_tracking_no'
);
SET @sql_pay_tracking_no := IF(
    @has_pay_tracking_no = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_tracking_no` VARCHAR(50) NOT NULL DEFAULT '''' COMMENT ''운송장번호''',
    'SELECT 1'
);
PREPARE stmt_pay_tracking_no FROM @sql_pay_tracking_no;
EXECUTE stmt_pay_tracking_no;
DEALLOCATE PREPARE stmt_pay_tracking_no;

SET @has_pay_tracking_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_tracking_at'
);
SET @sql_pay_tracking_at := IF(
    @has_pay_tracking_at = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_tracking_at` DATETIME NULL DEFAULT NULL COMMENT ''운송장 등록 시각''',
    'SELECT 1'
);
PREPARE stmt_pay_tracking_at FROM @sql_pay_tracking_at;
EXECUTE stmt_pay_tracking_at;
DEALLOCATE PREPARE stmt_pay_tracking_at;
