-- ============================================================
-- 1:1 문의
-- ============================================================
CREATE TABLE IF NOT EXISTS `tb_inquiry` (
    `iq_idx`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mb_idx`        INT UNSIGNED NULL                    COMMENT '비로그인 허용 시 NULL',
    `iq_category`   VARCHAR(20)  NOT NULL DEFAULT 'etc'
                                                         COMMENT 'account|trade|community|payment|report|etc',
    `iq_name`       VARCHAR(30)  NOT NULL,
    `iq_email`      VARCHAR(100) NOT NULL                COMMENT '답변 받을 이메일',
    `iq_title`      VARCHAR(200) NOT NULL,
    `iq_content`    MEDIUMTEXT   NOT NULL,
    `iq_answer`     MEDIUMTEXT   NULL                    COMMENT '관리자 답변',
    `iq_is_private` TINYINT(1)   NOT NULL DEFAULT 1      COMMENT '비공개(본인+관리자만)',
    `iq_status`     TINYINT      NOT NULL DEFAULT 1      COMMENT '1:접수 2:처리중 3:답변완료 9:삭제',
    `iq_ip`         VARCHAR(45)  NOT NULL DEFAULT '',
    `iq_created_at` DATETIME     NOT NULL,
    `iq_updated_at` DATETIME     NOT NULL,
    `iq_answered_at` DATETIME    NULL,
    PRIMARY KEY (`iq_idx`),
    KEY `idx_member` (`mb_idx`, `iq_status`),
    KEY `idx_status` (`iq_status`, `iq_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
