-- 1:1 문의 첨부파일 테이블 (운영 DB 마이그레이션)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_inquiry_file` (
    `if_idx`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `iq_idx`        INT UNSIGNED NOT NULL                    COMMENT '문의 번호',
    `if_path`       VARCHAR(255) NOT NULL                    COMMENT '파일경로 (웹루트 기준)',
    `if_orig_name`  VARCHAR(255)     NULL DEFAULT NULL        COMMENT '원본 파일명',
    `if_size`       INT UNSIGNED NOT NULL DEFAULT 0          COMMENT '바이트',
    `if_mime`       VARCHAR(50)      NULL DEFAULT NULL        COMMENT 'mime 타입',
    `if_order`      TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT '정렬순서',
    `if_created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`if_idx`),
    KEY `idx_iq_idx` (`iq_idx`, `if_order`),
    CONSTRAINT `fk_if_iq_idx` FOREIGN KEY (`iq_idx`) REFERENCES `tb_inquiry`(`iq_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='1:1 문의 첨부파일';
