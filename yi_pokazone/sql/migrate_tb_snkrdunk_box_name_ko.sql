-- 기존 tb_snkrdunk_box에 관리자 입력 한글 상품명 추가
SET NAMES utf8mb4;

SET @has_name_ko := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_snkrdunk_box'
      AND COLUMN_NAME = 'sb_name_ko'
);

SET @sql := IF(
    @has_name_ko = 0,
    'ALTER TABLE `tb_snkrdunk_box` ADD COLUMN `sb_name_ko` VARCHAR(255) NOT NULL DEFAULT '''' COMMENT ''관리자 입력 한글 상품명'' AFTER `sb_product_number`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
