-- 캐시 출금 수수료 내역 (거래 건 연동)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_cash_withdraw_fee` (
    `cwf_idx`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `cw_idx`             INT UNSIGNED     NOT NULL                COMMENT '출금 신청 tb_cash_withdraw.cw_idx',
    `mb_idx`             INT UNSIGNED     NOT NULL                COMMENT '신청 회원',
    `cwf_source_type`    VARCHAR(20)      NOT NULL DEFAULT 'general' COMMENT 'general|trade|auction',
    `pay_idx`            INT UNSIGNED     NULL DEFAULT NULL       COMMENT '거래 결제 pay_idx',
    `tr_idx`             INT UNSIGNED     NULL DEFAULT NULL       COMMENT '거래글 tr_idx',
    `au_idx`             INT UNSIGNED     NULL DEFAULT NULL       COMMENT '경매 au_idx',
    `cwf_product_label`  VARCHAR(200)     NOT NULL DEFAULT ''     COMMENT '거래 상품명 등',
    `cwf_gross_amount`   INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '출금 신청 총액(캐시 차감)',
    `cwf_fee_rate`       TINYINT UNSIGNED NOT NULL DEFAULT 10     COMMENT '수수료율(%)',
    `cwf_fee_amount`     INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '수수료 금액',
    `cwf_net_amount`     INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '실제 입금액(총액-수수료)',
    `cwf_created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`cwf_idx`),
    UNIQUE KEY `uq_cw_fee` (`cw_idx`),
    KEY `idx_mb_created` (`mb_idx`, `cwf_created_at`),
    KEY `idx_pay` (`pay_idx`),
    KEY `idx_tr` (`tr_idx`),
    CONSTRAINT `fk_cwf_withdraw` FOREIGN KEY (`cw_idx`) REFERENCES `tb_cash_withdraw` (`cw_idx`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='캐시 출금 수수료·거래 연동';
