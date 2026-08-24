-- 거래 결제: 배송지·배송확인·구매확정·판매자 정산
-- (선행 컬럼 없어도 실행 가능 — AFTER 미사용)
SET NAMES utf8mb4;

SET @has_pay_fulfill_status := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_fulfill_status'
);
SET @sql_pay_fulfill_status := IF(
    @has_pay_fulfill_status = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_fulfill_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0:없음 1:운송장등록 2:배송확인 3:구매확정''',
    'SELECT 1'
);
PREPARE stmt_pay_fulfill_status FROM @sql_pay_fulfill_status;
EXECUTE stmt_pay_fulfill_status;
DEALLOCATE PREPARE stmt_pay_fulfill_status;

SET @has_pay_addr_msg_idx := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_addr_msg_idx'
);
SET @sql_pay_addr_msg_idx := IF(
    @has_pay_addr_msg_idx = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_addr_msg_idx` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT ''배송지 채팅 메시지''',
    'SELECT 1'
);
PREPARE stmt_pay_addr_msg_idx FROM @sql_pay_addr_msg_idx;
EXECUTE stmt_pay_addr_msg_idx;
DEALLOCATE PREPARE stmt_pay_addr_msg_idx;

SET @has_pay_shipped_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_shipped_at'
);
SET @sql_pay_shipped_at := IF(
    @has_pay_shipped_at = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_shipped_at` DATETIME NULL DEFAULT NULL COMMENT ''구매자 배송확인 시각''',
    'SELECT 1'
);
PREPARE stmt_pay_shipped_at FROM @sql_pay_shipped_at;
EXECUTE stmt_pay_shipped_at;
DEALLOCATE PREPARE stmt_pay_shipped_at;

SET @has_pay_buyer_confirmed_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_buyer_confirmed_at'
);
SET @sql_pay_buyer_confirmed_at := IF(
    @has_pay_buyer_confirmed_at = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_buyer_confirmed_at` DATETIME NULL DEFAULT NULL COMMENT ''구매자 구매확정 시각''',
    'SELECT 1'
);
PREPARE stmt_pay_buyer_confirmed_at FROM @sql_pay_buyer_confirmed_at;
EXECUTE stmt_pay_buyer_confirmed_at;
DEALLOCATE PREPARE stmt_pay_buyer_confirmed_at;

SET @has_pay_seller_settle_amount := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_seller_settle_amount'
);
SET @sql_pay_seller_settle_amount := IF(
    @has_pay_seller_settle_amount = 0,
    'ALTER TABLE `tb_trade_payment` ADD COLUMN `pay_seller_settle_amount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''판매자 정산 금액(원)''',
    'SELECT 1'
);
PREPARE stmt_pay_seller_settle_amount FROM @sql_pay_seller_settle_amount;
EXECUTE stmt_pay_seller_settle_amount;
DEALLOCATE PREPARE stmt_pay_seller_settle_amount;
