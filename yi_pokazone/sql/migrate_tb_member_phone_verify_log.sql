-- 기존 DB: 휴대폰 인증 SMS 발송 이력 테이블 추가 (일일 3회 제한)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_member_phone_verify_log` (
    `mpv_idx`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mb_idx`         INT UNSIGNED    NOT NULL,
    `mpv_phone`      VARCHAR(20)     NOT NULL DEFAULT '',
    `mpv_success`    TINYINT(1)      NOT NULL DEFAULT 0,
    `mpv_ip`         VARCHAR(45)     NOT NULL DEFAULT '',
    `mpv_created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`mpv_idx`),
    KEY `idx_mb_created` (`mb_idx`, `mpv_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='휴대폰 인증 SMS 발송 이력';
