-- 관리자 선매입 (판매중 상품 선지급)
ALTER TABLE `tb_gifticon`
  ADD COLUMN `prebuy` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '관리자 선매입' AFTER `settled`,
  ADD COLUMN `prebuy_at` DATETIME NULL DEFAULT NULL AFTER `prebuy`,
  ADD COLUMN `prebuy_admin` VARCHAR(30) NULL DEFAULT NULL AFTER `prebuy_at`,
  ADD COLUMN `prebuy_paid` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '선매입 지급액' AFTER `prebuy_admin`;
