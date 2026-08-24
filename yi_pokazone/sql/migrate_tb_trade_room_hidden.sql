-- 거래 채팅: 대화종료 목록에서 회원별 숨김 (DB 기록 유지)
SET NAMES utf8mb4;

SET @has_buyer_hidden := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room'
      AND COLUMN_NAME = 'room_buyer_hidden_at'
);
SET @sql_buyer_hidden := IF(
    @has_buyer_hidden = 0,
    'ALTER TABLE `tb_trade_room` ADD COLUMN `room_buyer_hidden_at` DATETIME NULL DEFAULT NULL COMMENT ''구매자 목록 숨김 시각'' AFTER `room_seller_archived_at`',
    'SELECT 1'
);
PREPARE stmt_buyer_hidden FROM @sql_buyer_hidden;
EXECUTE stmt_buyer_hidden;
DEALLOCATE PREPARE stmt_buyer_hidden;

SET @has_seller_hidden := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room'
      AND COLUMN_NAME = 'room_seller_hidden_at'
);
SET @sql_seller_hidden := IF(
    @has_seller_hidden = 0,
    'ALTER TABLE `tb_trade_room` ADD COLUMN `room_seller_hidden_at` DATETIME NULL DEFAULT NULL COMMENT ''판매자 목록 숨김 시각'' AFTER `room_buyer_hidden_at`',
    'SELECT 1'
);
PREPARE stmt_seller_hidden FROM @sql_seller_hidden;
EXECUTE stmt_seller_hidden;
DEALLOCATE PREPARE stmt_seller_hidden;
