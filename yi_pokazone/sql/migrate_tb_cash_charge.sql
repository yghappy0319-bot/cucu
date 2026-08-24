-- 캐시 충전 신청 (무통장 입금)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_cash_charge` (
    `cc_idx`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `mb_idx`           INT UNSIGNED     NOT NULL                COMMENT '신청 회원',
    `cc_amount`        INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '충전 신청 금액',
    `cc_method`        VARCHAR(20)      NOT NULL DEFAULT 'bank' COMMENT 'bank|card',
    `cc_depositor`     VARCHAR(50)      NOT NULL DEFAULT ''     COMMENT '입금자명',
    `cc_status`        TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT '0:입금대기 1:충전완료 2:취소',
    `cc_balance_after` INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '충전 후 보유 캐시',
    `cc_memo`          VARCHAR(200)     NOT NULL DEFAULT ''     COMMENT '관리자 메모',
    `cc_created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cc_confirmed_at`  DATETIME         NULL DEFAULT NULL       COMMENT '입금 확인·충전 완료 시각',
    PRIMARY KEY (`cc_idx`),
    KEY `idx_mb_created` (`mb_idx`, `cc_created_at`),
    KEY `idx_mb_status` (`mb_idx`, `cc_status`),
    KEY `idx_pending_match` (`cc_status`, `cc_amount`, `cc_depositor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='캐시 충전 신청';
