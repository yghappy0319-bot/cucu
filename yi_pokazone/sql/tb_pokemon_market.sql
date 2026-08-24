-- ============================================================
-- 전체 시세 — 관리자 등록 포켓몬명 (제목·카드명 포함 매칭)
-- ============================================================
CREATE TABLE IF NOT EXISTS `tb_pokemon_market` (
    `pm_idx`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pm_name`       VARCHAR(100) NOT NULL                COMMENT '포켓몬명 (tr_title·tr_card_name 포함 매칭)',
    `pm_sort`       INT          NOT NULL DEFAULT 0      COMMENT '정렬(작을수록 앞)',
    `pm_status`     TINYINT      NOT NULL DEFAULT 1      COMMENT '1:노출 9:삭제',
    `pm_created_at` DATETIME     NOT NULL,
    `pm_updated_at` DATETIME     NOT NULL,
    PRIMARY KEY (`pm_idx`),
    UNIQUE KEY `uk_pm_name` (`pm_name`),
    KEY `idx_status_sort` (`pm_status`, `pm_sort`, `pm_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
