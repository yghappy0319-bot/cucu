-- 커뮤니티: 포카존 자체글 / 네이버 포켓몬TCG카페 연동 분리
SET NAMES utf8mb4;

-- 출처 구분 (pokazone: 포카존 커뮤니티, naver_tcg: 네이버 포켓몬TCG카페)
SET @col_source := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_community'
      AND COLUMN_NAME = 'co_source'
);
SET @sql_source := IF(
    @col_source = 0,
    "ALTER TABLE `tb_community`
        ADD COLUMN `co_source` VARCHAR(20) NOT NULL DEFAULT 'pokazone'
            COMMENT 'pokazone:포카존커뮤니티 / naver_tcg:네이버포켓몬TCG카페'
            AFTER `co_category`",
    'SELECT 1'
);
PREPARE stmt_source FROM @sql_source;
EXECUTE stmt_source;
DEALLOCATE PREPARE stmt_source;

-- 외부 원문 URL (중복 방지용)
SET @col_url := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_community'
      AND COLUMN_NAME = 'co_external_url'
);
SET @sql_url := IF(
    @col_url = 0,
    "ALTER TABLE `tb_community`
        ADD COLUMN `co_external_url` VARCHAR(500) NULL DEFAULT NULL
            COMMENT '외부 원문 URL (네이버 카페 등)'
            AFTER `co_source`",
    'SELECT 1'
);
PREPARE stmt_url FROM @sql_url;
EXECUTE stmt_url;
DEALLOCATE PREPARE stmt_url;

-- 외부 작성자 닉네임
SET @col_nick := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_community'
      AND COLUMN_NAME = 'co_external_nick'
);
SET @sql_nick := IF(
    @col_nick = 0,
    "ALTER TABLE `tb_community`
        ADD COLUMN `co_external_nick` VARCHAR(50) NULL DEFAULT NULL
            COMMENT '외부 원문 작성자 닉네임'
            AFTER `co_external_url`",
    'SELECT 1'
);
PREPARE stmt_nick FROM @sql_nick;
EXECUTE stmt_nick;
DEALLOCATE PREPARE stmt_nick;

-- 인덱스
SET @idx_source := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_community'
      AND INDEX_NAME = 'idx_co_source'
);
SET @sql_idx_source := IF(
    @idx_source = 0,
    'ALTER TABLE `tb_community` ADD KEY `idx_co_source` (`co_source`, `co_created_at`)',
    'SELECT 1'
);
PREPARE stmt_idx_source FROM @sql_idx_source;
EXECUTE stmt_idx_source;
DEALLOCATE PREPARE stmt_idx_source;

SET @idx_url := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_community'
      AND INDEX_NAME = 'uk_co_external_url'
);
SET @sql_idx_url := IF(
    @idx_url = 0,
    'ALTER TABLE `tb_community` ADD UNIQUE KEY `uk_co_external_url` (`co_external_url`)',
    'SELECT 1'
);
PREPARE stmt_idx_url FROM @sql_idx_url;
EXECUTE stmt_idx_url;
DEALLOCATE PREPARE stmt_idx_url;
