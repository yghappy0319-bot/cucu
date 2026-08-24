-- 운영 DB 마이그레이션: 무통장 입금 알림 수신 로그
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_trade_bank_deposit_log` (
    `bdl_idx`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `bdl_depositor`         VARCHAR(80)     NOT NULL DEFAULT '' COMMENT '수신 입금자명',
    `bdl_amount`            INT UNSIGNED    NOT NULL DEFAULT 0 COMMENT '수신 입금금액',
    `bdl_bank_msg`          VARCHAR(500)    NOT NULL DEFAULT '' COMMENT '은행 알림 원문',
    `pay_idx`               INT UNSIGNED        NULL DEFAULT NULL COMMENT '매칭된 결제 pay_idx',
    `bdl_status`            VARCHAR(20)     NOT NULL DEFAULT 'error' COMMENT 'confirmed|already|unmatched|error',
    `bdl_result_msg`        VARCHAR(255)    NOT NULL DEFAULT '' COMMENT '처리 결과·오류 메시지',
    `bdl_expected_depositor` VARCHAR(80)    NOT NULL DEFAULT '' COMMENT '금액 일치·이름 불일치 시 신청 입금자명',
    `bdl_created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`bdl_idx`),
    KEY `idx_status_created` (`bdl_status`, `bdl_created_at`),
    KEY `idx_amount_created` (`bdl_amount`, `bdl_created_at`),
    KEY `idx_pay_idx` (`pay_idx`),
    KEY `idx_created` (`bdl_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='무통장 입금 알림 로그';
