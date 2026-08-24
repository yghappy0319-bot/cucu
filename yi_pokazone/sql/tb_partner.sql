-- ============================================================
-- 제휴/입점 문의
-- ============================================================
CREATE TABLE IF NOT EXISTS `tb_partner` (
    `pt_idx`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `pt_type`       VARCHAR(20)  NOT NULL DEFAULT 'general'
                                                         COMMENT 'general|ad|shop|creator|media',
    `pt_company`    VARCHAR(100) NOT NULL                COMMENT '회사/단체명',
    `pt_manager`    VARCHAR(30)  NOT NULL                COMMENT '담당자명',
    `pt_phone`      VARCHAR(30)  NOT NULL,
    `pt_email`      VARCHAR(100) NOT NULL,
    `pt_title`      VARCHAR(200) NOT NULL,
    `pt_content`    MEDIUMTEXT   NOT NULL,
    `pt_status`     TINYINT      NOT NULL DEFAULT 1      COMMENT '1:접수 2:검토 3:회신완료 9:반려',
    `pt_ip`         VARCHAR(45)  NOT NULL DEFAULT '',
    `pt_created_at` DATETIME     NOT NULL,
    PRIMARY KEY (`pt_idx`),
    KEY `idx_status` (`pt_status`, `pt_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
