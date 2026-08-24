-- 캐시 출금 신청 (정산계좌로 송금)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_cash_withdraw` (
    `cw_idx`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `mb_idx`           INT UNSIGNED     NOT NULL                COMMENT '신청 회원',
    `cw_amount`        INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '실제 입금액(레거시·cw_net_amount 동기)',
    `cw_gross_amount`  INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '출금 신청 총액(캐시 차감)',
    `cw_fee_rate`      TINYINT UNSIGNED NOT NULL DEFAULT 10     COMMENT '수수료율(%)',
    `cw_fee_amount`    INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '수수료 금액',
    `cw_net_amount`    INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '실제 입금액(총액-수수료)',
    `cw_bank`          VARCHAR(30)      NOT NULL DEFAULT ''     COMMENT '정산 은행명(신청 시점)',
    `cw_holder`        VARCHAR(50)      NOT NULL DEFAULT ''     COMMENT '정산 예금주(신청 시점)',
    `cw_account`       VARCHAR(30)      NOT NULL DEFAULT ''     COMMENT '정산 계좌번호(신청 시점)',
    `cw_status`        TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT '0:신청 1:완료 2:취소',
    `cw_balance_after` INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '차감 후 보유 캐시',
    `cw_memo`          VARCHAR(200)     NOT NULL DEFAULT ''     COMMENT '관리자 메모',
    `cw_created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cw_processed_at`  DATETIME         NULL DEFAULT NULL       COMMENT '처리 완료·취소 시각',
    PRIMARY KEY (`cw_idx`),
    KEY `idx_mb_created` (`mb_idx`, `cw_created_at`),
    KEY `idx_mb_status` (`mb_idx`, `cw_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='캐시 출금 신청';
