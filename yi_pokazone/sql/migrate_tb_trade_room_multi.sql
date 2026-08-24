-- 거래 채팅: 대화종료 후 같은 거래글·구매자로 새 대화방 생성 허용
SET NAMES utf8mb4;

SET @has_uq := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room'
      AND INDEX_NAME = 'uq_trade_buyer'
);
SET @sql_drop_uq := IF(
    @has_uq > 0,
    'ALTER TABLE `tb_trade_room` DROP INDEX `uq_trade_buyer`',
    'SELECT 1'
);
PREPARE stmt_drop_uq FROM @sql_drop_uq;
EXECUTE stmt_drop_uq;
DEALLOCATE PREPARE stmt_drop_uq;

SET @has_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_room'
      AND INDEX_NAME = 'idx_trade_buyer'
);
SET @sql_add_idx := IF(
    @has_idx = 0,
    'ALTER TABLE `tb_trade_room` ADD INDEX `idx_trade_buyer` (`tr_idx`, `buyer_mb_idx`)',
    'SELECT 1'
);
PREPARE stmt_add_idx FROM @sql_add_idx;
EXECUTE stmt_add_idx;
DEALLOCATE PREPARE stmt_add_idx;
