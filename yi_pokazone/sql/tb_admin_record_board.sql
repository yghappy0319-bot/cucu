-- ============================================================
-- 관리자 기록용 게시판 (총 구매가·건당 판매가 N개·차익=판매합계−총구매)
-- ============================================================
CREATE TABLE IF NOT EXISTS `tb_admin_record_board` (
    `rb_idx`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ad_idx`        INT UNSIGNED NOT NULL                COMMENT '작성 관리자',
    `rb_title`      VARCHAR(200) NOT NULL,
    `rb_memo`       MEDIUMTEXT   NULL,
    `rb_count`      TINYINT UNSIGNED NOT NULL DEFAULT 1  COMMENT '판매가 입력 개수(1~20)',
    `rb_buy`        INT UNSIGNED NOT NULL DEFAULT 0      COMMENT '총 구매가(원)',
    `rb_sells`      TEXT         NOT NULL                COMMENT '판매가 배열 JSON, 길이=rb_count',
    `rb_created_at` DATETIME     NOT NULL,
    `rb_updated_at` DATETIME     NOT NULL,
    PRIMARY KEY (`rb_idx`),
    KEY `idx_admin_created` (`ad_idx`, `rb_created_at`),
    KEY `idx_created` (`rb_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
