-- =============================================================================
-- Pokazone - 휴대폰 인증 SMS 발송 이력 (일일 횟수 제한)
-- =============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_member_phone_verify_log` (
    `mpv_idx`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT            COMMENT '로그 고유번호',
    `mb_idx`         INT UNSIGNED    NOT NULL                           COMMENT '회원 고유번호',
    `mpv_phone`      VARCHAR(20)     NOT NULL DEFAULT ''                COMMENT '요청 휴대폰(숫자만)',
    `mpv_success`    TINYINT(1)      NOT NULL DEFAULT 0                 COMMENT 'SMS 발송 API 성공 여부',
    `mpv_ip`         VARCHAR(45)     NOT NULL DEFAULT ''                COMMENT '요청 IP',
    `mpv_created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '요청일시',
    PRIMARY KEY (`mpv_idx`),
    KEY `idx_mb_created` (`mb_idx`, `mpv_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='휴대폰 인증 SMS 발송 이력';
