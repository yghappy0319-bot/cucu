-- 거래 구매확정 후 판매자 후기·별점
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `tb_trade_seller_review` (
  `tsr_idx`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pay_idx`         INT UNSIGNED NOT NULL COMMENT 'tb_trade_payment.pay_idx',
  `tr_idx`          INT UNSIGNED NULL DEFAULT NULL,
  `seller_mb_idx`   INT UNSIGNED NOT NULL,
  `buyer_mb_idx`    INT UNSIGNED NOT NULL,
  `tsr_rating`      TINYINT UNSIGNED NOT NULL COMMENT '1~5',
  `tsr_body`        VARCHAR(500) NOT NULL DEFAULT '',
  `tsr_status`      TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1:노출 0:숨김',
  `tsr_created_at`  DATETIME NOT NULL,
  PRIMARY KEY (`tsr_idx`),
  UNIQUE KEY `uq_tsr_pay` (`pay_idx`),
  KEY `idx_tsr_seller` (`seller_mb_idx`, `tsr_status`, `tsr_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='거래 구매확정 후 판매자 후기';
