-- 거래글 택배비 (0 = 판매자 부담 / 구매자 무료)
SET NAMES utf8mb4;

ALTER TABLE `tb_trade`
    ADD COLUMN `tr_shipping_fee` INT UNSIGNED NOT NULL DEFAULT 0
        COMMENT '택배비(원). 0=판매자부담, >0=구매자 부담(바로구매 시 상품가에 합산)'
        AFTER `tr_method`;
