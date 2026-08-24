-- 기존 DB: 캐시 내역 테이블 추가
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_cash_log` (
    `cl_idx`        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mb_idx`        INT UNSIGNED    NOT NULL,
    `cl_change`     INT             NOT NULL COMMENT '증감 원 (+적립 -차감)',
    `cl_balance`    INT UNSIGNED    NOT NULL COMMENT '처리 후 잔액',
    `cl_type`       VARCHAR(30)     NOT NULL DEFAULT 'etc' COMMENT 'auction_pay, auction_settle, admin, etc',
    `cl_memo`       VARCHAR(255)        NULL DEFAULT NULL,
    `au_idx`        INT UNSIGNED        NULL DEFAULT NULL COMMENT '관련 경매',
    `ao_idx`        INT UNSIGNED        NULL DEFAULT NULL COMMENT '관련 주문',
    `cl_created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`cl_idx`),
    KEY `idx_mb_created` (`mb_idx`, `cl_created_at`),
    KEY `idx_au` (`au_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='캐시 내역';
