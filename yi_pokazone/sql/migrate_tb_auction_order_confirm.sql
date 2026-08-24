-- 경매 주문: 구매확정·정산
SET NAMES utf8mb4;

ALTER TABLE `tb_auction_order`
    ADD COLUMN `ao_buyer_confirmed_at` DATETIME NULL DEFAULT NULL COMMENT '구매자 구매확정 일시' AFTER `ao_shipped_at`,
    ADD COLUMN `ao_seller_settle_amount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '판매자 정산액(원)' AFTER `ao_buyer_confirmed_at`,
    ADD COLUMN `ao_platform_fee` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '플랫폼 수수료(원)' AFTER `ao_seller_settle_amount`;
