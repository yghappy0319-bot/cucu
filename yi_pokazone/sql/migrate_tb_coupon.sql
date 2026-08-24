-- 회원 쿠폰 (바로구매 할인)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_coupon` (
    `cp_idx`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `cp_name`            VARCHAR(100)     NOT NULL DEFAULT ''     COMMENT '쿠폰명',
    `cp_discount_type`   TINYINT UNSIGNED NOT NULL DEFAULT 1      COMMENT '1:정액(원) 2:정률(%)',
    `cp_discount_value`  INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '할인액 또는 할인율',
    `cp_valid_from`      DATE             NOT NULL                COMMENT '사용 시작일',
    `cp_valid_until`     DATE             NOT NULL                COMMENT '사용 종료일',
    `cp_issue_target`    TINYINT UNSIGNED NOT NULL DEFAULT 1      COMMENT '1:전체회원 2:개별회원',
    `cp_status`          TINYINT UNSIGNED NOT NULL DEFAULT 1      COMMENT '1:활성 9:비활성',
    `cp_issued_count`    INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '발행 건수',
    `cp_used_count`      INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '사용 건수',
    `cp_created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cp_updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`cp_idx`),
    KEY `idx_cp_valid` (`cp_status`, `cp_valid_from`, `cp_valid_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='쿠폰 마스터';

CREATE TABLE IF NOT EXISTS `tb_member_coupon` (
    `mc_idx`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `cp_idx`             INT UNSIGNED     NOT NULL                COMMENT 'tb_coupon.cp_idx',
    `mb_idx`             INT UNSIGNED     NOT NULL                COMMENT '회원',
    `mc_name`            VARCHAR(100)     NOT NULL DEFAULT ''     COMMENT '발행 시 쿠폰명',
    `mc_discount_type`   TINYINT UNSIGNED NOT NULL DEFAULT 1      COMMENT '1:정액 2:정률',
    `mc_discount_value`  INT UNSIGNED     NOT NULL DEFAULT 0      COMMENT '할인액 또는 할인율',
    `mc_valid_from`      DATE             NOT NULL                COMMENT '사용 시작일',
    `mc_valid_until`     DATE             NOT NULL                COMMENT '사용 종료일',
    `mc_status`          TINYINT UNSIGNED NOT NULL DEFAULT 0      COMMENT '0:사용가능 1:사용완료 9:취소',
    `mc_pay_idx`         INT UNSIGNED     NULL DEFAULT NULL       COMMENT '사용 결제 pay_idx',
    `mc_used_at`         DATETIME         NULL DEFAULT NULL,
    `mc_issued_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`mc_idx`),
    KEY `idx_mb_status_valid` (`mb_idx`, `mc_status`, `mc_valid_until`),
    KEY `idx_cp` (`cp_idx`),
    KEY `idx_pay` (`mc_pay_idx`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='회원 보유 쿠폰';

SET @has_pay_original := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tb_trade_payment'
      AND COLUMN_NAME = 'pay_original_amount'
);
SET @sql_pay_original := IF(
    @has_pay_original = 0,
    'ALTER TABLE `tb_trade_payment`
        ADD COLUMN `pay_original_amount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''할인 전 결제금액'' AFTER `pay_amount`,
        ADD COLUMN `pay_discount_amount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''쿠폰 할인액'' AFTER `pay_original_amount`,
        ADD COLUMN `pay_member_coupon_idx` INT UNSIGNED NULL DEFAULT NULL COMMENT ''tb_member_coupon.mc_idx'' AFTER `pay_discount_amount`',
    'SELECT 1'
);
PREPARE stmt_pay_original FROM @sql_pay_original;
EXECUTE stmt_pay_original;
DEALLOCATE PREPARE stmt_pay_original;
