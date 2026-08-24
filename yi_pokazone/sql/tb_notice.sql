-- ============================================================
-- 공지사항 (관리자만 작성)
-- ============================================================
CREATE TABLE IF NOT EXISTS `tb_notice` (
    `no_idx`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mb_idx`        INT UNSIGNED     NULL                COMMENT '작성자(회원), 백오피스 전용 공지는 NULL',
    `no_ad_idx`     INT UNSIGNED     NULL                COMMENT '백오피스 작성자(tb_admin)',
    `no_category`   VARCHAR(20)  NOT NULL DEFAULT 'general'
                                                         COMMENT 'general|update|event|maintenance',
    `no_is_pinned`  TINYINT(1)   NOT NULL DEFAULT 0      COMMENT '상단 고정',
    `no_title`      VARCHAR(200) NOT NULL,
    `no_content`    MEDIUMTEXT   NOT NULL,
    `no_views`      INT UNSIGNED NOT NULL DEFAULT 0,
    `no_status`     TINYINT      NOT NULL DEFAULT 1      COMMENT '1:노출 9:삭제',
    `no_created_at` DATETIME     NOT NULL,
    `no_updated_at` DATETIME     NOT NULL,
    PRIMARY KEY (`no_idx`),
    KEY `idx_status_pinned` (`no_status`, `no_is_pinned`, `no_created_at`),
    KEY `idx_category` (`no_category`),
    KEY `idx_no_ad_idx` (`no_ad_idx`),
    CONSTRAINT `fk_notice_member` FOREIGN KEY (`mb_idx`) REFERENCES `tb_member` (`mb_idx`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_notice_ad` FOREIGN KEY (`no_ad_idx`) REFERENCES `tb_admin` (`ad_idx`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
