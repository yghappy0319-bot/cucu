-- 거래글 끌올(목록 상단 노출) 시각
SET NAMES utf8mb4;

SET @has_bumped := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade'
      AND COLUMN_NAME = 'tr_bumped_at'
);
SET @sql_bumped := IF(
    @has_bumped = 0,
    'ALTER TABLE `tb_trade` ADD COLUMN `tr_bumped_at` DATETIME NULL DEFAULT NULL COMMENT ''끌올 시각'' AFTER `tr_updated_at`',
    'SELECT 1'
);
PREPARE stmt_bumped FROM @sql_bumped;
EXECUTE stmt_bumped;
DEALLOCATE PREPARE stmt_bumped;

SET @has_bump_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade'
      AND INDEX_NAME = 'idx_mb_bumped'
);
SET @sql_bump_idx := IF(
    @has_bump_idx = 0,
    'ALTER TABLE `tb_trade` ADD KEY `idx_mb_bumped` (`mb_idx`, `tr_bumped_at`)',
    'SELECT 1'
);
PREPARE stmt_bump_idx FROM @sql_bump_idx;
EXECUTE stmt_bump_idx;
DEALLOCATE PREPARE stmt_bump_idx;
