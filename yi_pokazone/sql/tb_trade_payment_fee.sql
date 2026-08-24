-- 거래 결제 플랫폼 수수료 (구매확정 정산 시)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_trade_payment_fee` (
    `tpf_idx`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `pay_idx`            INT UNSIGNED     NOT NULL                COMMENT 'tb_trade_payment.pay_idx',
    `mb_idx`             INT UNSIGNED     NOT NULL                COMMENT '판매자',
    `tr_idx`             INT UNSIGNED     NULL DEFAULT NULL       COMMENT '거래글',
    `tpf_gross_amount`   INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '결제금액',
    `tpf_fee_rate`       TINYINT UNSIGNED NOT NULL DEFAULT 8      COMMENT '수수료율(%)',
    `tpf_fee_amount`     INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '플랫폼 수수료',
    `tpf_seller_amount`  INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '판매자 정산액',
    `tpf_auto_confirmed` TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT '1:자동구매확정',
    `tpf_created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`tpf_idx`),
    UNIQUE KEY `uq_pay_fee` (`pay_idx`),
    KEY `idx_mb_created` (`mb_idx`, `tpf_created_at`),
    KEY `idx_tr` (`tr_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='거래 결제 플랫폼 수수료';
