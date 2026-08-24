-- 거래 채팅: 회원별 대화 종료(보관) 시각
SET NAMES utf8mb4;

SET @has_buyer_archived := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room'
      AND COLUMN_NAME = 'room_buyer_archived_at'
);
SET @sql_buyer_archived := IF(
    @has_buyer_archived = 0,
    'ALTER TABLE `tb_trade_room` ADD COLUMN `room_buyer_archived_at` DATETIME NULL DEFAULT NULL COMMENT ''구매자 대화 종료 시각'' AFTER `room_seller_read_msg_idx`',
    'SELECT 1'
);
PREPARE stmt_buyer_archived FROM @sql_buyer_archived;
EXECUTE stmt_buyer_archived;
DEALLOCATE PREPARE stmt_buyer_archived;

SET @has_seller_archived := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room'
      AND COLUMN_NAME = 'room_seller_archived_at'
);
SET @sql_seller_archived := IF(
    @has_seller_archived = 0,
    'ALTER TABLE `tb_trade_room` ADD COLUMN `room_seller_archived_at` DATETIME NULL DEFAULT NULL COMMENT ''판매자 대화 종료 시각'' AFTER `room_buyer_archived_at`',
    'SELECT 1'
);
PREPARE stmt_seller_archived FROM @sql_seller_archived;
EXECUTE stmt_seller_archived;
DEALLOCATE PREPARE stmt_seller_archived;
