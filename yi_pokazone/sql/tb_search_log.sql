-- Pokazone - 검색어 로그 (실시간 인기 검색어 집계용)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_search_log` (
    `sl_idx`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sl_keyword`    VARCHAR(100)    NOT NULL COMMENT '정규화된 검색어',
    `mb_idx`        INT UNSIGNED        NULL DEFAULT NULL COMMENT '검색 회원 (비로그인 NULL)',
    `sl_created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`sl_idx`),
    KEY `idx_keyword_created` (`sl_keyword`, `sl_created_at`),
    KEY `idx_created` (`sl_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='검색어 로그';
