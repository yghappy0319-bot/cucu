-- Pokazone - 출석 체크 & 포인트 내역
-- DB 적용 후 출석체크 기능 사용 가능합니다.

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `tb_point_log`;
CREATE TABLE `tb_point_log` (
    `pl_idx`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mb_idx`        INT UNSIGNED    NOT NULL,
    `pl_change`     INT             NOT NULL COMMENT '증감 (+적립 -차감)',
    `pl_balance`    INT UNSIGNED    NOT NULL COMMENT '처리 후 잔여 포인트',
    `pl_type`       VARCHAR(20)     NOT NULL DEFAULT 'etc' COMMENT 'attendance, admin, etc',
    `pl_memo`       VARCHAR(200)        NULL DEFAULT NULL,
    `pl_created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`pl_idx`),
    KEY `idx_mb_created` (`mb_idx`, `pl_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='포인트 내역';

DROP TABLE IF EXISTS `tb_attendance`;
CREATE TABLE `tb_attendance` (
    `att_idx`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mb_idx`         INT UNSIGNED NOT NULL,
    `att_ymd`        DATE         NOT NULL COMMENT '출석 일자 (사이트 기준 KST)',
    `att_point`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '지급 포인트',
    `att_created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`att_idx`),
    UNIQUE KEY `uq_mb_ymd` (`mb_idx`, `att_ymd`),
    KEY `idx_mb` (`mb_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='출석 체크';
